'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { GeoSessionStatusBadge } from '../../_components/GeoSessionStatusBadge';
import { ApproveSessionModal } from '../../_components/ApproveSessionModal';
import { RejectSessionModal } from '../../_components/RejectSessionModal';

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

function formatDateTime(iso?: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('fr-FR', {
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
      setError(err instanceof ApiError ? err.message : 'Impossible de charger la session.');
    } finally {
      setLoading(false);
    }
  }, [id]);

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
      alert(err instanceof ApiError ? err.message : 'Erreur lors de l\'approbation.');
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
      alert(err instanceof ApiError ? err.message : 'Erreur lors du refus.');
    } finally {
      setModalLoading(false);
    }
  };

  return (
    <>
      <ModulePageShell
        title="Détail de session"
        subtitle="Informations complètes de la session de présence géolocalisée."
        accentClassName="bg-gradient-to-br from-blue-500/10 via-white to-white"
      >
        {/* Back */}
        <div className="flex items-center gap-4">
          <Link
            href="/smart-attendance/sessions"
            className="text-sm font-bold text-slate-500 transition hover:text-slate-900"
          >
            ← Retour aux sessions
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
            Session introuvable.
          </div>
        ) : (
          <div className="space-y-6">
            {/* Carte employé */}
            <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
              <div className="flex items-center gap-4">
                {session.employee_photo ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={session.employee_photo}
                    alt={session.employee_name ?? ''}
                    className="h-16 w-16 rounded-2xl object-cover"
                  />
                ) : (
                  <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-200 to-slate-300 text-xl font-black text-slate-600">
                    {(session.employee_name ?? 'E').charAt(0).toUpperCase()}
                  </div>
                )}
                <div className="flex-1">
                  <p className="text-xl font-black text-slate-950">
                    {session.employee_name ?? `Employé #${session.employee_id}`}
                  </p>
                  {session.employee_matricule ? (
                    <p className="text-sm text-slate-500">{session.employee_matricule}</p>
                  ) : null}
                </div>
                <GeoSessionStatusBadge status={session.status} />
              </div>

              {/* Notes */}
              {session.note ? (
                <div className="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                  <span className="font-bold">Note : </span>{session.note}
                </div>
              ) : null}
              {session.rejection_reason ? (
                <div className="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                  <span className="font-bold">Raison du refus : </span>{session.rejection_reason}
                </div>
              ) : null}
            </section>

            {/* Timeline */}
            <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Timeline</h2>
              <div className="flex items-start gap-0">
                {/* Check in */}
                <div className="flex flex-col items-center">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 text-lg">▶</div>
                  <div className="mt-2 h-12 w-0.5 bg-slate-200" />
                </div>
                <div className="ml-4 mt-1">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Arrivée détectée</p>
                  <p className="text-lg font-black text-slate-900">{formatDateTime(session.check_in_time)}</p>
                </div>
              </div>

              <div className="flex items-start gap-0 mt-2">
                <div className="flex flex-col items-center">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600 text-lg">■</div>
                </div>
                <div className="ml-4 mt-1">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Départ</p>
                  <p className="text-lg font-black text-slate-900">{formatDateTime(session.check_out_time)}</p>
                  {session.duration_minutes != null ? (
                    <p className="text-sm text-slate-500">Durée : {formatDuration(session.duration_minutes)}</p>
                  ) : null}
                </div>
              </div>
            </section>

            {/* Carte GPS */}
            <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
              <h2 className="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Coordonnées GPS</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                  <p className="text-xs font-bold uppercase tracking-wider text-emerald-600">Check-in</p>
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
                      Voir sur Maps →
                    </a>
                  ) : null}
                </div>

                <div className="rounded-xl border border-red-100 bg-red-50 p-4">
                  <p className="text-xs font-bold uppercase tracking-wider text-red-600">Check-out</p>
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
                      Voir sur Maps →
                    </a>
                  ) : null}
                </div>
              </div>
            </section>

            {/* Événements de localisation */}
            {session.location_events && session.location_events.length > 0 ? (
              <section className="overflow-hidden rounded-2xl border border-app-border bg-white shadow-sm">
                <div className="border-b border-app-border px-6 py-4">
                  <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Historique des événements GPS ({session.location_events.length})
                  </h2>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-app-border bg-slate-50/50">
                        <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Type</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Heure</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Latitude</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Longitude</th>
                        <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Précision (m)</th>
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
                          <td className="px-4 py-3 text-slate-700">{formatDateTime(ev.recorded_at)}</td>
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

            {/* Actions validation */}
            {canValidate ? (
              <section className="rounded-2xl border border-amber-100 bg-amber-50 p-5">
                <p className="mb-3 text-sm font-bold text-amber-800">Cette session est en attente de validation.</p>
                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setModal('approve')}
                    className="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-emerald-500"
                  >
                    ✓ Approuver
                  </button>
                  <button
                    type="button"
                    onClick={() => setModal('reject')}
                    className="rounded-xl bg-red-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-red-500"
                  >
                    ✕ Refuser
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
          employeeName={session.employee_name ?? `Employé #${session.employee_id}`}
          onConfirm={(note) => void handleApprove(note)}
          onCancel={() => setModal(null)}
          loading={modalLoading}
        />
      ) : null}

      {modal === 'reject' && session ? (
        <RejectSessionModal
          employeeName={session.employee_name ?? `Employé #${session.employee_id}`}
          onConfirm={(reason) => void handleReject(reason)}
          onCancel={() => setModal(null)}
          loading={modalLoading}
        />
      ) : null}
    </>
  );
}
