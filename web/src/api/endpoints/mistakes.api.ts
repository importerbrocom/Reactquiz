import { apiClient } from '../client';
import type { ApiEnvelope, CursorPaginated } from '@/types/api';
import type { Mistake } from '@/types/models';

export interface MistakesFilter {
  course_id?: string;
  day?: number;
  topic?: string;
  difficulty?: string;
  status?: 'resolved' | 'unresolved';
  cursor?: string;
}

export async function getMistakes(filter: MistakesFilter = {}): Promise<CursorPaginated<Mistake>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<Mistake>>>(
    '/student/mistakes',
    { params: filter },
  );
  return data.data;
}
