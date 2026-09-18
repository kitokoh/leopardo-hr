'use client';

/**
 * TravelManager (BC-24, #7637) — rapports du gérant : ventes, occupation,
 * recettes et annulations sur période (`GET /travel/reports/{type}` —
 * `TravelReportRequest` : `from`/`to` REQUIS), et export CSV asynchrone
 * idempotent (`POST /travel/reports/export` → asset 202, puis polling
 * `GET /travel/reports/export/{asset}` jusqu'à `generated` → `signed_url`
 * éphémère rendue en lien de téléchargement).
 *
 * Gardes backend reflétées :
 * - montants TOUJOURS en minor units, recalculés serveur ;
 * - `StoreTravelExportRequest` n'allowliste QUE `report_type: sales` —
 *   le bouton d'export n'est proposé que sur l'onglet ventes ;
 * - `idempotency_key` obligatoire (générée client via `crypto.randomUUID()`,
 *   nouvelle clé par demande — le rejeu d'une même clé renvoie le même asset).
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { ChartColumn, Download } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { readApiError } from '@/components/travel/TravelCrudTable';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type ReportTab = 'sales' | 'occupancy' | 'revenue' | 'cancellations';

/** TravelReportService::sales — ventes nettes (annulées/remboursées exclues). */
type SalesReport = {
  bookings_count: number;
  passengers_count: number;
  revenue_minor: number;
  by_source: Record<string, number>;
  by_status: Record<string, number>;
};

/** TravelReportService::occupancy — taux par trajet, trié décroissant côté serveur. */
type OccupancyReport = {
  trips_count: number;
  by_trip: Array<{
    trip_id: number;
    code: string;
    route_id: number;
    departure_date: string;
    seats_sold: number;
    total_seats: number;
    occupancy_rate: number;
  }>;
};

/** TravelReportService::revenue — paiements confirmés − remboursés. */
type RevenueReport = {
  confirmed_minor: number;
  refunded_minor: number;
  net_minor: number;
  by_status: Record<string, number>;
};

/** TravelReportService::cancellations — réservations annulées sur période. */
type CancellationsReport = {
  cancellations_count: number;
  passengers_count: number;
  amount_minor: number;
  by_source: Record<string, number>;
};

type LoadedReport =
  | { tab: 'sales'; data: SalesReport }
  | { tab: 'occupancy'; data: OccupancyReport }
  | { tab: 'revenue'; data: RevenueReport }
  | { tab: 'cancellations'; data: CancellationsReport };

/** TravelExportAssetResource — `signed_url` présent uniquement si `generated`. */
type ExportAsset = {
  id: number;
  report_type: string;
  status: 'pending' | 'generated' | 'failed';
  error: string | null;
  signed_url?: string;
};

const EXPORT_POLL_INTERVAL_MS = 1500;
const EXPORT_POLL_MAX_ATTEMPTS = 20;

function toDateInput(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function formatMinor(locale: AppLocale, amountMinor: number): string {
  return amountMinor.toLocaleString(locale);
}

function formatPercent(locale: AppLocale, ratio: number): string {
  return `${(ratio * 100).toLocaleString(locale, { maximumFractionDigits: 1 })}%`;
}

function KpiCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl bg-slate-50 p-4">
      <dt className="text-sm text-slate-500">{label}</dt>
      <dd className="mt-1 text-xl font-bold text-slate-900">{value}</dd>
    </div>
  );
}

function BreakdownList({
  title,
  entries,
  labelFor,
  valueFor,
}: {
  title: string;
  entries: Record<string, number>;
  labelFor: (key: string) => string;
  valueFor: (value: number) => string;
}) {
  const items = Object.entries(entries);
  if (items.length === 0) return null;
  return (
    <div className="rounded-xl border border-slate-200 p-4">
      <h3 className="text-sm font-semibold text-slate-700">{title}</h3>
      <ul className="mt-2 space-y-1">
        {items.map(([key, value]) => (
          <li key={key} className="flex items-center justify-between text-sm">
            <span className="text-slate-600">{labelFor(key)}</span>
            <span className="font-medium text-slate-900">{valueFor(value)}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

export default function TravelReportsPage() {
  const locale = getPreferredLocale();
  const [from, setFrom] = useState(() => toDateInput(new Date(Date.now() - 30 * 86_400_000)));
  const [to, setTo] = useState(() => toDateInput(new Date()));
  const [active, setActive] = useState<ReportTab>('sales');
  const [report, setReport] = useState<LoadedReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState('');
  const [downloadUrl, setDownloadUrl] = useState('');
  const exportRunRef = useRef(0);

  const load = useCallback(async () => {
    if (!from || !to) return; // `from`/`to` requis par TravelReportRequest
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ from, to });
      const res = await apiFetch(`/travel/reports/${active}?${params.toString()}`);
      if (!res.ok) throw new Error((await readApiError(res)) ?? '');
      const payload = (await res.json()) as { data?: LoadedReport['data'] };
      if (!payload.data) {
        setReport(null);
      } else {
        setReport({ tab: active, data: payload.data } as LoadedReport);
      }
    } catch (err) {
      setReport(null);
      setError(
        err instanceof Error && err.message
          ? err.message
          : t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'),
      );
    } finally {
      setLoading(false);
    }
  }, [active, from, to, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  /**
   * Export asynchrone : 202 + asset, puis polling du même asset jusqu'à
   * `generated` (URL signée) ou `failed`. `exportRunRef` invalide les
   * pollings obsolètes si l'utilisateur relance un export.
   */
  const exportCsv = async () => {
    const run = ++exportRunRef.current;
    setExporting(true);
    setExportError('');
    setDownloadUrl('');
    try {
      const res = await apiFetch('/travel/reports/export', {
        method: 'POST',
        body: JSON.stringify({
          report_type: 'sales',
          from,
          to,
          idempotency_key: crypto.randomUUID(),
        }),
      });
      if (!res.ok) throw new Error((await readApiError(res)) ?? '');
      const payload = (await res.json()) as { data?: ExportAsset };
      let asset = payload.data ?? null;
      let attempts = 0;

      while (asset && asset.status === 'pending' && attempts < EXPORT_POLL_MAX_ATTEMPTS) {
        await new Promise((resolve) => setTimeout(resolve, EXPORT_POLL_INTERVAL_MS));
        if (exportRunRef.current !== run) return; // export relancé entre-temps
        attempts += 1;
        const poll = await apiFetch(`/travel/reports/export/${asset.id}`);
        if (!poll.ok) throw new Error((await readApiError(poll)) ?? '');
        asset = ((await poll.json()) as { data?: ExportAsset }).data ?? null;
      }

      if (exportRunRef.current !== run) return;
      if (asset?.status === 'generated' && asset.signed_url) {
        setDownloadUrl(asset.signed_url);
      } else {
        throw new Error(asset?.error ?? '');
      }
    } catch (err) {
      if (exportRunRef.current !== run) return;
      setExportError(
        err instanceof Error && err.message
          ? err.message
          : t(locale, 'travel.reports.exportError', 'Export impossible.'),
      );
    } finally {
      if (exportRunRef.current === run) setExporting(false);
    }
  };

  const tabs: Array<{ key: ReportTab; label: string }> = [
    { key: 'sales', label: t(locale, 'travel.reports.sales', 'Ventes') },
    { key: 'occupancy', label: t(locale, 'travel.reports.occupancy', 'Occupation') },
    { key: 'revenue', label: t(locale, 'travel.reports.revenue', 'Recettes') },
    { key: 'cancellations', label: t(locale, 'travel.reports.cancellations', 'Annulations') },
  ];

  const sourceLabel = (key: string) => t(locale, `travel.bookingSource.${key}`, key);
  const bookingStatusLabel = (key: string) => t(locale, `travel.bookingStatus.${key}`, key);
  const paymentStatusLabel = (key: string) => t(locale, `travel.paymentStatus.${key}`, key);
  const asCount = (value: number) => value.toLocaleString(locale);
  const asMinor = (value: number) => formatMinor(locale, value);

  const current = report && report.tab === active ? report : null;
  const isEmpty =
    current !== null &&
    ((current.tab === 'sales' && current.data.bookings_count === 0) ||
      (current.tab === 'occupancy' && current.data.by_trip.length === 0) ||
      (current.tab === 'revenue' && Object.keys(current.data.by_status).length === 0) ||
      (current.tab === 'cancellations' && current.data.cancellations_count === 0));

  return (
    <ModulePageShell
      icon={ChartColumn}
      title={t(locale, 'travel.reports.tabsLabel', 'Rapports')}
      description={t(
        locale,
        'travel.reports.subtitle',
        'Ventes, occupation, recettes et annulations sur période, export CSV asynchrone.',
      )}
    >
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}
      {exportError ? (
        <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{exportError}</p>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setActive(tab.key)}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
              active === tab.key
                ? 'bg-cyan-700 text-white'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
            }`}
          >
            {tab.label}
          </button>
        ))}
        <input
          type="date"
          value={from}
          max={to}
          onChange={(e) => setFrom(e.target.value)}
          className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
          aria-label={t(locale, 'travel.bookings.from', 'Du')}
        />
        <input
          type="date"
          value={to}
          min={from}
          onChange={(e) => setTo(e.target.value)}
          className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
          aria-label={t(locale, 'travel.bookings.to', 'Au')}
        />
        {active === 'sales' ? (
          // StoreTravelExportRequest n'accepte que report_type=sales (allowlist).
          <button
            type="button"
            onClick={() => void exportCsv()}
            disabled={exporting}
            className="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
          >
            <Download className="h-4 w-4" />
            {exporting
              ? t(locale, 'travel.reports.exportPending', 'Export en cours…')
              : t(locale, 'travel.reports.exportCsv', 'Exporter CSV')}
          </button>
        ) : null}
        {downloadUrl ? (
          <a
            href={downloadUrl}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 rounded-lg border border-cyan-600 px-3 py-1.5 text-sm font-medium text-cyan-700 hover:bg-cyan-50"
          >
            <Download className="h-4 w-4" />
            {t(locale, 'travel.reports.download', 'Télécharger le CSV')}
          </a>
        ) : null}
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        {loading ? (
          <p className="py-8 text-center text-slate-500">{t(locale, 'travel.loading', 'Chargement…')}</p>
        ) : !current || isEmpty ? (
          <p className="py-8 text-center text-slate-500">
            {t(locale, 'travel.reports.empty', 'Aucune donnée sur la période.')}
          </p>
        ) : current.tab === 'sales' ? (
          <div className="space-y-4">
            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <KpiCard
                label={t(locale, 'travel.bookings.title', 'Réservations')}
                value={asCount(current.data.bookings_count)}
              />
              <KpiCard
                label={t(locale, 'travel.bookings.passengers', 'Passagers')}
                value={asCount(current.data.passengers_count)}
              />
              <KpiCard
                label={t(locale, 'travel.home.kpiRevenue', 'Recette nette')}
                value={asMinor(current.data.revenue_minor)}
              />
            </dl>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <BreakdownList
                title={t(locale, 'travel.reports.bySource', 'Par canal')}
                entries={current.data.by_source}
                labelFor={sourceLabel}
                valueFor={asCount}
              />
              <BreakdownList
                title={t(locale, 'travel.reports.byStatus', 'Par statut')}
                entries={current.data.by_status}
                labelFor={bookingStatusLabel}
                valueFor={asCount}
              />
            </div>
          </div>
        ) : current.tab === 'occupancy' ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-slate-500">
                  <th className="px-3 py-2 font-medium">{t(locale, 'travel.field.code', 'Code')}</th>
                  <th className="px-3 py-2 font-medium">
                    {t(locale, 'travel.field.departureDate', 'Départ')}
                  </th>
                  <th className="px-3 py-2 font-medium">
                    {t(locale, 'travel.reports.seatsSold', 'Places vendues')}
                  </th>
                  <th className="px-3 py-2 font-medium">{t(locale, 'travel.field.totalSeats', 'Places')}</th>
                  <th className="px-3 py-2 font-medium">
                    {t(locale, 'travel.reports.occupancyRate', "Taux d'occupation")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {current.data.by_trip.map((row) => (
                  <tr key={row.trip_id} className="border-b border-slate-100">
                    <td className="px-3 py-2 font-medium text-slate-900">{row.code}</td>
                    <td className="px-3 py-2 text-slate-600">{row.departure_date}</td>
                    <td className="px-3 py-2 text-slate-600">{asCount(row.seats_sold)}</td>
                    <td className="px-3 py-2 text-slate-600">{asCount(row.total_seats)}</td>
                    <td className="px-3 py-2 font-medium text-slate-900">
                      {formatPercent(locale, row.occupancy_rate)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : current.tab === 'revenue' ? (
          <div className="space-y-4">
            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <KpiCard
                label={t(locale, 'travel.reports.confirmedAmount', 'Encaissé')}
                value={asMinor(current.data.confirmed_minor)}
              />
              <KpiCard
                label={t(locale, 'travel.reports.refundedAmount', 'Remboursé')}
                value={asMinor(current.data.refunded_minor)}
              />
              <KpiCard
                label={t(locale, 'travel.home.kpiRevenue', 'Recette nette')}
                value={asMinor(current.data.net_minor)}
              />
            </dl>
            <BreakdownList
              title={t(locale, 'travel.reports.byStatus', 'Par statut')}
              entries={current.data.by_status}
              labelFor={paymentStatusLabel}
              valueFor={asMinor}
            />
          </div>
        ) : (
          <div className="space-y-4">
            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <KpiCard
                label={t(locale, 'travel.reports.cancellations', 'Annulations')}
                value={asCount(current.data.cancellations_count)}
              />
              <KpiCard
                label={t(locale, 'travel.bookings.passengers', 'Passagers')}
                value={asCount(current.data.passengers_count)}
              />
              <KpiCard
                label={t(locale, 'travel.field.amount', 'Montant')}
                value={asMinor(current.data.amount_minor)}
              />
            </dl>
            <BreakdownList
              title={t(locale, 'travel.reports.bySource', 'Par canal')}
              entries={current.data.by_source}
              labelFor={sourceLabel}
              valueFor={asCount}
            />
          </div>
        )}
      </div>
    </ModulePageShell>
  );
}
