'use client';

import { useCallback, useEffect, useState } from 'react';
import { FileText } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { Card, DataTable, SectionTitle, StatusBadge, type Column } from '../_components/health-ui';

/**
 * HC-008 (#7792) — Référentiel clinique (lecture, HC-002).
 *
 * Services médicaux, salles, lits, spécialités et praticiens du tenant.
 * La gestion (CRUD) est réservée à la direction côté API (deny-by-default) ;
 * cette page donne la vue d'ensemble de la structure.
 */

type Row = Record<string, unknown>;

type Section = {
  key: 'departments' | 'rooms' | 'beds' | 'specialties' | 'practitioners';
  path: string;
};

const SECTIONS: Section[] = [
  { key: 'departments', path: '/health-manager/departments' },
  { key: 'rooms', path: '/health-manager/rooms' },
  { key: 'beds', path: '/health-manager/beds' },
  { key: 'specialties', path: '/health-manager/specialties' },
  { key: 'practitioners', path: '/health-manager/practitioners' },
];

export default function HealthReferentialPage() {
  const locale = getPreferredLocale();
  const [data, setData] = useState<Record<string, Row[]>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const responses = await Promise.all(
        SECTIONS.map((section) =>
          apiFetch(`${section.path}?per_page=200`)
            .then((res) => (res.ok ? res.json() : null))
            .catch(() => null),
        ),
      );
      const next: Record<string, Row[]> = {};
      SECTIONS.forEach((section, index) => {
        const json = responses[index] as { data?: Row[] } | null;
        next[section.key] = Array.isArray(json?.data) ? json.data : [];
      });
      setData(next);
    } catch {
      setError(t(locale, 'health.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const nameOf = (row: Row): string =>
    String(row.name ?? row.display_name ?? row.label ?? row.code ?? '—');

  const columnsFor = (): Column<Row>[] => [
    {
      key: 'name',
      header: t(locale, 'health.referential.name'),
      render: (row) => <span className="font-bold text-slate-900">{nameOf(row)}</span>,
    },
    {
      key: 'code',
      header: t(locale, 'health.referential.code'),
      render: (row) => <span className="font-mono text-xs text-slate-500">{String(row.code ?? row.id ?? '—')}</span>,
    },
    {
      key: 'status',
      header: t(locale, 'health.referential.status'),
      render: (row) => <StatusBadge status={String(row.status ?? 'active')} />,
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'health.referential.title')}
      subtitle={t(locale, 'health.referential.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
      icon={FileText}
    >
      <div className="space-y-6">
        {SECTIONS.map((section) => (
          <Card key={section.key}>
            <SectionTitle title={t(locale, `health.referential.${section.key}`)} />
            <DataTable
              columns={columnsFor()}
              rows={data[section.key] ?? []}
              rowKey={(row) => String(row.id)}
              emptyLabel={t(locale, 'health.referential.empty')}
              loading={loading}
              error={error}
              onRetry={() => void load()}
            />
          </Card>
        ))}
      </div>
    </ModulePageShell>
  );
}
