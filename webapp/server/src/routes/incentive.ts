import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

interface Slab {
  slabId: number;
  minAchievementPercent: number;
  maxAchievementPercent: number | null;
  incentiveAmount: number;
}

/** Port of includes/incentive_helper.php::incentive_amount_for() -- first matching band, else 0. */
function incentiveAmountFor(slabs: Slab[], achievementPercent: number): number {
  for (const slab of slabs) {
    const aboveMin = achievementPercent >= Number(slab.minAchievementPercent);
    const belowMax = slab.maxAchievementPercent === null || achievementPercent < Number(slab.maxAchievementPercent);
    if (aboveMin && belowMax) return Number(slab.incentiveAmount);
  }
  return 0;
}

function isAdminOrSuper(roleName: string | null): boolean {
  return roleName === 'Admin' || roleName === 'SuperAdmin';
}

router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const branches = await pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`);
  res.json({ success: true, data: { branches: branches.recordset } });
});

/**
 * GET /api/incentive/report
 * Query: year, month, branch
 *
 * Same live calculation incentive_report.php does -- incentive is never
 * stored, it's computed on every request from current Loan/Collection/
 * Staff_Target/Daily_Collection_Sheet data against whatever Incentive_Slab
 * rows are active right now. Admin/SuperAdmin see all branches (optional
 * filter); Branch Manager is forced to their own branch.
 */
router.get('/report', async (req, res) => {
  const claims = req.staff!;
  if (!isAdminOrSuper(claims.roleName) && claims.roleName !== 'Branch Manager') {
    return res.status(403).json({ success: false, message: 'Only Admin or Branch Manager can view the incentive report' });
  }

  const year = Number(req.query.year) || new Date().getFullYear();
  const month = Number(req.query.month) || new Date().getMonth() + 1;
  const branch = claims.roleName === 'Branch Manager' ? claims.branchId : req.query.branch ? Number(req.query.branch) : null;

  const pool = await getPool();

  const [disbSlabs, collSlabs] = await Promise.all([
    pool.request().query(`SELECT SlabID AS slabId, MinAchievementPercent AS minAchievementPercent, MaxAchievementPercent AS maxAchievementPercent, IncentiveAmount AS incentiveAmount FROM Incentive_Slab WHERE MetricType = 'Disbursement' AND IsActive = 1 ORDER BY MinAchievementPercent`),
    pool.request().query(`SELECT SlabID AS slabId, MinAchievementPercent AS minAchievementPercent, MaxAchievementPercent AS maxAchievementPercent, IncentiveAmount AS incentiveAmount FROM Incentive_Slab WHERE MetricType = 'Collection' AND IsActive = 1 ORDER BY MinAchievementPercent`),
  ]);

  const staffReq = pool.request();
  if (branch) staffReq.input('branch', sql.Int, branch);
  const staffResult = await staffReq.query(`
    SELECT s.StaffID AS staffId, s.FullName AS fullName, b.BranchName AS branchName
    FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    LEFT JOIN Branch b ON s.BranchID = b.BranchID
    WHERE r.RoleName IN ('Loan Officer', 'Field Officer', 'Center Manager') AND s.IsActive = 1
    ${branch ? 'AND s.BranchID = @branch' : ''}
    ORDER BY b.BranchName, s.FullName
  `);

  const monthStart = `${year}-${String(month).padStart(2, '0')}-01`;

  const items = [];
  let totalIncentive = 0;

  for (const staffRow of staffResult.recordset) {
    const metricsReq = pool.request();
    metricsReq.input('staffId', sql.Int, staffRow.staffId);
    metricsReq.input('monthStart', sql.Date, monthStart);
    metricsReq.input('year', sql.Int, year);
    metricsReq.input('month', sql.Int, month);

    const metrics = await metricsReq.query(`
      SELECT
        (SELECT COALESCE(SUM(COALESCE(LoanAmountSanctioned, LoanAmountApplied)), 0) FROM Loan
          WHERE LoanOfficerStaffID = @staffId AND DisbursementDate >= @monthStart AND DisbursementDate < DATEADD(MONTH, 1, @monthStart)) AS amountDisbursed,
        (SELECT DisbursementTarget FROM Staff_Target WHERE StaffID = @staffId AND TargetYear = @year AND TargetMonth = @month) AS target,
        (SELECT COALESCE(SUM(TotalCollected), 0) FROM Collection
          WHERE CollectedByStaffID = @staffId AND CollectionDate >= @monthStart AND CollectionDate < DATEADD(MONTH, 1, @monthStart)) AS collected,
        (SELECT COALESCE(SUM(EMIAmount), 0) FROM Daily_Collection_Sheet
          WHERE OfficerStaffID = @staffId AND CollectionDueDate >= @monthStart AND CollectionDueDate < DATEADD(MONTH, 1, @monthStart)) AS due
    `);
    const m = metrics.recordset[0];
    const amountDisbursed = Number(m.amountDisbursed ?? 0);
    const target = Number(m.target ?? 0);
    const collected = Number(m.collected ?? 0);
    const due = Number(m.due ?? 0);

    const disbPercent = target > 0 ? Math.round((amountDisbursed / target) * 10000) / 100 : 0;
    const collPercent = due > 0 ? Math.round((collected / due) * 10000) / 100 : 0;
    const disbIncentive = incentiveAmountFor(disbSlabs.recordset, disbPercent);
    const collIncentive = incentiveAmountFor(collSlabs.recordset, collPercent);
    const rowTotal = disbIncentive + collIncentive;
    totalIncentive += rowTotal;

    items.push({
      staffId: staffRow.staffId,
      fullName: staffRow.fullName,
      branchName: staffRow.branchName,
      amountDisbursed,
      target,
      disbPercent,
      disbIncentive,
      collected,
      due,
      collPercent,
      collIncentive,
      total: rowTotal,
    });
  }

  res.json({ success: true, data: { items, totalIncentive, year, month } });
});

// ---------------------------------------------------------------------------
// Incentive Slabs -- admin-only config CRUD on Incentive_Slab
// ---------------------------------------------------------------------------

router.get('/slabs', requireAdmin, async (_req, res) => {
  const pool = await getPool();
  const [disbursement, collection] = await Promise.all([
    pool.request().query(`SELECT SlabID AS slabId, MinAchievementPercent AS minAchievementPercent, MaxAchievementPercent AS maxAchievementPercent, IncentiveAmount AS incentiveAmount, IsActive AS isActive FROM Incentive_Slab WHERE MetricType = 'Disbursement' ORDER BY MinAchievementPercent`),
    pool.request().query(`SELECT SlabID AS slabId, MinAchievementPercent AS minAchievementPercent, MaxAchievementPercent AS maxAchievementPercent, IncentiveAmount AS incentiveAmount, IsActive AS isActive FROM Incentive_Slab WHERE MetricType = 'Collection' ORDER BY MinAchievementPercent`),
  ]);
  res.json({ success: true, data: { disbursement: disbursement.recordset, collection: collection.recordset } });
});

router.post('/slabs', requireAdmin, async (req, res) => {
  const { metricType, minAchievementPercent, maxAchievementPercent, incentiveAmount } = req.body;
  if (!['Disbursement', 'Collection'].includes(metricType) || minAchievementPercent === undefined || incentiveAmount === undefined) {
    return res.status(400).json({ success: false, message: 'metricType, minAchievementPercent and incentiveAmount are required' });
  }
  const pool = await getPool();
  const request = pool.request();
  request.input('metricType', sql.VarChar, metricType);
  request.input('min', sql.Decimal(6, 2), minAchievementPercent);
  request.input('max', sql.Decimal(6, 2), maxAchievementPercent ?? null);
  request.input('amount', sql.Decimal(18, 2), incentiveAmount);
  request.input('staffId', sql.Int, req.staff!.staffId);
  const result = await request.query(`
    INSERT INTO Incentive_Slab (MetricType, MinAchievementPercent, MaxAchievementPercent, IncentiveAmount, IsActive, UpdatedByStaffID, UpdatedAt)
    OUTPUT INSERTED.SlabID AS slabId
    VALUES (@metricType, @min, @max, @amount, 1, @staffId, GETDATE())
  `);
  res.json({ success: true, data: { slabId: result.recordset[0].slabId } });
});

router.post('/slabs/:id/toggle', requireAdmin, async (req, res) => {
  const pool = await getPool();
  await pool
    .request()
    .input('id', sql.Int, Number(req.params.id))
    .input('staffId', sql.Int, req.staff!.staffId)
    .query(`UPDATE Incentive_Slab SET IsActive = ~IsActive, UpdatedByStaffID = @staffId, UpdatedAt = GETDATE() WHERE SlabID = @id`);
  res.json({ success: true, data: { toggled: true } });
});

router.delete('/slabs/:id', requireAdmin, async (req, res) => {
  const pool = await getPool();
  await pool.request().input('id', sql.Int, Number(req.params.id)).query(`DELETE FROM Incentive_Slab WHERE SlabID = @id`);
  res.json({ success: true, data: { deleted: true } });
});

export default router;
