'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getCopy, getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';
import { GeoSessionStatusBadge } from '../_components/GeoSessionStatusBadge';

const emptySubscribe = () => () => {};

// â”€â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

type Meta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type SessionsPayload = {
  data?: GeoSession[];
  meta?: Meta;
};

type Filters = {
  status: string;
  employee_id: string;
  date_from: string;
  date_to: string;
};

// â”€â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function formatDateTime(iso: string | null | undefined, intlLocale: string): string {
  if (!iso) return 'â€”';
  const d = new Date(iso);
  return d.toLocaleString(intlLocale, {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDuration(minutes?: number | null): string {
  if (minutes == null) return 'â€”';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m}min`;
  return `${h}h${m > 0 ? String(m).padStart(2, '0') : ''}`;
}

function buildQuery(filters: Filters, page: number): string {
  const params = new URLSearchParams({ page: String(page), per_page: '20' });
  if (filters.status) params.set('status', filters.status);
  if (filters.employee_id) params.set('employee_id', filters.employee_id);
  if (filters.date_from) params.set('date_from', filters.date_from);
  if (filters.date_to) params.set('date_to', filters.date_to);
  return params.toString();
}

function sessionsToCSV(
  sessions: GeoSession[],
  labels: ReturnType<typeof getCopy>['smartAttendanceSessionsPage'],
): string {
  const header = [
    labels.csvHeaderId,
    labels.csvHeaderEmployee,
    labels.csvHeaderMatricule,
    labels.csvHeaderCheckIn,
    labels.csvHeaderCheckOut,
    labels.csvHeaderDuration,
    labels.csvHeaderStatus,
  ];
  const rows = sessions.map((s) => [
    String(s.id),
    s.employee_name ?? '',
    s.employee_matricule ?? '',
    s.check_in_time ?? '',
    s.check_out_time ?? '',
    String(s.duration_minutes ?? ''),
    s.status,
  ]);
  return [header, ...rows].map((r) => r.map((c) => `"${c}"`).join(',')).join('\n');
}

// â”€â”€â”€ Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export default function SmartAttendanceSessionsPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).smartAttendanceSessionsPage;
  const statusLabels = getCopy(locale).smartAttendancePage;
  const intlLocale = toIntlLocale(locale);
  const [sessions, setSessions] = useState<GeoSession[]>([]);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<Filters>({
    status: '',
    employee_id: '',
    date_from: '',
    date_to: '',
  });
  const [pendingFilters, setPendingFilters] = useState<Filters>({
    status: '',
    employee_id: '',
    date_from: '',
    date_to: '',
  });

  const load = useCallback(async (currentFilters: Filters, currentPage: number) => {
    setLoading(true);
    try {
      const query = buildQuery(currentFilters, currentPage);
      const response = await apiFetch(`/smart-attendance/sessions?${query}`);
      const payload = await response.json() as SessionsPayload;
      setSessions(payload.data ?? []);
      setMeta(payload.meta ?? null);
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setLoading(false);
    }
  }, [labels.loadError]);

  useEffect(() => {
    void load(filters, page);
  }, [load, filters, page]);

  const applyFilters = () => {
    setFilters(pendingFilters);
    setPage(1);
  };

  const resetFilters = () => {
    const empty: Filters = { status: '', employee_id: '', date_from: '', date_to: '' };
    setPendingFilters(empty);
    setFilters(empty);
    setPage(1);
  };

  const exportCSV = () => {
    const csv = sessionsToCSV(sessions, labels);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `smart-attendance-sessions-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <ModulePageShell
      title={labels.title}
      subtitle={labels.subtitle}
      accentClassName="bg-gradient-to-br from-security/10 via-white to-white"
    >
      {/* Back link */}
      <div>
        <Link
          href="/smart-attendance"
          className="text-sm font-bold text-slate-500 transition hover:text-slate-900"
        >
          {labels.backToDashboard}
        </Link>
      </div>

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {/* Filters */}
      <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
        <h2 className="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">{labels.filtersTitle}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label className="mb-1 block text-xs font-bold text-slate-600">{labels.filterStatus}</label>
            <select
              className="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm text-slate-900 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
              value={pendingFilters.status}
              onChange={(e) => setPendingFilters((f) => ({ ...f, status: e.target.value }))}
            >
              <option value="">{labels.filterStatusAll}</option>
              <option value="detected">{statusLabels.statusDetected}</option>
              <option value="pending_validation">{statusLabels.statusPendingValidation}</option>
              <option value="approved">{statusLabels.statusApproved}</option>
              <option value="rejected">{statusLabels.statusRejected}</option>
              <option value="cancelled">{statusLabels.statusCancelled}</option>
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-bold text-slate-600">{labels.filterEmployee}</label>
            <input
              type="text"
              className="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
              placeholder={labels.filterEmployeePlaceholder}
              value={pendingFilters.employee_id}
              onChange={(e) => setPendingFilters((f) => ({ ...f, employee_id: e.target.value }))}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-bold text-slate-600">{labels.filterDateFrom}</label>
            <input
              type="date"
              className="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm text-slate-900 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
              value={pendingFilters.date_from}
              onChange={(e) => setPendingFilters((f) => ({ ...f, date_from: e.target.value }))}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-bold text-slate-600">{labels.filterDateTo}</label>
            <input
              type="date"
              className="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm text-slate-900 focus:border-security focus:outline-none focus:ring-2 focus:ring-security-light"
              value={pendingFilters.date_to}
              onChange={(e) => setPendingFilters((f) => ({ ...f, date_to: e.target.value }))}
            />
          </div>
        </div>

        <div className="mt-4 flex gap-3">
          <button
            type="button"
            onClick={applyFilters}
            className="rounded-xl bg-security px-4 py-2 text-sm font-bold text-white transition hover:bg-security-dark"
          >
            {labels.apply}
          </button>
          <button
            type="button"
            onClick={resetFilters}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-transparent"
          >
            {labels.reset}
          </button>
          <button
            type="button"
            onClick={exportCSV}
            disabled={sessions.length === 0}
            className="ml-auto rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-50"
          >
            {labels.exportCsv}
          </button>
        </div>
      </section>

      {/* Table */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">{labels.sessionsTitle}</h2>
          {meta ? (
            <span className="text-xs text-slate-500">
              {meta.total} {meta.total > 1 ? labels.sessionCountPlural : labels.sessionCountSingular}
            </span>
          ) : null}
        </div>

        {loading ? (
          <div className="divide-y divide-app-border">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="flex gap-4 px-6 py-4 animate-pulse">
                <div className="h-4 w-40 rounded bg-slate-200" />
                <div className="h-4 w-32 rounded bg-slate-200" />
                <div className="h-4 w-32 rounded bg-slate-200" />
                <div className="h-4 w-16 rounded bg-slate-200" />
                <div className="h-6 w-24 rounded-full bg-slate-200" />
              </div>
            ))}
          </div>
        ) : sessions.length === 0 ? (
          <div className="px-6 py-10 text-center text-sm text-slate-500">
            {labels.noSessions}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-app-border bg-transparent/50">
                  <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnEmployee}</th>
                  <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnCheckIn}</th>
                  <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnCheckOut}</th>
                  <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnDuration}</th>
                  <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnStatus}</th>
                  <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnDetail}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-app-border">
                {sessions.map((session) => (
                  <tr key={session.id} className="transition-colors hover:bg-transparent/60">
                    <td className="px-6 py-4">
                      <p className="font-bold text-slate-900">
                        {session.employee_name ?? `${labels.employeeFallback} #${session.employee_id}`}
                      </p>
                      {session.employee_matricule ? (
                        <p className="text-xs text-slate-500">{session.employee_matricule}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-4 text-slate-700">{formatDateTime(session.check_in_time, intlLocale)}</td>
                    <td className="px-4 py-4 text-slate-700">{formatDateTime(session.check_out_time, intlLocale)}</td>
                    <td className="px-4 py-4 text-slate-700">{formatDuration(session.duration_minutes)}</td>
                    <td className="px-4 py-4">
                      <GeoSessionStatusBadge status={session.status} labels={statusLabels} />
                    </td>
                    <td className="px-6 py-4 text-right">
                      <Link
                        href={`/smart-attendance/sessions/${session.id}`}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:border-security hover:text-security-dark"
                      >
                        {labels.viewDetail}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {meta && meta.last_page > 1 ? (
          <div className="flex items-center justify-between border-t border-app-border px-6 py-4">
            <span className="text-xs text-slate-500">
              {labels.pageLabel} {meta.current_page} / {meta.last_page}
            </span>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-40"
              >
                {labels.previous}
              </button>
              <button
                type="button"
                onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                disabled={page >= meta.last_page}
                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-40"
              >
                {labels.next}
              </button>
            </div>
          </div>
        ) : null}
      </section>
    </ModulePageShell>
  );
}

