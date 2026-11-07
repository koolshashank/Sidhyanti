import { apiClient } from './client';

export interface PaymentFrequencyRow {
  frequencyId: number;
  frequencyName: string;
  isActive: boolean;
  schemeCount: number;
}

export async function fetchPaymentFrequencies(): Promise<{ items: PaymentFrequencyRow[] }> {
  const res = await apiClient.get('/payment-frequencies');
  return res.data.data;
}

export async function createPaymentFrequency(frequencyName: string): Promise<{ frequencyId: number }> {
  const res = await apiClient.post('/payment-frequencies', { frequencyName });
  return res.data.data;
}

export async function updatePaymentFrequency(id: number, frequencyName: string, isActive: boolean): Promise<void> {
  await apiClient.put(`/payment-frequencies/${id}`, { frequencyName, isActive });
}

export async function deletePaymentFrequency(id: number): Promise<void> {
  await apiClient.delete(`/payment-frequencies/${id}`);
}
