import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/grt/lookups -- dropdown data for the GRT filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, centers, statuses] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT CenterID AS id, CenterName AS name, BranchID AS branchId FROM Center WHERE IsActive = 1 ORDER BY CenterName`),
    pool.request().query(`SELECT GRTStatusID AS id, GRTStatusName AS name FROM GRT_Status_Master ORDER BY GRTStatusName`),
  ]);
  res.json({ success: true, data: { branches: branches.recordset, centers: centers.recordset, statuses: statuses.recordset } });
});

/**
 * GET /api/grt
 * Query: page, perPage, dateFrom, dateTo, branch, center, status, q
 *
 * GRT (Group Recognition Test) data is sourced from GRT_Details only, same
 * canonical join grt.php uses -- see memory grt_data_source. Center isn't on
 * GRT_Details or Loan directly, so it's reached via Member.CenterID.
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const dateFrom = (req.query.dateFrom as string | undefined) || null;
  const dateTo = (req.query.dateTo as string | undefined) || null;
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const center = req.query.center ? Number(req.query.center) : null;
  const status = req.query.status ? Number(req.query.status) : null;
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [];
  if (dateFrom && dateTo) conditions.push('g.GRTDate BETWEEN @dateFrom AND @dateTo');
  if (branch) conditions.push('l.BranchID = @branch');
  if (center) conditions.push('m.CenterID = @center');
  if (status) conditions.push('gs.GRTStatusID = @status');
  if (q) conditions.push('(l.LoanNo LIKE @q OR m.FirstName LIKE @q OR m.LastName LIKE @q)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();

  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (dateFrom && dateTo) {
      request.input('dateFrom', sql.Date, dateFrom);
      request.input('dateTo', sql.Date, dateTo);
    }
    if (branch) request.input('branch', sql.Int, branch);
    if (center) request.input('center', sql.Int, center);
    if (status) request.input('status', sql.Int, status);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }

  const baseFrom = `
    FROM GRT_Details g
    JOIN Loan l ON g.LoanID = l.LoanID
    JOIN Member m ON l.MemberID = m.MemberID
    JOIN GRT_Status_Master gs ON g.GRTStatusID = gs.GRTStatusID
    LEFT JOIN Staff st ON g.ConductedByStaffID = st.StaffID
    LEFT JOIN Branch b ON l.BranchID = b.BranchID
    LEFT JOIN Center c ON m.CenterID = c.CenterID
  `;

  const selectCols = `
    g.GRTID AS grtId, g.LoanID AS loanId, l.LoanNo AS loanNo,
    b.BranchName AS branchName, c.CenterName AS centerName,
    CONCAT(m.FirstName, ' ', ISNULL(m.MiddleName + ' ', ''), m.LastName) AS clientName,
    (SELECT TOP 1 CONCAT(cb.FirstName, ' ', ISNULL(cb.MiddleName + ' ', ''), cb.LastName)
       FROM Co_Borrower cb WHERE cb.MemberID = m.MemberID) AS coBorrowerName,
    g.FinanceAmountVerified AS financeAmount,
    gs.GRTStatusID AS statusId, gs.GRTStatusName AS statusCode,
    st.FullName AS conductedBy,
    g.GRTDate AS grtDate,
    g.EnrollmentGRTFileName AS fileName
  `;

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      SELECT ${selectCols}
      ${baseFrom}
      ${whereSql}
      ORDER BY g.GRTDate DESC
    `);
    const header = ['Loan No', 'Client', 'Co-Borrower', 'Branch', 'Center', 'Finance Verified', 'Status', 'Conducted By', 'GRT Date'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.loanNo,
        r.clientName,
        r.coBorrowerName ?? '-',
        r.branchName ?? '-',
        r.centerName ?? '-',
        r.financeAmount,
        r.statusCode === 'P' ? 'Passed' : r.statusCode === 'F' ? 'Failed' : r.statusCode,
        r.conductedBy ?? 'Unassigned',
        r.grtDate ? new Date(r.grtDate).toISOString().slice(0, 10) : '',
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="grt-records.csv"');
    return res.send(csv);
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total ${baseFrom} ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT ${selectCols}
    ${baseFrom}
    ${whereSql}
    ORDER BY g.GRTDate DESC
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await bindFilters(pool.request()).query(`
    SELECT
      COUNT(*) AS totalGrt,
      SUM(CASE WHEN gs.GRTStatusName = 'P' THEN 1 ELSE 0 END) AS passed,
      SUM(CASE WHEN gs.GRTStatusName = 'F' THEN 1 ELSE 0 END) AS failed,
      COALESCE(SUM(g.FinanceAmountVerified), 0) AS financeVerified
    ${baseFrom}
    ${whereSql}
  `);
  const row = statsResult.recordset[0];
  const stats = {
    totalGrt: row.totalGrt ?? 0,
    passed: row.passed ?? 0,
    failed: row.failed ?? 0,
    financeVerified: row.financeVerified ?? 0,
  };

  res.json({
    success: true,
    data: {
      items: listResult.recordset,
      total,
      page,
      perPage,
      stats,
    },
  });
});

export default router;
