'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { GeoSessionStatusBadge } from './_components/GeoSessionStatusBadge';
import { GeoSessionStatsCards, type DashboardStats } from './_components/GeoSessionStatsCards';
import { ApproveSessionModal } from './_components/ApproveSessionModal';
import { RejectSessionModal } from './_components/RejectSessionModal';

// ─── Types ────────────────────────────────────────────────────────────────────

type GeoSession = {
  id: number | string;
  employee_id: number;
  employee_name?: string;
  employee_matricule?: string;
  check_in_time?: string | null;
  check_out_time?: string | null;
  duration_minutes?: number | null;
  status: string;
};

type DashboardPayload = {
  data?: {
    stats?: DashboardStats;
    sessions_pending?: GeoSession[];
    pending_sessions?: GeoSession[];
  };
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatTime(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes?: number | null): string {
  if (minutes == null) return '—';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}min`;
  return `${h}h${m > 0 ? String(m).padStart(2, '0') : ''}`;
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function TableSkeleton() {
  return (
    <div className="divide-y divide-app-border">
      {[1, 2, 3].map((i) => (
        <div key={i} className="flex items-center gap-4 px-6 py-4 animate-pulse">
          <div className="h-4 w-36 rounded bg-slate-200" />
          <div className="h-4 w-20 rounded bg-slate-200" />
          <div className="h-4 w-20 rounded bg-slate-200" />
          <div className="h-4 w-16 rounded bg-slate-200" />
          <div className="h-6 w-24 rounded-full bg-slate-200" />
          <div className="ml-auto flex gap-2">
            <div className="h-8 w-24 rounded-lg bg-slate-200" />
            <div className="h-8 w-24 rounded-lg bg-slate-200" />
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

type ModalState =
  | { type: 'approve'; session: GeoSession }
  | { type: 'reject'; session: GeoSession }
  | null;

export default function SmartAttendanceDashboardPage() {
  const [stats, setStats] = useState<DashboardStats>({
    total: 0,
    detected: 0,
    pending_validation: 0,
    approved: 0,
    rejected: 0,
  });
  const [pendingSessions, setPendingSessions] = useState<GeoSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modal, setModal] = useState<ModalState>(null);
  const [modalLoading, setModalLoading] = useState(false);

  const load = useCallback(async () => {
    try {
      const response = await apiFetch('/smart-attendance/dashboard');
      const payload = await response.json() as DashboardPayload;
      const d = payload.data;

      setStats(d?.stats ?? { total: 0, detected: 0, pending_validation: 0, approved: 0, rejected: 0 });
      const sessions = d?.sessions_pending ?? d?.pending_sessions ?? [];
      setPendingSessions(sessions);
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger le tableau de bord.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();

    const interval = window.setInterval(() => {
      void load();
    }, 30000);

    return () => window.clearInterval(interval);
  }, [load]);

  const handleApprove = async (note: string) => {
    if (!modal || modal.type !== 'approve') return;
    setModalLoading(true);
    try {
      await apiFetch(`/smart-attendance/sessions/${modal.session.id}/approve`, {
        method: 'POST',
        body: JSON.stringify({ note }),
      });
      setModal(null);
      setLoading(true);
      await load();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : 'Erreur lors de l\'approbation.');
    } finally {
      setModalLoading(false);
    }
  };

  const handleReject = async (reason: string) => {
    if (!modal || modal.type !== 'reject') return;
    setModalLoading(true);
    try {
      await apiFetch(`/smart-attendance/sessions/${modal.session.id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      });
      setModal(null);
      setLoading(true);
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
        title="Smart Attendance"
        subtitle="Suivi intelligent de présence par géolocalisation — validation des sessions en attente et statistiques du jour."
        accentClassName="bg-gradient-to-br from-blue-500/10 via-white to-white"
      >
        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        ) : null}

        {/* Stats */}
        <GeoSessionStatsCards stats={stats} loading={loading} />

        {/* Actions rapides */}
        <div className="flex items-center gap-3">
          <Link
            href="/smart-attendance/sessions"
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 shadow-sm"
          >
            Toutes les sessions →
          </Link>
          <Link
            href="/smart-attendance/settings"
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 shadow-sm"
          >
            ⚙️ Paramètres
          </Link>
        </div>

        {/* Tableau sessions pending */}
        <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
              Sessions en attente de validation
            </h2>
            <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">
              {pendingSessions.length}
            </span>
          </div>

          {loading ? (
            <TableSkeleton />
          ) : pendingSessions.length === 0 ? (
            <div className="px-6 py-10 text-center text-sm text-slate-500">
              Aucune session en attente de validation. ✅
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-app-border bg-slate-50/50">
                    <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Employé</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Arrivée</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Départ</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Durée</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Statut</th>
                    <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-app-border">
                  {pendingSessions.map((session) => (
                    <tr key={session.id} className="group transition-colors hover:bg-slate-50/60">
                      <td className="px-6 py-4">
                        <Link href={`/smart-attendance/sessions/${session.id}`} className="hover:underline">
                          <p className="font-bold text-slate-900">{session.employee_name ?? `Employé #${session.employee_id}`}</p>
                          {session.employee_matricule ? (
                            <p className="text-xs text-slate-500">{session.employee_matricule}</p>
                          ) : null}
                        </Link>
                      </td>
                      <td className="px-4 py-4 text-slate-700">{formatTime(session.check_in_time)}</td>
                      <td className="px-4 py-4 text-slate-700">{formatTime(session.check_out_time)}</td>
                      <td className="px-4 py-4 text-slate-700">{formatDuration(session.duration_minutes)}</td>
                      <td className="px-4 py-4">
                        <GeoSessionStatusBadge status={session.status} />
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => setModal({ type: 'approve', session })}
                            className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-500"
                          >
                            Approuver
                          </button>
                          <button
                            type="button"
                            onClick={() => setModal({ type: 'reject', session })}
                            className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-red-500"
                          >
                            Refuser
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </ModulePageShell>

      {/* Modals */}
      {modal?.type === 'approve' ? (
        <ApproveSessionModal
          employeeName={modal.session.employee_name ?? `Employé #${modal.session.employee_id}`}
          onConfirm={(note) => void handleApprove(note)}
          onCancel={() => setModal(null)}
          loading={modalLoading}
        />
      ) : null}

      {modal?.type === 'reject' ? (
        <RejectSessionModal
          employeeName={modal.session.employee_name ?? `Employé #${modal.session.employee_id}`}
          onConfirm={(reason) => void handleReject(reason)}
          onCancel={() => setModal(null)}
          loading={modalLoading}
        />
      ) : null}
    </>
  );
}
