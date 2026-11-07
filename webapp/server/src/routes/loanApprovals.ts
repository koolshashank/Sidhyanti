import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { roleHasPermission } from '../utils/permissions.js';
import { notifyStaff } from '../utils/notify.js';

const router = Router();
router.use(requireAuth);

const LOAN_PROPOSAL_BM_LIMIT = 20000;

function isAdminOrSuper(roleName: string | null): boolean {
  return roleName === 'Admin' || roleName === 'SuperAdmin';
}

/** Port of loan_approval_helper.php::loan_proposal_status_condition() -- which status this role's queue shows. */
function proposalStatusForRole(roleName: string | null): string | null {
  if (roleName === 'Branch Manager') return 'Pending';
  if (roleName === 'Area Manager') return 'BM Approved';
  return null;
}

/** Port of loan_preclose_helper.php::loan_preclose_quote(). */
async function precloseQuote(loanId: number) {
  const pool = await getPool();
  const rows = (
    await pool.request().input('loanId', sql.Int, loanId).query(`
      SELECT PrincipalDue, InterestDue, IsPaid,
        (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
        (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected', 'Preclosed')) AS SheetCollectionDate
      FROM Repayment_Schedule rs WHERE rs.LoanID = @loanId
    `)
  ).recordset;
  if (!rows.length) return null;

  let paidCount = 0;
  let principalOutstanding = 0;
  let interestSavings = 0;
  for (const r of rows) {
    const isPaid = Boolean(r.IsPaid) || r.LedgerCollectionDate || r.SheetCollectionDate;
    if (isPaid) {
      paidCount++;
      continue;
    }
    principalOutstanding += Number(r.PrincipalDue ?? 0);
    interestSavings += Number(r.InterestDue ?? 0);
  }
  return {
    installmentsPaid: paidCount,
    totalInstallments: rows.length,
    principalOutstanding: Math.round(principalOutstanding * 100) / 100,
    interestSavings: Math.round(interestSavings * 100) / 100,
    precloseAmount: Math.round(principalOutstanding * 100) / 100,
  };
}

/** GET /api/loan-approvals -- pending Proposal + Preclose counts/lists this caller can review. */
router.get('/', async (req, res) => {
  const claims = req.staff!;
  const [canProposal, canPreclose] = await Promise.all([
    roleHasPermission(claims, 'approve_loan_proposal'),
    roleHasPermission(claims, 'approve_loan_preclose'),
  ]);

  const pool = await getPool();
  const branchScope = isAdminOrSuper(claims.roleName) ? '' : 'AND l.BranchID = @branchId';

  let proposals: unknown[] = [];
  if (canProposal) {
    const status = proposalStatusForRole(claims.roleName);
    if (status) {
      const request = pool.request();
      request.input('status', sql.NVarChar, status);
      if (branchScope) request.input('branchId', sql.Int, claims.branchId);
      const result = await request.query(`
        SELECT la.LoanApprovalID AS loanApprovalId, la.LoanID AS loanId, la.RequestRemarks AS requestRemarks, la.CreatedAt AS createdAt,
               l.LoanNo AS loanNo, l.LoanAmountSanctioned AS loanAmountSanctioned, l.LoanAmountApplied AS loanAmountApplied,
               m.FirstName AS firstName, m.LastName AS lastName, req.FullName AS requestedByName
        FROM Loan_Approval la
        JOIN Loan l ON la.LoanID = l.LoanID
        JOIN Member m ON l.MemberID = m.MemberID
        LEFT JOIN Staff req ON la.RequestedByStaffID = req.StaffID
        WHERE la.RequestType = 'Proposal' AND la.Status = @status ${branchScope}
        ORDER BY la.CreatedAt ASC
      `);
      proposals = result.recordset;
    }
  }

  let preclosures: unknown[] = [];
  if (canPreclose) {
    const request = pool.request();
    if (branchScope) request.input('branchId', sql.Int, claims.branchId);
    const result = await request.query(`
      SELECT la.LoanApprovalID AS loanApprovalId, la.LoanID AS loanId, la.RequestRemarks AS requestRemarks, la.CreatedAt AS createdAt,
             l.LoanNo AS loanNo, l.LoanAmountSanctioned AS loanAmountSanctioned, l.LoanAmountApplied AS loanAmountApplied,
             m.FirstName AS firstName, m.LastName AS lastName, req.FullName AS requestedByName
      FROM Loan_Approval la
      JOIN Loan l ON la.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Staff req ON la.RequestedByStaffID = req.StaffID
      WHERE la.RequestType = 'Preclose' AND la.Status = 'Pending' ${branchScope}
      ORDER BY la.CreatedAt ASC
    `);
    preclosures = await Promise.all(
      result.recordset.map(async (p: any) => ({ ...p, quote: await precloseQuote(p.loanId) })),
    );
  }

  res.json({ success: true, data: { canProposal, canPreclose, proposals, preclosures } });
});

/**
 * POST /api/loan-approvals/:id/review -- full port of review_loan_approval().
 * Preclose: single-step, moves Loan.CurrentStageID to 'Preclosed' on approve.
 * Proposal: two-step above LOAN_PROPOSAL_BM_LIMIT (BM -> Area Manager);
 * approving at either stage requires the loan's latest GRT to have passed.
 */
router.post('/:id/review', async (req, res) => {
  const claims = req.staff!;
  const loanApprovalId = Number(req.params.id);
  const action = req.body.action as 'approve' | 'reject';
  const remarks = (req.body.remarks as string | undefined)?.trim() || null;
  if (!['approve', 'reject'].includes(action)) {
    return res.status(400).json({ success: false, message: 'Invalid action' });
  }

  const pool = await getPool();
  const la = (
    await pool.request().input('id', sql.Int, loanApprovalId).query(`
      SELECT la.LoanApprovalID, la.LoanID, la.RequestType, la.RequestedByStaffID, la.Status,
             l.LoanNo, l.BranchID, l.LoanAmountSanctioned, l.LoanAmountApplied
      FROM Loan_Approval la JOIN Loan l ON la.LoanID = l.LoanID
      WHERE la.LoanApprovalID = @id
    `)
  ).recordset[0];
  if (!la) return res.status(404).json({ success: false, message: 'Approval request not found' });

  const permissionKey = la.RequestType === 'Preclose' ? 'approve_loan_preclose' : 'approve_loan_proposal';
  if (!(await roleHasPermission(claims, permissionKey))) {
    return res.status(403).json({ success: false, message: 'You do not have permission to review this request' });
  }
  if (!isAdminOrSuper(claims.roleName) && la.BranchID !== claims.branchId) {
    return res.status(403).json({ success: false, message: 'This loan is outside your branch scope' });
  }

  const notify: { staffId: number; title: string; message: string }[] = [];
  let newStatus = '';

  if (la.RequestType === 'Preclose') {
    if (la.Status !== 'Pending') {
      return res.status(409).json({ success: false, message: 'This request was already reviewed' });
    }
    newStatus = action === 'approve' ? 'Approved' : 'Rejected';

    const transaction = new sql.Transaction(pool);
    await transaction.begin();
    try {
      const updateReq = new sql.Request(transaction);
      updateReq.input('status', sql.NVarChar, newStatus);
      updateReq.input('remarks', sql.NVarChar, remarks);
      updateReq.input('staffId', sql.Int, claims.staffId);
      updateReq.input('id', sql.Int, loanApprovalId);
      const updateResult = await updateReq.query(`
        UPDATE Loan_Approval SET Status = @status, RemarksByApprover = @remarks, ReviewedByStaffID = @staffId, ReviewedAt = GETDATE()
        OUTPUT INSERTED.LoanApprovalID
        WHERE LoanApprovalID = @id AND Status = 'Pending'
      `);
      if (updateResult.recordset.length !== 1) {
        throw Object.assign(new Error('This request was already reviewed'), { httpCode: 409 });
      }
      if (action === 'approve') {
        const stageReq = new sql.Request(transaction);
        stageReq.input('loanId', sql.Int, la.LoanID);
        stageReq.input('staffId', sql.Int, claims.staffId);
        await stageReq.query(`
          UPDATE Loan SET CurrentStageID = (SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Preclosed'), UpdatedAt = GETDATE(), UpdatedBy = @staffId
          WHERE LoanID = @loanId
        `);
      }
      await transaction.commit();
    } catch (err: any) {
      await transaction.rollback();
      if (err.httpCode) return res.status(err.httpCode).json({ success: false, message: err.message });
      throw err;
    }

    let msg = `Preclose request for loan ${la.LoanNo} was ${newStatus.toLowerCase()}.`;
    if (remarks) msg += ` Remarks: ${remarks}`;
    notify.push({ staffId: la.RequestedByStaffID, title: 'Preclose Request Reviewed', message: msg });
  } else {
    const violation =
      claims.roleName === 'Branch Manager' && la.Status !== 'Pending'
        ? 'This request is no longer awaiting Branch Manager review'
        : claims.roleName === 'Area Manager' && la.Status !== 'BM Approved'
          ? 'This request is not awaiting Area Manager review -- it needs Branch Manager sign-off first'
          : null;
    if (violation) return res.status(403).json({ success: false, message: violation });

    if (action === 'approve') {
      const grtStatus = (
        await pool.request().input('loanId', sql.Int, la.LoanID).query(`
          SELECT TOP 1 gsm.GRTStatusName FROM GRT_Details gd JOIN GRT_Status_Master gsm ON gd.GRTStatusID = gsm.GRTStatusID
          WHERE gd.LoanID = @loanId ORDER BY gd.GRTID DESC
        `)
      ).recordset[0]?.GRTStatusName;
      if (grtStatus !== 'P') {
        return res.status(409).json({ success: false, message: 'GRT must be conducted and passed before this proposal can be approved' });
      }
    }

    const loanAmount = Number(la.LoanAmountSanctioned || la.LoanAmountApplied);
    const isForwardToAm = claims.roleName === 'Branch Manager' && action === 'approve' && loanAmount > LOAN_PROPOSAL_BM_LIMIT;
    newStatus = action === 'reject' ? 'Rejected' : isForwardToAm ? 'BM Approved' : 'Approved';

    const transaction = new sql.Transaction(pool);
    await transaction.begin();
    try {
      let rowCount = 0;
      if (isForwardToAm) {
        const fwdReq = new sql.Request(transaction);
        fwdReq.input('staffId', sql.Int, claims.staffId);
        fwdReq.input('remarks', sql.NVarChar, remarks);
        fwdReq.input('id', sql.Int, loanApprovalId);
        const r = await fwdReq.query(`
          UPDATE Loan_Approval SET Status = 'BM Approved', BMReviewedByStaffID = @staffId, BMReviewedAt = GETDATE(), BMRemarks = @remarks
          OUTPUT INSERTED.LoanApprovalID
          WHERE LoanApprovalID = @id AND Status = 'Pending'
        `);
        rowCount = r.recordset.length;
      } else {
        const updReq = new sql.Request(transaction);
        updReq.input('status', sql.NVarChar, newStatus);
        updReq.input('remarks', sql.NVarChar, remarks);
        updReq.input('staffId', sql.Int, claims.staffId);
        updReq.input('id', sql.Int, loanApprovalId);
        updReq.input('currentStatus', sql.NVarChar, la.Status);
        const r = await updReq.query(`
          UPDATE Loan_Approval SET Status = @status, RemarksByApprover = @remarks, ReviewedByStaffID = @staffId, ReviewedAt = GETDATE()
          OUTPUT INSERTED.LoanApprovalID
          WHERE LoanApprovalID = @id AND Status = @currentStatus
        `);
        rowCount = r.recordset.length;
      }
      if (rowCount !== 1) {
        throw Object.assign(new Error('This request was already reviewed'), { httpCode: 409 });
      }
      await transaction.commit();
    } catch (err: any) {
      await transaction.rollback();
      if (err.httpCode) return res.status(err.httpCode).json({ success: false, message: err.message });
      throw err;
    }

    if (isForwardToAm) {
      let msg = `Loan proposal for loan ${la.LoanNo} was approved by Branch Manager and forwarded to Area Manager for final approval.`;
      if (remarks) msg += ` Remarks: ${remarks}`;
      notify.push({ staffId: la.RequestedByStaffID, title: 'Loan Proposal Forwarded', message: msg });

      const amStaff = (
        await pool.request().input('branchId', sql.Int, la.BranchID).query(`
          SELECT DISTINCT s.StaffID FROM Staff s
          WHERE s.RoleID = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Area Manager') AND s.IsActive = 1
            AND (s.BranchID = @branchId OR EXISTS (SELECT 1 FROM Staff_Branch_Map m WHERE m.StaffID = s.StaffID AND m.BranchID = @branchId))
        `)
      ).recordset;
      const amMsg = `A loan proposal for loan ${la.LoanNo} (above Rs.${LOAN_PROPOSAL_BM_LIMIT.toLocaleString('en-IN')}) is awaiting your final approval.`;
      for (const row of amStaff) {
        if (row.StaffID !== claims.staffId) notify.push({ staffId: row.StaffID, title: 'Loan Proposal Awaiting Final Approval', message: amMsg });
      }
    } else {
      let msg = `Loan proposal for loan ${la.LoanNo} was ${newStatus.toLowerCase()}.`;
      if (remarks) msg += ` Remarks: ${remarks}`;
      notify.push({ staffId: la.RequestedByStaffID, title: 'Loan Proposal Reviewed', message: msg });
    }
  }

  for (const n of notify) {
    if (n.staffId > 0) await notifyStaff(pool, n.staffId, n.title, n.message, 1, 'Loan', la.LoanID);
  }

  res.json({ success: true, data: { status: newStatus } });
});

export default router;
