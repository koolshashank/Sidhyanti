import { apiClient } from './client';

export interface WriteoffPendingRow {
  requestId: number;
  loanId: number;
  requestReason: string | null;
  outstandingAmount: number;
  createdAt: string;
  loanNo: string;
  firstName: string;
  lastName: string;
  branchName: string | null;
  centerName: string | null;
  requestedByName: string | null;
}

export interface WriteoffHistoryRow {
  requestId: number;
  loanId: number;
  status: 'Approved' | 'Rejected';
  reviewedAt: string | null;
  outstandingAmount: number;
  reviewRemarks: string | null;
  loanNo: string;
  firstName: string;
  lastName: string;
  reviewedByName: string | null;
}

export async function fetchLoanWriteoffs(): Promise<{ pending: WriteoffPendingRow[]; history: WriteoffHistoryRow[] }> {
  const res = await apiClient.get('/loan-writeoffs');
  return res.data.data;
}

export async function reviewLoanWriteoff(id: number, action: 'approve' | 'reject', remarks?: string): Promise<{ status: string }> {
  const res = await apiClient.post(`/loan-writeoffs/${id}/review`, { action, remarks });
  return res.data.data;
}
