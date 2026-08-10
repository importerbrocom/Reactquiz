import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { ProgressOverview, ProgressCalendarDay, TopicProgress } from '@/types/models';

export async function getProgressOverview(courseId: string): Promise<ProgressOverview> {
  const { data } = await apiClient.get<ApiEnvelope<ProgressOverview>>(
    `/student/progress/${courseId}`,
  );
  return data.data;
}

export async function getProgressCalendar(courseId: string): Promise<ProgressCalendarDay[]> {
  const { data } = await apiClient.get<ApiEnvelope<ProgressCalendarDay[]>>(
    `/student/progress/${courseId}/calendar`,
  );
  return data.data;
}

export async function getProgressCharts(courseId: string): Promise<unknown> {
  const { data } = await apiClient.get<ApiEnvelope<unknown>>(
    `/student/progress/${courseId}/charts`,
  );
  return data.data;
}

export async function getTopicProgress(courseId: string): Promise<TopicProgress[]> {
  const { data } = await apiClient.get<ApiEnvelope<TopicProgress[]>>(
    `/student/progress/${courseId}/topics`,
  );
  return data.data;
}
