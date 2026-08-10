import { apiClient } from '../client';
import type { ApiEnvelope, CursorPaginated } from '@/types/api';
import type { UserStatus } from '@/types/enums';

export interface AdminStudent {
  id: string;
  name: string;
  email: string;
  status: UserStatus;
  timezone: string;
  created_at: string;
  last_activity_at: string | null;
  courses_enrolled: number;
  days_completed: number;
  current_streak: number;
}

export interface AdminStudentDetail extends AdminStudent {
  enrolments: AdminEnrolment[];
}

export interface AdminEnrolment {
  id: string;
  course_id: string;
  course_name: string;
  current_level: number;
  current_day: number;
  cycle: number;
  status: string;
  enrolled_at: string;
}

export interface StudentFilters {
  search?: string;
  course_id?: string;
  status?: string;
  cursor?: string;
}

export async function getStudents(filters: StudentFilters = {}): Promise<CursorPaginated<AdminStudent>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<AdminStudent>>>(
    '/admin/students',
    { params: filters },
  );
  return data.data;
}

export async function getStudent(id: string): Promise<AdminStudentDetail> {
  const { data } = await apiClient.get<ApiEnvelope<AdminStudentDetail>>(`/admin/students/${id}`);
  return data.data;
}

export async function suspendStudent(id: string): Promise<void> {
  await apiClient.post(`/admin/students/${id}/suspend`);
}

export async function activateStudent(id: string): Promise<void> {
  await apiClient.post(`/admin/students/${id}/activate`);
}

export async function revokeStudentSessions(id: string): Promise<void> {
  await apiClient.post(`/admin/students/${id}/revoke-sessions`);
}

export async function resetPasswordLink(id: string): Promise<{ link: string }> {
  const { data } = await apiClient.post<ApiEnvelope<{ link: string }>>(
    `/admin/students/${id}/reset-password-link`,
  );
  return data.data;
}
