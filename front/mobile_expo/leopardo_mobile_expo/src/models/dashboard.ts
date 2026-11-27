export type DashboardSummary = {
  employees_total?: number;
  employees_active?: number;
  departments?: number;
  today_attendance?: number;
  pending_absences?: number;
};

export type RecentActivity = {
  id?: number;
  action?: string;
  description?: string;
  event?: string;
  created_at?: string | null;
  actor?: { name?: string } | null;
};

export function activityLabel(activity: RecentActivity): string {
  return activity.description ?? activity.action ?? activity.event ?? 'Activite';
}
