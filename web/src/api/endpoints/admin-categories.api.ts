import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { ExamCategory } from '@/types/models';
import type { ContentStatus } from '@/types/enums';

export interface AdminCategory extends ExamCategory {
  display_order: number;
  created_at: string;
  updated_at: string;
}

export interface CreateCategoryPayload {
  name: string;
  description?: string;
  status?: ContentStatus;
}

export async function getCategories(): Promise<AdminCategory[]> {
  const { data } = await apiClient.get<ApiEnvelope<AdminCategory[]>>('/admin/exam-categories');
  return data.data;
}

export async function getCategory(id: string): Promise<AdminCategory> {
  const { data } = await apiClient.get<ApiEnvelope<AdminCategory>>(`/admin/exam-categories/${id}`);
  return data.data;
}

export async function createCategory(payload: CreateCategoryPayload): Promise<AdminCategory> {
  const { data } = await apiClient.post<ApiEnvelope<AdminCategory>>('/admin/exam-categories', payload);
  return data.data;
}

export async function updateCategory(id: string, payload: Partial<CreateCategoryPayload>): Promise<AdminCategory> {
  const { data } = await apiClient.put<ApiEnvelope<AdminCategory>>(`/admin/exam-categories/${id}`, payload);
  return data.data;
}

export async function deleteCategory(id: string): Promise<void> {
  await apiClient.delete(`/admin/exam-categories/${id}`);
}

export async function restoreCategory(id: string): Promise<void> {
  await apiClient.post(`/admin/exam-categories/${id}/restore`);
}

export async function toggleCategoryStatus(id: string): Promise<void> {
  await apiClient.post(`/admin/exam-categories/${id}/toggle-status`);
}

export async function reorderCategories(order: Array<{ id: string; display_order: number }>): Promise<void> {
  await apiClient.put('/admin/exam-categories/reorder', { order });
}
