import { Router, type Request, type Response } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/**
 * GET /api/death-entries
 * Query: status (Pending|Approved|Rejected|All, default Pending), q
 *
 * Death_Entry is a simple approval queue -- field staff report a member's
 * death (date, cause, informant, proof document), Admin approves/rejects.
 * No cascading side effects on Member/Loan on approve, same as death_entries.php.
 */
router.get('/', async (req, res) => {
  const status = (req.query.status as string | undefined) || 'Pending';
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [];
  if (status && status !== 'All') conditions.push('de.Status = @status');
  if (q) conditions.push('(m.FirstName LIKE @q OR m.LastName LIKE @q OR m.EnrollmentNo LIKE @q)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();
  const request = pool.request();
  if (status && status !== 'All') request.input('status', sql.NVarChar, status);
  if (q) request.input('q', sql.NVarChar, `%${q}%`);

  const result = await request.query(`
    SELECT de.DeathEntryID AS deathEntryId, de.MemberID AS memberId, m.FirstName AS firstName, m.LastName AS lastName,
           m.EnrollmentNo AS enrollmentNo, m.MobileNo AS mobileNo, c.CenterName AS centerName, b.BranchName AS branchName,
           de.DateOfDeath AS dateOfDeath, de.Cause AS cause, de.InformantName AS informantName, de.InformantRelation AS informantRelation,
           de.ProofDocumentPath AS proofDocumentPath, de.Status AS status, de.RemarksByApprover AS remarksByApprover,
           rep.FullName AS reportedByName, rev.FullName AS reviewedByName, de.ReviewedAt AS reviewedAt, de.CreatedAt AS createdAt
    FROM Death_Entry de
    JOIN Member m ON de.MemberID = m.MemberID
    LEFT JOIN Center c ON m.CenterID = c.CenterID
    LEFT JOIN Branch b ON m.BranchID = b.BranchID
    LEFT JOIN Staff rep ON de.ReportedByStaffID = rep.StaffID
    LEFT JOIN Staff rev ON de.ReviewedByStaffID = rev.StaffID
    ${whereSql}
    ORDER BY CASE de.Status WHEN 'Pending' THEN 1 ELSE 2 END, de.CreatedAt DESC
  `);

  const countsResult = await pool.request().query(`SELECT Status AS status, COUNT(*) AS total FROM Death_Entry GROUP BY Status`);
  const statusCounts: Record<string, number> = { Pending: 0, Approved: 0, Rejected: 0 };
  for (const row of countsResult.recordset) statusCounts[row.status] = row.total;
  const all = statusCounts.Pending + statusCounts.Approved + statusCounts.Rejected;

  res.json({ success: true, data: { items: result.recordset, statusCounts: { ...statusCounts, All: all } } });
});

function reviewAction(newStatus: 'Approved' | 'Rejected') {
  return async (req: Request, res: Response) => {
    const pool = await getPool();
    const request = pool.request();
    request.input('status', sql.NVarChar, newStatus);
    request.input('remarks', sql.NVarChar, req.body.remarks ?? null);
    request.input('staffId', sql.Int, req.staff!.staffId);
    request.input('id', sql.Int, Number(req.params.id));
    const result = await request.query(`
      UPDATE Death_Entry SET Status = @status, RemarksByApprover = @remarks, ReviewedByStaffID = @staffId, ReviewedAt = GETDATE()
      OUTPUT INSERTED.DeathEntryID AS deathEntryId
      WHERE DeathEntryID = @id AND Status = 'Pending'
    `);
    if (result.recordset.length === 0) {
      return res.status(409).json({ success: false, message: 'This death entry has already been reviewed' });
    }
    res.json({ success: true, data: { deathEntryId: result.recordset[0].deathEntryId, status: newStatus } });
  };
}

router.post('/:id/approve', reviewAction('Approved'));
router.post('/:id/reject', reviewAction('Rejected'));

export default router;
