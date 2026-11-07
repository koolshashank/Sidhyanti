import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { roleHasPermission } from '../utils/permissions.js';

const router = Router();
router.use(requireAuth);

/** GET /api/tvr/lookups -- dropdown data for the TVR filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, centers, statuses] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT CenterID AS id, CenterName AS name, BranchID AS branchId FROM Center WHERE IsActive = 1 ORDER BY CenterName`),
    pool.request().query(`SELECT TVRStatusID AS id, TVRStatusName AS name FROM TVR_Status_Master ORDER BY TVRStatusID`),
  ]);
  res.json({ success: true, data: { branches: branches.recordset, centers: centers.recordset, statuses: statuses.recordset } });
});

/**
 * GET /api/tvr
 * Query: page, perPage, dateFrom, dateTo, branch, center, status, q
 *
 * TVR (Telephonic Verification Report) is sourced from TVR_Details, loan-level
 * same as GRT -- one row per loan (app-level upsert on conduct), joined
 * through Loan for branch/center/amount and Member for the client.
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
  if (dateFrom && dateTo) conditions.push('tv.TVRDate BETWEEN @dateFrom AND @dateTo');
  if (branch) conditions.push('l.BranchID = @branch');
  if (center) conditions.push('l.CenterID = @center');
  if (status) conditions.push('tv.TVRStatusID = @status');
  if (q) conditions.push('(l.LoanNo LIKE @q OR m.FirstName LIKE @q OR m.LastName LIKE @q OR tv.ContactPersonName LIKE @q)');
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
    FROM TVR_Details tv
    JOIN Loan l ON tv.LoanID = l.LoanID
    JOIN Member m ON l.MemberID = m.MemberID
    JOIN TVR_Status_Master ts ON tv.TVRStatusID = ts.TVRStatusID
    LEFT JOIN Branch b ON l.BranchID = b.BranchID
    LEFT JOIN Center c ON l.CenterID = c.CenterID
    LEFT JOIN Staff st ON tv.ConductedByStaffID = st.StaffID
  `;

  const selectCols = `
    tv.TVRID AS tvrId, l.LoanID AS loanId, l.LoanNo AS loanNo,
    b.BranchName AS branchName, c.CenterName AS centerName,
    CONCAT(m.FirstName, ' ', ISNULL(m.MiddleName + ' ', ''), m.LastName) AS clientName,
    m.MobileNo AS mobileNo, tv.ContactPersonName AS contactPersonName,
    COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS loanAmount,
    tv.TVRDate AS tvrDate, st.FullName AS conductedBy,
    ts.TVRStatusID AS statusId, ts.TVRStatusName AS statusName,
    tv.VerificationNotes AS verificationNotes
  `;

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      SELECT ${selectCols}
      ${baseFrom}
      ${whereSql}
      ORDER BY tv.TVRDate DESC
    `);
    const header = ['Loan No', 'Client', 'Mobile', 'Contact Person', 'Branch', 'Center', 'Loan Amount', 'TVR Date', 'Conducted By', 'Status', 'Notes'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.loanNo,
        r.clientName,
        r.mobileNo,
        r.contactPersonName ?? '-',
        r.branchName ?? '-',
        r.centerName ?? '-',
        r.loanAmount,
        r.tvrDate ? new Date(r.tvrDate).toISOString().slice(0, 10) : '',
        r.conductedBy ?? 'Unassigned',
        r.statusName,
        r.verificationNotes ?? '',
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="tvr-records.csv"');
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
    ORDER BY tv.TVRDate DESC
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await bindFilters(pool.request()).query(`
    SELECT
      COUNT(*) AS totalTvr,
      SUM(CASE WHEN ts.TVRStatusName = 'Approved' THEN 1 ELSE 0 END) AS approved,
      SUM(CASE WHEN ts.TVRStatusName = 'Rejected' THEN 1 ELSE 0 END) AS rejected,
      SUM(CASE WHEN ts.TVRStatusName = 'Pending' THEN 1 ELSE 0 END) AS pending,
      SUM(CASE WHEN ts.TVRStatusName NOT IN ('Approved', 'Rejected', 'Pending') THEN 1 ELSE 0 END) AS other
    ${baseFrom}
    ${whereSql}
  `);
  const row = statsResult.recordset[0];
  const stats = {
    totalTvr: row.totalTvr ?? 0,
    approved: row.approved ?? 0,
    rejected: row.rejected ?? 0,
    pending: row.pending ?? 0,
    other: row.other ?? 0,
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

/**
 * GET /api/tvr/pending
 * Worklist of loans that still need a TVR -- same shape as the mobile app's
 * tvr_pending.php: loans in Applied/GRT Pending/Approved with no TVR_Details
 * row yet. Query: branch, center, q.
 */
router.get('/pending', async (req, res) => {
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const center = req.query.center ? Number(req.query.center) : null;
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [`stg.StageName IN ('Applied', 'GRT Pending', 'Approved')`, 'NOT EXISTS (SELECT 1 FROM TVR_Details tv WHERE tv.LoanID = l.LoanID)'];
  if (branch) conditions.push('l.BranchID = @branch');
  if (center) conditions.push('l.CenterID = @center');
  if (q) conditions.push('(l.LoanNo LIKE @q OR m.FirstName LIKE @q OR m.LastName LIKE @q)');
  const whereSql = `WHERE ${conditions.join(' AND ')}`;

  const pool = await getPool();
  const request = pool.request();
  if (branch) request.input('branch', sql.Int, branch);
  if (center) request.input('center', sql.Int, center);
  if (q) request.input('q', sql.NVarChar, `%${q}%`);

  const result = await request.query(`
    SELECT l.LoanID AS loanId, l.LoanNo AS loanNo, m.MemberID AS memberId,
           CONCAT(m.FirstName, ' ', ISNULL(m.MiddleName + ' ', ''), m.LastName) AS clientName,
           m.MobileNo AS mobileNo, b.BranchName AS branchName, c.CenterName AS centerName,
           COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS loanAmount,
           l.ApplicationDate AS applicationDate, stg.StageName AS stageName
    FROM Loan l
    JOIN Member m ON l.MemberID = m.MemberID
    LEFT JOIN Branch b ON l.BranchID = b.BranchID
    LEFT JOIN Center c ON l.CenterID = c.CenterID
    LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
    ${whereSql}
    ORDER BY l.ApplicationDate ASC
  `);

  res.json({ success: true, data: { items: result.recordset } });
});

/**
 * POST /api/tvr/record
 * Conduct/update a TVR for a loan -- same upsert-by-LoanID as the mobile
 * app's tvr_create.php (one TVR per loan; re-recording updates it).
 */
router.post('/record', async (req, res) => {
  if (!(await roleHasPermission(req.staff!, 'conduct_tvr'))) {
    return res.status(403).json({ success: false, message: 'You do not have permission to conduct TVR' });
  }
  const { loanId, contactPersonName, tvrDate, tvrStatusId, verificationNotes } = req.body;
  if (!loanId || !contactPersonName || !tvrDate || !tvrStatusId) {
    return res.status(400).json({ success: false, message: 'loanId, contactPersonName, tvrDate and tvrStatusId are required' });
  }
  const pool = await getPool();

  const existing = await pool.request().input('loanId', sql.Int, loanId).query(`SELECT TVRID AS tvrId FROM TVR_Details WHERE LoanID = @loanId`);

  if (existing.recordset.length > 0) {
    const request = pool.request();
    request.input('tvrId', sql.Int, existing.recordset[0].tvrId);
    request.input('contactPersonName', sql.NVarChar, contactPersonName);
    request.input('tvrDate', sql.Date, tvrDate);
    request.input('tvrStatusId', sql.Int, tvrStatusId);
    request.input('verificationNotes', sql.NVarChar, verificationNotes ?? null);
    await request.query(`
      UPDATE TVR_Details SET ContactPersonName = @contactPersonName, TVRDate = @tvrDate, TVRStatusID = @tvrStatusId, VerificationNotes = @verificationNotes
      WHERE TVRID = @tvrId
    `);
    return res.json({ success: true, data: { tvrId: existing.recordset[0].tvrId, updated: true } });
  }

  const request = pool.request();
  request.input('loanId', sql.Int, loanId);
  request.input('conductedBy', sql.Int, req.staff!.staffId);
  request.input('contactPersonName', sql.NVarChar, contactPersonName);
  request.input('tvrDate', sql.Date, tvrDate);
  request.input('tvrStatusId', sql.Int, tvrStatusId);
  request.input('verificationNotes', sql.NVarChar, verificationNotes ?? null);
  const result = await request.query(`
    INSERT INTO TVR_Details (LoanID, ConductedByStaffID, TVRDate, ContactPersonName, VerificationNotes, TVRStatusID, CreatedAt)
    OUTPUT INSERTED.TVRID AS tvrId
    VALUES (@loanId, @conductedBy, @tvrDate, @contactPersonName, @verificationNotes, @tvrStatusId, GETDATE())
  `);
  res.json({ success: true, data: { tvrId: result.recordset[0].tvrId, created: true } });
});

export default router;
