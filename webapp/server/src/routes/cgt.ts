import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

const DAY_NAMES: Record<number, string> = {
  1: 'Monday',
  2: 'Tuesday',
  3: 'Wednesday',
  4: 'Thursday',
  5: 'Friday',
  6: 'Saturday',
  7: 'Sunday',
};

/** GET /api/cgt/lookups -- dropdown data for the CGT filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const branches = await pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`);
  res.json({ success: true, data: { branches: branches.recordset } });
});

/**
 * GET /api/cgt
 * Query: page, perPage, branch, status (done|pending), q
 *
 * CGT (Center Group Training) is one-per-center, not loan-level like GRT:
 * every active Center gets a row, LEFT JOINed to its latest
 * Center_CGT_Details record (if any) -- same CTE/ROW_NUMBER pattern cgt.php
 * uses to pick the latest training per center.
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const status = (req.query.status as string | undefined)?.trim();
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = ['c.IsActive = 1'];
  if (branch) conditions.push('b.BranchID = @branch');
  if (status === 'done') conditions.push('lc.CGTID IS NOT NULL');
  else if (status === 'pending') conditions.push('lc.CGTID IS NULL');
  if (q) conditions.push('(c.CenterCode LIKE @q OR c.CenterName LIKE @q OR b.BranchName LIKE @q)');
  const whereSql = `WHERE ${conditions.join(' AND ')}`;

  const pool = await getPool();

  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (branch) request.input('branch', sql.Int, branch);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }

  const cte = `
    WITH LatestCGT AS (
      SELECT ccd.*, ROW_NUMBER() OVER (PARTITION BY ccd.CenterID ORDER BY ccd.CGTDate DESC, ccd.CGTID DESC) AS rn
      FROM Center_CGT_Details ccd
    )
  `;

  const baseFrom = `
    FROM Branch b
    JOIN Center c ON c.BranchID = b.BranchID
    LEFT JOIN LatestCGT lc ON lc.CenterID = c.CenterID AND lc.rn = 1
    LEFT JOIN Staff st ON lc.ConductedByStaffID = st.StaffID
  `;

  const selectCols = `
    b.BranchID AS branchId, b.BranchName AS branchName,
    c.CenterID AS centerId, c.CenterCode AS centerCode, c.CenterName AS centerName, c.CenterPlace AS centerPlace,
    c.ContactPerson AS contactPerson, c.ContactNo AS contactNo, c.MeetingDay AS meetingDay, c.MeetingTime AS meetingTime,
    lc.CGTID AS cgtId, lc.CGTDate AS cgtDate, st.FullName AS conductedBy, lc.DocumentFilePath AS cgtImage, lc.Remarks AS remarks,
    CASE WHEN lc.CGTID IS NULL THEN 'Pending' ELSE 'Done' END AS cgtStatus
  `;

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      ${cte}
      SELECT ${selectCols}
      ${baseFrom}
      ${whereSql}
      ORDER BY b.BranchName, c.CenterName
    `);
    const header = ['Branch', 'Center Code', 'Center', 'Contact Person', 'Contact No', 'Meeting Day', 'CGT Date', 'Conducted By', 'Status'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.branchName,
        r.centerCode,
        r.centerName,
        r.contactPerson ?? '-',
        r.contactNo ?? '-',
        DAY_NAMES[Number(r.meetingDay)] ?? r.meetingDay ?? '-',
        r.cgtDate ? new Date(r.cgtDate).toISOString().slice(0, 10) : '',
        r.conductedBy ?? 'Unassigned',
        r.cgtStatus,
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="cgt-records.csv"');
    return res.send(csv);
  }

  const total = (await bindFilters(pool.request()).query(`${cte} SELECT COUNT(*) AS total ${baseFrom} ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    ${cte}
    SELECT ${selectCols}
    ${baseFrom}
    ${whereSql}
    ORDER BY b.BranchName, c.CenterName
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const totalCentersAll = (await pool.request().query(`SELECT COUNT(*) AS total FROM Center WHERE IsActive = 1`)).recordset[0].total;
  const doneAll = (
    await pool.request().query(`
      ${cte}
      SELECT COUNT(*) AS total
      FROM Center c
      JOIN LatestCGT lc ON lc.CenterID = c.CenterID AND lc.rn = 1
      WHERE c.IsActive = 1
    `)
  ).recordset[0].total;
  const stats = {
    totalCenters: totalCentersAll,
    done: doneAll,
    pending: totalCentersAll - doneAll,
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
