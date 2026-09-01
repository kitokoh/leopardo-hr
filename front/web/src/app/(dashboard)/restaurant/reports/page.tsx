'use client';

/**
 * RESTO-706 (#6219) / RESTO-703 (#6216) — UI admin : écran rapports.
 * Ventes, occupation, top produits, COGS, clôtures de caisse + export CSV
 * idempotent (URL signée) — API `/restaurant/reports/*` + `/restaurant/dashboard/kpis`.
 */
import { useCallback, useEffect, useState } from 'react';
import { ChartColumn, Download } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Report = { period: { from: string; to: string }; report: Record<string, unknown> };

export default function RestaurantReportsPage() {
  const locale = getPreferredLocale();
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [active, setActive] = useState<'sales' | 'occupancy' | 'products' | 'cogs' | 'pos'>('sales');
  const [report, setReport] = useState<Report | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [exporting, setExporting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams();
      if (from) params.set('from', from);
      if (to) params.set('to', to);
      const qs = params.toString();
      const res = await apiFetch(`/restaurant/reports/${active}${qs ? `?${qs}` : ''}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = await res.json();
      setReport((payload as { data?: Report }).data ?? null);
    } catch {
      setError(t(locale, 'restaurant.reports.loadError', 'Impossible de charger le rapport.'));
    } finally {
      setLoading(false);
    }
  }, [active, from, to, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const exportCsv = async () => {
    setExporting(true);
    setError('');
    try {
      const res = await apiFetch('/restaurant/reports/export', {
        method: 'POST',
        body: JSON.stringify({ report_type: active, from: from || undefined, to: to || undefined }),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: { signed_url?: string } };
      const url = payload.data?.signed_url;
      if (url) window.open(url, '_blank');
    } catch {
      setError(t(locale, 'restaurant.reports.exportError', 'Erreur lors de l\'export CSV.'));
    } finally {
      setExporting(false);
    }
  };

  const tabs = [
    { key: 'sales' as const, label: t(locale, 'restaurant.reports.tabSales', 'Ventes') },
    { key: 'occupancy' as const, label: t(locale, 'restaurant.reports.tabOccupancy', 'Occupation') },
    { key: 'products' as const, label: t(locale, 'restaurant.reports.tabProducts', 'Produits') },
    { key: 'cogs' as const, label: t(locale, 'restaurant.reports.tabCogs', 'COGS') },
    { key: 'pos' as const, label: t(locale, 'restaurant.reports.tabPos', 'Caisses') },
  ];

  const entries = report ? Object.entries(report.report) : [];

  return (
    <ModulePageShell icon={ChartColumn} title={t(locale, 'restaurant.reports.title', 'Rapports')} description={t(locale, 'restaurant.reports.subtitle', 'Ventes, occupation, produits, COGS, caisses et export CSV')}>
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      <div className="flex flex-wrap items-center gap-2">
        {tabs.map((tb) => (
          <button key={tb.key} type="button" onClick={() => setActive(tb.key)} className={`rounded-lg px-3 py-1.5 text-sm font-medium ${active === tb.key ? 'bg-emerald-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}>
            {tb.label}
          </button>
        ))}
        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" aria-label="Du" />
        <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" aria-label="Au" />
        <button type="button" onClick={() => void exportCsv()} disabled={exporting} className="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
          <Download className="h-4 w-4" /> {t(locale, 'restaurant.reports.export', 'Export CSV')}
        </button>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        {loading ? (
          <p className="py-8 text-center text-slate-500">{t(locale, 'restaurant.reports.loading', 'Chargement...')}</p>
        ) : entries.length === 0 ? (
          <p className="py-8 text-center text-slate-500">{t(locale, 'restaurant.reports.empty', 'Aucune donnée pour cette période.')}</p>
        ) : (
          <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {entries.map(([key, value]) => (
              <div key={key} className="rounded-xl bg-slate-50 p-4">
                <dt className="text-sm text-slate-500">{key}</dt>
                <dd className="mt-1 text-xl font-bold text-slate-900">{typeof value === 'object' ? JSON.stringify(value) : String(value)}</dd>
              </div>
            ))}
          </dl>
        )}
      </div>
    </ModulePageShell>
  );
}
