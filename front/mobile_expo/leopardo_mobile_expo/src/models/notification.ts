export type AppNotification = {
  id: number;
  type?: string;
  title?: string;
  message?: string;
  body?: string;
  read_at?: string | null;
  is_read?: boolean;
  created_at?: string | null;
  data?: Record<string, unknown> | null;
};

export function isUnread(notification: AppNotification): boolean {
  if (typeof notification.is_read === 'boolean') return !notification.is_read;
  return !notification.read_at;
}

export function notificationText(notification: AppNotification): string {
  return notification.message ?? notification.body ?? '';
}
