import { api, unwrap, unwrapList } from './client';
import type { AppNotification } from '../models/notification';
import type { ProjectTask } from '../models/task';
import type { Schedule } from '../models/schedule';
import type { Employee } from '../models/employee';
import type { DashboardSummary, RecentActivity } from '../models/dashboard';

/** Compteurs du tableau de bord. GET /dashboard/summary */
export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await api.get('/dashboard/summary');
  return unwrap<DashboardSummary>(data);
}

/** Activite recente. GET /dashboard/recent-activity?limit=5 */
export async function fetchRecentActivity(limit = 5): Promise<RecentActivity[]> {
  const { data } = await api.get('/dashboard/recent-activity', { params: { limit } });
  return unwrapList<RecentActivity>(data);
}

/** Notifications. GET /notifications */
export async function fetchNotifications(): Promise<AppNotification[]> {
  const { data } = await api.get('/notifications', { params: { per_page: 50 } });
  return unwrapList<AppNotification>(data);
}

/** Marquer une notification comme lue. PATCH /notifications/{id}/read */
export async function markNotificationRead(id: number): Promise<void> {
  await api.patch(`/notifications/${id}/read`);
}

/** Tout marquer comme lu. POST /notifications/mark-all-read */
export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/mark-all-read');
}

/** Taches du jour. GET /tasks/today */
export async function fetchTodayTasks(): Promise<ProjectTask[]> {
  const { data } = await api.get('/tasks/today');
  return unwrapList<ProjectTask>(data);
}

/** Terminer une tache. PATCH /tasks/{id} */
export async function completeTask(id: number, note?: string): Promise<void> {
  await api.patch(`/tasks/${id}`, {
    status: 'done',
    ...(note ? { completion_note: note.trim() } : {}),
  });
}

/** Plannings. GET /schedules */
export async function fetchSchedules(): Promise<Schedule[]> {
  const { data } = await api.get('/schedules', { params: { per_page: 50 } });
  return unwrapList<Schedule>(data);
}

/** Equipe (manager/RH). GET /employees */
export async function fetchEmployees(search?: string): Promise<Employee[]> {
  const { data } = await api.get('/employees', {
    params: { per_page: 100, ...(search ? { search } : {}) },
  });
  return unwrapList<Employee>(data);
}
