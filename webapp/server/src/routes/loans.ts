import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/loans/lookups -- dropdown data for the Loans list filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, stages] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT StageID AS id, StageName AS name, SortOrder AS sortOrder FROM Loan_Stage_Master ORDER BY SortOrder`),
  ]);
  res.json({ success: true, data: { branches: branches.recordset, stages: stages.recordset } });
});

/**
 * GET /api/loans
 * Query: page, perPage, branch, stage, q
 *
 * "Disbursed" is a stage name in Loan_Stage_Master, but the workflow moves
 * CurrentStageID straight on to Active/Closed/Preclosed after disbursing --
 * same as loans.php, so a loan is counted/filtered into "Disbursed" by
 * DisbursementDate being set, not by CurrentStageID, while every other
 * stage filters by CurrentStageID as normal.
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const stage = (req.query.stage as string | undefined)?.trim();
  const q = (req.query.q as string | undefined)?.trim();

  const baseConditions: string[] = [];
  if (branch) baseConditions.push('l.BranchID = @branch');
  if (q) baseConditions.push('(l.LoanNo LIKE @q OR m.FirstName LIKE @q OR m.LastName LIKE @q)');
  const baseWhereSql = baseConditions.length ? `WHERE ${baseConditions.join(' AND ')}` : '';

  const stageConditions = [...baseConditions];
  if (stage === 'Disbursed') stageConditions.push('l.DisbursementDate IS NOT NULL');
  else if (stage) stageConditions.push('stg.StageName = @stage');
  const stageWhereSql = stageConditions.length ? `WHERE ${stageConditions.join(' AND ')}` : '';

  const pool = await getPool();

  function bindBase(request: ReturnType<typeof pool.request>) {
    if (branch) request.input('branch', sql.Int, branch);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }
  function bindStage(request: ReturnType<typeof pool.request>) {
    bindBase(request);
    if (stage) request.input('stage', sql.NVarChar, stage);
    return request;
  }

  if (req.query.format === 'csv') {
    const csvResult = await bindStage(pool.request()).query(`
      SELECT l.LoanNo AS loanNo, m.FirstName AS firstName, m.LastName AS lastName, b.BranchName AS branchName,
             p.PurposeName AS purposeName, officer.FullName AS officerName,
             COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS amount, stg.StageName AS stageName, l.ApplicationDate AS applicationDate
      FROM Loan l
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      LEFT JOIN Loan_Purpose_Master p ON l.PurposeID = p.PurposeID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      ${stageWhereSql}
      ORDER BY l.ApplicationDate DESC
    `);
    const header = ['Loan No', 'Member', 'Branch', 'Purpose', 'Officer', 'Amount', 'Stage', 'Applied'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.loanNo,
        `${r.firstName} ${r.lastName}`.trim(),
        r.branchName ?? '-',
        r.purposeName ?? '-',
        r.officerName ?? 'Unassigned',
        r.amount,
        r.stageName,
        r.applicationDate ? new Date(r.applicationDate).toISOString().slice(0, 10) : '',
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="loans.csv"');
    return res.send(csv);
  }

  const total = (
    await bindStage(pool.request()).query(`
      SELECT COUNT(*) AS total FROM Loan l
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      ${stageWhereSql}
    `)
  ).recordset[0].total;

  const listReq = bindStage(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT l.LoanID AS loanId, l.LoanNo AS loanNo, l.LoanAmountSanctioned AS loanAmountSanctioned,
           l.LoanAmountApplied AS loanAmountApplied, l.ApplicationDate AS applicationDate, l.DisbursementDate AS disbursementDate,
           m.MemberID AS memberId, m.FirstName AS firstName, m.LastName AS lastName,
           b.BranchName AS branchName, stg.StageName AS stageName, p.PurposeName AS purposeName, officer.FullName AS officerName
    FROM Loan l
    JOIN Member m ON l.MemberID = m.MemberID
    LEFT JOIN Branch b ON l.BranchID = b.BranchID
    LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
    LEFT JOIN Loan_Purpose_Master p ON l.PurposeID = p.PurposeID
    LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
    ${stageWhereSql}
    ORDER BY l.ApplicationDate DESC
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  // KPIs + per-stage tab counts are computed over the branch/search filter
  // only (not the stage filter) -- same scope loans.php's $allLoans uses,
  // so switching stage tabs doesn't make the top numbers jump around.
  const summaryResult = await bindBase(pool.request()).query(`
    SELECT stg.StageName AS stageName,
           COUNT(*) AS loanCount,
           COALESCE(SUM(COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied)), 0) AS stageAmount,
           SUM(CASE WHEN l.DisbursementDate IS NOT NULL THEN 1 ELSE 0 END) AS disbursedCount
    FROM Loan l
    JOIN Member m ON l.MemberID = m.MemberID
    LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
    ${baseWhereSql}
    GROUP BY stg.StageName
  `);

  const stageCounts: Record<string, number> = {};
  let totalLoans = 0;
  let totalAmount = 0;
  let activeCount = 0;
  let disbursedCount = 0;
  for (const row of summaryResult.recordset) {
    stageCounts[row.stageName] = row.loanCount;
    totalLoans += row.loanCount;
    totalAmount += row.stageAmount;
    if (row.stageName === 'Active') activeCount = row.loanCount;
    disbursedCount += row.disbursedCount;
  }
  stageCounts['Disbursed'] = disbursedCount;

  res.json({
    success: true,
    data: {
      items: listResult.recordset,
      total,
      page,
      perPage,
      stats: {
        totalLoans,
        totalAmount,
        activeCount,
        avgTicket: totalLoans > 0 ? totalAmount / totalLoans : 0,
      },
      stageCounts,
    },
  });
});

export default router;
