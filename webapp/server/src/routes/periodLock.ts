import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** The effective lock is simply the most recent LockDate set -- no entry may be dated on/before it. */
export async function getEffectiveLockDate(): Promise<string | null> {
  const pool = await getPool();
  const result = await pool.request().query(`SELECT TOP 1 LockDate FROM Accounting_Period_Lock ORDER BY LockedAt DESC`);
  const row = result.recordset[0];
  return row ? new Date(row.LockDate).toISOString().slice(0, 10) : null;
}

router.get('/', async (_req, res) => {
  const pool = await getPool();
  const [current, history] = await Promise.all([
    getEffectiveLockDate(),
    pool.request().query(`
      SELECT pl.LockID AS lockId, pl.LockDate AS lockDate, pl.LockedAt AS lockedAt, s.FullName AS lockedByName
      FROM Accounting_Period_Lock pl LEFT JOIN Staff s ON pl.LockedByStaffID = s.StaffID
      ORDER BY pl.LockedAt DESC
    `),
  ]);
  res.json({ success: true, data: { currentLockDate: current, history: history.recordset } });
});

/** POST /api/period-lock -- Admin only. Body: { lockDate } (or null to clear the lock entirely). */
router.post('/', requireAdmin, async (req, res) => {
  const lockDate = req.body.lockDate as string | null;
  const pool = await getPool();
  const request = pool.request();
  request.input('lockDate', sql.Date, lockDate ?? '1900-01-01');
  request.input('staffId', sql.Int, req.staff!.staffId);
  await request.query(`INSERT INTO Accounting_Period_Lock (LockDate, LockedByStaffID, LockedAt) VALUES (@lockDate, @staffId, GETDATE())`);
  res.json({ success: true, data: { lockDate } });
});

export default router;
