import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

/** GET /api/attendance/lookups -- dropdown data for the Meeting Attendance filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, centers] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT CenterID AS id, CenterName AS name, BranchID AS branchId FROM Center WHERE IsActive = 1 ORDER BY CenterName`),
  ]);
  res.json({ success: true, data: { branches: branches.recordset, centers: centers.recordset } });
});

/**
 * GET /api/attendance
 * Query: page, perPage, dateFrom, dateTo, branch, center, status, q
 * Mirrors meeting_attendance.php -- Center_Meeting_Attendance is loan-centric
 * (BorrowerName is a denormalized text column, not a Member FK), same as
 * Daily_Collection_Sheet.
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 25));
  const dateFrom = (req.query.dateFrom as string | undefined) || todayIso();
  const dateTo = (req.query.dateTo as string | undefined) || todayIso();
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const center = req.query.center ? Number(req.query.center) : null;
  const status = (req.query.status as string | undefined)?.trim();
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = ['a.AttendanceDate BETWEEN @dateFrom AND @dateTo'];
  if (branch) conditions.push('c.BranchID = @branch');
  if (center) conditions.push('a.CenterID = @center');
  if (status) conditions.push('a.Status = @status');
  if (q) conditions.push('(a.BorrowerName LIKE @q OR l.LoanNo LIKE @q)');
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
    FROM Center_Meeting_Attendance a
    LEFT JOIN Center c ON a.CenterID = c.CenterID
    LEFT JOIN Branch b ON c.BranchID = b.BranchID
    LEFT JOIN Loan l ON a.LoanID = l.LoanID
    LEFT JOIN Staff s ON a.MarkedByStaffID = s.StaffID
  `;

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      SELECT a.AttendanceDate AS attendanceDate, c.CenterName AS centerName, b.BranchName AS branchName,
             a.BorrowerName AS borrowerName, l.LoanNo AS loanNo, a.Status AS status,
             s.FullName AS markedByName, a.MarkedAt AS markedAt
      ${baseFrom}
      ${whereSql}
      ORDER BY a.AttendanceDate DESC, c.CenterName, a.BorrowerName
    `);
    const header = ['Date', 'Center', 'Branch', 'Borrower', 'Loan No', 'Status', 'Marked By', 'Marked At'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.attendanceDate ? new Date(r.attendanceDate).toISOString().slice(0, 10) : '',
        r.centerName,
        r.branchName,
        r.borrowerName,
        r.loanNo,
        r.status,
        r.markedByName ?? '-',
        r.markedAt ? new Date(r.markedAt).toISOString() : '',
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="meeting-attendance.csv"');
    return res.send(csv);
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total ${baseFrom} ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT a.AttendanceID AS attendanceId, a.AttendanceDate AS attendanceDate, a.CenterID AS centerId,
           c.CenterName AS centerName, b.BranchName AS branchName, a.LoanID AS loanId, l.LoanNo AS loanNo,
           a.BorrowerName AS borrowerName, a.Status AS status, a.MarkedByStaffID AS markedByStaffId,
           s.FullName AS markedByName, a.MarkedAt AS markedAt
    ${baseFrom}
    ${whereSql}
    ORDER BY a.AttendanceDate DESC, c.CenterName, a.BorrowerName
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await bindFilters(pool.request()).query(`
    SELECT
      COUNT(*) AS totalMarked,
      SUM(CASE WHEN a.Status = 'Present' THEN 1 ELSE 0 END) AS present,
      SUM(CASE WHEN a.Status = 'Absent' THEN 1 ELSE 0 END) AS absent,
      COUNT(DISTINCT a.CenterID) AS centersCovered
    ${baseFrom}
    ${whereSql}
  `);
  const row = statsResult.recordset[0];
  const stats = {
    totalMarked: row.totalMarked ?? 0,
    present: row.present ?? 0,
    absent: row.absent ?? 0,
    centersCovered: row.centersCovered ?? 0,
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
