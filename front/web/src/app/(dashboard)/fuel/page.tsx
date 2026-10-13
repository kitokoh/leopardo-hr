'use client';

/**
 * BC-15 Fuel (#7225, FUEL-019) — hub de l'espace station-service du portail
 * client.
 *
 * Constat (audit 2026-10-13) : la verticale FuelStation n'exposait, côté
 * portail web, qu'un écran pompiste (`/fuel/pump`) puis un simple `redirect`
 * sur `/fuel` — un responsable de station-service n'avait donc AUCUNE surface
 * pour créer ses stations, gérer son équipe, ses rotations, son stock ou sa
 * caisse, alors que l'API (`/fuel-station/*`, 127 routes, module
 * `App\Modules\FuelStation`) était complète. Ce hub reprend le modèle du hub
 * restaurant (BC-25) : KPIs d'accueil + tuiles de navigation vers chaque
 * outil, chacune pointant vers une page réellement fonctionnelle.
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import {
  AlertTriangle,
  Building2,
  CalendarClock,
  Fuel,
  Gauge,
  PackageSearch,
  UsersRound,
  Wallet,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Kpis = { stations: number; shifts: number; incidents: number };

/**
 * Lecture défensive d'une collection `/fuel-station/*` : une surface de hub ne
 * doit jamais tomber en erreur parce qu'un compteur secondaire est
 * inaccessible (ex. `shifts` réservé au manager) — on retombe sur un tableau
 * vide plutôt que de casser la page.
 */
async function safeCollection(path: string): Promise<unknown[]> {
  try {
    const res = await apiFetch(path);
    if (!res.ok) {
      return [];
    }
    const payload = (await res.json()) as { data?: unknown[] };
    return Array.isArray(payload.data) ? payload.data : [];
  } catch {
    return [];
  }
}

export default function FuelHomePage() {
  const locale = getPreferredLocale();
  const [kpis, setKpis] = useState<Kpis | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [stations, shifts, incidents] = await Promise.all([
        safeCollection('/fuel-station/stations?per_page=100'),
        safeCollection('/fuel-station/shifts?per_page=100'),
        safeCollection('/fuel-station/incidents?per_page=100'),
      ]);
      setKpis({ stations: stations.length, shifts: shifts.length, incidents: incidents.length });
    } catch {
      setError(t(locale, 'fuel.home.loadError', 'Impossible de charger le tableau de bord station-service.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const tiles = [
    {
      href: '/fuel/stations',
      icon: Building2,
      title: t(locale, 'fuel.home.stations', 'Stations'),
      description: t(locale, 'fuel.home.stationsDesc', 'Créez et gérez vos stations-service'),
      accent: 'from-amber-500 to-orange-600',
    },
    {
      href: '/fuel/team',
      icon: UsersRound,
      title: t(locale, 'fuel.home.team', 'Équipe & shifts'),
      description: t(locale, 'fuel.home.teamDesc', 'Employés et affectations aux shifts'),
      accent: 'from-fuchsia-500 to-purple-600',
    },
    {
      href: '/fuel/rotations',
      icon: CalendarClock,
      title: t(locale, 'fuel.home.rotations', 'Rotations & plannings'),
      description: t(locale, 'fuel.home.rotationsDesc', 'Définissez les shifts (horaires, pause)'),
      accent: 'from-sky-500 to-blue-600',
    },
    {
      href: '/fuel/stock',
      icon: PackageSearch,
      title: t(locale, 'fuel.home.stock', 'Stock & carburants'),
      description: t(locale, 'fuel.home.stockDesc', 'Niveaux de cuve, entrées et mouvements'),
      accent: 'from-emerald-500 to-teal-600',
    },
    {
      href: '/fuel/sales',
      icon: Wallet,
      title: t(locale, 'fuel.home.sales', 'Ventes & caisse'),
      description: t(locale, 'fuel.home.salesDesc', 'Sessions de caisse et ventes'),
      accent: 'from-lime-500 to-emerald-600',
    },
    {
      href: '/fuel/pump',
      icon: Gauge,
      title: t(locale, 'fuel.home.pump', 'Poste pompiste'),
      description: t(locale, 'fuel.home.pumpDesc', 'Saisie des relevés compteurs et anomalies'),
      accent: 'from-cyan-500 to-indigo-600',
    },
  ];

  const cards: Array<{ key: string; label: string; value: number; icon: typeof Fuel; tone: string }> = [
    { key: 'stations', label: t(locale, 'fuel.home.kpiStations', 'Stations'), value: kpis?.stations ?? 0, icon: Fuel, tone: 'text-amber-600 bg-amber-500/15' },
    { key: 'shifts', label: t(locale, 'fuel.home.kpiShifts', 'Shifts'), value: kpis?.shifts ?? 0, icon: CalendarClock, tone: 'text-sky-600 bg-sky-500/15' },
    { key: 'incidents', label: t(locale, 'fuel.home.kpiIncidents', 'Incidents'), value: kpis?.incidents ?? 0, icon: AlertTriangle, tone: 'text-rose-600 bg-rose-500/15' },
  ];

  return (
    <ModulePageShell
      icon={Fuel}
      title={t(locale, 'fuel.home.title', 'Station-service')}
      description={t(locale, 'fuel.home.subtitle', 'Pilotage de vos stations : équipe, rotations, stock et caisse.')}
    >
      {error ? (
        <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {error}
        </p>
      ) : null}

      <section className="grid gap-3 sm:grid-cols-3">
        {cards.map((card) => (
          <div key={card.key} className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <span className={`inline-flex h-10 w-10 items-center justify-center rounded-xl ${card.tone}`}>
              <card.icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <div>
              <p className="text-2xl font-black tracking-tight text-slate-950" data-testid={`fuel-kpi-${card.key}`}>
                {loading ? '—' : card.value}
              </p>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{card.label}</p>
            </div>
          </div>
        ))}
      </section>

      {!loading && kpis !== null && kpis.stations === 0 ? (
        <section className="rounded-2xl border border-amber-200 bg-amber-50 p-6" data-testid="fuel-empty-state">
          <h2 className="text-lg font-black tracking-tight text-amber-900">
            {t(locale, 'fuel.home.emptyTitle', 'Créez votre première station')}
          </h2>
          <p className="mt-1 text-sm text-amber-800">
            {t(locale, 'fuel.home.emptyBody', 'Commencez par déclarer une station, puis ajoutez vos employés, vos shifts et votre stock.')}
          </p>
          <Link
            href="/fuel/stations"
            className="mt-4 inline-flex rounded-xl bg-amber-600 px-4 py-2 text-sm font-black text-white transition hover:bg-amber-700"
          >
            {t(locale, 'fuel.home.setupStation', 'Créer une station')}
          </Link>
        </section>
      ) : null}

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {tiles.map((tile) => (
          <Link
            key={tile.href}
            href={tile.href}
            className={`group rounded-2xl bg-gradient-to-br ${tile.accent} p-5 text-white shadow-sm transition hover:shadow-md`}
          >
            <tile.icon className="h-8 w-8" aria-hidden="true" />
            <h3 className="mt-3 text-lg font-bold">{tile.title}</h3>
            <p className="mt-1 text-sm text-white/85">{tile.description}</p>
          </Link>
        ))}
      </section>
    </ModulePageShell>
  );
}
