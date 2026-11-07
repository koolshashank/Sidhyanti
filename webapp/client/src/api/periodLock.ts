import { apiClient } from './client';

export interface PeriodLockHistoryRow {
  lockId: number;
  lockDate: string;
  lockedAt: string;
  lockedByName: string | null;
}

export async function fetchPeriodLock(): Promise<{ currentLockDate: string | null; history: PeriodLockHistoryRow[] }> {
  const res = await apiClient.get('/period-lock');
  return res.data.data;
}

export async function setPeriodLock(lockDate: string | null): Promise<void> {
  await apiClient.post('/period-lock', { lockDate });
}
