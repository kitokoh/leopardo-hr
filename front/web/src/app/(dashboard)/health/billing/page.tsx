'use client';

import { useCallback, useEffect, useState } from 'react';
import { FileText, Hourglass, Wallet } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { DataTable, ErrorState, StatCard, StatusBadge, type Column } from '../_components/health-ui';

/**
 * HC-008 (#7792) — Facturation des soins (lecture, HC-007).
 *
 * Factures de soins + indicateurs (CA encaissé du mois, impayés). Surface
 * réservée à `health.billing` et `health.admin` : un 403 de l'API affiche
 * un message dédié (la garde réelle est l'API, deny-by-default).
 */

type Invoice = Record<string, unknown>;

type BillingStats = {
  month_revenue: string;
  outstanding_total: string;
  outstanding_count: number;
};

export default function HealthBillingPage() {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Invoice[]>([]);
  const [stats, setStats] = useState<BillingStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [forbidden, setForbidden] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    setForbidden(false);
    try {
      const [invoicesRes, statsRes] = await Promise.all([
        apiFetch('/health-manager/invoices?per_page=100'),
        apiFetch('/health-manager/billing/stats'),
      ]);
      if (invoicesRes.status === 403 || statsRes.status === 403) {
        setForbidden(true);
        return;
      }
      if (!invoicesRes.ok || !statsRes.ok) {
        throw new Error('http');
      }
      const invoices = (await invoicesRes.json()) as { data?: Invoice[] };
      const indicators = (await statsRes.json()) as { data?: BillingStats };
      setRows(Array.isArray(invoices.data) ? invoices.data : []);
      setStats(indicators.data ?? null);
    } catch {
      setError(t(locale, 'health.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const columns: Column<Invoice>[] = [
    {
      key: 'number',
      header: t(locale, 'health.billing.number'),
      render: (row) => (
        <span className="font-mono text-xs font-bold text-slate-500">
          {typeof row.number === 'string' && row.number !== '' ? row.number : t(locale, 'health.billing.draft')}
        </span>
      ),
    },
    {
      key: 'patient_id',
      header: t(locale, 'health.billing.patient'),
      render: (row) => <span className="font-mono text-xs text-slate-500">#{String(row.patient_id ?? '—')}</span>,
    },
    {
      key: 'total',
      header: t(locale, 'health.billing.total'),
      render: (row) => <span className="font-bold text-slate-900">{String(row.total ?? '—')}</span>,
    },
    {
      key: 'amount_paid',
      header: t(locale, 'health.billing.paid'),
      render: (row) => <span className="text-slate-700">{String(row.amount_paid ?? '—')}</span>,
    },
    {
      key: 'balance',
      header: t(locale, 'health.billing.balance'),
      render: (row) => <span className="text-slate-700">{String(row.balance ?? '—')}</span>,
    },
    {
      key: 'status',
      header: t(locale, 'health.billing.status'),
      render: (row) => <StatusBadge status={String(row.status ?? 'draft')} />,
    },
  ];

  return (
    <ModulePageShell
      title={t(locale, 'health.billing.title')}
      subtitle={t(locale, 'health.billing.subtitle')}
      accentClassName="border-emerald-500/10 bg-emerald-500/5"
      icon={Wallet}
    >
      {forbidden ? (
        <ErrorState message={t(locale, 'health.billing.forbidden')} />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard
              icon={Wallet}
              label={t(locale, 'health.billing.monthRevenue')}
              value={stats?.month_revenue ?? '–'}
            />
            <StatCard
              icon={Hourglass}
              label={t(locale, 'health.billing.outstandingTotal')}
              value={stats?.outstanding_total ?? '–'}
            />
            <StatCard
              icon={FileText}
              label={t(locale, 'health.billing.outstandingCount')}
              value={stats ? String(stats.outstanding_count) : '–'}
            />
          </div>

          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(row) => String(row.id)}
            emptyLabel={t(locale, 'health.billing.empty')}
            loading={loading}
            error={error}
            onRetry={() => void load()}
          />
        </div>
      )}
    </ModulePageShell>
  );
}
