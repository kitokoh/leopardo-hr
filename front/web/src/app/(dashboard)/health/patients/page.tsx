'use client';

import { useCallback, useEffect, useState } from 'react';
import { Search, Users } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { DataTable, StatusBadge, type Column } from '../_components/health-ui';

/**
 * HC-008 (#7792) — Registre patients (lecture, HC-003).
 *
 * Liste paginée avec recherche nom / n° de dossier / téléphone. Le MRN est
 * généré côté serveur ; la gestion fine (création, archivage) reste portée
 * par l'API — RBAC deny-by-default (direction/accueil gèrent, praticiens
 * et facturation lisent).
 */

type Patient = Record<string, unknown>;

export default function HealthPatientsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState('');

  const load = useCallback(
    async (term: string) => {
      setLoading(true);
      setError(null);
      try {
        const search = term.trim() === '' ? '' : `&q=${encodeURIComponent(term.trim())}`;
        const res = await apiFetch(`/health-manager/patients?per_page=100${search}`);
        if (!res.ok) {
          throw new Error(String(res.status));
        }
        const json = (await res.json()) as { data?: Patient[] };
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
    void load('');
  }, [load]);

  const columns: Column<Patient>[] = [
    {
      key: 'mrn',
      header: t(locale, 'health.patients.mrn'),
      render: (row) => <span className="font-mono text-xs font-bold text-slate-500">{String(row.mrn ?? '—')}</span>,
    },
    {
      key: 'name',
      header: t(locale, 'health.patients.name'),
      render: (row) => (
        <span className="font-bold text-slate-900">
          {[row.first_name, row.last_name].filter(Boolean).map(String).join(' ') || '—'}
        </span>
      ),
    },
    {
      key: 'phone',
      header: t(locale, 'health.patients.phone'),
      render: (row) => <span className="text-slate-500">{String(row.phone ?? '—')}</span>,
    },
    {
      key: 'status',
      header: t(locale, 'health.patients.status'),
      render: (row) => <StatusBadge status={String(row.status ?? 'active')} />,
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'health.patients.title')}
      subtitle={t(locale, 'health.patients.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
      icon={Users}
    >
      <div className="space-y-4">
        <form
          className="flex max-w-md items-center gap-2"
          onSubmit={(event) => {
            event.preventDefault();
            void load(query);
          }}
        >
          <label className="relative grow">
            <span className="sr-only">{t(locale, 'health.common.search')}</span>
            <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t(locale, 'health.patients.searchPlaceholder')}
              className="w-full rounded-xl border border-slate-200 bg-white py-2 pe-3 ps-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            />
          </label>
          <button
            type="submit"
            className="rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-emerald-500/20 hover:from-emerald-600 hover:to-cyan-700"
          >
            {t(locale, 'health.common.search')}
          </button>
        </form>

        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => String(row.id)}
          emptyLabel={t(locale, 'health.patients.empty')}
          loading={loading}
          error={error}
          onRetry={() => void load(query)}
        />
      </div>
    </ModulePageShell>
  );
}
