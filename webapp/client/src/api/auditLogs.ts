import { apiClient } from './client';

export interface AuditLogRow {
  auditId: string | number;
  changedAt: string;
  tableName: string;
  recordId: number;
  actionType: 'INSERT' | 'UPDATE' | 'DELETE';
  staffName: string | null;
  oldValues: string | null;
  newValues: string | null;
  ipAddress: string | null;
}

export interface AuditLogListResponse {
  items: AuditLogRow[];
  total: number;
  page: number;
  perPage: number;
}

export interface AuditLogListParams {
  page?: number;
  perPage?: number;
  table?: string;
  action?: 'INSERT' | 'UPDATE' | 'DELETE';
  dateFrom?: string;
  dateTo?: string;
}

export async function fetchAuditLogs(params: AuditLogListParams): Promise<AuditLogListResponse> {
  const res = await apiClient.get('/audit-logs', { params });
  return res.data.data;
}

export async function fetchAuditLogLookups(): Promise<{ tables: string[] }> {
  const res = await apiClient.get('/audit-logs/lookups');
  return res.data.data;
}

export async function downloadAuditLogsCsv(params: AuditLogListParams): Promise<void> {
  const res = await apiClient.get('/audit-logs', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'audit-log.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
