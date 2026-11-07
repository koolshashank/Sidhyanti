import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

/** Derived status -- Daily_Collection_Sheet only ever stores NULL/'Collected'/'Preclosed';
 * the richer Pending/Partial/Paid/Preclosed labels are computed, same as collection.php. */
const STATUS_CASE = `
  CASE
    WHEN dcs.IsPreclose = 1 THEN 'Preclosed'
    WHEN (dcs.EMIAmount - ISNULL(dcs.CollectionAmount, 0)) <= 0 THEN 'Paid'
    WHEN ISNULL(dcs.CollectionAmount, 0) > 0 THEN 'Partial'
    ELSE 'Pending'
  END
`;

/** GET /api/collections/lookups -- dropdown data for Branch/Center filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, centers] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT CenterID AS id, CenterName AS name, BranchID AS branchId FROM Center WHERE IsActive = 1 ORDER BY CenterName`),
  ]);
  res.json({ success: true, data: { branches: branches.recordset, centers: centers.recordset } });
});

/**
 * GET /api/collections
 * Query: page, perPage, dateFrom, dateTo, branch, center, status, q
 * Flat, filterable version of collection.php's drill-down dashboard --
 * modeled on api/v1/collections/due.php, with an explicit branch filter
 * added (due.php only scopes branch implicitly via the caller's role).
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(200, Math.max(1, Number(req.query.perPage) || 50));
  const dateFrom = (req.query.dateFrom as string | undefined) || todayIso();
  const dateTo = (req.query.dateTo as string | undefined) || todayIso();
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const center = req.query.center ? Number(req.query.center) : null;
  const status = (req.query.status as string | undefined)?.trim();
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = ['dcs.CollectionDueDate BETWEEN @dateFrom AND @dateTo'];
  if (branch) conditions.push('dcs.BranchID = @branch');
  if (center) conditions.push('dcs.CenterID = @center');
  if (status) conditions.push(`${STATUS_CASE} = @status`);
  if (q) conditions.push('(dcs.BorrowerName LIKE @q OR dcs.CoBorrowerName LIKE @q OR l.LoanNo LIKE @q)');
  const whereSql = `WHERE ${conditions.join(' AND ')}`;

  const pool = await getPool();

  function bindFilters(request: ReturnType<typeof pool.request>) {
    request.input('dateFrom', sql.Date, dateFrom);
    request.input('dateTo', sql.Date, dateTo);
    if (branch) request.input('branch', sql.Int, branch);
    if (center) request.input('center', sql.Int, center);
    if (status) request.input('status', sql.NVarChar, status);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }

  const baseFrom = `
    FROM Daily_Collection_Sheet dcs
    JOIN Loan l ON dcs.LoanID = l.LoanID
    LEFT JOIN Center c ON dcs.CenterID = c.CenterID
    LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
    LEFT JOIN Staff officer ON dcs.OfficerStaffID = officer.StaffID
  `;

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      SELECT dcs.CollectionDueDate AS dueDate, b.BranchName AS branchName, c.CenterName AS centerName,
             dcs.BorrowerName AS borrowerName, l.LoanNo AS loanNo, dcs.EMIAmount AS dueAmount,
             ISNULL(dcs.CollectionAmount, 0) AS collectedAmount, officer.FullName AS officerName,
             ${STATUS_CASE} AS status
      ${baseFrom}
      ${whereSql}
      ORDER BY dcs.CollectionDueDate DESC, b.BranchName, c.CenterName, dcs.BorrowerName
    `);
    const header = ['Date', 'Branch', 'Center', 'Borrower', 'Loan No', 'Due Amount', 'Collected Amount', 'Officer', 'Status'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.dueDate ? new Date(r.dueDate).toISOString().slice(0, 10) : '',
        r.branchName,
        r.centerName,
        r.borrowerName,
        r.loanNo,
        r.dueAmount,
        r.collectedAmount,
        r.officerName ?? 'Unassigned',
        r.status,
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="collections.csv"');
    return res.send(csv);
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total ${baseFrom} ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT dcs.SheetID AS sheetId, dcs.CollectionDueDate AS dueDate, dcs.LoanID AS loanId, l.LoanNo AS loanNo,
           dcs.InstallmentNo AS installmentNo, dcs.BorrowerName AS borrowerName, dcs.CoBorrowerName AS coBorrowerName,
           dcs.BranchID AS branchId, b.BranchName AS branchName, dcs.CenterID AS centerId, c.CenterName AS centerName,
           dcs.EMIAmount AS dueAmount, ISNULL(dcs.CollectionAmount, 0) AS collectedAmount,
           dcs.OfficerStaffID AS officerStaffId, officer.FullName AS officerName,
           ${STATUS_CASE} AS status
    ${baseFrom}
    ${whereSql}
    ORDER BY dcs.CollectionDueDate DESC, b.BranchName, c.CenterName, dcs.BorrowerName
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await bindFilters(pool.request()).query(`
    SELECT
      COUNT(DISTINCT dcs.LoanID) AS clientsDue,
      COALESCE(SUM(dcs.EMIAmount), 0) AS dueAmount,
      COALESCE(SUM(dcs.CollectionAmount), 0) AS collectedAmount
    ${baseFrom}
    ${whereSql}
  `);
  const row = statsResult.recordset[0];
  const dueAmount = Number(row.dueAmount ?? 0);
  const collectedAmount = Number(row.collectedAmount ?? 0);
  const stats = {
    clientsDue: row.clientsDue ?? 0,
    dueAmount,
    collectedAmount,
    balanceAmount: Math.max(0, dueAmount - collectedAmount),
    collectionPct: dueAmount > 0 ? Math.round((collectedAmount / dueAmount) * 1000) / 10 : 0,
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
