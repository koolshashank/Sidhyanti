import { apiClient } from './client';

export interface StaffProfile {
  staffId: number;
  fullName: string;
  username: string;
  roleId: number | null;
  roleName: string | null;
  branchId: number | null;
  designationName: string | null;
}

export async function login(username: string, password: string): Promise<{ token: string; staff: StaffProfile }> {
  const res = await apiClient.post('/auth/login', { username, password });
  return res.data.data;
}

export async function fetchMe(): Promise<StaffProfile> {
  const res = await apiClient.get('/auth/me');
  return res.data.data;
}
