import { apiClient } from './client';

export interface GrtRow {
  grtId: number;
  loanId: number;
  loanNo: string | null;
  branchName: string | null;
  centerName: string | null;
  clientName: string;
  coBorrowerName: string | null;
  financeAmount: number | null;
  statusId: number;
  statusCode: string;
  conductedBy: string | null;
  grtDate: string;
  fileName: string | null;
}

export interface GrtStats {
  totalGrt: number;
  passed: number;
  failed: number;
  financeVerified: number;
}

export interface GrtListResponse {
  items: GrtRow[];
  total: number;
  page: number;
  perPage: number;
  stats: GrtStats;
}

export interface GrtListParams {
  page?: number;
  perPage?: number;
  dateFrom?: string;
  dateTo?: string;
  branch?: number;
  center?: number;
  status?: number;
  q?: string;
}

export async function fetchGrtList(params: GrtListParams): Promise<GrtListResponse> {
  const res = await apiClient.get('/grt', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface GrtLookups {
  branches: LookupOption[];
  centers: (LookupOption & { branchId: number | null })[];
  statuses: LookupOption[];
}

export async function fetchGrtLookups(): Promise<GrtLookups> {
  const res = await apiClient.get('/grt/lookups');
  return res.data.data;
}

export async function downloadGrtCsv(params: GrtListParams): Promise<void> {
  const res = await apiClient.get('/grt', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'grt-records.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
