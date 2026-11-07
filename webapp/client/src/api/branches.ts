import { apiClient } from './client';

export interface BranchRow {
  branchId: number;
  branchCode: string;
  branchName: string;
  address: string | null;
  districtId: number | null;
  districtName: string | null;
  stateId: number | null;
  stateName: string | null;
  pincode: string | null;
  phone: string | null;
  email: string | null;
  dateOpened: string | null;
  isActive: boolean;
  totalMembers: number;
  activeCenters: number;
  activeLoans: number;
  outstandingPortfolio: number;
  lifetimeDisbursed: number;
}

export async function fetchBranches(): Promise<BranchRow[]> {
  const res = await apiClient.get('/branches');
  return res.data.data.items;
}

export interface BranchDetail {
  branchId: number;
  branchCode: string;
  branchName: string;
  address: string | null;
  districtId: number | null;
  stateId: number | null;
  pincode: string | null;
  phone: string | null;
  email: string | null;
  dateOpened: string | null;
  isActive: boolean;
}

export async function fetchBranchDetail(branchId: number): Promise<BranchDetail> {
  const res = await apiClient.get(`/branches/${branchId}`);
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface BranchLookups {
  states: LookupOption[];
  districts: (LookupOption & { stateId: number })[];
}

export async function fetchBranchLookups(): Promise<BranchLookups> {
  const res = await apiClient.get('/branches/lookups');
  return res.data.data;
}

export interface BranchFormValues {
  branchCode: string;
  branchName: string;
  address?: string;
  districtId?: number | null;
  stateId?: number | null;
  pincode?: string;
  phone?: string;
  email?: string;
  dateOpened?: string | null;
  isActive: boolean;
}

export async function createBranch(values: BranchFormValues): Promise<{ branchId: number }> {
  const res = await apiClient.post('/branches', values);
  return res.data.data;
}

export async function updateBranch(branchId: number, values: BranchFormValues): Promise<{ branchId: number }> {
  const res = await apiClient.put(`/branches/${branchId}`, values);
  return res.data.data;
}
