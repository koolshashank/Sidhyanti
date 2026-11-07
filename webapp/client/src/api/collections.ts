import { apiClient } from './client';

export type CollectionStatus = 'Pending' | 'Partial' | 'Paid' | 'Preclosed';

export interface CollectionRow {
  sheetId: number;
  dueDate: string;
  loanId: number;
  loanNo: string | null;
  installmentNo: number | null;
  borrowerName: string | null;
  coBorrowerName: string | null;
  branchId: number | null;
  branchName: string | null;
  centerId: number | null;
  centerName: string | null;
  dueAmount: number;
  collectedAmount: number;
  officerStaffId: number | null;
  officerName: string | null;
  status: CollectionStatus;
}

export interface CollectionStats {
  clientsDue: number;
  dueAmount: number;
  collectedAmount: number;
  balanceAmount: number;
  collectionPct: number;
}

export interface CollectionListResponse {
  items: CollectionRow[];
  total: number;
  page: number;
  perPage: number;
  stats: CollectionStats;
}

export interface CollectionListParams {
  page?: number;
  perPage?: number;
  dateFrom?: string;
  dateTo?: string;
  branch?: number;
  center?: number;
  status?: CollectionStatus;
  q?: string;
}

export async function fetchCollectionList(params: CollectionListParams): Promise<CollectionListResponse> {
  const res = await apiClient.get('/collections', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface CollectionLookups {
  branches: LookupOption[];
  centers: (LookupOption & { branchId: number | null })[];
}

export async function fetchCollectionLookups(): Promise<CollectionLookups> {
  const res = await apiClient.get('/collections/lookups');
  return res.data.data;
}

export async function downloadCollectionsCsv(params: CollectionListParams): Promise<void> {
  const res = await apiClient.get('/collections', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'collections.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
