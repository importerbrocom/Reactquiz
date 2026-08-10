import { apiClient } from '../client';
import type { ApiEnvelope, CursorPaginated } from '@/types/api';
import type { ContentStatus, Difficulty, QuestionOption } from '@/types/enums';

export interface AdminQuestion {
  id: string;
  text: string;
  image_url: string | null;
  correct_option: QuestionOption;
  explanation: string | null;
  difficulty: Difficulty | null;
  topic: string | null;
  status: ContentStatus;
  course_name: string | null;
  day_number: number | null;
  times_served: number;
  times_correct: number;
  observed_difficulty: number | null;
  shuffle_options: boolean;
  created_at: string;
  options: AdminQuestionOption[];
}

export interface AdminQuestionOption {
  key: QuestionOption;
  text: string;
  is_correct: boolean;
}

export interface QuestionFilters {
  search?: string;
  course_id?: string;
  day?: number;
  difficulty?: string;
  status?: string;
  topic?: string;
  has_explanation?: boolean;
  cursor?: string;
}

export interface CreateQuestionPayload {
  text: string;
  course_id: string;
  correct_option: QuestionOption;
  explanation?: string;
  difficulty?: Difficulty;
  topic?: string;
  shuffle_options?: boolean;
  day_number?: number;
  options: Array<{ key: QuestionOption; text: string }>;
}

export interface BulkActionPayload {
  action: 'delete' | 'activate' | 'deactivate' | 'assign_day' | 'move_day' | 'set_difficulty' | 'set_topic';
  ids: string[];
  payload?: Record<string, unknown>;
}

export async function getQuestions(filters: QuestionFilters = {}): Promise<CursorPaginated<AdminQuestion>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<AdminQuestion>>>(
    '/admin/questions',
    { params: filters },
  );
  return data.data;
}

export async function getQuestion(id: string): Promise<AdminQuestion> {
  const { data } = await apiClient.get<ApiEnvelope<AdminQuestion>>(`/admin/questions/${id}`);
  return data.data;
}

export async function createQuestion(payload: CreateQuestionPayload): Promise<AdminQuestion> {
  const { data } = await apiClient.post<ApiEnvelope<AdminQuestion>>('/admin/questions', payload);
  return data.data;
}

export async function updateQuestion(id: string, payload: Partial<CreateQuestionPayload>): Promise<AdminQuestion> {
  const { data } = await apiClient.put<ApiEnvelope<AdminQuestion>>(`/admin/questions/${id}`, payload);
  return data.data;
}

export async function deleteQuestion(id: string): Promise<void> {
  await apiClient.delete(`/admin/questions/${id}`);
}

export async function restoreQuestion(id: string): Promise<void> {
  await apiClient.post(`/admin/questions/${id}/restore`);
}

export async function duplicateQuestion(id: string): Promise<AdminQuestion> {
  const { data } = await apiClient.post<ApiEnvelope<AdminQuestion>>(`/admin/questions/${id}/duplicate`);
  return data.data;
}

export async function bulkAction(payload: BulkActionPayload): Promise<{ affected_count: number }> {
  const { data } = await apiClient.post<ApiEnvelope<{ affected_count: number }>>('/admin/questions/bulk', payload);
  return data.data;
}

export async function getDuplicates(courseId: string): Promise<AdminQuestion[]> {
  const { data } = await apiClient.get<ApiEnvelope<AdminQuestion[]>>(
    '/admin/questions/duplicates',
    { params: { course_id: courseId } },
  );
  return data.data;
}

export async function previewQuestion(id: string): Promise<AdminQuestion> {
  const { data } = await apiClient.get<ApiEnvelope<AdminQuestion>>(`/admin/questions/${id}/preview`);
  return data.data;
}
