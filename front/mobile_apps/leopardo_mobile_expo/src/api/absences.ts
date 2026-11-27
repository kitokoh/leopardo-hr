import { api, unwrap, unwrapList } from './client';
import type { Absence, LeaveBalance } from '../models/absence';

/** Mes absences. GET /absences */
export async function fetchAbsences(): Promise<Absence[]> {
  const { data } = await api.get('/absences', { params: { per_page: 100 } });
  return unwrapList<Absence>(data);
}

/** Soldes de conges. GET /me/leave-balances */
export async function fetchLeaveBalances(): Promise<LeaveBalance[]> {
  const { data } = await api.get('/me/leave-balances');
  return unwrapList<LeaveBalance>(data);
}

export type CreateAbsenceInput = {
  absenceTypeId: number;
  startDate: string; // YYYY-MM-DD
  endDate: string; // YYYY-MM-DD
  reason?: string;
};

/** Declaration d'absence. POST /absences */
export async function createAbsence(input: CreateAbsenceInput): Promise<Absence> {
  const { data } = await api.post('/absences', {
    absence_type_id: input.absenceTypeId,
    start_date: input.startDate,
    end_date: input.endDate,
    ...(input.reason ? { reason: input.reason } : {}),
  });
  return unwrap<Absence>(data);
}

/** Approbation manager. POST /absences/{id}/approve */
export async function approveAbsence(id: number): Promise<void> {
  await api.post(`/absences/${id}/approve`);
}

/** Refus manager. POST /absences/{id}/reject */
export async function rejectAbsence(id: number, reason: string): Promise<void> {
  await api.post(`/absences/${id}/reject`, { rejected_reason: reason.trim() });
}
