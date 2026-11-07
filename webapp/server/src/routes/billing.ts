import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth, requireSuperAdmin } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth, requireSuperAdmin);

async function getServicesEnabled(): Promise<boolean> {
  const pool = await getPool();
  const result = await pool.request().query(`SELECT SettingValue FROM App_Setting WHERE SettingKey = 'services_enabled'`);
  const value = result.recordset[0]?.SettingValue;
  return value === undefined || value === '1' || String(value).toLowerCase() === 'true';
}

/** Port of billing.php's billing_period_end(): start + 1mo/3mo/1yr, minus 1 day. */
function periodEnd(periodType: string, periodStart: string): string {
  const start = new Date(periodStart);
  const end = new Date(start);
  if (periodType === 'Monthly') end.setMonth(end.getMonth() + 1);
  else if (periodType === 'Quarterly') end.setMonth(end.getMonth() + 3);
  else end.setFullYear(end.getFullYear() + 1);
  end.setDate(end.getDate() - 1);
  return end.toISOString().slice(0, 10);
}

/** GET /api/billing -- service status + invoice history. */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const [enabled, invoices] = await Promise.all([
    getServicesEnabled(),
    pool.request().query(`
      SELECT bi.BillingID AS billingId, bi.PeriodType AS periodType, bi.PeriodStart AS periodStart, bi.PeriodEnd AS periodEnd,
             bi.Amount AS amount, bi.Notes AS notes, s.FullName AS generatedByName, bi.CreatedAt AS createdAt
      FROM Billing_Invoice bi
      LEFT JOIN Staff s ON bi.GeneratedByStaffID = s.StaffID
      ORDER BY bi.PeriodStart DESC, bi.BillingID DESC
    `),
  ]);
  res.json({ success: true, data: { servicesEnabled: enabled, invoices: invoices.recordset } });
});

/** POST /api/billing/toggle -- enable/disable the whole CRM (except SuperAdmin), system-wide. */
router.post('/toggle', async (req, res) => {
  const enable = Boolean(req.body.enable);
  const pool = await getPool();
  const request = pool.request();
  request.input('value', sql.NVarChar, enable ? '1' : '0');
  request.input('staffId', sql.Int, req.staff!.staffId);
  const result = await request.query(`
    UPDATE App_Setting SET SettingValue = @value, UpdatedAt = GETDATE(), UpdatedByStaffID = @staffId WHERE SettingKey = 'services_enabled'
  `);
  if (result.rowsAffected[0] === 0) {
    const insertReq = pool.request();
    insertReq.input('value', sql.NVarChar, enable ? '1' : '0');
    insertReq.input('staffId', sql.Int, req.staff!.staffId);
    await insertReq.query(`
      INSERT INTO App_Setting (SettingKey, SettingValue, UpdatedAt, UpdatedByStaffID) VALUES ('services_enabled', @value, GETDATE(), @staffId)
    `);
  }
  res.json({ success: true, data: { servicesEnabled: enable } });
});

/** POST /api/billing/generate -- record a flat billing-history entry (no payment tracking, just a log). */
router.post('/generate', async (req, res) => {
  const { periodType, periodStart, amount, notes } = req.body;
  if (!['Monthly', 'Quarterly', 'Yearly'].includes(periodType) || !periodStart || !(Number(amount) > 0)) {
    return res.status(400).json({ success: false, message: 'Period, Period Start and a positive Amount are required' });
  }
  const pool = await getPool();
  const request = pool.request();
  request.input('periodType', sql.VarChar, periodType);
  request.input('periodStart', sql.Date, periodStart);
  request.input('periodEnd', sql.Date, periodEnd(periodType, periodStart));
  request.input('amount', sql.Decimal(12, 2), amount);
  request.input('notes', sql.NVarChar, notes || null);
  request.input('staffId', sql.Int, req.staff!.staffId);
  const result = await request.query(`
    INSERT INTO Billing_Invoice (PeriodType, PeriodStart, PeriodEnd, Amount, Notes, GeneratedByStaffID, CreatedAt)
    OUTPUT INSERTED.BillingID AS billingId
    VALUES (@periodType, @periodStart, @periodEnd, @amount, @notes, @staffId, GETDATE())
  `);
  res.json({ success: true, data: { billingId: result.recordset[0].billingId } });
});

export default router;
