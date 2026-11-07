import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/**
 * GET /api/accounting-reports/ledger/:accountId
 * Query: dateFrom, dateTo. Opening balance = net of everything before
 * dateFrom; each row then carries a running balance, signed by the
 * account's NormalBalance (Debit-normal accounts increase on debit,
 * Credit-normal accounts increase on credit).
 */
router.get('/ledger/:accountId', async (req, res) => {
  const accountId = Number(req.params.accountId);
  const dateFrom = (req.query.dateFrom as string | undefined) || null;
  const dateTo = (req.query.dateTo as string | undefined) || null;

  const pool = await getPool();
  const account = (
    await pool.request().input('id', sql.Int, accountId).query(`SELECT * FROM Chart_Of_Account WHERE AccountID = @id`)
  ).recordset[0];
  if (!account) return res.status(404).json({ success: false, message: 'Account not found' });

  const sign = account.NormalBalance === 'Debit' ? 1 : -1;

  // Baseline = the account's declared OpeningBalance (as of OpeningBalanceDate)
  // plus every posted line between that date and dateFrom -- so a ledger
  // pulled for a brand-new book still starts from where the accountant said
  // it should, not from zero.
  let openingBalance = Number(account.OpeningBalance) || 0;
  if (dateFrom) {
    const openingReq = pool.request();
    openingReq.input('accountId', sql.Int, accountId);
    openingReq.input('dateFrom', sql.Date, dateFrom);
    const openingConditions = ['l.AccountID = @accountId', 'je.EntryDate < @dateFrom'];
    if (account.OpeningBalanceDate) {
      openingReq.input('openingBalanceDate', sql.Date, account.OpeningBalanceDate);
      openingConditions.push('je.EntryDate >= @openingBalanceDate');
    }
    const openingResult = await openingReq.query(`
      SELECT COALESCE(SUM(l.DebitAmount), 0) AS totalDebit, COALESCE(SUM(l.CreditAmount), 0) AS totalCredit
      FROM Journal_Entry_Line l JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID
      WHERE ${openingConditions.join(' AND ')}
    `);
    const row = openingResult.recordset[0];
    openingBalance += sign * (Number(row.totalDebit) - Number(row.totalCredit));
  }

  const conditions = ['l.AccountID = @accountId'];
  if (dateFrom) conditions.push('je.EntryDate >= @dateFrom');
  if (dateTo) conditions.push('je.EntryDate <= @dateTo');

  const request = pool.request();
  request.input('accountId', sql.Int, accountId);
  if (dateFrom) request.input('dateFrom', sql.Date, dateFrom);
  if (dateTo) request.input('dateTo', sql.Date, dateTo);
  const rows = (
    await request.query(`
      SELECT je.JournalEntryID AS journalEntryId, je.EntryNo AS entryNo, je.EntryDate AS entryDate, je.Narration AS narration,
             l.DebitAmount AS debitAmount, l.CreditAmount AS creditAmount, l.LineNarration AS lineNarration
      FROM Journal_Entry_Line l JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID
      WHERE ${conditions.join(' AND ')}
      ORDER BY je.EntryDate, je.JournalEntryID
    `)
  ).recordset;

  let runningBalance = openingBalance;
  const items = rows.map((r) => {
    runningBalance += sign * (Number(r.debitAmount) - Number(r.creditAmount));
    return { ...r, runningBalance: Math.round(runningBalance * 100) / 100 };
  });

  res.json({
    success: true,
    data: {
      account: { accountId: account.AccountID, accountCode: account.AccountCode, accountName: account.AccountName, normalBalance: account.NormalBalance },
      openingBalance: Math.round(openingBalance * 100) / 100,
      closingBalance: Math.round(runningBalance * 100) / 100,
      items,
    },
  });
});

/** GET /api/accounting-reports/trial-balance?asOfDate= -- every active account's net Dr/Cr position as of that date. */
router.get('/trial-balance', async (req, res) => {
  const asOfDate = (req.query.asOfDate as string | undefined) || new Date().toISOString().slice(0, 10);
  const pool = await getPool();
  const result = await pool.request().input('asOfDate', sql.Date, asOfDate).query(`
    SELECT a.AccountID AS accountId, a.AccountCode AS accountCode, a.AccountName AS accountName, a.AccountType AS accountType,
           a.NormalBalance AS normalBalance, a.OpeningBalance AS openingBalance,
           COALESCE(SUM(l.DebitAmount), 0) AS totalDebit, COALESCE(SUM(l.CreditAmount), 0) AS totalCredit
    FROM Chart_Of_Account a
    LEFT JOIN Journal_Entry_Line l ON l.AccountID = a.AccountID
    LEFT JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID AND je.EntryDate <= @asOfDate
    WHERE a.IsActive = 1
    GROUP BY a.AccountID, a.AccountCode, a.AccountName, a.AccountType, a.NormalBalance, a.OpeningBalance
    HAVING COALESCE(SUM(l.DebitAmount), 0) <> 0 OR COALESCE(SUM(l.CreditAmount), 0) <> 0 OR a.OpeningBalance <> 0
    ORDER BY a.AccountCode
  `);

  let totalDebitColumn = 0;
  let totalCreditColumn = 0;
  const items = result.recordset.map((r) => {
    const openingSigned = r.normalBalance === 'Debit' ? Number(r.openingBalance) : -Number(r.openingBalance);
    const net = openingSigned + Number(r.totalDebit) - Number(r.totalCredit);
    const debitColumn = net >= 0 ? net : 0;
    const creditColumn = net < 0 ? -net : 0;
    totalDebitColumn += debitColumn;
    totalCreditColumn += creditColumn;
    return {
      accountId: r.accountId,
      accountCode: r.accountCode,
      accountName: r.accountName,
      accountType: r.accountType,
      debit: Math.round(debitColumn * 100) / 100,
      credit: Math.round(creditColumn * 100) / 100,
    };
  });

  res.json({
    success: true,
    data: {
      asOfDate,
      items,
      totalDebit: Math.round(totalDebitColumn * 100) / 100,
      totalCredit: Math.round(totalCreditColumn * 100) / 100,
    },
  });
});

/** GET /api/accounting-reports/profit-loss?dateFrom=&dateTo= -- Income minus Expense for the period. */
router.get('/profit-loss', async (req, res) => {
  const dateFrom = (req.query.dateFrom as string | undefined) || `${new Date().getFullYear()}-01-01`;
  const dateTo = (req.query.dateTo as string | undefined) || new Date().toISOString().slice(0, 10);
  const pool = await getPool();
  const result = await pool
    .request()
    .input('dateFrom', sql.Date, dateFrom)
    .input('dateTo', sql.Date, dateTo)
    .query(`
      SELECT a.AccountID AS accountId, a.AccountCode AS accountCode, a.AccountName AS accountName, a.AccountType AS accountType,
             COALESCE(SUM(l.DebitAmount), 0) AS totalDebit, COALESCE(SUM(l.CreditAmount), 0) AS totalCredit
      FROM Chart_Of_Account a
      JOIN Journal_Entry_Line l ON l.AccountID = a.AccountID
      JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID AND je.EntryDate BETWEEN @dateFrom AND @dateTo
      WHERE a.AccountType IN ('Income', 'Expense') AND a.IsActive = 1
      GROUP BY a.AccountID, a.AccountCode, a.AccountName, a.AccountType
      ORDER BY a.AccountType DESC, a.AccountCode
    `);

  const income: { accountId: number; accountCode: string; accountName: string; amount: number }[] = [];
  const expense: { accountId: number; accountCode: string; accountName: string; amount: number }[] = [];
  let totalIncome = 0;
  let totalExpense = 0;
  for (const r of result.recordset) {
    if (r.accountType === 'Income') {
      const amount = Number(r.totalCredit) - Number(r.totalDebit);
      totalIncome += amount;
      income.push({ accountId: r.accountId, accountCode: r.accountCode, accountName: r.accountName, amount: Math.round(amount * 100) / 100 });
    } else {
      const amount = Number(r.totalDebit) - Number(r.totalCredit);
      totalExpense += amount;
      expense.push({ accountId: r.accountId, accountCode: r.accountCode, accountName: r.accountName, amount: Math.round(amount * 100) / 100 });
    }
  }

  res.json({
    success: true,
    data: {
      dateFrom,
      dateTo,
      income,
      expense,
      totalIncome: Math.round(totalIncome * 100) / 100,
      totalExpense: Math.round(totalExpense * 100) / 100,
      netProfit: Math.round((totalIncome - totalExpense) * 100) / 100,
    },
  });
});

/**
 * GET /api/accounting-reports/balance-sheet?asOfDate=
 * Assets (net Dr) vs Liabilities + Equity (net Cr), as of that date. The
 * period's running net profit/loss (Income - Expense up to that date) is
 * folded into Equity as "Current Period Earnings" so the sheet balances --
 * no closing journal entry is required from the accountant for this report
 * to tie out.
 */
router.get('/balance-sheet', async (req, res) => {
  const asOfDate = (req.query.asOfDate as string | undefined) || new Date().toISOString().slice(0, 10);
  const pool = await getPool();
  const result = await pool.request().input('asOfDate', sql.Date, asOfDate).query(`
    SELECT a.AccountID AS accountId, a.AccountCode AS accountCode, a.AccountName AS accountName, a.AccountType AS accountType,
           a.NormalBalance AS normalBalance, a.OpeningBalance AS openingBalance,
           COALESCE(SUM(l.DebitAmount), 0) AS totalDebit, COALESCE(SUM(l.CreditAmount), 0) AS totalCredit
    FROM Chart_Of_Account a
    LEFT JOIN Journal_Entry_Line l ON l.AccountID = a.AccountID
    LEFT JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID AND je.EntryDate <= @asOfDate
    WHERE a.IsActive = 1
    GROUP BY a.AccountID, a.AccountCode, a.AccountName, a.AccountType, a.NormalBalance, a.OpeningBalance
    HAVING COALESCE(SUM(l.DebitAmount), 0) <> 0 OR COALESCE(SUM(l.CreditAmount), 0) <> 0 OR a.OpeningBalance <> 0
  `);

  const assets: { accountId: number; accountCode: string; accountName: string; amount: number }[] = [];
  const liabilities: { accountId: number; accountCode: string; accountName: string; amount: number }[] = [];
  const equity: { accountId: number; accountCode: string; accountName: string; amount: number }[] = [];
  let totalAssets = 0;
  let totalLiabilities = 0;
  let totalEquity = 0;
  let periodEarnings = 0;

  for (const r of result.recordset) {
    const debit = Number(r.totalDebit);
    const credit = Number(r.totalCredit);
    const opening = Number(r.openingBalance) || 0;
    if (r.accountType === 'Asset') {
      const amount = opening + debit - credit;
      totalAssets += amount;
      assets.push({ accountId: r.accountId, accountCode: r.accountCode, accountName: r.accountName, amount: Math.round(amount * 100) / 100 });
    } else if (r.accountType === 'Liability') {
      const amount = opening + credit - debit;
      totalLiabilities += amount;
      liabilities.push({ accountId: r.accountId, accountCode: r.accountCode, accountName: r.accountName, amount: Math.round(amount * 100) / 100 });
    } else if (r.accountType === 'Equity') {
      const amount = opening + credit - debit;
      totalEquity += amount;
      equity.push({ accountId: r.accountId, accountCode: r.accountCode, accountName: r.accountName, amount: Math.round(amount * 100) / 100 });
    } else if (r.accountType === 'Income') {
      periodEarnings += credit - debit;
    } else if (r.accountType === 'Expense') {
      periodEarnings -= debit - credit;
    }
  }

  equity.push({ accountId: 0, accountCode: '', accountName: 'Current Period Earnings', amount: Math.round(periodEarnings * 100) / 100 });
  totalEquity += periodEarnings;

  res.json({
    success: true,
    data: {
      asOfDate,
      assets,
      liabilities,
      equity,
      totalAssets: Math.round(totalAssets * 100) / 100,
      totalLiabilities: Math.round(totalLiabilities * 100) / 100,
      totalEquity: Math.round(totalEquity * 100) / 100,
      totalLiabilitiesAndEquity: Math.round((totalLiabilities + totalEquity) * 100) / 100,
    },
  });
});

/**
 * GET /api/accounting-reports/day-book?dateFrom=&dateTo=
 * Every voucher posted in the range, each with its lines inline -- Tally's
 * classic "all vouchers for the day/period" view.
 */
router.get('/day-book', async (req, res) => {
  const dateFrom = (req.query.dateFrom as string | undefined) || new Date().toISOString().slice(0, 10);
  const dateTo = (req.query.dateTo as string | undefined) || dateFrom;
  const pool = await getPool();

  const entries = (
    await pool.request().input('dateFrom', sql.Date, dateFrom).input('dateTo', sql.Date, dateTo).query(`
      SELECT je.JournalEntryID AS journalEntryId, je.EntryNo AS entryNo, je.EntryDate AS entryDate, je.Narration AS narration,
             je.VoucherType AS voucherType, je.IsReversal AS isReversal, je.IsReversed AS isReversed,
             s.FullName AS createdByName,
             (SELECT COALESCE(SUM(DebitAmount), 0) FROM Journal_Entry_Line l WHERE l.JournalEntryID = je.JournalEntryID) AS totalAmount
      FROM Journal_Entry je LEFT JOIN Staff s ON je.CreatedByStaffID = s.StaffID
      WHERE je.EntryDate BETWEEN @dateFrom AND @dateTo
      ORDER BY je.EntryDate, je.JournalEntryID
    `)
  ).recordset;

  const lines = (
    await pool.request().input('dateFrom', sql.Date, dateFrom).input('dateTo', sql.Date, dateTo).query(`
      SELECT l.JournalEntryID AS journalEntryId, a.AccountCode AS accountCode, a.AccountName AS accountName,
             l.DebitAmount AS debitAmount, l.CreditAmount AS creditAmount
      FROM Journal_Entry_Line l
      JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID
      JOIN Chart_Of_Account a ON l.AccountID = a.AccountID
      WHERE je.EntryDate BETWEEN @dateFrom AND @dateTo
      ORDER BY l.JournalEntryID, l.LineOrder
    `)
  ).recordset;

  const linesByEntry = new Map<number, (typeof lines)[number][]>();
  for (const line of lines) {
    const arr = linesByEntry.get(line.journalEntryId) ?? [];
    arr.push(line);
    linesByEntry.set(line.journalEntryId, arr);
  }

  res.json({
    success: true,
    data: {
      dateFrom,
      dateTo,
      items: entries.map((e) => ({ ...e, lines: linesByEntry.get(e.journalEntryId) ?? [] })),
    },
  });
});

/**
 * GET /api/accounting-reports/cash-bank-book?dateFrom=&dateTo=
 * Combined ledger across every account flagged IsCashBankAccount -- same
 * running-balance shape as a single-account Ledger, one section per account.
 */
router.get('/cash-bank-book', async (req, res) => {
  const dateFrom = (req.query.dateFrom as string | undefined) || null;
  const dateTo = (req.query.dateTo as string | undefined) || null;
  const pool = await getPool();

  const accounts = (await pool.request().query(`SELECT * FROM Chart_Of_Account WHERE IsCashBankAccount = 1 AND IsActive = 1 ORDER BY AccountCode`)).recordset;

  const books = [];
  for (const account of accounts) {
    const sign = account.NormalBalance === 'Debit' ? 1 : -1;
    let openingBalance = Number(account.OpeningBalance) || 0;
    if (dateFrom) {
      const openingReq = pool.request();
      openingReq.input('accountId', sql.Int, account.AccountID);
      openingReq.input('dateFrom', sql.Date, dateFrom);
      const openingResult = await openingReq.query(`
        SELECT COALESCE(SUM(l.DebitAmount), 0) AS totalDebit, COALESCE(SUM(l.CreditAmount), 0) AS totalCredit
        FROM Journal_Entry_Line l JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID
        WHERE l.AccountID = @accountId AND je.EntryDate < @dateFrom
      `);
      const row = openingResult.recordset[0];
      openingBalance += sign * (Number(row.totalDebit) - Number(row.totalCredit));
    }

    const conditions = ['l.AccountID = @accountId'];
    const lineReq = pool.request();
    lineReq.input('accountId', sql.Int, account.AccountID);
    if (dateFrom) {
      lineReq.input('dateFrom', sql.Date, dateFrom);
      conditions.push('je.EntryDate >= @dateFrom');
    }
    if (dateTo) {
      lineReq.input('dateTo', sql.Date, dateTo);
      conditions.push('je.EntryDate <= @dateTo');
    }
    const rows = (
      await lineReq.query(`
        SELECT je.JournalEntryID AS journalEntryId, je.EntryNo AS entryNo, je.EntryDate AS entryDate, je.Narration AS narration,
               l.LineID AS lineId, l.DebitAmount AS debitAmount, l.CreditAmount AS creditAmount, l.IsReconciled AS isReconciled
        FROM Journal_Entry_Line l JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID
        WHERE ${conditions.join(' AND ')}
        ORDER BY je.EntryDate, je.JournalEntryID
      `)
    ).recordset;

    let runningBalance = openingBalance;
    const items = rows.map((r) => {
      runningBalance += sign * (Number(r.debitAmount) - Number(r.creditAmount));
      return { ...r, runningBalance: Math.round(runningBalance * 100) / 100 };
    });

    books.push({
      account: { accountId: account.AccountID, accountCode: account.AccountCode, accountName: account.AccountName },
      openingBalance: Math.round(openingBalance * 100) / 100,
      closingBalance: Math.round(runningBalance * 100) / 100,
      items,
    });
  }

  res.json({ success: true, data: { dateFrom, dateTo, books } });
});

/**
 * GET /api/accounting-reports/gst-summary?dateFrom=&dateTo=
 * Net movement on every account flagged IsTaxAccount for the period --
 * Output GST collected vs Input GST credit, and the net payable/receivable.
 */
router.get('/gst-summary', async (req, res) => {
  const dateFrom = (req.query.dateFrom as string | undefined) || `${new Date().getFullYear()}-01-01`;
  const dateTo = (req.query.dateTo as string | undefined) || new Date().toISOString().slice(0, 10);
  const pool = await getPool();
  const result = await pool
    .request()
    .input('dateFrom', sql.Date, dateFrom)
    .input('dateTo', sql.Date, dateTo)
    .query(`
      SELECT a.AccountID AS accountId, a.AccountCode AS accountCode, a.AccountName AS accountName, a.AccountType AS accountType,
             a.NormalBalance AS normalBalance,
             COALESCE(SUM(l.DebitAmount), 0) AS totalDebit, COALESCE(SUM(l.CreditAmount), 0) AS totalCredit
      FROM Chart_Of_Account a
      LEFT JOIN Journal_Entry_Line l ON l.AccountID = a.AccountID
      LEFT JOIN Journal_Entry je ON l.JournalEntryID = je.JournalEntryID AND je.EntryDate BETWEEN @dateFrom AND @dateTo
      WHERE a.IsTaxAccount = 1
      GROUP BY a.AccountID, a.AccountCode, a.AccountName, a.AccountType, a.NormalBalance
      ORDER BY a.AccountCode
    `);

  const items = result.recordset.map((r) => {
    const net = r.normalBalance === 'Debit' ? Number(r.totalDebit) - Number(r.totalCredit) : Number(r.totalCredit) - Number(r.totalDebit);
    return { accountId: r.accountId, accountCode: r.accountCode, accountName: r.accountName, accountType: r.accountType, amount: Math.round(net * 100) / 100 };
  });

  const inputCredit = items.filter((i) => i.accountType === 'Asset').reduce((a, i) => a + i.amount, 0);
  const outputPayable = items.filter((i) => i.accountType === 'Liability').reduce((a, i) => a + i.amount, 0);

  res.json({
    success: true,
    data: {
      dateFrom,
      dateTo,
      items,
      inputCredit: Math.round(inputCredit * 100) / 100,
      outputPayable: Math.round(outputPayable * 100) / 100,
      netPayable: Math.round((outputPayable - inputCredit) * 100) / 100,
    },
  });
});

export default router;
