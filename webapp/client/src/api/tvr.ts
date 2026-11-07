import { apiClient } from './client';

export interface TvrRow {
  tvrId: number;
  loanId: number;
  loanNo: string | null;
  branchName: string | null;
  centerName: string | null;
  clientName: string;
  mobileNo: string | null;
  contactPersonName: string | null;
  loanAmount: number | null;
  tvrDate: string;
  conductedBy: string | null;
  statusId: number;
  statusName: string;
  verificationNotes: string | null;
}

export interface TvrStats {
  totalTvr: number;
  approved: number;
  rejected: number;
  pending: number;
  other: number;
}

export interface TvrListResponse {
  items: TvrRow[];
  total: number;
  page: number;
  perPage: number;
  stats: TvrStats;
}

export interface TvrListParams {
  page?: number;
  perPage?: number;
  dateFrom?: string;
  dateTo?: string;
  branch?: number;
  center?: number;
  status?: number;
  q?: string;
}

export async function fetchTvrList(params: TvrListParams): Promise<TvrListResponse> {
  const res = await apiClient.get('/tvr', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface TvrLookups {
  branches: LookupOption[];
  centers: (LookupOption & { branchId: number | null })[];
  statuses: LookupOption[];
}

export async function fetchTvrLookups(): Promise<TvrLookups> {
  const res = await apiClient.get('/tvr/lookups');
  return res.data.data;
}

export async function downloadTvrCsv(params: TvrListParams): Promise<void> {
  const res = await apiClient.get('/tvr', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'tvr-records.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

export interface TvrPendingRow {
  loanId: number;
  loanNo: string;
  memberId: number;
  clientName: string;
  mobileNo: string | null;
  branchName: string | null;
  centerName: string | null;
  loanAmount: number | null;
  applicationDate: string | null;
  stageName: string | null;
}

export interface TvrPendingParams {
  branch?: number;
  center?: number;
  q?: string;
}

export async function fetchTvrPending(params: TvrPendingParams): Promise<{ items: TvrPendingRow[] }> {
  const res = await apiClient.get('/tvr/pending', { params });
  return res.data.data;
}

export async function recordTvr(payload: {
  loanId: number;
  contactPersonName: string;
  tvrDate: string;
  tvrStatusId: number;
  verificationNotes?: string;
}): Promise<{ tvrId: number }> {
  const res = await apiClient.post('/tvr/record', payload);
  return res.data.data;
}
