import { apiClient } from './client';

export interface IncentiveReportRow {
  staffId: number;
  fullName: string;
  branchName: string | null;
  amountDisbursed: number;
  target: number;
  disbPercent: number;
  disbIncentive: number;
  collected: number;
  due: number;
  collPercent: number;
  collIncentive: number;
  total: number;
}

export interface IncentiveReportResponse {
  items: IncentiveReportRow[];
  totalIncentive: number;
  year: number;
  month: number;
}

export async function fetchIncentiveReport(params: { year: number; month: number; branch?: number }): Promise<IncentiveReportResponse> {
  const res = await apiClient.get('/incentive/report', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export async function fetchIncentiveLookups(): Promise<{ branches: LookupOption[] }> {
  const res = await apiClient.get('/incentive/lookups');
  return res.data.data;
}

export interface IncentiveSlab {
  slabId: number;
  minAchievementPercent: number;
  maxAchievementPercent: number | null;
  incentiveAmount: number;
  isActive: boolean;
}

export async function fetchIncentiveSlabs(): Promise<{ disbursement: IncentiveSlab[]; collection: IncentiveSlab[] }> {
  const res = await apiClient.get('/incentive/slabs');
  return res.data.data;
}

export async function createIncentiveSlab(payload: {
  metricType: 'Disbursement' | 'Collection';
  minAchievementPercent: number;
  maxAchievementPercent: number | null;
  incentiveAmount: number;
}): Promise<void> {
  await apiClient.post('/incentive/slabs', payload);
}

export async function toggleIncentiveSlab(id: number): Promise<void> {
  await apiClient.post(`/incentive/slabs/${id}/toggle`);
}

export async function deleteIncentiveSlab(id: number): Promise<void> {
  await apiClient.delete(`/incentive/slabs/${id}`);
}
