import { apiClient } from './client';

export interface MenuVisibilityItem {
  key: string;
  label: string;
  visible: boolean;
}

export async function fetchMenuVisibility(): Promise<{ items: MenuVisibilityItem[] }> {
  const res = await apiClient.get('/menu-visibility');
  return res.data.data;
}

export async function saveMenuVisibility(hidden: string[]): Promise<void> {
  await apiClient.put('/menu-visibility', { hidden });
}
