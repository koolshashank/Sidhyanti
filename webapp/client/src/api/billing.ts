import { apiClient } from './client';

export interface BillingInvoice {
  billingId: number;
  periodType: 'Monthly' | 'Quarterly' | 'Yearly';
  periodStart: string;
  periodEnd: string;
  amount: number;
  notes: string | null;
  generatedByName: string | null;
  createdAt: string;
}

export interface BillingStatus {
  servicesEnabled: boolean;
  invoices: BillingInvoice[];
}

export async function fetchBillingStatus(): Promise<BillingStatus> {
  const res = await apiClient.get('/billing');
  return res.data.data;
}

export async function toggleServices(enable: boolean): Promise<void> {
  await apiClient.post('/billing/toggle', { enable });
}

export async function generateBillingInvoice(payload: {
  periodType: 'Monthly' | 'Quarterly' | 'Yearly';
  periodStart: string;
  amount: number;
  notes?: string;
}): Promise<void> {
  await apiClient.post('/billing/generate', payload);
}
