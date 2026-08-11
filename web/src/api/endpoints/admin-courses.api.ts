import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { Course } from '@/types/models';
import type { ContentStatus, UnlockMode, RetryMode } from '@/types/enums';

export interface AdminCourse extends Course {
  students_enrolled: number;
  questions_count: number;
  created_at: string;
  updated_at: string;
}

export interface CreateCoursePayload {
  exam_category_id: string;
  name: string;
  description?: string;
  status?: ContentStatus;
  total_questions?: number;
  levels_count?: number;
  days_per_level?: number;
  questions_per_day?: number;
}

export interface QuizConfig {
  days_per_level: number;
  questions_per_day: number;
  final_test_questions: number;
  pass_percent: number;
  timer_minutes: number | null;
  max_attempts: number;
  unlock_mode: UnlockMode;
  retry_mode: RetryMode;
  shuffle_options: boolean;
  allow_pause: boolean;
  require_test_pass_to_advance: boolean;
}

export interface CourseReadiness {
  ready: boolean;
  issues: Array<{ type: string; message: string; severity: 'error' | 'warning' }>;
}

export async function getCourses(): Promise<AdminCourse[]> {
  const { data } = await apiClient.get<ApiEnvelope<AdminCourse[]>>('/admin/courses');
  return data.data;
}

export async function getCourse(id: string): Promise<AdminCourse> {
  const { data } = await apiClient.get<ApiEnvelope<AdminCourse>>(`/admin/courses/${id}`);
  return data.data;
}

export async function createCourse(payload: CreateCoursePayload): Promise<AdminCourse> {
  const { data } = await apiClient.post<ApiEnvelope<AdminCourse>>('/admin/courses', payload);
  return data.data;
}

export async function updateCourse(id: string, payload: Partial<CreateCoursePayload>): Promise<AdminCourse> {
  const { data } = await apiClient.put<ApiEnvelope<AdminCourse>>(`/admin/courses/${id}`, payload);
  return data.data;
}

export async function deleteCourse(id: string): Promise<void> {
  await apiClient.delete(`/admin/courses/${id}`);
}

export async function toggleCourseStatus(id: string): Promise<void> {
  await apiClient.post(`/admin/courses/${id}/toggle-status`);
}

export async function duplicateCourse(id: string): Promise<AdminCourse> {
  const { data } = await apiClient.post<ApiEnvelope<AdminCourse>>(`/admin/courses/${id}/duplicate`);
  return data.data;
}

export async function getQuizConfig(id: string): Promise<QuizConfig> {
  const { data } = await apiClient.get<ApiEnvelope<QuizConfig>>(`/admin/courses/${id}/quiz-config`);
  return data.data;
}

export async function updateQuizConfig(id: string, config: Partial<QuizConfig>): Promise<QuizConfig> {
  const { data } = await apiClient.put<ApiEnvelope<QuizConfig>>(`/admin/courses/${id}/quiz-config`, config);
  return data.data;
}

export async function getCourseReadiness(id: string): Promise<CourseReadiness> {
  const { data } = await apiClient.get<ApiEnvelope<CourseReadiness>>(`/admin/courses/${id}/readiness`);
  return data.data;
}

export async function generateDailyQuizzes(id: string): Promise<{ message: string }> {
  const { data } = await apiClient.post<ApiEnvelope<{ message: string }>>(
    `/admin/courses/${id}/generate-daily-quizzes`,
    {},
    { idempotent: true },
  );
  return data.data;
}
