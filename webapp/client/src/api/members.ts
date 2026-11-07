import { apiClient } from './client';

export interface MemberRow {
  memberId: number;
  enrollmentNo: string;
  firstName: string;
  lastName: string;
  mobileNo: string | null;
  gender: string | null;
  createdAt: string | null;
  branchName: string | null;
  centerName: string | null;
  stageName: string | null;
  assignee: string | null;
  activeLoanCount: number;
  activeLoanAmount: number | null;
}

export interface MemberListStats {
  totalMembers: number;
  newLeads: number;
  converted: number;
  conversionRate: number;
}

export interface MemberListResponse {
  items: MemberRow[];
  total: number;
  page: number;
  perPage: number;
  stats: MemberListStats;
}

export interface MemberListParams {
  page?: number;
  perPage?: number;
  branch?: number;
  stage?: number;
  officer?: number;
  q?: string;
}

export async function fetchMemberList(params: MemberListParams): Promise<MemberListResponse> {
  const res = await apiClient.get('/members', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export interface MemberLookups {
  branches: LookupOption[];
  stages: LookupOption[];
  officers: LookupOption[];
}

export async function fetchMemberLookups(): Promise<MemberLookups> {
  const res = await apiClient.get('/members/lookups');
  return res.data.data;
}

export interface MemberProfile {
  MemberID: number;
  EnrollmentNo: string;
  FirstName: string;
  MiddleName: string | null;
  LastName: string;
  FatherHusbandName: string | null;
  Gender: string | null;
  DOB: string | null;
  MaritalStatus: string | null;
  MobileNo: string | null;
  OccupationName: string | null;
  Address: string | null;
  VillageCity: string | null;
  DistrictName: string | null;
  StateName: string | null;
  Pincode: string | null;
  AadharNo: string | null;
  PANNo: string | null;
  BranchName: string | null;
  CenterName: string | null;
  CurrentStage: string | null;
  LeadSource: string | null;
  AssignedOfficer: string | null;
  IsActive: boolean;
  CreatedAt: string;
}

export interface MemberLoanRow {
  loanId: number;
  loanNo: string;
  loanAmountSanctioned: number | null;
  loanAmountApplied: number | null;
  applicationDate: string | null;
  disbursementDate: string | null;
  stageName: string | null;
}

export interface MemberKycDocument {
  kycDocId: number;
  docTypeName: string;
  docNumber: string | null;
  docFilePath: string;
  isVerified: boolean;
  uploadedAt: string;
}

export interface MemberBankDetails {
  bankName: string | null;
  ifscCode: string | null;
  bankBranch: string | null;
  accountNumber: string | null;
  beneficiaryName: string | null;
}

export interface MemberJourneyEvent {
  eventDate: string;
  eventType: string;
  eventTitle: string;
  eventDetail: string | null;
  relatedLoanNo: string | null;
  performedBy: string | null;
  amount: number | null;
}

export interface CoBorrowerDetail {
  coBorrowerId: number;
  firstName: string;
  middleName: string | null;
  lastName: string;
  relationToMember: string | null;
  gender: string | null;
  dob: string | null;
  maritalStatus: string | null;
  mobileNo: string | null;
  address: string | null;
  villageCity: string | null;
  pincode: string | null;
  aadharNo: string | null;
  panNo: string | null;
  voterId: string | null;
}

export interface CenterInfo {
  centerCode: string;
  meetingDay: string | null;
  meetingTime: string | null;
  place: string | null;
  address: string | null;
  contactPerson: string | null;
  contactNo: string | null;
}

export interface MemberDetail {
  profile: MemberProfile;
  photoUrl: string | null;
  loans: MemberLoanRow[];
  kycDocuments: MemberKycDocument[];
  bankDetails: MemberBankDetails | null;
  journey: MemberJourneyEvent[];
  coBorrowers: CoBorrowerDetail[];
  center: CenterInfo | null;
}

export async function fetchMemberDetail(memberId: number): Promise<MemberDetail> {
  const res = await apiClient.get(`/members/${memberId}`);
  return res.data.data;
}

export async function downloadMembersCsv(params: MemberListParams): Promise<void> {
  const res = await apiClient.get('/members', { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'members.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
