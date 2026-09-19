'use client';

import { useCallback, useEffect, useState } from 'react';
import { BedDouble } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { Card, DataTable, SectionTitle, StatusBadge, type Column } from '../_components/health-ui';

/**
 * HC-008 (#7792) — Hospitalisations & occupation des lits (lecture, HC-006).
 *
 * Séjours du tenant + tableau d'occupation par service (état RÉEL des
 * lits, calculé côté API). Admission/transfert/sortie restent des gestes
 * API transactionnels — RBAC deny-by-default (direction/accueil gèrent,
 * praticiens actifs consultent).
 */

type Admission = Record<string, unknown>;

type OccupancyRow = {
  department_id: number;
  department_name: string;
  total_beds: number;
  occupied_beds: number;
  free_beds: number;
  maintenance_beds: number;
  occupancy_rate: number;
};

function formatDate(locale: AppLocale, value: unknown): string {
  if (typeof value !== 'string' || value === '') {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export default function HealthAdmissionsPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Admission[]>([]);
  const [occupancy, setOccupancy] = useState<OccupancyRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [admissionsRes, occupancyRes] = await Promise.all([
        apiFetch('/health-manager/admissions?per_page=100'),
        apiFetch('/health-manager/occupancy'),
      ]);
      if (!admissionsRes.ok || !occupancyRes.ok) {
        throw new Error('http');
      }
      const admissions = (await admissionsRes.json()) as { data?: Admission[] };
      const beds = (await occupancyRes.json()) as { data?: OccupancyRow[] };
      setRows(Array.isArray(admissions.data) ? admissions.data : []);
      setOccupancy(Array.isArray(beds.data) ? beds.data : []);
    } catch {
      setError(t(locale, 'health.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const admissionColumns: Column<Admission>[] = [
    {
      key: 'patient_id',
      header: t(locale, 'health.admissions.patient'),
      render: (row) => <span className="font-mono text-xs text-slate-500">#{String(row.patient_id ?? '—')}</span>,
    },
    {
      key: 'bed_id',
      header: t(locale, 'health.admissions.bed'),
      render: (row) => <span className="font-mono text-xs text-slate-500">#{String(row.bed_id ?? '—')}</span>,
    },
    {
      key: 'department_id',
      header: t(locale, 'health.admissions.department'),
      render: (row) => <span className="font-mono text-xs text-slate-500">#{String(row.department_id ?? '—')}</span>,
    },
    {
      key: 'admitted_at',
      header: t(locale, 'health.admissions.admittedAt'),
      render: (row) => <span className="font-bold text-slate-900">{formatDate(locale, row.admitted_at)}</span>,
    },
    {
      key: 'status',
      header: t(locale, 'health.admissions.status'),
      render: (row) => <StatusBadge status={String(row.status ?? 'admitted')} />,
    },
  ];

  const occupancyColumns: Column<OccupancyRow>[] = [
    {
      key: 'department_name',
      header: t(locale, 'health.admissions.department'),
      render: (row) => <span className="font-bold text-slate-900">{row.department_name}</span>,
    },
    {
      key: 'total_beds',
      header: t(locale, 'health.admissions.totalBeds'),
      render: (row) => <span className="text-slate-700">{row.total_beds}</span>,
    },
    {
      key: 'occupied_beds',
      header: t(locale, 'health.admissions.occupiedBeds'),
      render: (row) => <span className="text-slate-700">{row.occupied_beds}</span>,
    },
    {
      key: 'free_beds',
      header: t(locale, 'health.admissions.freeBeds'),
      render: (row) => <span className="text-slate-700">{row.free_beds}</span>,
    },
    {
      key: 'maintenance_beds',
      header: t(locale, 'health.admissions.maintenanceBeds'),
      render: (row) => <span className="text-slate-700">{row.maintenance_beds}</span>,
    },
    {
      key: 'occupancy_rate',
      header: t(locale, 'health.admissions.rate'),
      render: (row) => <span className="font-bold text-slate-900">{Math.round(row.occupancy_rate * 100)}%</span>,
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'health.admissions.title')}
      subtitle={t(locale, 'health.admissions.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
      icon={BedDouble}
    >
      <div className="space-y-6">
        <DataTable
          columns={admissionColumns}
          rows={rows}
          rowKey={(row) => String(row.id)}
          emptyLabel={t(locale, 'health.admissions.empty')}
          loading={loading}
          error={error}
          onRetry={() => void load()}
        />

        <Card>
          <SectionTitle
            title={t(locale, 'health.admissions.occupancyTitle')}
            subtitle={t(locale, 'health.admissions.occupancySubtitle')}
          />
          <DataTable
            columns={occupancyColumns}
            rows={occupancy}
            rowKey={(row) => String(row.department_id)}
            emptyLabel={t(locale, 'health.common.noData')}
            loading={loading}
            error={error}
            onRetry={() => void load()}
          />
        </Card>
      </div>
    </ModulePageShell>
  );
}
