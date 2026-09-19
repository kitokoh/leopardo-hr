'use client';

/**
 * TravelManager (BC-24, #7633) — hub de l'espace gérant de la verticale
 * Agence de voyage, calqué sur le hub restaurant. Affiche les KPIs du jour
 * (`GET /travel/reports/dashboard`) et les tuiles de navigation vers les
 * sous-espaces (réseau, voyages, réservations, rapports), plus un lien vers
 * le portail voyageur `/travel/portal` (existant, à ne pas casser).
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import {
  Bus,
  CalendarClock,
  ChartColumn,
  ExternalLink,
  LayoutGrid,
  Map,
  Ticket,
  UsersRound,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** Payload de `TravelReportService::dashboard` (enveloppe `{ data }`). */
type TravelDashboard = {
  date: string;
  period_days: number;
  trip_id: number | null;
  sales_today: number;
  passengers: number;
  revenue_minor: number;
  confirmed_minor: number;
  cancellations: number;
  occupancy_rate: number;
  trips_count: number;
};

export default function TravelHomePage() {
  const locale = getPreferredLocale();
  const [dashboard, setDashboard] = useState<TravelDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/travel/reports/dashboard');
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const payload = (await res.json()) as { data?: TravelDashboard };
      setDashboard(payload.data ?? null);
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const kpis = dashboard
    ? [
        { label: t(locale, 'travel.reports.sales', 'Ventes'), value: dashboard.sales_today.toLocaleString(locale) },
        { label: t(locale, 'travel.bookings.passengers', 'Passagers'), value: dashboard.passengers.toLocaleString(locale) },
        { label: t(locale, 'travel.home.kpiRevenue', 'Recette nette'), value: dashboard.revenue_minor.toLocaleString(locale) },
        { label: t(locale, 'travel.reports.occupancy', 'Occupation'), value: `${Math.round(dashboard.occupancy_rate * 100)}%` },
        { label: t(locale, 'travel.reports.cancellations', 'Annulations'), value: dashboard.cancellations.toLocaleString(locale) },
        { label: t(locale, 'travel.home.kpiTrips', 'Trajets du jour'), value: dashboard.trips_count.toLocaleString(locale) },
      ]
    : [];

  const tiles = [
    {
      href: '/travel/network',
      icon: Map,
      title: t(locale, 'travel.home.network', 'Réseau'),
      description: t(locale, 'travel.home.networkDesc', 'Gares, bureaux, lignes et arrêts ordonnés.'),
      accent: 'from-emerald-500 to-teal-600',
    },
    {
      href: '/travel/trips',
      icon: CalendarClock,
      title: t(locale, 'travel.home.trips', 'Voyages'),
      description: t(locale, 'travel.home.tripsDesc', 'Programmation, tarifs par classe, publication et manifeste.'),
      accent: 'from-cyan-500 to-blue-600',
    },
    {
      href: '/travel/bookings',
      icon: Ticket,
      title: t(locale, 'travel.bookings.title', 'Réservations'),
      description: t(locale, 'travel.home.bookingsDesc', 'Ventes au guichet, confirmation, annulation, remboursement et billets.'),
      accent: 'from-amber-500 to-orange-600',
    },
    {
      href: '/travel/staff',
      icon: UsersRound,
      title: t(locale, 'travel.staff.title', 'Équipe'),
      description: t(locale, 'travel.home.staffDesc', 'Affectations chauffeur, guichetier, contrôleur et chef de bureau.'),
      accent: 'from-violet-500 to-purple-600',
    },
    {
      href: '/travel/reports',
      icon: ChartColumn,
      title: t(locale, 'travel.tab.reports', 'Rapports'),
      description: t(locale, 'travel.home.reportsDesc', 'Ventes, occupation, recettes, annulations et exports CSV.'),
      accent: 'from-rose-500 to-pink-600',
    },
  ];

  return (
    <ModulePageShell
      icon={Bus}
      title={t(locale, 'travel.section.title', 'Agence de voyage')}
      description={t(locale, 'travel.section.subtitle', 'Gestion de la verticale TravelAgency : référentiel, réseau, ventes, billetterie et rapports.')}
    >
      {error ? (
        <div className="flex items-center justify-between gap-4 rounded-lg bg-red-50 px-4 py-3">
          <p className="text-sm font-semibold text-red-700">{error}</p>
          <button
            type="button"
            onClick={() => void load()}
            className="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100"
          >
            {t(locale, 'travel.gate.retry', 'Réessayer')}
          </button>
        </div>
      ) : null}

      <section aria-label={t(locale, 'travel.home.kpisToday', 'Activité du jour')}>
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'travel.home.kpisToday', 'Activité du jour')}
        </h2>
        {loading ? (
          <p className="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
            {t(locale, 'travel.loading', 'Chargement…')}
          </p>
        ) : dashboard ? (
          <div className="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            {kpis.map((kpi) => (
              <div key={kpi.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p className="text-sm text-slate-500">{kpi.label}</p>
                <p className="mt-1 text-2xl font-black text-slate-900">{kpi.value}</p>
              </div>
            ))}
          </div>
        ) : !error ? (
          <p className="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
            {t(locale, 'travel.reports.empty', 'Aucune donnée sur la période.')}
          </p>
        ) : null}
      </section>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {tiles.map((tile) => (
          <Link
            key={tile.href}
            href={tile.href}
            className={`group rounded-2xl bg-gradient-to-br ${tile.accent} p-5 text-white shadow-sm transition hover:shadow-md`}
          >
            <tile.icon className="h-8 w-8" />
            <h3 className="mt-3 text-lg font-bold">{tile.title}</h3>
            <p className="mt-1 text-sm text-white/85">{tile.description}</p>
            <p className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-white/90 group-hover:underline">
              <LayoutGrid className="h-4 w-4" /> {t(locale, 'travel.home.open', 'Ouvrir')}
            </p>
          </Link>
        ))}
      </div>

      <Link
        href="/travel/portal"
        className="group flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md"
      >
        <div>
          <h3 className="font-bold text-slate-900">{t(locale, 'travel.home.portal', 'Portail voyageur')}</h3>
          <p className="mt-1 text-sm text-slate-600">
            {t(locale, 'travel.home.portalDesc', 'Recherche de trajets et réservation côté client.')}
          </p>
        </div>
        <ExternalLink className="h-5 w-5 shrink-0 text-slate-400 transition group-hover:text-slate-700" />
      </Link>
    </ModulePageShell>
  );
}
