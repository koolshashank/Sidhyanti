import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/loan-proposals/lookups -- Products (active schemes) + Purposes for the New Loan form. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [schemes, purposes] = await Promise.all([
    pool.request().query(`
      SELECT SchemeID AS id, SchemeName AS name, TenureMonths AS tenureMonths, DefaultLoanAmount AS defaultAmount,
             InterestRatePercent AS interestRatePercent, InstallmentAmountSlot1 AS emiAmount
      FROM Loan_Scheme WHERE IsActive = 1 ORDER BY SchemeName
    `),
    pool.request().query(`SELECT PurposeID AS id, PurposeName AS name FROM Loan_Purpose_Master ORDER BY PurposeName`),
  ]);
  res.json({ success: true, data: { schemes: schemes.recordset, purposes: purposes.recordset } });
});

/**
 * GET /api/loan-proposals/member-context/:memberId
 * Everything the New Loan form needs to know about this member before the
 * officer fills it out: deceased flag, any open loan blocking a new one,
 * reloan eligibility (port of includes/reloan_helper.php), and existing
 * bank details to prefill.
 */
router.get('/member-context/:memberId', async (req, res) => {
  const memberId = Number(req.params.memberId);
  const pool = await getPool();

  const memberResult = await pool
    .request()
    .input('memberId', sql.Int, memberId)
    .query(`SELECT MemberID, FirstName, LastName, BranchID, CenterID FROM Member WHERE MemberID = @memberId`);
  const member = memberResult.recordset[0];
  if (!member) return res.status(404).json({ success: false, message: 'Member not found' });

  const isDeceased = Boolean(
    (
      await pool.request().input('memberId', sql.Int, memberId).query(`SELECT 1 FROM Death_Entry WHERE MemberID = @memberId AND Status = 'Approved'`)
    ).recordset.length,
  );

  const openLoan = (
    await pool.request().input('memberId', sql.Int, memberId).query(`
      SELECT TOP 1 l.LoanNo FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.MemberID = @memberId AND stg.IsFinal = 0
    `)
  ).recordset[0]?.LoanNo as string | undefined;

  const bankDetails = (
    await pool.request().input('memberId', sql.Int, memberId).query(`
      SELECT BankName AS bankName, IFSCCode AS ifscCode, BankBranch AS bankBranch, BankAddress AS bankAddress,
             AccountNumber AS accountNumber, BeneficiaryName AS beneficiaryName
      FROM Member_Bank_Details WHERE MemberID = @memberId
    `)
  ).recordset[0] ?? null;

  // Port of compute_reloan_eligibility() -- null unless the member has a completed prior cycle.
  let reloanEligibility = null;
  const prevLoan = (
    await pool.request().input('memberId', sql.Int, memberId).query(`
      SELECT TOP 1 LoanID, LoanNo, LoanAmountSanctioned, LoanAmountApplied
      FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.MemberID = @memberId AND stg.StageName IN ('Closed', 'Preclosed')
      ORDER BY COALESCE(l.ClosureDate, CAST(l.UpdatedAt AS DATE)) DESC, l.LoanID DESC
    `)
  ).recordset[0];

  if (prevLoan) {
    const cycleNumber =
      (
        await pool.request().input('memberId', sql.Int, memberId).query(`
          SELECT COUNT(*) AS total FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
          WHERE l.MemberID = @memberId AND stg.StageName IN ('Closed', 'Preclosed')
        `)
      ).recordset[0].total + 1;

    const latePayments = (
      await pool.request().input('loanId', sql.Int, prevLoan.LoanID).query(`
        SELECT COUNT(*) AS total FROM Repayment_Schedule rs
        LEFT JOIN Collection c ON c.ScheduleID = rs.ScheduleID
        WHERE rs.LoanID = @loanId AND (
          (c.CollectionDate IS NOT NULL AND c.CollectionDate > rs.DueDate)
          OR (c.CollectionDate IS NULL AND rs.IsPaid = 0)
        )
      `)
    ).recordset[0].total;

    const rule = (
      await pool
        .request()
        .input('cycleNumber', sql.Int, cycleNumber)
        .input('latePayments', sql.Int, latePayments)
        .query(`
          SELECT TOP 1 RuleID, RuleName, IncreasePercent FROM Reloan_Eligibility_Rule
          WHERE IsActive = 1 AND MinCycleNumber <= @cycleNumber AND MaxLatePayments >= @latePayments
          ORDER BY IncreasePercent DESC
        `)
    ).recordset[0];

    if (rule) {
      const previousAmount = Number(prevLoan.LoanAmountSanctioned || prevLoan.LoanAmountApplied);
      const eligibleAmount = Math.round((previousAmount * (1 + Number(rule.IncreasePercent) / 100)) / 100) * 100;
      reloanEligibility = {
        previousLoanNo: prevLoan.LoanNo,
        previousLoanAmount: previousAmount,
        cycleNumber,
        latePayments,
        ruleName: rule.RuleName,
        increasePercent: Number(rule.IncreasePercent),
        eligibleAmount,
      };
    }
  }

  res.json({
    success: true,
    data: {
      member: { memberId: member.MemberID, firstName: member.FirstName, lastName: member.LastName, branchId: member.BranchID, centerId: member.CenterID },
      isDeceased,
      openLoanNo: openLoan ?? null,
      bankDetails,
      reloanEligibility,
    },
  });
});

/**
 * POST /api/loan-proposals -- full port of api/v1/loans/create.php.
 * Writes Loan + Loan_Income_Assessment + Loan_Approval('Proposal','Pending')
 * in one transaction, plus an optional Member_Bank_Details upsert.
 */
router.post('/', async (req, res) => {
  const b = req.body;
  const memberId = Number(b.memberId);
  const schemeId = Number(b.schemeId);
  if (!memberId) return res.status(422).json({ success: false, message: 'Member is required' });
  if (!schemeId) return res.status(422).json({ success: false, message: 'Product (scheme) is required' });

  const pool = await getPool();

  const member = (
    await pool.request().input('memberId', sql.Int, memberId).query(`SELECT MemberID, BranchID, CenterID FROM Member WHERE MemberID = @memberId`)
  ).recordset[0];
  if (!member) return res.status(404).json({ success: false, message: 'Member not found' });

  const isDeceased = (
    await pool.request().input('memberId', sql.Int, memberId).query(`SELECT 1 FROM Death_Entry WHERE MemberID = @memberId AND Status = 'Approved'`)
  ).recordset.length;
  if (isDeceased) {
    return res
      .status(409)
      .json({ success: false, code: 'MEMBER_DECEASED', message: 'This member has an approved death record on file. A new loan cannot be proposed for a deceased member.' });
  }

  const openLoanNo = (
    await pool.request().input('memberId', sql.Int, memberId).query(`
      SELECT TOP 1 l.LoanNo FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.MemberID = @memberId AND stg.IsFinal = 0
    `)
  ).recordset[0]?.LoanNo;
  if (openLoanNo) {
    return res.status(409).json({
      success: false,
      message: `This member already has an open loan (${openLoanNo}). It must be closed, preclosed, or rejected before a new loan can be proposed.`,
    });
  }

  const scheme = (
    await pool
      .request()
      .input('schemeId', sql.Int, schemeId)
      .query(`SELECT SchemeID, TenureMonths, DefaultLoanAmount, InterestRatePercent FROM Loan_Scheme WHERE SchemeID = @schemeId AND IsActive = 1`)
  ).recordset[0];
  if (!scheme) return res.status(422).json({ success: false, message: 'Invalid product' });

  let loanAmount = Number(scheme.DefaultLoanAmount);
  const requestedAmount = b.amount !== undefined && b.amount !== null && b.amount !== '' ? Number(b.amount) : null;
  if (requestedAmount !== null) {
    const prevLoan = (
      await pool.request().input('memberId', sql.Int, memberId).query(`
        SELECT TOP 1 LoanID, LoanAmountSanctioned, LoanAmountApplied
        FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
        WHERE l.MemberID = @memberId AND stg.StageName IN ('Closed', 'Preclosed')
        ORDER BY COALESCE(l.ClosureDate, CAST(l.UpdatedAt AS DATE)) DESC, l.LoanID DESC
      `)
    ).recordset[0];
    let eligibleAmount: number | null = null;
    if (prevLoan) {
      const cycleNumber =
        (
          await pool.request().input('memberId', sql.Int, memberId).query(`
            SELECT COUNT(*) AS total FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
            WHERE l.MemberID = @memberId AND stg.StageName IN ('Closed', 'Preclosed')
          `)
        ).recordset[0].total + 1;
      const latePayments = (
        await pool.request().input('loanId', sql.Int, prevLoan.LoanID).query(`
          SELECT COUNT(*) AS total FROM Repayment_Schedule rs
          LEFT JOIN Collection c ON c.ScheduleID = rs.ScheduleID
          WHERE rs.LoanID = @loanId AND ((c.CollectionDate IS NOT NULL AND c.CollectionDate > rs.DueDate) OR (c.CollectionDate IS NULL AND rs.IsPaid = 0))
        `)
      ).recordset[0].total;
      const rule = (
        await pool
          .request()
          .input('cycleNumber', sql.Int, cycleNumber)
          .input('latePayments', sql.Int, latePayments)
          .query(`SELECT TOP 1 IncreasePercent FROM Reloan_Eligibility_Rule WHERE IsActive = 1 AND MinCycleNumber <= @cycleNumber AND MaxLatePayments >= @latePayments ORDER BY IncreasePercent DESC`)
      ).recordset[0];
      if (rule) {
        const previousAmount = Number(prevLoan.LoanAmountSanctioned || prevLoan.LoanAmountApplied);
        eligibleAmount = Math.round((previousAmount * (1 + Number(rule.IncreasePercent) / 100)) / 100) * 100;
      }
    }
    if (eligibleAmount === null) {
      return res.status(422).json({ success: false, message: 'A custom amount is only allowed for a reloan.' });
    }
    if (requestedAmount <= 0 || requestedAmount > eligibleAmount) {
      return res.status(422).json({ success: false, message: `Requested amount exceeds this member's pre-approved eligibility of ${eligibleAmount}` });
    }
    loanAmount = requestedAmount;
  }

  const purposeId = b.purposeId ? Number(b.purposeId) : null;
  const appliedStageId = (await pool.request().query(`SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Applied'`)).recordset[0].StageID;
  const loanCycle = (await pool.request().input('memberId', sql.Int, memberId).query(`SELECT COUNT(*) AS total FROM Loan WHERE MemberID = @memberId`)).recordset[0].total + 1;

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    const loanReq = new sql.Request(transaction);
    loanReq.input('memberId', sql.Int, memberId);
    loanReq.input('branchId', sql.Int, member.BranchID);
    loanReq.input('centerId', sql.Int, member.CenterID);
    loanReq.input('purposeId', sql.Int, purposeId);
    loanReq.input('schemeId', sql.Int, schemeId);
    loanReq.input('loanAmount', sql.Decimal(18, 2), loanAmount);
    loanReq.input('interestRate', sql.Decimal(6, 2), scheme.InterestRatePercent);
    loanReq.input('tenure', sql.Int, scheme.TenureMonths);
    loanReq.input('emiAmount', sql.Decimal(18, 2), b.emiAmount ? Number(b.emiAmount) : null);
    loanReq.input('officerId', sql.Int, req.staff!.staffId);
    loanReq.input('stageId', sql.Int, appliedStageId);
    loanReq.input('loanCycle', sql.Int, loanCycle);
    loanReq.input('createdBy', sql.Int, req.staff!.staffId);
    const loanResult = await loanReq.query(`
      INSERT INTO Loan (MemberID, BranchID, CenterID, PurposeID, SchemeID, LoanAmountApplied, InterestRatePercent, TenureMonths, EMIAmount,
                         ApplicationDate, LoanOfficerStaffID, CurrentStageID, LoanCycle, CreatedBy, CreatedAt, IsActive)
      OUTPUT INSERTED.LoanID AS loanId
      VALUES (@memberId, @branchId, @centerId, @purposeId, @schemeId, @loanAmount, @interestRate, @tenure, @emiAmount,
              CAST(GETDATE() AS DATE), @officerId, @stageId, @loanCycle, @createdBy, GETDATE(), 1)
    `);
    const loanId = loanResult.recordset[0].loanId;

    const num = (key: string) => (b[key] !== undefined && b[key] !== null && b[key] !== '' ? Number(b[key]) : null);
    const selfIncome = num('selfIncome');
    const husbandSonIncome = num('husbandSonIncome');
    const otherIncome = num('otherIncome');
    const residentIncome = num('residentIncome');
    const houseExpenses = num('houseExpenses');
    const mfiExpenses = num('mfiExpenses');
    const businessExpenses = num('businessExpenses');
    const incomeParts = [selfIncome, husbandSonIncome, otherIncome, residentIncome].filter((v) => v !== null) as number[];
    const totalIncome = incomeParts.length ? incomeParts.reduce((a, v) => a + v, 0) : null;
    const expenseParts = [houseExpenses, mfiExpenses, businessExpenses].filter((v) => v !== null) as number[];
    const totalExpenses = expenseParts.length ? expenseParts.reduce((a, v) => a + v, 0) : null;
    const disposableIncome = totalIncome !== null || totalExpenses !== null ? (totalIncome ?? 0) - (totalExpenses ?? 0) : null;

    const assessReq = new sql.Request(transaction);
    assessReq.input('loanId', sql.Int, loanId);
    assessReq.input('selfIncome', sql.Decimal(12, 2), selfIncome);
    assessReq.input('husbandSonIncome', sql.Decimal(12, 2), husbandSonIncome);
    assessReq.input('otherIncome', sql.Decimal(12, 2), otherIncome);
    assessReq.input('residentIncome', sql.Decimal(12, 2), residentIncome);
    assessReq.input('totalIncome', sql.Decimal(12, 2), totalIncome);
    assessReq.input('houseExpenses', sql.Decimal(12, 2), houseExpenses);
    assessReq.input('mfiExpenses', sql.Decimal(12, 2), mfiExpenses);
    assessReq.input('businessExpenses', sql.Decimal(12, 2), businessExpenses);
    assessReq.input('totalExpenses', sql.Decimal(12, 2), totalExpenses);
    assessReq.input('disposableIncome', sql.Decimal(12, 2), disposableIncome);
    assessReq.input('sourceOfIncome', sql.NVarChar, b.sourceOfIncome || null);
    await assessReq.query(`
      INSERT INTO Loan_Income_Assessment (LoanID, SelfIncomeMonthly, HusbandSonIncomeMonthly, OtherIncomeMonthly, ResidentIncomeMonthly,
                                           TotalIncomeMonthly, HouseExpensesMonthly, MFIExpensesMonthly, BusinessExpensesMonthly,
                                           TotalExpensesMonthly, DisposableIncomeMonthly, SourceOfIncome, AssessmentDate, CreatedAt)
      VALUES (@loanId, @selfIncome, @husbandSonIncome, @otherIncome, @residentIncome, @totalIncome, @houseExpenses, @mfiExpenses,
              @businessExpenses, @totalExpenses, @disposableIncome, @sourceOfIncome, CAST(GETDATE() AS DATE), GETDATE())
    `);

    const approvalReq = new sql.Request(transaction);
    approvalReq.input('loanId', sql.Int, loanId);
    approvalReq.input('requestedBy', sql.Int, req.staff!.staffId);
    await approvalReq.query(`
      INSERT INTO Loan_Approval (LoanID, RequestType, Status, RequestedByStaffID, CreatedAt) VALUES (@loanId, 'Proposal', 'Pending', @requestedBy, GETDATE())
    `);

    const ifscCode = (b.ifscCode || '').trim() || null;
    const bankName = (b.bankName || '').trim() || null;
    const bankBranch = (b.bankBranch || '').trim() || null;
    const bankAddress = (b.bankAddress || '').trim() || null;
    const accountNumber = (b.accountNumber || '').trim() || null;
    const beneficiaryName = (b.beneficiaryName || '').trim() || null;
    if (ifscCode || bankName || accountNumber || beneficiaryName) {
      const existing = (
        await new sql.Request(transaction).input('memberId', sql.Int, memberId).query(`SELECT BankDetailID FROM Member_Bank_Details WHERE MemberID = @memberId`)
      ).recordset[0];
      const bankReq = new sql.Request(transaction);
      bankReq.input('memberId', sql.Int, memberId);
      bankReq.input('bankName', sql.NVarChar, bankName);
      bankReq.input('ifscCode', sql.NVarChar, ifscCode);
      bankReq.input('bankBranch', sql.NVarChar, bankBranch);
      bankReq.input('bankAddress', sql.NVarChar, bankAddress);
      bankReq.input('accountNumber', sql.NVarChar, accountNumber);
      bankReq.input('beneficiaryName', sql.NVarChar, beneficiaryName);
      bankReq.input('staffId', sql.Int, req.staff!.staffId);
      if (existing) {
        bankReq.input('bankDetailId', sql.Int, existing.BankDetailID);
        await bankReq.query(`
          UPDATE Member_Bank_Details SET BankName=@bankName, IFSCCode=@ifscCode, BankBranch=@bankBranch, BankAddress=@bankAddress,
                 AccountNumber=@accountNumber, BeneficiaryName=@beneficiaryName, UpdatedAt=GETDATE(), UpdatedBy=@staffId
          WHERE BankDetailID = @bankDetailId
        `);
      } else {
        await bankReq.query(`
          INSERT INTO Member_Bank_Details (MemberID, BankName, IFSCCode, BankBranch, BankAddress, AccountNumber, BeneficiaryName, CreatedBy, CreatedAt)
          VALUES (@memberId, @bankName, @ifscCode, @bankBranch, @bankAddress, @accountNumber, @beneficiaryName, @staffId, GETDATE())
        `);
      }
    }

    await transaction.commit();
    res.json({ success: true, data: { loanId } });
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
});

export default router;
