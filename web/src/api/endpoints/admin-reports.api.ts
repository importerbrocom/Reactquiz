import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';

export interface Report {
  id: string;
  type: string;
  format: string;
  status: 'queued' | 'processing' | 'completed' | 'failed';
  filters: Record<string, unknown>;
  download_url: string | null;
  created_at: string;
  completed_at: string | null;
}

export interface CreateReportPayload {
  type: 'student_progress' | 'question_performance' | 'quiz_completion' | 'final_test_results';
  format: 'csv' | 'xlsx' | 'pdf';
  filters?: Record<string, unknown>;
}

export interface ActivityLog {
  id: string;
  user_name: string;
  event: string;
  subject_type: string;
  subject_id: string;
  properties: Record<string, unknown>;
  reason: string | null;
  created_at: string;
}

export async function getReports(): Promise<Report[]> {
  const { data } = await apiClient.get<ApiEnvelope<Report[]>>('/admin/reports');
  return data.data;
}

export async function getReport(id: string): Promise<Report> {
  const { data } = await apiClient.get<ApiEnvelope<Report>>(`/admin/reports/${id}`);
  return data.data;
}

export async function createReport(payload: CreateReportPayload): Promise<Report> {
  const { data } = await apiClient.post<ApiEnvelope<Report>>('/admin/reports', payload, { idempotent: true });
  return data.data;
}

export async function getActivityLogs(params?: { cursor?: string; event?: string }): Promise<{ data: ActivityLog[]; next_cursor: string | null }> {
  const { data } = await apiClient.get<ApiEnvelope<{ data: ActivityLog[]; next_cursor: string | null }>>(
    '/admin/activity-logs',
    { params },
  );
  return data.data;
}
