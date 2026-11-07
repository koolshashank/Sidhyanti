import { apiClient } from './client';

export interface StaffRow {
  staffId: number;
  employeeCode: string;
  fullName: string;
  username: string;
  email: string | null;
  phone: string | null;
  isActive: boolean;
  roleId: number;
  roleName: string | null;
  designationName: string | null;
  branchId: number | null;
  branchName: string | null;
  leadCount: number;
  activeLoans: number;
}

export interface StaffListStats {
  totalStaff: number;
  activeStaff: number;
  branchesCovered: number;
  activeLoansManaged: number;
}

export interface StaffListResponse {
  items: StaffRow[];
  total: number;
  page: number;
  perPage: number;
  stats: StaffListStats;
}

export interface StaffListParams {
  page?: number;
  perPage?: number;
  branch?: number;
  role?: number;
  status?: 'active' | 'inactive';
  q?: string;
}

export async function fetchStaffList(params: StaffListParams): Promise<StaffListResponse> {
  const res = await apiClient.get('/staff', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface StaffLookups {
  roles: LookupOption[];
  designations: (LookupOption & { roleId: number })[];
  branches: LookupOption[];
  managers: LookupOption[];
}

export async function fetchStaffLookups(): Promise<StaffLookups> {
  const res = await apiClient.get('/staff/lookups');
  return res.data.data;
}

export interface StaffDetail {
  staffId: number;
  employeeCode: string;
  fullName: string;
  username: string;
  email: string | null;
  phone: string | null;
  roleId: number;
  designationId: number | null;
  branchId: number | null;
  reportingManagerId: number | null;
  dateOfJoining: string | null;
  isActive: boolean;
  address: string | null;
  city: string | null;
  pincode: string | null;
  district: string | null;
  state: string | null;
}

export async function fetchStaffDetail(staffId: number): Promise<StaffDetail> {
  const res = await apiClient.get(`/staff/${staffId}`);
  return res.data.data;
}

export interface StaffFormValues {
  fullName: string;
  username: string;
  password?: string;
  email?: string;
  phone?: string;
  roleId: number;
  designationId?: number | null;
  branchId?: number | null;
  reportingManagerId?: number | null;
  dateOfJoining?: string | null;
  isActive: boolean;
  address?: string;
  city?: string;
  pincode?: string;
  district?: string;
  state?: string;
}

export async function createStaff(values: StaffFormValues): Promise<{ staffId: number }> {
  const res = await apiClient.post('/staff', values);
  return res.data.data;
}

export async function updateStaff(staffId: number, values: StaffFormValues): Promise<{ staffId: number }> {
  const res = await apiClient.put(`/staff/${staffId}`, values);
  return res.data.data;
}
