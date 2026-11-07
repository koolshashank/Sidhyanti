import { apiClient } from './client';

export interface CenterRow {
  centerId: number;
  centerCode: string;
  centerName: string;
  centerPlace: string | null;
  address: string | null;
  branchId: number | null;
  branchName: string | null;
  contactPerson: string | null;
  contactNo: string | null;
  meetingDay: string | null;
  meetingTime: string | null;
  managedByStaffId: number | null;
  managerName: string | null;
  isActive: boolean;
  latitude: string | null;
  longitude: string | null;
  memberCount: number;
}

export interface CenterListStats {
  totalCenters: number;
  activeCenters: number;
  branchesCovered: number;
  totalMembersInCenters: number;
}

export interface CenterListResponse {
  items: CenterRow[];
  total: number;
  page: number;
  perPage: number;
  stats: CenterListStats;
}

export interface CenterListParams {
  page?: number;
  perPage?: number;
  branch?: number;
  day?: number;
  status?: 'active' | 'inactive';
  q?: string;
}

export async function fetchCenterList(params: CenterListParams): Promise<CenterListResponse> {
  const res = await apiClient.get('/centers', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface CenterLookups {
  branches: LookupOption[];
  staff: (LookupOption & { branchId: number | null })[];
}

export async function fetchCenterLookups(): Promise<CenterLookups> {
  const res = await apiClient.get('/centers/lookups');
  return res.data.data;
}

export interface CenterDetail {
  centerId: number;
  centerCode: string;
  centerName: string;
  branchId: number;
  address: string | null;
  meetingDay: string | number | null;
  meetingTime: string | null;
  managedByStaffId: number | null;
  isActive: boolean;
  dissolvedDate: string | null;
  contactPerson: string | null;
  contactNo: string | null;
  centerPlace: string | null;
  latitude: string | null;
  longitude: string | null;
}

export async function fetchCenterDetail(centerId: number): Promise<CenterDetail> {
  const res = await apiClient.get(`/centers/${centerId}`);
  return res.data.data;
}

export interface CenterFormValues {
  centerCode: string;
  centerName: string;
  branchId: number;
  address?: string;
  meetingDay?: number | null;
  meetingTime?: string;
  managedByStaffId?: number | null;
  isActive: boolean;
  dissolvedDate?: string | null;
  contactPerson?: string;
  contactNo?: string;
  centerPlace?: string;
  latitude?: string;
  longitude?: string;
}

export async function updateCenter(centerId: number, values: CenterFormValues): Promise<{ centerId: number }> {
  const res = await apiClient.put(`/centers/${centerId}`, values);
  return res.data.data;
}

export async function shiftCenterMeeting(
  centerId: number,
  payload: { newDay: number; newTime?: string; remark?: string },
): Promise<{ shiftedCount: number }> {
  const res = await apiClient.post(`/centers/${centerId}/shift-meeting`, payload);
  return res.data.data;
}

export async function transferCenter(
  centerId: number,
  payload: { newStaffId: number; remark?: string },
): Promise<{ membersMoved: number; loansMoved: number }> {
  const res = await apiClient.post(`/centers/${centerId}/transfer`, payload);
  return res.data.data;
}

export interface CenterShiftHistoryRow {
  shiftId: number;
  oldMeetingDay: string | null;
  newMeetingDay: number;
  oldMeetingTime: string | null;
  newMeetingTime: string | null;
  schedulesShiftedCount: number;
  loansAffectedCount: number;
  remark: string | null;
  shiftedAt: string;
  shiftedByName: string | null;
}

export interface CenterTransferHistoryRow {
  transferId: number;
  oldStaffName: string | null;
  newStaffName: string | null;
  membersTransferredCount: number;
  loansTransferredCount: number;
  remark: string | null;
  transferredAt: string;
  transferredByName: string | null;
}

export async function fetchCenterHistory(centerId: number): Promise<{ shifts: CenterShiftHistoryRow[]; transfers: CenterTransferHistoryRow[] }> {
  const res = await apiClient.get(`/centers/${centerId}/history`);
  return res.data.data;
}
