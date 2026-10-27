'use client';

/**
 * HOSP-007 (#7949) — hub de l'espace Hospitality (BC-32) du portail client.
 *
 * Le backend HospitalityManager etait complet (HOSP-001 a HOSP-006 :
 * referentiel, inventaire, equipe, reservations, locatif, vitrine publique)
 * mais **aucun ecran** ne l'exposait : un hotelier qui activait la verticale
 * arrivait sur un portail sans porte d'entree. Cette page est ce point
 * d'entree — tuiles de navigation + indicateur sante du module.
 *
 * Choix : les KPIs ne sont pas inventes ici. Le module Hospitality n'expose
 * pas d'endpoint d'agregation (contrairement a `/restaurant/dashboard/kpis`),
 * donc afficher un chiffre d'affaires « calcule cote client » a partir d'une
 * page partielle serait trompeur. On affiche ce que l'API sait vraiment :
 * l'etat du module (`/hospitality/status`).
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import {
  Building2,
  CalendarCheck,
  Boxes,
  UsersRound,
  KeyRound,
  Loader2,
  AlertTriangle,
  CheckCircle2,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type ModuleStatus = {
  active?: boolean;
  solution_code?: string | null;
  properties_count?: number | null;
};

type Tile = {
  key: string;
  href: string;
  titleKey: string;
  descKey: string;
  icon: typeof Building2;
};

const TILES: Tile[] = [
  {
    key: 'properties',
    href: '/hospitality/properties',
    titleKey: 'hospitality.hub.tiles.properties.title',
    descKey: 'hospitality.hub.tiles.properties.description',
    icon: Building2,
  },
  {
    key: 'inventory',
    href: '/hospitality/inventory',
    titleKey: 'hospitality.hub.tiles.inventory.title',
    descKey: 'hospitality.hub.tiles.inventory.description',
    icon: Boxes,
  },
  {
    key: 'reservations',
    href: '/hospitality/reservations',
    titleKey: 'hospitality.hub.tiles.reservations.title',
    descKey: 'hospitality.hub.tiles.reservations.description',
    icon: CalendarCheck,
  },
  {
    key: 'team',
    href: '/hospitality/team',
    titleKey: 'hospitality.hub.tiles.team.title',
    descKey: 'hospitality.hub.tiles.team.description',
    icon: UsersRound,
  },
  {
    key: 'rentals',
    href: '/hospitality/rentals',
    titleKey: 'hospitality.hub.tiles.rentals.title',
    descKey: 'hospitality.hub.tiles.rentals.description',
    icon: KeyRound,
  },
];

export default function HospitalityHomePage() {
  const locale = getPreferredLocale();
  const [status, setStatus] = useState<ModuleStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/hospitality/status');
      if (!res.ok) {
        // 403 = solution inactive pour ce tenant. Ce n'est pas une panne :
        // c'est un etat attendu, et le dire evite un ecran « erreur ».
        setStatus({ active: false });
        return;
      }
      const payload = (await res.json()) as { data?: ModuleStatus };
      setStatus(payload.data ?? { active: true });
    } catch {
      setError(t(locale, 'hospitality.hub.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <ModulePageShell
      title={t(locale, 'hospitality.hub.title')}
      subtitle={t(locale, 'hospitality.hub.subtitle')}
      icon={Building2}
    >
      {loading ? (
        <div className="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t(locale, 'common.loading')}
        </div>
      ) : null}

      {error !== '' ? (
        <div
          role="alert"
          className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
          <AlertTriangle className="h-4 w-4" />
          {error}
        </div>
      ) : null}

      {/* Un module inactif ne doit pas afficher ses tuiles : elles meneraient
          toutes a un 403 cote API. */}
      {!loading && status !== null && status.active === false ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <p className="font-medium">
            {t(locale, 'hospitality.hub.inactive.title')}
          </p>
          <p className="mt-1">
            {t(locale, 'hospitality.hub.inactive.body')}
          </p>
        </div>
      ) : null}

      {!loading && (status === null || status.active !== false) ? (
        <>
          <div className="flex items-center gap-2 text-sm text-emerald-700">
            <CheckCircle2 className="h-4 w-4" />
            {t(locale, 'hospitality.hub.active')}
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {TILES.map((tile) => {
              const Icon = tile.icon;

              return (
                <Link
                  key={tile.key}
                  href={tile.href}
                  className="group rounded-xl border border-neutral-200 bg-white p-5 transition hover:border-emerald-300 hover:shadow-sm"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                      <Icon className="h-5 w-5" />
                    </span>
                    <span className="font-medium text-neutral-900">
                      {t(locale, tile.titleKey)}
                    </span>
                  </div>
                  <p className="mt-3 text-sm text-neutral-600">
                    {t(locale, tile.descKey)}
                  </p>
                </Link>
              );
            })}
          </div>
        </>
      ) : null}
    </ModulePageShell>
  );
}
