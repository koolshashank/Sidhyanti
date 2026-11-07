import { apiClient } from './client';

export type DeathEntryStatus = 'Pending' | 'Approved' | 'Rejected';

export interface DeathEntryRow {
  deathEntryId: number;
  memberId: number;
  firstName: string;
  lastName: string;
  enrollmentNo: string;
  mobileNo: string | null;
  centerName: string | null;
  branchName: string | null;
  dateOfDeath: string;
  cause: string | null;
  informantName: string | null;
  informantRelation: string | null;
  proofDocumentPath: string | null;
  status: DeathEntryStatus;
  remarksByApprover: string | null;
  reportedByName: string | null;
  reviewedByName: string | null;
  reviewedAt: string | null;
  createdAt: string;
}

export interface DeathEntryListResponse {
  items: DeathEntryRow[];
  statusCounts: { Pending: number; Approved: number; Rejected: number; All: number };
}

export async function fetchDeathEntries(params: { status?: DeathEntryStatus | 'All'; q?: string }): Promise<DeathEntryListResponse> {
  const res = await apiClient.get('/death-entries', { params });
  return res.data.data;
}

export async function approveDeathEntry(id: number, remarks?: string): Promise<void> {
  await apiClient.post(`/death-entries/${id}/approve`, { remarks });
}

export async function rejectDeathEntry(id: number, remarks?: string): Promise<void> {
  await apiClient.post(`/death-entries/${id}/reject`, { remarks });
}
