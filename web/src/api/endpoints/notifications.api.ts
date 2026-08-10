import { apiClient } from '../client';
import type { ApiEnvelope, CursorPaginated } from '@/types/api';
import type { Notification } from '@/types/models';

export async function getNotifications(cursor?: string): Promise<CursorPaginated<Notification>> {
  const { data } = await apiClient.get<ApiEnvelope<CursorPaginated<Notification>>>(
    '/student/notifications',
    { params: cursor ? { cursor } : undefined },
  );
  return data.data;
}

export async function getUnreadCount(): Promise<number> {
  const { data } = await apiClient.get<ApiEnvelope<{ count: number }>>(
    '/student/notifications/unread-count',
  );
  return data.data.count;
}

export async function markAsRead(notificationId: string): Promise<void> {
  await apiClient.post(`/student/notifications/${notificationId}/read`);
}

export async function markAllAsRead(): Promise<void> {
  await apiClient.post('/student/notifications/read-all');
}
