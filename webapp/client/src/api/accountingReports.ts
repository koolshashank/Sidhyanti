import { apiClient } from './client';
import type { AccountType, NormalBalance } from './chartOfAccounts';

export interface LedgerLine {
  journalEntryId: number;
  entryNo: string;
  entryDate: string;
  narration: string | null;
  debitAmount: number;
  creditAmount: number;
  lineNarration: string | null;
  runningBalance: number;
}

export interface LedgerResponse {
  account: { accountId: number; accountCode: string; accountName: string; normalBalance: NormalBalance };
  openingBalance: number;
  closingBalance: number;
  items: LedgerLine[];
}

export async function fetchLedger(accountId: number, params: { dateFrom?: string; dateTo?: string }): Promise<LedgerResponse> {
  const res = await apiClient.get(`/accounting-reports/ledger/${accountId}`, { params });
  return res.data.data;
}

export interface TrialBalanceRow {
  accountId: number;
  accountCode: string;
  accountName: string;
  accountType: AccountType;
  debit: number;
  credit: number;
}

export interface TrialBalanceResponse {
  asOfDate: string;
  items: TrialBalanceRow[];
  totalDebit: number;
  totalCredit: number;
}

export async function fetchTrialBalance(asOfDate: string): Promise<TrialBalanceResponse> {
  const res = await apiClient.get('/accounting-reports/trial-balance', { params: { asOfDate } });
  return res.data.data;
}

export interface PLRow {
  accountId: number;
  accountCode: string;
  accountName: string;
  amount: number;
}

export interface ProfitLossResponse {
  dateFrom: string;
  dateTo: string;
  income: PLRow[];
  expense: PLRow[];
  totalIncome: number;
  totalExpense: number;
  netProfit: number;
}

export async function fetchProfitLoss(dateFrom: string, dateTo: string): Promise<ProfitLossResponse> {
  const res = await apiClient.get('/accounting-reports/profit-loss', { params: { dateFrom, dateTo } });
  return res.data.data;
}

export interface BalanceSheetResponse {
  asOfDate: string;
  assets: PLRow[];
  liabilities: PLRow[];
  equity: PLRow[];
  totalAssets: number;
  totalLiabilities: number;
  totalEquity: number;
  totalLiabilitiesAndEquity: number;
}

export async function fetchBalanceSheet(asOfDate: string): Promise<BalanceSheetResponse> {
  const res = await apiClient.get('/accounting-reports/balance-sheet', { params: { asOfDate } });
  return res.data.data;
}

export interface DayBookLine {
  accountCode: string;
  accountName: string;
  debitAmount: number;
  creditAmount: number;
}

export interface DayBookEntry {
  journalEntryId: number;
  entryNo: string;
  entryDate: string;
  narration: string | null;
  voucherType: string;
  isReversal: boolean;
  isReversed: boolean;
  createdByName: string | null;
  totalAmount: number;
  lines: DayBookLine[];
}

export async function fetchDayBook(dateFrom: string, dateTo: string): Promise<{ dateFrom: string; dateTo: string; items: DayBookEntry[] }> {
  const res = await apiClient.get('/accounting-reports/day-book', { params: { dateFrom, dateTo } });
  return res.data.data;
}

export interface CashBankBookEntry {
  journalEntryId: number;
  entryNo: string;
  entryDate: string;
  narration: string | null;
  lineId: number;
  debitAmount: number;
  creditAmount: number;
  isReconciled: boolean;
  runningBalance: number;
}

export interface CashBankBook {
  account: { accountId: number; accountCode: string; accountName: string };
  openingBalance: number;
  closingBalance: number;
  items: CashBankBookEntry[];
}

export async function fetchCashBankBook(params: { dateFrom?: string; dateTo?: string }): Promise<{ books: CashBankBook[] }> {
  const res = await apiClient.get('/accounting-reports/cash-bank-book', { params });
  return res.data.data;
}

export interface GstSummaryRow {
  accountId: number;
  accountCode: string;
  accountName: string;
  accountType: string;
  amount: number;
}

export interface GstSummaryResponse {
  dateFrom: string;
  dateTo: string;
  items: GstSummaryRow[];
  inputCredit: number;
  outputPayable: number;
  netPayable: number;
}

export async function fetchGstSummary(dateFrom: string, dateTo: string): Promise<GstSummaryResponse> {
  const res = await apiClient.get('/accounting-reports/gst-summary', { params: { dateFrom, dateTo } });
  return res.data.data;
}
