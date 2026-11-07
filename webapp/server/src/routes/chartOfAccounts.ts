import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/chart-of-accounts -- every account, parent name resolved, child/usage counts for delete guards. */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT a.AccountID AS accountId, a.AccountCode AS accountCode, a.AccountName AS accountName, a.AccountType AS accountType,
           a.NormalBalance AS normalBalance, a.ParentAccountID AS parentAccountId, p.AccountName AS parentAccountName,
           a.IsActive AS isActive, a.OpeningBalance AS openingBalance, a.OpeningBalanceDate AS openingBalanceDate,
           a.IsCashBankAccount AS isCashBankAccount, a.IsTaxAccount AS isTaxAccount,
           (SELECT COUNT(*) FROM Journal_Entry_Line l WHERE l.AccountID = a.AccountID) AS lineCount,
           (SELECT COUNT(*) FROM Chart_Of_Account c WHERE c.ParentAccountID = a.AccountID) AS childCount
    FROM Chart_Of_Account a
    LEFT JOIN Chart_Of_Account p ON a.ParentAccountID = p.AccountID
    ORDER BY a.AccountCode
  `);
  res.json({ success: true, data: { items: result.recordset } });
});

interface AccountPayload {
  accountCode: string;
  accountName: string;
  accountType: 'Asset' | 'Liability' | 'Equity' | 'Income' | 'Expense';
  normalBalance: 'Debit' | 'Credit';
  parentAccountId?: number | null;
  isActive: boolean;
  openingBalance?: number;
  openingBalanceDate?: string | null;
  isCashBankAccount?: boolean;
  isTaxAccount?: boolean;
}

router.post('/', requireAdmin, async (req, res) => {
  const b: AccountPayload = req.body;
  if (!b.accountCode?.trim() || !b.accountName?.trim() || !b.accountType || !b.normalBalance) {
    return res.status(400).json({ success: false, message: 'Account code, name, type and normal balance are required' });
  }
  const pool = await getPool();
  const request = pool.request();
  request.input('code', sql.NVarChar, b.accountCode.trim());
  request.input('name', sql.NVarChar, b.accountName.trim());
  request.input('type', sql.NVarChar, b.accountType);
  request.input('normal', sql.NVarChar, b.normalBalance);
  request.input('parentId', sql.Int, b.parentAccountId ?? null);
  request.input('isActive', sql.Bit, b.isActive ?? true);
  request.input('openingBalance', sql.Decimal(18, 2), b.openingBalance ?? 0);
  request.input('openingBalanceDate', sql.Date, b.openingBalanceDate ?? null);
  request.input('isCashBankAccount', sql.Bit, b.isCashBankAccount ?? false);
  request.input('isTaxAccount', sql.Bit, b.isTaxAccount ?? false);
  request.input('staffId', sql.Int, req.staff!.staffId);
  try {
    const result = await request.query(`
      INSERT INTO Chart_Of_Account (AccountCode, AccountName, AccountType, NormalBalance, ParentAccountID, IsActive,
                                     OpeningBalance, OpeningBalanceDate, IsCashBankAccount, IsTaxAccount, CreatedByStaffID, CreatedAt)
      OUTPUT INSERTED.AccountID AS accountId
      VALUES (@code, @name, @type, @normal, @parentId, @isActive, @openingBalance, @openingBalanceDate, @isCashBankAccount, @isTaxAccount, @staffId, GETDATE())
    `);
    res.json({ success: true, data: { accountId: result.recordset[0].accountId } });
  } catch {
    res.status(409).json({ success: false, message: 'Could not create -- an account with this code may already exist' });
  }
});

router.put('/:id', requireAdmin, async (req, res) => {
  const b: AccountPayload = req.body;
  if (!b.accountCode?.trim() || !b.accountName?.trim() || !b.accountType || !b.normalBalance) {
    return res.status(400).json({ success: false, message: 'Account code, name, type and normal balance are required' });
  }
  const pool = await getPool();
  const request = pool.request();
  request.input('id', sql.Int, Number(req.params.id));
  request.input('code', sql.NVarChar, b.accountCode.trim());
  request.input('name', sql.NVarChar, b.accountName.trim());
  request.input('type', sql.NVarChar, b.accountType);
  request.input('normal', sql.NVarChar, b.normalBalance);
  request.input('parentId', sql.Int, b.parentAccountId ?? null);
  request.input('isActive', sql.Bit, b.isActive ?? true);
  request.input('openingBalance', sql.Decimal(18, 2), b.openingBalance ?? 0);
  request.input('openingBalanceDate', sql.Date, b.openingBalanceDate ?? null);
  request.input('isCashBankAccount', sql.Bit, b.isCashBankAccount ?? false);
  request.input('isTaxAccount', sql.Bit, b.isTaxAccount ?? false);
  request.input('staffId', sql.Int, req.staff!.staffId);
  try {
    await request.query(`
      UPDATE Chart_Of_Account SET AccountCode=@code, AccountName=@name, AccountType=@type, NormalBalance=@normal,
             ParentAccountID=@parentId, IsActive=@isActive, OpeningBalance=@openingBalance, OpeningBalanceDate=@openingBalanceDate,
             IsCashBankAccount=@isCashBankAccount, IsTaxAccount=@isTaxAccount, UpdatedAt=GETDATE(), UpdatedByStaffID=@staffId
      WHERE AccountID = @id
    `);
    res.json({ success: true, data: { accountId: Number(req.params.id) } });
  } catch {
    res.status(409).json({ success: false, message: 'Could not update -- an account with this code may already exist' });
  }
});

router.delete('/:id', requireAdmin, async (req, res) => {
  const pool = await getPool();
  const id = Number(req.params.id);
  const [lineCount, childCount] = await Promise.all([
    pool.request().input('id', sql.Int, id).query(`SELECT COUNT(*) AS total FROM Journal_Entry_Line WHERE AccountID = @id`),
    pool.request().input('id', sql.Int, id).query(`SELECT COUNT(*) AS total FROM Chart_Of_Account WHERE ParentAccountID = @id`),
  ]);
  if (lineCount.recordset[0].total > 0) {
    return res.status(400).json({ success: false, message: 'Cannot delete -- this account already has journal entries posted to it. Deactivate it instead.' });
  }
  if (childCount.recordset[0].total > 0) {
    return res.status(400).json({ success: false, message: 'Cannot delete -- other accounts use this as their parent' });
  }
  await pool.request().input('id', sql.Int, id).query(`DELETE FROM Chart_Of_Account WHERE AccountID = @id`);
  res.json({ success: true, data: { deleted: true } });
});

export default router;
