import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAccountant, requireAuth } from '../middleware/auth.js';
import { uploadVoucherFile } from '../middleware/upload.js';
import { getEffectiveLockDate } from './periodLock.js';

const router = Router();
router.use(requireAuth);

const VOUCHER_TYPES = ['Payment', 'Receipt', 'Contra', 'Journal'] as const;

async function assertDateNotLocked(entryDate: string): Promise<string | null> {
  const lockDate = await getEffectiveLockDate();
  if (lockDate && entryDate <= lockDate) {
    return `This date falls in a locked accounting period (locked up to ${lockDate}) -- ask an Admin to move the lock before posting here.`;
  }
  return null;
}

/** GET /api/journal-entries/lookups -- active accounts for the entry-line account picker. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const result = await pool
    .request()
    .query(`SELECT AccountID AS id, AccountCode AS code, AccountName AS name, AccountType AS accountType FROM Chart_Of_Account WHERE IsActive = 1 ORDER BY AccountCode`);
  res.json({ success: true, data: { accounts: result.recordset } });
});

/** GET /api/journal-entries -- Query: page, perPage, dateFrom, dateTo, q (entry no / narration), voucherType. */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const dateFrom = (req.query.dateFrom as string | undefined) || null;
  const dateTo = (req.query.dateTo as string | undefined) || null;
  const q = (req.query.q as string | undefined)?.trim();
  const voucherType = (req.query.voucherType as string | undefined)?.trim();

  const conditions: string[] = [];
  if (dateFrom && dateTo) conditions.push('je.EntryDate BETWEEN @dateFrom AND @dateTo');
  if (q) conditions.push('(je.EntryNo LIKE @q OR je.Narration LIKE @q)');
  if (voucherType && (VOUCHER_TYPES as readonly string[]).includes(voucherType)) conditions.push('je.VoucherType = @voucherType');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();
  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (dateFrom && dateTo) {
      request.input('dateFrom', sql.Date, dateFrom);
      request.input('dateTo', sql.Date, dateTo);
    }
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    if (voucherType) request.input('voucherType', sql.NVarChar, voucherType);
    return request;
  }

  const total = (
    await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total FROM Journal_Entry je ${whereSql}`)
  ).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT je.JournalEntryID AS journalEntryId, je.EntryNo AS entryNo, je.EntryDate AS entryDate, je.Narration AS narration,
           je.ReferenceNo AS referenceNo, je.VoucherType AS voucherType, je.VoucherFilePath AS voucherFilePath,
           je.IsReversal AS isReversal, je.IsReversed AS isReversed, je.ReversalOfEntryID AS reversalOfEntryId,
           s.FullName AS createdByName, je.CreatedAt AS createdAt,
           (SELECT COALESCE(SUM(DebitAmount), 0) FROM Journal_Entry_Line l WHERE l.JournalEntryID = je.JournalEntryID) AS totalAmount
    FROM Journal_Entry je
    LEFT JOIN Staff s ON je.CreatedByStaffID = s.StaffID
    ${whereSql}
    ORDER BY je.EntryDate DESC, je.JournalEntryID DESC
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  res.json({ success: true, data: { items: listResult.recordset, total, page, perPage } });
});

router.get('/:id', async (req, res) => {
  const pool = await getPool();
  const id = Number(req.params.id);
  const header = (
    await pool.request().input('id', sql.Int, id).query(`
      SELECT je.JournalEntryID AS journalEntryId, je.EntryNo AS entryNo, je.EntryDate AS entryDate, je.Narration AS narration,
             je.ReferenceNo AS referenceNo, je.VoucherType AS voucherType, je.VoucherFilePath AS voucherFilePath,
             je.IsReversal AS isReversal, je.IsReversed AS isReversed, je.ReversalOfEntryID AS reversalOfEntryId,
             s.FullName AS createdByName, je.CreatedAt AS createdAt
      FROM Journal_Entry je LEFT JOIN Staff s ON je.CreatedByStaffID = s.StaffID
      WHERE je.JournalEntryID = @id
    `)
  ).recordset[0];
  if (!header) return res.status(404).json({ success: false, message: 'Journal entry not found' });

  const lines = (
    await pool.request().input('id', sql.Int, id).query(`
      SELECT l.LineID AS lineId, l.AccountID AS accountId, a.AccountCode AS accountCode, a.AccountName AS accountName,
             l.DebitAmount AS debitAmount, l.CreditAmount AS creditAmount, l.LineNarration AS lineNarration,
             l.IsReconciled AS isReconciled, l.ReconciledDate AS reconciledDate, a.IsCashBankAccount AS isCashBankAccount
      FROM Journal_Entry_Line l JOIN Chart_Of_Account a ON l.AccountID = a.AccountID
      WHERE l.JournalEntryID = @id
      ORDER BY l.LineOrder
    `)
  ).recordset;

  res.json({ success: true, data: { ...header, lines } });
});

interface LinePayload {
  accountId: number;
  debitAmount?: number;
  creditAmount?: number;
  lineNarration?: string;
}

async function nextEntryNo(pool: Awaited<ReturnType<typeof getPool>>): Promise<string> {
  const result = await pool.request().query(`SELECT COUNT(*) AS total FROM Journal_Entry`);
  const next = Number(result.recordset[0].total) + 1;
  return `JE-${String(next).padStart(5, '0')}`;
}

/** POST /api/journal-entries -- create a balanced manual journal entry (>=2 lines, total debit == total credit > 0). */
router.post('/', requireAccountant, async (req, res) => {
  const b = req.body;
  const entryDate = b.entryDate as string | undefined;
  const voucherType = (VOUCHER_TYPES as readonly string[]).includes(b.voucherType) ? b.voucherType : 'Journal';
  const lines: LinePayload[] = Array.isArray(b.lines) ? b.lines : [];
  if (!entryDate) return res.status(400).json({ success: false, message: 'Entry date is required' });
  if (lines.length < 2) return res.status(400).json({ success: false, message: 'A journal entry needs at least two lines' });

  const lockMessage = await assertDateNotLocked(entryDate);
  if (lockMessage) return res.status(409).json({ success: false, message: lockMessage });

  let totalDebit = 0;
  let totalCredit = 0;
  for (const line of lines) {
    if (!line.accountId) return res.status(400).json({ success: false, message: 'Every line needs an account' });
    totalDebit += Number(line.debitAmount) || 0;
    totalCredit += Number(line.creditAmount) || 0;
  }
  totalDebit = Math.round(totalDebit * 100) / 100;
  totalCredit = Math.round(totalCredit * 100) / 100;
  if (totalDebit <= 0) return res.status(400).json({ success: false, message: 'Entry total must be greater than zero' });
  if (totalDebit !== totalCredit) {
    return res.status(400).json({ success: false, message: `Entry does not balance -- total debit ${totalDebit} does not equal total credit ${totalCredit}` });
  }

  const pool = await getPool();
  const entryNo = await nextEntryNo(pool);

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    const headerReq = new sql.Request(transaction);
    headerReq.input('entryNo', sql.NVarChar, entryNo);
    headerReq.input('entryDate', sql.Date, entryDate);
    headerReq.input('narration', sql.NVarChar, b.narration || null);
    headerReq.input('referenceNo', sql.NVarChar, b.referenceNo || null);
    headerReq.input('voucherType', sql.NVarChar, voucherType);
    headerReq.input('staffId', sql.Int, req.staff!.staffId);
    const headerResult = await headerReq.query(`
      INSERT INTO Journal_Entry (EntryNo, EntryDate, Narration, ReferenceNo, VoucherType, CreatedByStaffID, CreatedAt)
      OUTPUT INSERTED.JournalEntryID AS journalEntryId
      VALUES (@entryNo, @entryDate, @narration, @referenceNo, @voucherType, @staffId, GETDATE())
    `);
    const journalEntryId = headerResult.recordset[0].journalEntryId;

    let order = 0;
    for (const line of lines) {
      const lineReq = new sql.Request(transaction);
      lineReq.input('journalEntryId', sql.Int, journalEntryId);
      lineReq.input('accountId', sql.Int, line.accountId);
      lineReq.input('debit', sql.Decimal(18, 2), Number(line.debitAmount) || 0);
      lineReq.input('credit', sql.Decimal(18, 2), Number(line.creditAmount) || 0);
      lineReq.input('narration', sql.NVarChar, line.lineNarration || null);
      lineReq.input('order', sql.Int, order++);
      await lineReq.query(`
        INSERT INTO Journal_Entry_Line (JournalEntryID, AccountID, DebitAmount, CreditAmount, LineNarration, LineOrder)
        VALUES (@journalEntryId, @accountId, @debit, @credit, @narration, @order)
      `);
    }
    await transaction.commit();
    res.json({ success: true, data: { journalEntryId, entryNo } });
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
});

/** POST /api/journal-entries/:id/reverse -- post an equal-and-opposite entry, mark the original as reversed. */
router.post('/:id/reverse', requireAccountant, async (req, res) => {
  const pool = await getPool();
  const id = Number(req.params.id);
  const header = (await pool.request().input('id', sql.Int, id).query(`SELECT * FROM Journal_Entry WHERE JournalEntryID = @id`)).recordset[0];
  if (!header) return res.status(404).json({ success: false, message: 'Journal entry not found' });
  if (header.IsReversed) return res.status(409).json({ success: false, message: 'This entry has already been reversed' });

  const reversalDate = (req.body.entryDate as string | undefined) || new Date().toISOString().slice(0, 10);
  const lockMessage = await assertDateNotLocked(reversalDate);
  if (lockMessage) return res.status(409).json({ success: false, message: lockMessage });

  const lines = (await pool.request().input('id', sql.Int, id).query(`SELECT * FROM Journal_Entry_Line WHERE JournalEntryID = @id ORDER BY LineOrder`)).recordset;
  const entryNo = await nextEntryNo(pool);

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    const headerReq = new sql.Request(transaction);
    headerReq.input('entryNo', sql.NVarChar, entryNo);
    headerReq.input('entryDate', sql.Date, reversalDate);
    headerReq.input('narration', sql.NVarChar, `Reversal of ${header.EntryNo}${header.Narration ? ': ' + header.Narration : ''}`);
    headerReq.input('originalId', sql.Int, id);
    headerReq.input('voucherType', sql.NVarChar, header.VoucherType ?? 'Journal');
    headerReq.input('staffId', sql.Int, req.staff!.staffId);
    const headerResult = await headerReq.query(`
      INSERT INTO Journal_Entry (EntryNo, EntryDate, Narration, IsReversal, ReversalOfEntryID, VoucherType, CreatedByStaffID, CreatedAt)
      OUTPUT INSERTED.JournalEntryID AS journalEntryId
      VALUES (@entryNo, @entryDate, @narration, 1, @originalId, @voucherType, @staffId, GETDATE())
    `);
    const newEntryId = headerResult.recordset[0].journalEntryId;

    let order = 0;
    for (const line of lines) {
      const lineReq = new sql.Request(transaction);
      lineReq.input('journalEntryId', sql.Int, newEntryId);
      lineReq.input('accountId', sql.Int, line.AccountID);
      lineReq.input('debit', sql.Decimal(18, 2), line.CreditAmount);
      lineReq.input('credit', sql.Decimal(18, 2), line.DebitAmount);
      lineReq.input('narration', sql.NVarChar, line.LineNarration);
      lineReq.input('order', sql.Int, order++);
      await lineReq.query(`
        INSERT INTO Journal_Entry_Line (JournalEntryID, AccountID, DebitAmount, CreditAmount, LineNarration, LineOrder)
        VALUES (@journalEntryId, @accountId, @debit, @credit, @narration, @order)
      `);
    }

    const markReq = new sql.Request(transaction);
    markReq.input('id', sql.Int, id);
    await markReq.query(`UPDATE Journal_Entry SET IsReversed = 1 WHERE JournalEntryID = @id`);

    await transaction.commit();
    res.json({ success: true, data: { journalEntryId: newEntryId, entryNo } });
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
});

/** POST /api/journal-entries/:id/attachment -- attach a bill/receipt proof image or PDF to a posted voucher. */
router.post('/:id/attachment', requireAccountant, uploadVoucherFile.single('file'), async (req, res) => {
  const id = Number(req.params.id);
  if (!req.file) return res.status(400).json({ success: false, message: 'No file uploaded' });
  const pool = await getPool();
  const filePath = `/uploads/vouchers/${req.file.filename}`;
  const result = await pool
    .request()
    .input('id', sql.Int, id)
    .input('path', sql.NVarChar, filePath)
    .query(`UPDATE Journal_Entry SET VoucherFilePath = @path OUTPUT INSERTED.JournalEntryID WHERE JournalEntryID = @id`);
  if (result.recordset.length === 0) return res.status(404).json({ success: false, message: 'Journal entry not found' });
  res.json({ success: true, data: { voucherFilePath: filePath } });
});

/** POST /api/journal-entries/lines/:lineId/reconcile -- toggle a Cash/Bank line's bank-reconciliation tick. */
router.post('/lines/:lineId/reconcile', requireAccountant, async (req, res) => {
  const lineId = Number(req.params.lineId);
  const reconciled = Boolean(req.body.reconciled);
  const pool = await getPool();
  const request = pool.request();
  request.input('id', sql.Int, lineId);
  request.input('reconciled', sql.Bit, reconciled);
  request.input('date', sql.Date, reconciled ? (req.body.reconciledDate || new Date().toISOString().slice(0, 10)) : null);
  const result = await request.query(`
    UPDATE Journal_Entry_Line SET IsReconciled = @reconciled, ReconciledDate = @date
    OUTPUT INSERTED.LineID
    WHERE LineID = @id
  `);
  if (result.recordset.length === 0) return res.status(404).json({ success: false, message: 'Line not found' });
  res.json({ success: true, data: { reconciled } });
});

export default router;
