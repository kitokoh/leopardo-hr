export type Schedule = {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  break_minutes?: number;
  late_tolerance_minutes?: number;
  days?: string[];
  employees_count?: number;
};

export function scheduleHours(schedule: Schedule): string {
  return `${schedule.start_time?.slice(0, 5) ?? '--:--'} – ${
    schedule.end_time?.slice(0, 5) ?? '--:--'
  }`;
}
