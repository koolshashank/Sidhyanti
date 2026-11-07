import { apiClient } from './client';

export interface LoanSchemeOption {
  id: number;
  name: string;
  tenureMonths: number;
  defaultAmount: number;
  interestRatePercent: number;
  emiAmount: number | null;
}

export interface LookupOption {
  id: number;
  name: string;
}

export async function fetchLoanProposalLookups(): Promise<{ schemes: LoanSchemeOption[]; purposes: LookupOption[] }> {
  const res = await apiClient.get('/loan-proposals/lookups');
  return res.data.data;
}

export interface ReloanEligibility {
  previousLoanNo: string;
  previousLoanAmount: number;
  cycleNumber: number;
  latePayments: number;
  ruleName: string;
  increasePercent: number;
  eligibleAmount: number;
}

export interface MemberBankDetails {
  bankName: string | null;
  ifscCode: string | null;
  bankBranch: string | null;
  bankAddress: string | null;
  accountNumber: string | null;
  beneficiaryName: string | null;
}

export interface MemberLoanContext {
  member: { memberId: number; firstName: string; lastName: string; branchId: number; centerId: number };
  isDeceased: boolean;
  openLoanNo: string | null;
  bankDetails: MemberBankDetails | null;
  reloanEligibility: ReloanEligibility | null;
}

export async function fetchMemberLoanContext(memberId: number): Promise<MemberLoanContext> {
  const res = await apiClient.get(`/loan-proposals/member-context/${memberId}`);
  return res.data.data;
}

export interface LoanProposalPayload {
  memberId: number;
  schemeId: number;
  amount?: number;
  purposeId?: number;
  emiAmount?: number;
  selfIncome?: number;
  husbandSonIncome?: number;
  otherIncome?: number;
  residentIncome?: number;
  houseExpenses?: number;
  mfiExpenses?: number;
  businessExpenses?: number;
  sourceOfIncome?: string;
  ifscCode?: string;
  bankName?: string;
  bankBranch?: string;
  bankAddress?: string;
  accountNumber?: string;
  beneficiaryName?: string;
}

export async function createLoanProposal(payload: LoanProposalPayload): Promise<{ loanId: number }> {
  const res = await apiClient.post('/loan-proposals', payload);
  return res.data.data;
}
