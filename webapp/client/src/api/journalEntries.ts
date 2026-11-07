import { apiClient } from './client';
import type { AccountType } from './chartOfAccounts';

export type VoucherType = 'Payment' | 'Receipt' | 'Contra' | 'Journal';

export interface AccountOption {
  id: number;
  code: string;
  name: string;
  accountType: AccountType;
}

export async function fetchJournalEntryLookups(): Promise<{ accounts: AccountOption[] }> {
  const res = await apiClient.get('/journal-entries/lookups');
  return res.data.data;
}

export interface JournalEntryRow {
  journalEntryId: number;
  entryNo: string;
  entryDate: string;
  narration: string | null;
  referenceNo: string | null;
  voucherType: VoucherType;
  voucherFilePath: string | null;
  isReversal: boolean;
  isReversed: boolean;
  reversalOfEntryId: number | null;
  createdByName: string | null;
  createdAt: string;
  totalAmount: number;
}

export interface JournalEntryListParams {
  page?: number;
  perPage?: number;
  dateFrom?: string;
  dateTo?: string;
  q?: string;
  voucherType?: VoucherType;
}

export async function fetchJournalEntries(params: JournalEntryListParams): Promise<{ items: JournalEntryRow[]; total: number; page: number; perPage: number }> {
  const res = await apiClient.get('/journal-entries', { params });
  return res.data.data;
}

export interface JournalEntryLine {
  lineId: number;
  accountId: number;
  accountCode: string;
  accountName: string;
  debitAmount: number;
  creditAmount: number;
  lineNarration: string | null;
  isReconciled: boolean;
  reconciledDate: string | null;
  isCashBankAccount: boolean;
}

export interface JournalEntryDetail extends JournalEntryRow {
  lines: JournalEntryLine[];
}

export async function fetchJournalEntry(id: number): Promise<JournalEntryDetail> {
  const res = await apiClient.get(`/journal-entries/${id}`);
  return res.data.data;
}

export interface JournalEntryLinePayload {
  accountId: number;
  debitAmount?: number;
  creditAmount?: number;
  lineNarration?: string;
}

export interface JournalEntryPayload {
  entryDate: string;
  narration?: string;
  referenceNo?: string;
  voucherType?: VoucherType;
  lines: JournalEntryLinePayload[];
}

export async function createJournalEntry(payload: JournalEntryPayload): Promise<{ journalEntryId: number; entryNo: string }> {
  const res = await apiClient.post('/journal-entries', payload);
  return res.data.data;
}

export async function reverseJournalEntry(id: number, entryDate?: string): Promise<{ journalEntryId: number; entryNo: string }> {
  const res = await apiClient.post(`/journal-entries/${id}/reverse`, { entryDate });
  return res.data.data;
}

export async function uploadJournalEntryAttachment(id: number, file: File): Promise<{ voucherFilePath: string }> {
  const formData = new FormData();
  formData.append('file', file);
  const res = await apiClient.post(`/journal-entries/${id}/attachment`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
  return res.data.data;
}

export async function reconcileLine(lineId: number, reconciled: boolean, reconciledDate?: string): Promise<void> {
  await apiClient.post(`/journal-entries/lines/${lineId}/reconcile`, { reconciled, reconciledDate });
}
