import { apiClient } from './client';

export interface LoanSchemeRow {
  schemeId: number;
  schemeCode: string;
  schemeName: string;
  productTypeName: string | null;
  minLoanAmount: number | null;
  maxLoanAmount: number | null;
  defaultLoanAmount: number | null;
  tenureMonths: number;
  interestRatePercent: number;
  interestMethod: string | null;
  paymentFrequency: string;
  isActive: boolean;
}

export interface LoanSchemeStats {
  totalSchemes: number;
  activeSchemes: number;
  productTypesUsed: number;
  avgInterest: number;
}

export interface LoanSchemeListResponse {
  items: LoanSchemeRow[];
  total: number;
  page: number;
  perPage: number;
  stats: LoanSchemeStats;
}

export interface LoanSchemeListParams {
  page?: number;
  perPage?: number;
  product?: number;
  status?: 'active' | 'inactive';
  q?: string;
}

export async function fetchLoanSchemeList(params: LoanSchemeListParams): Promise<LoanSchemeListResponse> {
  const res = await apiClient.get('/loan-schemes', { params });
  return res.data.data;
}

export interface LookupOption {
  id: number;
  name: string;
}

export async function fetchLoanSchemeLookups(): Promise<{ productTypes: LookupOption[] }> {
  const res = await apiClient.get('/loan-schemes/lookups');
  return res.data.data;
}

export interface LoanSchemeDetail {
  schemeId: number;
  schemeCode: string;
  schemeName: string;
  productTypeId: number | null;
  minLoanAmount: number | null;
  maxLoanAmount: number | null;
  defaultLoanAmount: number | null;
  tenureMonths: number;
  interestRatePercent: number;
  interestMethod: string | null;
  paymentFrequency: string;
  processingFeeAmount: number | null;
  processingFeeGstPercent: number | null;
  documentChargeAmount: number | null;
  documentChargeGstPercent: number | null;
  otherChargesAmount: number | null;
  otherChargesGstPercent: number | null;
  loginFeeAmount: number | null;
  loginFeeGstPercent: number | null;
  insuranceFeeAmount: number | null;
  insurancePeriodMonths: number | null;
  sumAssuredClient: number | null;
  sumAssuredGuarantor: number | null;
  insuredBy: string | null;
  numInstallmentsSlot1: number | null;
  installmentAmountSlot1: number | null;
  numInstallmentsSlot2: number | null;
  installmentAmountSlot2: number | null;
  numInstallmentsSlot3: number | null;
  installmentAmountSlot3: number | null;
  effectiveFrom: string | null;
  effectiveTo: string | null;
  isActive: boolean;
}

export async function fetchLoanScheme(schemeId: number): Promise<LoanSchemeDetail> {
  const res = await apiClient.get(`/loan-schemes/${schemeId}`);
  return res.data.data;
}

export type LoanSchemeFormValues = Omit<LoanSchemeDetail, 'schemeId'>;

export async function createLoanScheme(values: LoanSchemeFormValues): Promise<{ schemeId: number }> {
  const res = await apiClient.post('/loan-schemes', values);
  return res.data.data;
}

export async function updateLoanScheme(schemeId: number, values: LoanSchemeFormValues): Promise<{ schemeId: number }> {
  const res = await apiClient.put(`/loan-schemes/${schemeId}`, values);
  return res.data.data;
}
