export type Absence = {
  id: number;
  employee_id: number;
  absence_type_id: number;
  absence_type?: { name?: string } | null;
  start_date: string;
  end_date: string;
  days_count: number;
  status: 'pending' | 'approved' | 'rejected' | string;
  reason?: string | null;
  rejection_reason?: string | null;
  has_proof?: boolean;
  created_at?: string | null;
};

export type LeaveBalance = {
  id?: number;
  leave_policy_id?: number;
  name?: string;
  policy_name?: string;
  entitled_days?: number;
  used_days?: number;
  remaining_days?: number;
  balance?: number;
  unit?: string;
};

export function absenceTypeLabel(absence: Absence): string {
  return absence.absence_type?.name ?? `Type #${absence.absence_type_id}`;
}

export function formatDateRange(start: string, end: string): string {
  const fmt = (value: string) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
  };
  return start === end ? fmt(start) : `${fmt(start)} → ${fmt(end)}`;
}
