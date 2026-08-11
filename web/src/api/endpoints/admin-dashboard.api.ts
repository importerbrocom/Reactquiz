import { apiClient } from '../client';
import type { ApiEnvelope, CursorPaginated } from '@/types/api';

export interface DashboardSummary {
  students_total: number;
  students_active_today: number;
  exam_categories: number;
  courses: number;
  questions: number;
  quizzes_completed_today: number;
  final_tests_taken: number;
  avg_score_percent: number;
  completion_rate_percent: number;
}

export interface DashboardChart {
  date: string;
  quizzes_completed: number;
  students_active: number;
  avg_score: number;
}

export interface ActivityEntry {
  id: string;
  user_name: string;
  event: string;
  description: string;
  created_at: string;
}

export interface DifficultQuestion {
  id: string;
  text: string;
  course_name: string;
  difficulty_score: number;
  wrong_rate_percent: number;
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await apiClient.get<ApiEnvelope<DashboardSummary>>('/admin/dashboard/summary');
  return data.data;
}

export async function getDashboardCharts(params: {
  from?: string;
  to?: string;
  course_id?: string;
}): Promise<DashboardChart[]> {
  const { data } = await apiClient.get<ApiEnvelope<DashboardChart[]>>('/admin/dashboard/charts', { params });
  return data.data;
}

export async function getRecentActivity(cursor?: string): Promise<CursorPaginated<ActivityEntry>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<ActivityEntry>>>(
    '/admin/dashboard/activity',
    { params: cursor ? { cursor } : undefined },
  );
  return data.data;
}

export async function getDifficultQuestions(): Promise<DifficultQuestion[]> {
  const { data } = await apiClient.get<ApiEnvelope<DifficultQuestion[]>>(
    '/admin/dashboard/difficult-questions',
  );
  return data.data;
}

export async function getIncorrectQuestions(): Promise<DifficultQuestion[]> {
  const { data } = await apiClient.get<ApiEnvelope<DifficultQuestion[]>>(
    '/admin/dashboard/incorrect-questions',
  );
  return data.data;
}
