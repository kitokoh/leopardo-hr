'use client';

/**
 * Rapports de direction — /edu-manager/reports (#9789).
 *
 * Les 4 endpoints agrégés existaient (EDU-018, #5834) mais aucune page ne les
 * servait : la direction ne pouvait consulter ni la présence, ni les
 * inscriptions, ni les résultats, ni l'occupation des classes depuis
 * l'interface.
 *
 * Chaque rapport est chargé indépendamment : une erreur sur l'un n'empêche pas
 * les autres de s'afficher (états chargement / vide / erreur par bloc).
 */

import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Card, DataTable, SectionTitle, type Column } from '../_components/edu-ui';

type ReportKind = 'presence' | 'enrollment' | 'results' | 'capacity';

type ReportState = {
  rows: Record<string, unknown>[];
  loading: boolean;
  error: string | null;
};

const REPORTS: { kind: ReportKind; titleKey: string }[] = [
  { kind: 'presence', titleKey: 'edu.reports.presence' },
  { kind: 'enrollment', titleKey: 'edu.reports.enrollment' },
  { kind: 'results', titleKey: 'edu.reports.results' },
  { kind: 'capacity', titleKey: 'edu.reports.capacity' },
];

const EMPTY: ReportState = { rows: [], loading: true, error: null };

/**
 * Colonnes dérivées des données : les rapports sont agrégés et leur forme peut
 * évoluer côté serveur — on affiche les clés réellement reçues plutôt que de
 * figer un schéma qui casserait au premier ajout de champ.
 */
function columnsFor(rows: Record<string, unknown>[]): Column<Record<string, unknown>>[] {
  const first = rows[0];
  if (!first) return [];

  return Object.keys(first).map((key) => ({
    key,
    header: key.replace(/_/g, ' '),
    render: (row) => {
      const value = row[key];
      if (value === null || value === undefined) {
        return <span className="text-slate-400">—</span>;
      }
      if (typeof value === 'object') {
        return <span className="text-slate-500">{JSON.stringify(value)}</span>;
      }
      return <span className="tabular-nums text-slate-700">{String(value)}</span>;
    },
  }));
}

export default function ReportsPage() {
  const locale = getPreferredLocale();
  const [states, setStates] = useState<Record<ReportKind, ReportState>>({
    presence: EMPTY,
    enrollment: EMPTY,
    results: EMPTY,
    capacity: EMPTY,
  });

  const loadOne = useCallback(
    async (kind: ReportKind) => {
      setStates((prev) => ({ ...prev, [kind]: { ...prev[kind], loading: true, error: null } }));
      try {
        const res = await apiFetch(`/edu-manager/reports/${kind}`, { _cacheBust: true });
        const json = (await res.json()) as { data?: Record<string, unknown>[] };
        setStates((prev) => ({
          ...prev,
          [kind]: {
            rows: Array.isArray(json.data) ? json.data : [],
            loading: false,
            error: null,
          },
        }));
      } catch {
        setStates((prev) => ({
          ...prev,
          [kind]: { rows: [], loading: false, error: t(locale, 'edu.common.error') },
        }));
      }
    },
    [locale],
  );

  useEffect(() => {
    for (const report of REPORTS) {
      void loadOne(report.kind);
    }
  }, [loadOne]);

  return (
    <ModulePageShell
      title={t(locale, 'edu.reports.title')}
      subtitle={t(locale, 'edu.reports.subtitle')}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="space-y-6">
        {REPORTS.map((report) => {
          const state = states[report.kind];
          return (
            <Card key={report.kind}>
              <SectionTitle title={t(locale, report.titleKey)} />
              <DataTable
                columns={columnsFor(state.rows)}
                rows={state.rows}
                rowKey={(row) => String(row.id ?? JSON.stringify(row))}
                emptyLabel={t(locale, 'edu.reports.empty')}
                loading={state.loading}
                error={state.error}
                onRetry={() => void loadOne(report.kind)}
              />
            </Card>
          );
        })}
      </div>
    </ModulePageShell>
  );
}
