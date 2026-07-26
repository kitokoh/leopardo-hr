'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import Image from 'next/image';
import { Check, LogIn, LogOut, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getCopy, getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';
import { GeoSessionStatusBadge } from '../../_components/GeoSessionStatusBadge';
import { ApproveSessionModal } from '../../_components/ApproveSessionModal';
import { RejectSessionModal } from '../../_components/RejectSessionModal';

const emptySubscribe = () => () => {};

// ─── Types ────────────────────────────────────────────────────────────────────

type LocationEvent = {
  id: number | string;
  event_type: string;
  latitude?: number | null;
  longitude?: number | null;
  accuracy?: number | null;
  recorded_at?: string | null;
};

type GeoSessionDetail = {
  id: number | string;
  employee_id: number;
  employee_name?: string;
  employee_matricule?: string;
  employee_photo?: string | null;
  check_in_time?: string | null;
  check_out_time?: string | null;
  duration_minutes?: number | null;
  status: string;
  check_in_latitude?: number | null;
  check_in_longitude?: number | null;
  check_out_latitude?: number | null;
  check_out_longitude?: number | null;
  note?: string | null;
  rejection_reason?: string | null;
  location_events?: LocationEvent[];
};

type SessionPayload = {
  data?: GeoSessionDetail;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDateTime(iso: string | null | undefined, intlLocale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(intlLocale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDuration(minutes?: number | null): string {
  if (minutes == null) return '—';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}min`;
  return `${h}h${m > 0 ? String(m).padStart(2, '0') : ''}`;
}

function formatCoords(lat?: number | null, lng?: number | null): string {
  if (lat == null || lng == null) return '—';
  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

function mapsLink(lat?: number | null, lng?: number | null): string | null {
  if (lat == null || lng == null) return null;
  return `https://www.google.com/maps?q=${lat},${lng}`;
}

// ─── Page ─────────────────────────────────────────────────────────────────────

type ModalType = 'approve' | 'reject' | null;

export default function SmartAttendanceSessionDetailPage() {
  const { id } = useParams<{ id: string }>();
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).smartAttendanceSessionDetailPage;
  const statusLabels = getCopy(locale).smartAttendancePage;
  const approveLabels = getCopy(locale).smartAttendancePage;
  const rejectLabels = getCopy(locale).smartAttendancePage;
  const intlLocale = toIntlLocale(locale);
  const [session, setSession] = useState<GeoSessionDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modal, setModal] = useState<ModalType>(null);
  const [modalLoading, setModalLoading] = useState(false);

  const canValidate = session?.status === 'pending_validation' || session?.status === 'detected';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch(`/smart-attendance/sessions/${id}`);
      const payload = await response.json() as SessionPayload;
      setSession(payload.data ?? null);
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setLoading(false);
    }
  }, [id, labels.loadError]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleApprove = async (note: string) => {
    if (!session) return;
    setModalLoading(true);
    try {
      await apiFetch(`/smart-attendance/sessions/${session.id}/approve`, {
        method: 'POST',
        body: JSON.stringify({ note }),
      });
      setModal(null);
      await load();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : labels.approveErrorGeneric);
    } finally {
      setModalLoading(false);
    }
  };

  const handleReject = async (reason: string) => {
    if (!session) return;
    setModalLoading(true);
    try {
      await apiFetch(`/smart-attendance/sessions/${session.id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      });
      setModal(null);
      await load();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : labels.rejectErrorGeneric);
    } finally {
      setModalLoading(false);
    }
  };

  return (
    <>
      <ModulePageShell
        title={labels.title}
        subtitle={labels.subtitle}
        accentClassName="bg-gradient-to-br from-security/10 via-white to-white"
      >
        {/* Back */}
        <div className="flex items-center gap-4">
          <Link
            href="/smart-attendance/sessions"
            className="text-sm font-bold text-slate-500 transition hover:text-slate-900"
          >
            {labels.backToSessions}
          </Link>
        </div>

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        ) : null}

        {loading ? (
          <div className="space-y-4 animate-pulse">
            <div className="h-32 rounded-2xl bg-slate-200" />
            <div className="h-24 rounded-2xl bg-slate-200" />
            <div className="h-48 rounded-2xl bg-slate-200" />
          </div>
        ) : !session ? (
          <div className="rounded-2xl border border-app-border bg-white px-6 py-10 text-center text-sm text-slate-500">
            {labels.notFound}
          </div>
        ) : (
          <div className="space-y-6">
            {/* Employee card */}
            <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
              <div className="flex items-center gap-4">
                {session.employee_photo ? (
                  <Image
                    src={session.employee_photo}
                    alt={session.employee_name ?? ''}
                    width={64}
                    height={64}
                    className="h-16 w-16 rounded-2xl object-cover"
                  />
                ) : (
                  <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-200 to-slate-300 text-xl font-black text-slate-600">
                    {(session.employee_name ?? 'E').charAt(0).toUpperCase()}
                  </div>
                )}
                <div className="flex-1">
                  <p className="text-xl font-black text-slate-950">
                    {session.employee_name ?? `${labels.employeeFallback} #${session.employee_id}`}
                  </p>
                  {session.employee_matricule ? (
                    <p className="text-sm text-slate-500">{session.employee_matricule}</p>
                  ) : null}
                </div>
                <GeoSessionStatusBadge status={session.status} labels={statusLabels} />
              </div>

              {/* Notes */}
              {session.note ? (
                <div className="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                  <span className="font-bold">{labels.noteLabel}</span>{session.note}
                </div>
              ) : null}
              {session.rejection_reason ? (
                <div className="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                  <span className="font-bold">{labels.rejectionReasonLabel}</span>{session.rejection_reason}
                </div>
              ) : null}
            </section>

            {/* Timeline */}
            <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">{labels.timelineTitle}</h2>
              <div className="flex items-start gap-0">
                {/* Check in */}
                <div className="flex flex-col items-center">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <LogIn className="h-5 w-5" aria-hidden="true" />
                  </div>
                  <div className="mt-2 h-12 w-0.5 bg-slate-200" />
                </div>
                <div className="ml-4 mt-1">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{labels.checkInDetected}</p>
                  <p className="text-lg font-black text-slate-900">{formatDateTime(session.check_in_time, intlLocale)}</p>
                </div>
              </div>

              <div className="flex items-start gap-0 mt-2">
                <div className="flex flex-col items-center">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <LogOut className="h-5 w-5" aria-hidden="true" />
                  </div>
                </div>
                <div className="ml-4 mt-1">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{labels.departure}</p>
                  <p className="text-lg font-black text-slate-900">{formatDateTime(session.check_out_time, intlLocale)}</p>
                  {session.duration_minutes != null ? (
                    <p className="text-sm text-slate-500">{labels.durationLabel}{formatDuration(session.duration_minutes)}</p>
                  ) : null}
                </div>
              </div>
            </section>

            {/* GPS coordinates */}
            <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">{labels.gpsCoordinatesTitle}</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                  <p className="text-xs font-bold uppercase tracking-wider text-emerald-600">{labels.checkInLabel}</p>
                  <p className="mt-1 font-mono text-sm text-slate-800">
                    {formatCoords(session.check_in_latitude, session.check_in_longitude)}
                  </p>
                  {mapsLink(session.check_in_latitude, session.check_in_longitude) ? (
                    <a
                      href={mapsLink(session.check_in_latitude, session.check_in_longitude)!}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-2 inline-block text-xs font-bold text-emerald-700 underline"
                    >
                      {labels.viewOnMaps}
                    </a>
                  ) : null}
                </div>

                <div className="rounded-xl border border-red-100 bg-red-50 p-4">
                  <p className="text-xs font-bold uppercase tracking-wider text-red-600">{labels.checkOutLabel}</p>
                  <p className="mt-1 font-mono text-sm text-slate-800">
                    {formatCoords(session.check_out_latitude, session.check_out_longitude)}
                  </p>
                  {mapsLink(session.check_out_latitude, session.check_out_longitude) ? (
                    <a
                      href={mapsLink(session.check_out_latitude, session.check_out_longitude)!}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-2 inline-block text-xs font-bold text-red-700 underline"
                    >
                      {labels.viewOnMaps}
                    </a>
                  ) : null}
                </div>
              </div>
            </section>

            {/* Location events */}
            {session.location_events && session.location_events.length > 0 ? (
              <section className="overflow-hidden rounded-2xl border border-app-border bg-white shadow-sm">
                <div className="border-b border-app-border px-6 py-4">
                  <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                    {labels.gpsHistoryTitle} ({session.location_events.length})
                  </h2>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-app-border bg-slate-50/50">
                        <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnType}</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnTime}</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnLatitude}</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnLongitude}</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnAccuracy}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-app-border">
                      {session.location_events.map((ev) => (
                        <tr key={ev.id} className="hover:bg-slate-50/60">
                          <td className="px-6 py-3">
                            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-700">
                              {ev.event_type}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-slate-700">{formatDateTime(ev.recorded_at, intlLocale)}</td>
                          <td className="px-4 py-3 font-mono text-slate-700">{ev.latitude?.toFixed(6) ?? '—'}</td>
                          <td className="px-4 py-3 font-mono text-slate-700">{ev.longitude?.toFixed(6) ?? '—'}</td>
                          <td className="px-4 py-3 text-slate-700">{ev.accuracy?.toFixed(1) ?? '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </section>
            ) : null}

            {/* Validation actions */}
            {canValidate ? (
              <section className="rounded-2xl border border-amber-100 bg-amber-50 p-5">
                <p className="mb-3 text-sm font-bold text-amber-800">{labels.pendingValidationNotice}</p>
                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setModal('approve')}
                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-emerald-500"
                  >
                    <Check className="h-4 w-4" aria-hidden="true" /> {labels.approve}
                  </button>
                  <button
                    type="button"
                    onClick={() => setModal('reject')}
                    className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-red-500"
                  >
                    <X className="h-4 w-4" aria-hidden="true" /> {labels.reject}
                  </button>
                </div>
              </section>
            ) : null}
          </div>
        )}
      </ModulePageShell>

      {/* Modals */}
      {modal === 'approve' && session ? (
        <ApproveSessionModal
          employeeName={session.employee_name ?? `${labels.employeeFallback} #${session.employee_id}`}
          onConfirm={(note) => void handleApprove(note)}
          onCancel={() => setModal(null)}
          loading={modalLoading}
          labels={approveLabels}
        />
      ) : null}

      {modal === 'reject' && session ? (
        <RejectSessionModal
          employeeName={session.employee_name ?? `${labels.employeeFallback} #${session.employee_id}`}
          onConfirm={(reason) => void handleReject(reason)}
          onCancel={() => setModal(null)}
          loading={modalLoading}
          labels={rejectLabels}
        />
      ) : null}
    </>
  );
}
