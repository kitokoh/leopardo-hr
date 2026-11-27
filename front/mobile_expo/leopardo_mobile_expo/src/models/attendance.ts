export type AttendanceLog = {
  id: number;
  employee_id: number;
  date: string;
  session_number: number;
  check_in: string | null;
  check_out: string | null;
  status: string;
  work_type: string;
  punch_note?: string | null;
  hours_worked?: number | null;
  overtime_hours?: number | null;
  late_minutes?: number | null;
  timezone?: string | null;
  employee?: { name?: string; photo_url?: string | null } | null;
};

/** Une session est ouverte si l'entree existe sans sortie. */
export function isOpenSession(log: AttendanceLog): boolean {
  return Boolean(log.check_in) && !log.check_out;
}

export function formatTime(iso: string | null | undefined): string {
  if (!iso) return '--:--';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '--:--';
  return date.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatHours(value: number | null | undefined): string {
  if (value == null) return '0 h';
  const hours = Math.floor(value);
  const minutes = Math.round((value - hours) * 60);
  return minutes > 0 ? `${hours} h ${minutes}` : `${hours} h`;
}
