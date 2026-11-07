import { apiClient } from './client';

export interface PrecloseQuote {
  installmentsPaid: number;
  totalInstallments: number;
  principalOutstanding: number;
  interestSavings: number;
  precloseAmount: number;
}

export interface LoanApprovalRow {
  loanApprovalId: number;
  loanId: number;
  requestRemarks: string | null;
  createdAt: string;
  loanNo: string;
  loanAmountSanctioned: number | null;
  loanAmountApplied: number | null;
  firstName: string;
  lastName: string;
  requestedByName: string | null;
  quote?: PrecloseQuote | null;
}

export interface LoanApprovalsResponse {
  canProposal: boolean;
  canPreclose: boolean;
  proposals: LoanApprovalRow[];
  preclosures: LoanApprovalRow[];
}

export async function fetchLoanApprovals(): Promise<LoanApprovalsResponse> {
  const res = await apiClient.get('/loan-approvals');
  return res.data.data;
}

export async function reviewLoanApproval(id: number, action: 'approve' | 'reject', remarks?: string): Promise<{ status: string }> {
  const res = await apiClient.post(`/loan-approvals/${id}/review`, { action, remarks });
  return res.data.data;
}
