import { api, unwrap, unwrapList } from './client';
import type { AttendanceLog } from '../models/attendance';
import type { DailySummary, MonthlySummary } from '../models/summary';

export type PunchPayload = {
  work_type?: string;
  gps_lat?: number;
  gps_lng?: number;
  gps_accuracy?: number;
  punch_note?: string;
};

/** Pointage du jour (sessions en cours et terminees). GET /attendance/today */
export async function fetchToday(): Promise<AttendanceLog[]> {
  const { data } = await api.get('/attendance/today');
  return unwrapList<AttendanceLog>(data);
}

/** Entree. POST /attendance/check-in */
export async function checkIn(payload: PunchPayload = {}): Promise<AttendanceLog> {
  const { data } = await api.post('/attendance/check-in', {
    work_type: payload.work_type ?? 'normal',
    ...payload,
  });
  return unwrap<AttendanceLog>(data);
}

/** Sortie. POST /attendance/check-out */
export async function checkOut(payload: PunchPayload = {}): Promise<AttendanceLog> {
  const { data } = await api.post('/attendance/check-out', {
    work_type: payload.work_type ?? 'normal',
    ...payload,
  });
  return unwrap<AttendanceLog>(data);
}

/** Historique sur une plage de dates. GET /attendance?date_from&date_to */
export async function fetchHistory(from: string, to: string): Promise<AttendanceLog[]> {
  const { data } = await api.get('/attendance', {
    params: { date_from: from, date_to: to, per_page: 100 },
  });
  return unwrapList<AttendanceLog>(data);
}

/** Resume du jour (heures, gains estimes). GET /me/daily-summary */
export async function fetchDailySummary(): Promise<DailySummary> {
  const { data } = await api.get('/me/daily-summary');
  return unwrap<DailySummary>(data);
}

/** Resume du mois. GET /me/monthly-summary */
export async function fetchMonthlySummary(): Promise<MonthlySummary> {
  const { data } = await api.get('/me/monthly-summary');
  return unwrap<MonthlySummary>(data);
}
