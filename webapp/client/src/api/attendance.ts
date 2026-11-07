import { apiClient } from './client';

export interface AttendanceRow {
  attendanceId: number;
  attendanceDate: string;
  centerId: number;
  centerName: string | null;
  branchName: string | null;
  loanId: number;
  loanNo: string | null;
  borrowerName: string | null;
  status: 'Present' | 'Absent';
  markedByStaffId: number;
  markedByName: string | null;
  markedAt: string;
}

export interface AttendanceStats {
  totalMarked: number;
  present: number;
  absent: number;
  centersCovered: number;
}

export interface AttendanceListResponse {
  items: AttendanceRow[];
  total: number;
  page: number;
  perPage: number;
  stats: AttendanceStats;
}

export interface AttendanceListParams {
  page?: number;
  perPage?: number;
  dateFrom?: string;
  dateTo?: string;
  branch?: number;
  center?: number;
  status?: 'Present' | 'Absent';
  q?: string;
}

export async function fetchAttendanceList(params: AttendanceListParams): Promise<AttendanceListResponse> {
  const res = await apiClient.get('/attendance', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface AttendanceLookups {
  branches: LookupOption[];
  centers: (LookupOption & { branchId: number | null })[];
}

export async function fetchAttendanceLookups(): Promise<AttendanceLookups> {
  const res = await apiClient.get('/attendance/lookups');
  return res.data.data;
}

export async function downloadAttendanceCsv(params: AttendanceListParams): Promise<void> {
  const res = await apiClient.get('/attendance', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'meeting-attendance.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
