import { apiClient } from './client';

export type AccountType = 'Asset' | 'Liability' | 'Equity' | 'Income' | 'Expense';
export type NormalBalance = 'Debit' | 'Credit';

export interface AccountRow {
  accountId: number;
  accountCode: string;
  accountName: string;
  accountType: AccountType;
  normalBalance: NormalBalance;
  parentAccountId: number | null;
  parentAccountName: string | null;
  isActive: boolean;
  openingBalance: number;
  openingBalanceDate: string | null;
  isCashBankAccount: boolean;
  isTaxAccount: boolean;
  lineCount: number;
  childCount: number;
}

export async function fetchAccounts(): Promise<{ items: AccountRow[] }> {
  const res = await apiClient.get('/chart-of-accounts');
  return res.data.data;
}

export interface AccountPayload {
  accountCode: string;
  accountName: string;
  accountType: AccountType;
  normalBalance: NormalBalance;
  parentAccountId?: number | null;
  isActive: boolean;
  openingBalance?: number;
  openingBalanceDate?: string | null;
  isCashBankAccount?: boolean;
  isTaxAccount?: boolean;
}

export async function createAccount(payload: AccountPayload): Promise<{ accountId: number }> {
  const res = await apiClient.post('/chart-of-accounts', payload);
  return res.data.data;
}

export async function updateAccount(id: number, payload: AccountPayload): Promise<void> {
  await apiClient.put(`/chart-of-accounts/${id}`, payload);
}

export async function deleteAccount(id: number): Promise<void> {
  await apiClient.delete(`/chart-of-accounts/${id}`);
}
