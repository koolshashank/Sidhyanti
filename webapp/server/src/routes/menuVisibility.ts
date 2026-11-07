import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/**
 * Every sidebar item an Admin can switch off for everyone -- 'settings' is
 * deliberately not included so Admin can never lock themselves out of the
 * one screen that can turn a menu back on, same reasoning as Billing's
 * services_enabled being carved out of the generic App_Settings list.
 */
const MENU_DEFS: { key: string; label: string }[] = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'staff', label: 'Staff' },
  { key: 'members', label: 'Members' },
  { key: 'loans', label: 'Loans' },
  { key: 'loan-approvals', label: 'Loan Approvals' },
  { key: 'loan-writeoffs', label: 'Loan Write-offs' },
  { key: 'loan-schemes', label: 'Loan Schemes' },
  { key: 'loan-product-types', label: 'Loan Product Types' },
  { key: 'payment-frequencies', label: 'Payment Frequencies' },
  { key: 'collections', label: 'Collections' },
  { key: 'accounting', label: 'Accounting' },
  { key: 'grt', label: 'GRT' },
  { key: 'cgt', label: 'CGT' },
  { key: 'tvr', label: 'TVR' },
  { key: 'death-entries', label: 'Death Entries' },
  { key: 'incentive', label: 'Incentive' },
  { key: 'audit-log', label: 'Audit Log' },
  { key: 'branches', label: 'Branches' },
  { key: 'centers', label: 'Centers' },
  { key: 'attendance', label: 'Meeting Attendance' },
];

function settingKey(menuKey: string): string {
  return `web_menu_visible:${menuKey}`;
}

/** GET /api/menu-visibility -- any authenticated user; AppShell uses this to filter its own sidebar. */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const rows = (
    await pool.request().query(`SELECT SettingKey AS settingKey, SettingValue AS settingValue FROM App_Setting WHERE SettingKey LIKE 'web_menu_visible:%'`)
  ).recordset;
  const valueByKey = new Map(rows.map((r) => [r.settingKey as string, r.settingValue as string]));
  const items = MENU_DEFS.map((def) => ({
    key: def.key,
    label: def.label,
    visible: (valueByKey.get(settingKey(def.key)) ?? '1') === '1',
  }));
  res.json({ success: true, data: { items } });
});

/** PUT /api/menu-visibility -- Admin/SuperAdmin only. Body: { hidden: string[] } (menu keys to hide). */
router.put('/', requireAdmin, async (req, res) => {
  const hidden: string[] = Array.isArray(req.body.hidden) ? req.body.hidden : [];
  const hiddenSet = new Set(hidden);
  const pool = await getPool();

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    for (const def of MENU_DEFS) {
      const request = new sql.Request(transaction);
      request.input('key', sql.NVarChar, settingKey(def.key));
      request.input('value', sql.NVarChar, hiddenSet.has(def.key) ? '0' : '1');
      request.input('staffId', sql.Int, req.staff!.staffId);
      await request.query(`
        MERGE App_Setting AS target
        USING (SELECT @key AS SettingKey) AS src ON target.SettingKey = src.SettingKey
        WHEN MATCHED THEN UPDATE SET SettingValue = @value, UpdatedAt = GETDATE(), UpdatedByStaffID = @staffId
        WHEN NOT MATCHED THEN INSERT (SettingKey, SettingValue, UpdatedAt, UpdatedByStaffID) VALUES (@key, @value, GETDATE(), @staffId);
      `);
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
  res.json({ success: true, data: { saved: true } });
});

export default router;
