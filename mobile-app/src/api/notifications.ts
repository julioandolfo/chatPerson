import { client } from '@/api/client';
import type { AppNotification, Paginated, UnreadNotificationsData } from '@/types';

export async function listNotifications(page = 1): Promise<Paginated<AppNotification>> {
  const { data } = await client.get<Paginated<AppNotification>>('/notifications', {
    params: { page },
  });
  return data;
}

export async function getUnreadNotifications(): Promise<UnreadNotificationsData> {
  const { data } = await client.get<UnreadNotificationsData>('/notifications/unread');
  return data;
}

export async function markNotificationRead(id: number): Promise<void> {
  await client.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await client.post('/notifications/read-all');
}
