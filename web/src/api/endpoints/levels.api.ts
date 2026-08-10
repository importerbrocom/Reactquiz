import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type { LevelAdvanceInfo } from '@/types/models';

/**
 * Check if the student can advance to the next level or cycle.
 */
export async function getNextLevelInfo(): Promise<LevelAdvanceInfo> {
  const { data } = await apiClient.get<ApiEnvelope<LevelAdvanceInfo>>('/student/levels/next');
  return data.data;
}

/**
 * Advance to the next level or cycle (idempotent).
 */
export async function advanceLevel(): Promise<LevelAdvanceInfo> {
  const { data } = await apiClient.post<ApiEnvelope<LevelAdvanceInfo>>(
    '/student/levels/next',
    {},
    { idempotent: true },
  );
  return data.data;
}
