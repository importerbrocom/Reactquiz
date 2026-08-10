import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { Dashboard, DayTimelineEntry, CourseSummary } from '@/types/models';

export async function getDashboard(): Promise<Dashboard> {
  const { data } = await apiClient.get<ApiEnvelope<Dashboard>>('/student/dashboard');
  return data.data;
}

export async function getCourses(): Promise<CourseSummary[]> {
  const { data } = await apiClient.get<ApiEnvelope<CourseSummary[]>>('/student/courses');
  return data.data;
}

export async function getCourseDays(courseId: string): Promise<DayTimelineEntry[]> {
  const { data } = await apiClient.get<ApiEnvelope<DayTimelineEntry[]>>(
    `/student/courses/${courseId}/days`,
  );
  return data.data;
}

export async function getCourseSummary(courseId: string): Promise<CourseSummary> {
  const { data } = await apiClient.get<ApiEnvelope<CourseSummary>>(
    `/student/courses/${courseId}/summary`,
  );
  return data.data;
}
