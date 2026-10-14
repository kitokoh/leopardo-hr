'use client';

/**
 * RestaurantManager (BC-25) — hub de l'espace restaurant du portail client.
 * Affiche les KPIs du jour (`/restaurant/dashboard/kpis`), les alertes de
 * seuil de stock (`/restaurant/stock/alerts`) et les tuiles de navigation.
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { LayoutGrid, BookOpen, CalendarCheck, PackageSearch, Bike, ChartColumn, UtensilsCrossed, Globe, Wallet, UsersRound, Sparkles, Loader2, CheckCircle2, CalendarClock, Smartphone } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { useRestaurantBranches, type RestaurantBranch } from '@/components/restaurant/BranchSelect';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Kpis = { revenue_minor: number; orders_count: number; avg_basket_minor: number; table_rotation: number; sessions_count: number; date: string };

// RESTO-904 (#7749) — ligne de la vue consolidée multi-établissements :
// KPIs du jour par branche (`/restaurant/dashboard/kpis?branch_id=`) +
// nombre d'alertes de stock de la branche (agrégé côté client depuis
// `/restaurant/stock/alerts`, dont chaque ligne porte `branch_id`).
type ConsolidatedRow = { branch: RestaurantBranch; kpis: Kpis | null };

export default function RestaurantHomePage() {
  const locale = getPreferredLocale();
  const { branches, loading: branchesLoading, reload: reloadBranches } = useRestaurantBranches();
  const [kpis, setKpis] = useState<Kpis | null>(null);
  const [alerts, setAlerts] = useState<unknown[]>([]);
  const [consolidated, setConsolidated] = useState<ConsolidatedRow[]>([]);
  const [consolidatedError, setConsolidatedError] = useState('');
  const [error, setError] = useState('');
  // RESTO-ONBOARDING — état vide actionnable : un restaurateur fraîchement
  // activé arrivait sur un espace vide sans savoir par où commencer. On lui
  // propose d'installer le kit de démonstration (API #7865) et on lui donne
  // l'ordre de démarrage réel (branche → tables → produits → menu).
  const [demoImporting, setDemoImporting] = useState(false);
  const [demoImported, setDemoImported] = useState(false);
  const [demoError, setDemoError] = useState('');

  const load = useCallback(async () => {
    try {
      const [kpiRes, alertRes] = await Promise.all([
        apiFetch('/restaurant/dashboard/kpis'),
        apiFetch('/restaurant/stock/alerts'),
      ]);
      if (kpiRes.ok) {
        const payload = await kpiRes.json();
        setKpis((payload as { data?: Kpis }).data ?? null);
      }
      if (alertRes.ok) {
        const payload = await alertRes.json();
        setAlerts(Array.isArray((payload as { data?: unknown[] }).data) ? ((payload as { data: unknown[] }).data) : []);
      }
    } catch {
      setError(t(locale, 'restaurant.home.loadError', 'Impossible de charger le tableau de bord restaurant.'));
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const loadConsolidated = useCallback(async () => {
    if (branches.length === 0) return;
    setConsolidatedError('');
    try {
      const rows = await Promise.all(
        branches.map(async (branch): Promise<ConsolidatedRow> => {
          const res = await apiFetch(`/restaurant/dashboard/kpis?branch_id=${branch.id}`);
          const payload = res.ok ? ((await res.json()) as { data?: Kpis }) : {};
          return { branch, kpis: payload.data ?? null };
        }),
      );
      setConsolidated(rows);
    } catch {
      setConsolidatedError(t(locale, 'restaurant.home.consolidatedError'));
    }
  }, [branches, locale]);

  useEffect(() => {
    void loadConsolidated();
  }, [loadConsolidated]);

  const alertCountByBranch = new Map<number, number>();
  for (const alert of alerts) {
    const branchId = (alert as { branch_id?: number }).branch_id;
    if (typeof branchId === 'number') {
      alertCountByBranch.set(branchId, (alertCountByBranch.get(branchId) ?? 0) + 1);
    }
  }

  // RESTO-ONBOARDING — import du kit de démonstration de la verticale.
  // Le code opérationnel est `restaurantmanager` ; on retombe sur le code
  // descripteur `restaurant` si le premier n'est pas proposé (les deux codes
  // sont enregistrés côté serveur depuis l'alignement du registre).
  const importDemoData = useCallback(async () => {
    if (demoImporting) return;
    setDemoImporting(true);
    setDemoError('');
    try {
      const listRes = await apiFetch('/demo-data');
      const listPayload = listRes.ok
        ? ((await listRes.json()) as { data?: { kits?: { code: string; available?: boolean; status?: string }[] } })
        : {};
      const kits = listPayload.data?.kits ?? [];
      const target =
        kits.find((k) => k.code === 'restaurantmanager' && k.available === true && k.status === 'not_imported') ??
        kits.find((k) => k.code === 'restaurant' && k.available === true && k.status === 'not_imported');

      if (!target) {
        setDemoError(t(locale, 'restaurant.home.demoUnavailable', 'Aucun jeu de démonstration disponible pour votre espace.'));
        return;
      }

      const res = await apiFetch(`/demo-data/${target.code}/import`, { method: 'POST' });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        throw new Error((payload as { message?: string }).message ?? `HTTP ${res.status}`);
      }
      setDemoImported(true);
      await Promise.all([load(), reloadBranches()]);
    } catch (e) {
      setDemoError(e instanceof Error ? e.message : t(locale, 'restaurant.home.demoError', 'Import impossible pour le moment.'));
    } finally {
      setDemoImporting(false);
    }
  }, [demoImporting, load, locale, reloadBranches]);

  // Ordre de démarrage réel d'un restaurant : sans branche, rien d'autre ne
  // peut être créé (tables, produits et menu en dépendent).
  const setupOrder = [
    { step: 1, label: t(locale, 'restaurant.home.setupBranch', 'Créer votre établissement (branche)'), href: '/restaurant/referential' },
    { step: 2, label: t(locale, 'restaurant.home.setupTables', 'Déclarer vos tables et zones'), href: '/restaurant/referential' },
    { step: 3, label: t(locale, 'restaurant.home.setupProducts', 'Ajouter vos produits et ingrédients'), href: '/restaurant/referential' },
    { step: 4, label: t(locale, 'restaurant.home.setupMenu', 'Composer votre menu et le publier'), href: '/restaurant/referential' },
  ];

  const showEmptyState = !branchesLoading && branches.length === 0;

  const tiles = [
    { href: '/restaurant/online', icon: Globe, title: t(locale, 'restaurant.home.online'), description: t(locale, 'restaurant.home.onlineDesc'), accent: 'from-sky-500 to-indigo-600' },
    { href: '/restaurant/referential', icon: BookOpen, title: t(locale, 'restaurant.home.referential'), description: t(locale, 'restaurant.home.referentialDesc'), accent: 'from-emerald-500 to-teal-600' },
    { href: '/restaurant/reservations', icon: CalendarCheck, title: t(locale, 'restaurant.home.reservations'), description: t(locale, 'restaurant.home.reservationsDesc'), accent: 'from-cyan-500 to-blue-600' },
    { href: '/restaurant/stock', icon: PackageSearch, title: t(locale, 'restaurant.home.stock', 'Stock & achats'), description: t(locale, 'restaurant.home.stockDesc'), accent: 'from-amber-500 to-orange-600' },
    { href: '/restaurant/delivery', icon: Bike, title: t(locale, 'restaurant.home.delivery'), description: t(locale, 'restaurant.home.deliveryDesc'), accent: 'from-violet-500 to-purple-600' },
    { href: '/restaurant/reports', icon: ChartColumn, title: t(locale, 'restaurant.home.reports', 'Rapports'), description: t(locale, 'restaurant.home.reportsDesc', 'Ventes, occupation, produits, COGS, export CSV'), accent: 'from-rose-500 to-pink-600' },
    // #7728 — état de l'encaissement (profils de paiement tenant).
    { href: '/restaurant/payments', icon: Wallet, title: t(locale, 'restaurant.home.payments', 'Encaissement'), description: t(locale, 'restaurant.home.paymentsDesc', 'Paiement en ligne, profils Stripe & mobile money'), accent: 'from-lime-500 to-emerald-600' },
    // #7909 — affectations des employés aux succursales.
    { href: '/restaurant/team', icon: UsersRound, title: t(locale, 'restaurant.home.team', 'Équipe'), description: t(locale, 'restaurant.home.teamDesc', 'Affectations des employés aux succursales, rôles'), accent: 'from-fuchsia-500 to-purple-600' },
    // #8431 — rotations de travail (planning des équipes).
    { href: '/restaurant/rotations', icon: CalendarClock, title: t(locale, 'restaurant.home.rotations', 'Rotations & plannings'), description: t(locale, 'restaurant.home.rotationsDesc'), accent: 'from-sky-500 to-blue-600' },
    // #8432/#8447 — accès aux apps du restaurant (serveur, cuisine, caisse,
    // kiosque) : la tuile mène au hub « Mon Logiciel », filtré par solutions.
    { href: '/mon-logiciel', icon: Smartphone, title: t(locale, 'restaurant.home.mobileServer', 'Mes applications'), description: t(locale, 'restaurant.home.mobileServerDesc'), accent: 'from-orange-500 to-amber-600' },
  ];

  return (
    <ModulePageShell
      icon={UtensilsCrossed}
      title={t(locale, 'restaurant.home.title', 'Restaurant Manager')}
      description={t(locale, 'restaurant.home.subtitle')}
    >
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      {showEmptyState ? (
        <section
          data-testid="restaurant-empty-state"
          className="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-cyan-50 p-6"
        >
          <div className="flex items-start gap-3">
            <Sparkles className="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" aria-hidden="true" />
            <div className="min-w-0">
              <h3 className="text-lg font-bold text-slate-900">
                {t(locale, 'restaurant.home.emptyTitle', 'Votre restaurant est prêt à être configuré')}
              </h3>
              <p className="mt-1 text-sm text-slate-600">
                {t(locale, 'restaurant.home.emptyBody', 'Commencez par créer votre établissement, puis suivez l’ordre ci-dessous. Vous pouvez aussi installer un jeu de démonstration pour explorer librement.')}
              </p>

              <ol className="mt-4 space-y-2">
                {setupOrder.map((item) => (
                  <li key={item.step} className="flex items-center gap-3 text-sm">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">
                      {item.step}
                    </span>
                    <Link href={item.href} className="font-medium text-slate-800 hover:text-emerald-700 hover:underline">
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ol>

              {demoError ? <p className="mt-4 text-sm font-semibold text-red-600">{demoError}</p> : null}

              {demoImported ? (
                <p className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">
                  <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                  {t(locale, 'restaurant.home.demoImported', 'Données de démonstration installées.')}
                </p>
              ) : (
                <button
                  type="button"
                  disabled={demoImporting}
                  onClick={() => void importDemoData()}
                  data-testid="restaurant-install-demo"
                  className="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                >
                  {demoImporting ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <Sparkles className="h-4 w-4" aria-hidden="true" />}
                  {demoImporting
                    ? t(locale, 'restaurant.home.demoImporting', 'Import en cours…')
                    : t(locale, 'restaurant.home.demoImport', 'Installer les données de démo')}
                </button>
              )}
            </div>
          </div>
        </section>
      ) : null}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-sm text-slate-500">{t(locale, 'restaurant.home.kpiRevenue')}</p>
          <p className="mt-1 text-2xl font-black text-slate-900">{(kpis?.revenue_minor ?? 0).toLocaleString(locale)}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-sm text-slate-500">{t(locale, 'restaurant.home.kpiOrders', 'Commandes du jour')}</p>
          <p className="mt-1 text-2xl font-black text-slate-900">{kpis?.orders_count ?? 0}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-sm text-slate-500">{t(locale, 'restaurant.home.kpiBasket', 'Panier moyen')}</p>
          <p className="mt-1 text-2xl font-black text-slate-900">{(kpis?.avg_basket_minor ?? 0).toLocaleString(locale)}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-sm text-slate-500">{t(locale, 'restaurant.home.kpiRotation', 'Rotation des tables')}</p>
          <p className="mt-1 text-2xl font-black text-slate-900">{kpis?.table_rotation ?? 0}</p>
        </div>
      </div>

      {consolidated.length > 0 ? (
        <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="px-5 pt-4">
            <h3 className="font-bold text-slate-900">{t(locale, 'restaurant.home.consolidatedTitle')}</h3>
            <p className="text-sm text-slate-500">{t(locale, 'restaurant.home.consolidatedDesc')}</p>
          </div>
          {consolidatedError ? <p className="px-5 pt-2 text-sm text-red-600">{consolidatedError}</p> : null}
          <table className="mt-3 min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left font-semibold text-slate-700">{t(locale, 'restaurant.home.colBranch')}</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-700">{t(locale, 'restaurant.home.colRevenue')}</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-700">{t(locale, 'restaurant.home.colOrders')}</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-700">{t(locale, 'restaurant.home.colOccupancy')}</th>
                <th className="px-4 py-3 text-right font-semibold text-slate-700">{t(locale, 'restaurant.home.colAlerts')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {consolidated.map((row) => {
                const alertCount = alertCountByBranch.get(row.branch.id) ?? 0;
                return (
                  <tr key={row.branch.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">
                      {row.branch.name}
                      {row.branch.city ? <span className="block text-xs font-normal text-slate-500">{row.branch.city}</span> : null}
                    </td>
                    <td className="px-4 py-3 text-right">{(row.kpis?.revenue_minor ?? 0).toLocaleString(locale)}</td>
                    <td className="px-4 py-3 text-right">{row.kpis?.orders_count ?? 0}</td>
                    <td className="px-4 py-3 text-right">{row.kpis?.table_rotation ?? 0}</td>
                    <td className="px-4 py-3 text-right">
                      {alertCount > 0 ? (
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">{alertCount}</span>
                      ) : (
                        <span className="text-slate-400">0</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      {alerts.length > 0 ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <h3 className="font-bold text-amber-900">{t(locale, 'restaurant.home.alerts', 'Alertes de stock')}</h3>
          <ul className="mt-2 space-y-1 text-sm text-amber-800">
            {alerts.slice(0, 6).map((alert) => {
              const a = alert as { id?: number; ingredient_id?: number; quantity?: string; alert_threshold?: string };
              return (
                <li key={a.id}>
                  {t(locale, 'restaurant.home.alertLine')} #{a.ingredient_id} — {a.quantity} ≤ {a.alert_threshold}
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
              <LayoutGrid className="h-4 w-4" /> {t(locale, 'restaurant.home.open', 'Ouvrir')}
            </p>
          </Link>
        ))}
      </div>
    </ModulePageShell>
  );
}
