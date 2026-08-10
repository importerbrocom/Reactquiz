import { apiClient } from '../client';
import type { ApiEnvelope, CursorPaginated } from '@/types/api';

export interface PdfImport {
  id: string;
  filename: string;
  status: string;
  course_name: string | null;
  category_name: string | null;
  total_items: number;
  parsed_count: number;
  approved_count: number;
  rejected_count: number;
  progress_percent: number;
  created_at: string;
}

export interface PdfImportItem {
  id: string;
  question_text: string;
  options: Array<{ key: string; text: string }>;
  correct_option: string | null;
  explanation: string | null;
  confidence: number;
  status: string;
  issues: string[];
}

export interface PdfImportProgress {
  status: string;
  progress_percent: number;
  pages_processed: number;
  total_pages: number;
  items_parsed: number;
}

export async function getPdfImports(params?: { status?: string; cursor?: string }): Promise<CursorPaginated<PdfImport>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<PdfImport>>>(
    '/admin/pdf-imports',
    { params },
  );
  return data.data;
}

export async function getPdfImport(id: string): Promise<PdfImport> {
  const { data } = await apiClient.get<ApiEnvelope<PdfImport>>(`/admin/pdf-imports/${id}`);
  return data.data;
}

export async function getImportProgress(id: string): Promise<PdfImportProgress> {
  const { data } = await apiClient.get<ApiEnvelope<PdfImportProgress>>(`/admin/pdf-imports/${id}/progress`);
  return data.data;
}

export async function getImportItems(id: string, cursor?: string): Promise<CursorPaginated<PdfImportItem>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<PdfImportItem>>>(
    `/admin/pdf-imports/${id}/items`,
    { params: cursor ? { cursor } : undefined },
  );
  return data.data;
}

export async function uploadPdf(file: File, options?: {
  course_id?: string;
  exam_category_id?: string;
  parser_profile?: string;
}): Promise<PdfImport> {
  const formData = new FormData();
  formData.append('file', file);
  if (options?.course_id) formData.append('course_id', options.course_id);
  if (options?.exam_category_id) formData.append('exam_category_id', options.exam_category_id);
  if (options?.parser_profile) formData.append('parser_profile', options.parser_profile);

  const { data } = await apiClient.post<ApiEnvelope<PdfImport>>('/admin/pdf-imports', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    idempotent: true,
  });
  return data.data;
}

export async function updateImportItem(importId: string, itemId: string, payload: Partial<PdfImportItem>): Promise<void> {
  await apiClient.put(`/admin/pdf-imports/${importId}/items/${itemId}`, payload);
}

export async function rejectImportItem(importId: string, itemId: string): Promise<void> {
  await apiClient.post(`/admin/pdf-imports/${importId}/items/${itemId}/reject`);
}

export async function approveImport(importId: string, itemIds?: string[]): Promise<void> {
  await apiClient.post(`/admin/pdf-imports/${importId}/approve`, {
    item_ids: itemIds,
  }, { idempotent: true });
}

export async function retryImport(importId: string): Promise<void> {
  await apiClient.post(`/admin/pdf-imports/${importId}/retry`);
}

export async function deleteImport(importId: string): Promise<void> {
  await apiClient.delete(`/admin/pdf-imports/${importId}`);
}
