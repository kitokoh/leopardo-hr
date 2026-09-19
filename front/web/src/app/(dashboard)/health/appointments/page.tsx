'use client';

import { useCallback, useEffect, useState } from 'react';
import { CalendarClock } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { DataTable, StatusBadge, type Column } from '../_components/health-ui';

/**
 * HC-008 (#7792) — Rendez-vous (lecture, HC-004).
 *
 * Liste des rendez-vous du tenant avec bascule « aujourd'hui / tous ».
 * RBAC porté par l'API : direction et accueil voient tout, un praticien
 * actif ne voit que SON agenda (le filtrage est fait côté serveur).
 */

type Appointment = Record<string, unknown>;

function formatDateTime(locale: AppLocale, value: unknown): string {
  if (typeof value !== 'string' || value === '') {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export default function HealthAppointmentsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [todayOnly, setTodayOnly] = useState(true);

  const load = useCallback(
    async (restrictToToday: boolean) => {
      setLoading(true);
      setError(null);
      try {
        let range = '';
        if (restrictToToday) {
          const startOfDay = new Date();
          startOfDay.setHours(0, 0, 0, 0);
          const endOfDay = new Date();
          endOfDay.setHours(23, 59, 59, 999);
          range = `&from=${encodeURIComponent(startOfDay.toISOString())}&to=${encodeURIComponent(endOfDay.toISOString())}`;
        }
        const res = await apiFetch(`/health-manager/appointments?per_page=100${range}`);
        if (!res.ok) {
          throw new Error(String(res.status));
        }
        const json = (await res.json()) as { data?: Appointment[] };
        setRows(Array.isArray(json.data) ? json.data : []);
      } catch {
        setError(t(locale, 'health.common.error'));
      } finally {
        setLoading(false);
      }
    },
    [locale],
  );

  useEffect(() => {
    void load(todayOnly);
  }, [load, todayOnly]);

  const columns: Column<Appointment>[] = [
    {
      key: 'starts_at',
      header: t(locale, 'health.appointments.startsAt'),
      render: (row) => <span className="font-bold text-slate-900">{formatDateTime(locale, row.starts_at)}</span>,
    },
    {
      key: 'ends_at',
      header: t(locale, 'health.appointments.endsAt'),
      render: (row) => <span className="text-slate-500">{formatDateTime(locale, row.ends_at)}</span>,
    },
    {
      key: 'patient_id',
      header: t(locale, 'health.appointments.patient'),
      render: (row) => <span className="font-mono text-xs text-slate-500">#{String(row.patient_id ?? '—')}</span>,
    },
    {
      key: 'practitioner_id',
      header: t(locale, 'health.appointments.practitioner'),
      render: (row) => <span className="font-mono text-xs text-slate-500">#{String(row.practitioner_id ?? '—')}</span>,
    },
    {
      key: 'reason',
      header: t(locale, 'health.appointments.reason'),
      render: (row) => <span className="text-slate-700">{String(row.reason ?? '—')}</span>,
    },
    {
      key: 'status',
      header: t(locale, 'health.appointments.status'),
      render: (row) => <StatusBadge status={String(row.status ?? 'scheduled')} />,
    },
  ];

  const toggleBase =
    'rounded-xl px-4 py-2 text-sm font-bold transition-colors';

  return (
    <ModulePageShell
      title={t(locale, 'health.appointments.title')}
      subtitle={t(locale, 'health.appointments.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
      icon={CalendarClock}
    >
      <div className="space-y-4">
        <div className="inline-flex gap-1 rounded-2xl border border-slate-200 bg-white/70 p-1" role="group">
          <button
            type="button"
            onClick={() => setTodayOnly(true)}
            aria-pressed={todayOnly}
            className={`${toggleBase} ${todayOnly ? 'bg-gradient-to-r from-emerald-500 to-cyan-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            {t(locale, 'health.appointments.todayOnly')}
          </button>
          <button
            type="button"
            onClick={() => setTodayOnly(false)}
            aria-pressed={!todayOnly}
            className={`${toggleBase} ${!todayOnly ? 'bg-gradient-to-r from-emerald-500 to-cyan-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            {t(locale, 'health.appointments.all')}
          </button>
        </div>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => String(row.id)}
          emptyLabel={t(locale, 'health.appointments.empty')}
          loading={loading}
          error={error}
          onRetry={() => void load(todayOnly)}
        />
      </div>
    </ModulePageShell>
  );
}
