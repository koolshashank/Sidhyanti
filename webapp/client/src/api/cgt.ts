import { apiClient } from './client';

export interface CgtRow {
  branchId: number;
  branchName: string;
  centerId: number;
  centerCode: string;
  centerName: string;
  centerPlace: string | null;
  contactPerson: string | null;
  contactNo: string | null;
  meetingDay: string | number | null;
  meetingTime: string | null;
  cgtId: number | null;
  cgtDate: string | null;
  conductedBy: string | null;
  cgtImage: string | null;
  remarks: string | null;
  cgtStatus: 'Done' | 'Pending';
}

export interface CgtStats {
  totalCenters: number;
  done: number;
  pending: number;
}

export interface CgtListResponse {
  items: CgtRow[];
  total: number;
  page: number;
  perPage: number;
  stats: CgtStats;
}

export interface CgtListParams {
  page?: number;
  perPage?: number;
  branch?: number;
  status?: 'done' | 'pending';
  q?: string;
}

export async function fetchCgtList(params: CgtListParams): Promise<CgtListResponse> {
  const res = await apiClient.get('/cgt', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface CgtLookups {
  branches: LookupOption[];
}

export async function fetchCgtLookups(): Promise<CgtLookups> {
  const res = await apiClient.get('/cgt/lookups');
  return res.data.data;
}

export async function downloadCgtCsv(params: CgtListParams): Promise<void> {
  const res = await apiClient.get('/cgt', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'cgt-records.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
