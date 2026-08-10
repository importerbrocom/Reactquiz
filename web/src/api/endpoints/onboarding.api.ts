import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { OnboardingState, Dashboard } from '@/types/models';

export interface SaveCategoryPayload {
  exam_category_id: string;
}

export interface SaveCoursePayload {
  course_id: string;
}

export interface SavePreferencesPayload {
  reminder_time: string | null;
  notifications_opt_in: boolean;
  language: string;
  timezone: string;
  study_goal: string | null;
}

export async function getOnboardingState(): Promise<OnboardingState> {
  const { data } = await apiClient.get<ApiEnvelope<OnboardingState>>('/student/onboarding');
  return data.data;
}

export async function saveCategory(payload: SaveCategoryPayload): Promise<OnboardingState> {
  const { data } = await apiClient.put<ApiEnvelope<OnboardingState>>(
    '/student/onboarding/category',
    payload,
  );
  return data.data;
}

export async function saveCourse(payload: SaveCoursePayload): Promise<OnboardingState> {
  const { data } = await apiClient.put<ApiEnvelope<OnboardingState>>(
    '/student/onboarding/course',
    payload,
  );
  return data.data;
}

export async function savePreferences(payload: SavePreferencesPayload): Promise<OnboardingState> {
  const { data } = await apiClient.put<ApiEnvelope<OnboardingState>>(
    '/student/onboarding/preferences',
    payload,
  );
  return data.data;
}

export async function completeOnboarding(): Promise<Dashboard> {
  const { data } = await apiClient.post<ApiEnvelope<Dashboard>>('/student/onboarding/complete');
  return data.data;
}

export async function resetOnboarding(): Promise<void> {
  await apiClient.post('/student/onboarding/reset');
}
