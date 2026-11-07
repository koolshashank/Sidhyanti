import { apiClient } from './client';

export interface LoanRow {
  loanId: number;
  loanNo: string;
  loanAmountSanctioned: number | null;
  loanAmountApplied: number | null;
  applicationDate: string | null;
  disbursementDate: string | null;
  memberId: number;
  firstName: string;
  lastName: string;
  branchName: string | null;
  stageName: string | null;
  purposeName: string | null;
  officerName: string | null;
}

export interface LoanListStats {
  totalLoans: number;
  totalAmount: number;
  activeCount: number;
  avgTicket: number;
}

export interface LoanListResponse {
  items: LoanRow[];
  total: number;
  page: number;
  perPage: number;
  stats: LoanListStats;
  stageCounts: Record<string, number>;
}

export interface LoanListParams {
  page?: number;
  perPage?: number;
  branch?: number;
  stage?: string;
  q?: string;
}

export async function fetchLoanList(params: LoanListParams): Promise<LoanListResponse> {
  const res = await apiClient.get('/loans', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface LoanLookups {
  branches: LookupOption[];
  stages: { id: number; name: string; sortOrder: number }[];
}

export async function fetchLoanLookups(): Promise<LoanLookups> {
  const res = await apiClient.get('/loans/lookups');
  return res.data.data;
}

export async function downloadLoansCsv(params: LoanListParams): Promise<void> {
  const res = await apiClient.get('/loans', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'loans.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
