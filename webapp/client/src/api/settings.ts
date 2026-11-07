import { apiClient } from './client';

export interface DefItem {
  key: string;
  label: string;
}

export interface RoleOption {
  roleId: number;
  roleName: string;
}

// 1. Role Permissions ---------------------------------------------------

export interface PermissionsData {
  roles: RoleOption[];
  permissions: DefItem[];
  matrix: Record<number, Record<string, boolean>>;
}

export async function fetchPermissions(): Promise<PermissionsData> {
  const res = await apiClient.get('/settings/permissions');
  return res.data.data;
}

export async function savePermissions(allowed: string[]): Promise<void> {
  await apiClient.put('/settings/permissions', { allowed });
}

// 2. Designations ---------------------------------------------------

export interface DesignationRow {
  designationId: number;
  designationName: string;
  roleId: number;
  roleName: string | null;
  activeStaffCount: number;
}

export async function fetchDesignations(): Promise<{ items: DesignationRow[] }> {
  const res = await apiClient.get('/settings/designations');
  return res.data.data;
}

export async function fetchDesignationLookups(): Promise<{ roles: { id: number; name: string }[] }> {
  const res = await apiClient.get('/settings/designations/lookups');
  return res.data.data;
}

export async function createDesignation(payload: { designationName: string; roleId: number }): Promise<void> {
  await apiClient.post('/settings/designations', payload);
}

export async function updateDesignation(id: number, payload: { designationName: string; roleId: number }): Promise<void> {
  await apiClient.put(`/settings/designations/${id}`, payload);
}

export async function deleteDesignation(id: number): Promise<void> {
  await apiClient.delete(`/settings/designations/${id}`);
}

// 3. Mobile Menu Access ---------------------------------------------------

export interface MobileMenuData {
  designations: { designationId: number; designationName: string; roleName: string | null }[];
  menuKeys: DefItem[];
  matrix: Record<number, Record<string, boolean>>;
}

export async function fetchMobileMenu(): Promise<MobileMenuData> {
  const res = await apiClient.get('/settings/mobile-menu');
  return res.data.data;
}

export async function saveMobileMenu(visible: string[]): Promise<void> {
  await apiClient.put('/settings/mobile-menu', { visible });
}

// 4. AM Branch Mapping ---------------------------------------------------

export interface AmBranchMappingData {
  staff: { staffId: number; fullName: string }[];
  branches: { branchId: number; branchName: string }[];
  matrix: Record<number, Record<number, boolean>>;
}

export async function fetchAmBranchMapping(): Promise<AmBranchMappingData> {
  const res = await apiClient.get('/settings/am-branch-mapping');
  return res.data.data;
}

export async function saveAmBranchMapping(mapped: string[]): Promise<void> {
  await apiClient.put('/settings/am-branch-mapping', { mapped });
}

// 5. App Settings ---------------------------------------------------

export interface AppSettingRow {
  key: string;
  label: string;
  value: boolean;
}

export async function fetchAppSettings(): Promise<{ settings: AppSettingRow[] }> {
  const res = await apiClient.get('/settings/app-settings');
  return res.data.data;
}

export async function saveAppSettings(values: Record<string, boolean>): Promise<void> {
  await apiClient.put('/settings/app-settings', { values });
}

// 6. Device Lock Settings ---------------------------------------------------

export interface DeviceLockRole {
  roleId: number;
  roleName: string;
  deviceLockEnabled: boolean;
}

export async function fetchDeviceLock(): Promise<{ roles: DeviceLockRole[] }> {
  const res = await apiClient.get('/settings/device-lock');
  return res.data.data;
}

export async function saveDeviceLock(enabled: number[]): Promise<void> {
  await apiClient.put('/settings/device-lock', { enabled });
}

// 7. Registered Devices ---------------------------------------------------

export interface DeviceRow {
  staffId: number;
  fullName: string;
  username: string;
  roleName: string | null;
  deviceId: string;
  deviceRegisteredAt: string;
}

export async function fetchDevices(): Promise<{ items: DeviceRow[] }> {
  const res = await apiClient.get('/settings/devices');
  return res.data.data;
}

export async function resetDevice(staffId: number): Promise<void> {
  await apiClient.post(`/settings/devices/${staffId}/reset`);
}

// 8. KFS Settings ---------------------------------------------------

export interface KfsProfile {
  companyName: string;
  nbfcRegistrationNo: string;
  registeredOfficeAddress: string;
  customerCarePhone: string;
  customerCareEmail: string;
  grievanceOfficerName: string;
  grievanceOfficerPhone: string;
  coolingOffPeriodDays: number;
}

export async function fetchKfs(): Promise<KfsProfile> {
  const res = await apiClient.get('/settings/kfs');
  return res.data.data;
}

export async function saveKfs(payload: KfsProfile): Promise<void> {
  await apiClient.put('/settings/kfs', payload);
}
