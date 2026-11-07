import { apiClient } from './client';

export interface ProductTypeRow {
  productTypeId: number;
  productTypeName: string;
  schemeCount: number;
}

export async function fetchProductTypes(): Promise<{ items: ProductTypeRow[] }> {
  const res = await apiClient.get('/product-types');
  return res.data.data;
}

export async function createProductType(productTypeName: string): Promise<{ productTypeId: number }> {
  const res = await apiClient.post('/product-types', { productTypeName });
  return res.data.data;
}

export async function updateProductType(id: number, productTypeName: string): Promise<void> {
  await apiClient.put(`/product-types/${id}`, { productTypeName });
}

export async function deleteProductType(id: number): Promise<void> {
  await apiClient.delete(`/product-types/${id}`);
}
