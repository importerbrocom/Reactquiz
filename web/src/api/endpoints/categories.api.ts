import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { ExamCategory, Course } from '@/types/models';

export async function getCategories(): Promise<ExamCategory[]> {
  const { data } = await apiClient.get<ApiEnvelope<ExamCategory[]>>('/exam-categories');
  return data.data;
}

export async function getCategoryCourses(slug: string): Promise<Course[]> {
  const { data } = await apiClient.get<ApiEnvelope<Course[]>>(
    `/exam-categories/${slug}/courses`,
  );
  return data.data;
}

export async function getCourseDetail(slug: string): Promise<Course> {
  const { data } = await apiClient.get<ApiEnvelope<Course>>(`/courses/${slug}`);
  return data.data;
}
