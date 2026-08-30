'use client';

/**
 * RestaurantManager (BC-25) — hub de l'espace restaurant du portail client.
 * Affiche les KPIs du jour (`/restaurant/dashboard/kpis`), les alertes de
 * seuil de stock (`/restaurant/stock/alerts`) et les tuiles de navigation.
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { LayoutGrid, BookOpen, CalendarCheck, PackageSearch, Bike, ChartColumn, UtensilsCrossed } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type Kpis = { revenue_minor: number; orders_count: number; avg_basket_minor: number; table_rotation: number; sessions_count: number; date: string };

export default function RestaurantHomePage() {
  const locale = getPreferredLocale();
  const [kpis, setKpis] = useState<Kpis | null>(null);
  const [alerts, setAlerts] = useState<unknown[]>([]);
  const [error, setError] = useState('');

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

  const tiles = [
    { href: '/restaurant/referential', icon: BookOpen, title: t(locale, 'restaurant.home.referential', 'Référentiel'), description: t(locale, 'restaurant.home.referentialDesc', 'Branches, plan de salle, catalogue, matières, menus, fournisseurs'), accent: 'from-emerald-500 to-teal-600' },
    { href: '/restaurant/reservations', icon: CalendarCheck, title: t(locale, 'restaurant.home.reservations', 'Réservations'), description: t(locale, 'restaurant.home.reservationsDesc', 'Créneaux, check-in, dépôts'), accent: 'from-cyan-500 to-blue-600' },
    { href: '/restaurant/stock', icon: PackageSearch, title: t(locale, 'restaurant.home.stock', 'Stock & achats'), description: t(locale, 'restaurant.home.stockDesc', 'Niveaux, bons de commande, réceptions, inventaires'), accent: 'from-amber-500 to-orange-600' },
    { href: '/restaurant/delivery', icon: Bike, title: t(locale, 'restaurant.home.delivery', 'Livraison & fidélité'), description: t(locale, 'restaurant.home.deliveryDesc', 'Zones, livreurs, tournées, points, promotions'), accent: 'from-violet-500 to-purple-600' },
    { href: '/restaurant/reports', icon: ChartColumn, title: t(locale, 'restaurant.home.reports', 'Rapports'), description: t(locale, 'restaurant.home.reportsDesc', 'Ventes, occupation, produits, COGS, export CSV'), accent: 'from-rose-500 to-pink-600' },
  ];

  return (
    <ModulePageShell
      icon={UtensilsCrossed}
      title={t(locale, 'restaurant.home.title', 'Restaurant Manager')}
      description={t(locale, 'restaurant.home.subtitle', 'Point de vente, réservations, stock et livraison')}
    >
      {error ? <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-sm text-slate-500">{t(locale, 'restaurant.home.kpiRevenue', "Chiffre d'affaires du jour")}</p>
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

      {alerts.length > 0 ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <h3 className="font-bold text-amber-900">{t(locale, 'restaurant.home.alerts', 'Alertes de stock')}</h3>
          <ul className="mt-2 space-y-1 text-sm text-amber-800">
            {alerts.slice(0, 6).map((alert) => {
              const a = alert as { id?: number; ingredient_id?: number; quantity?: string; alert_threshold?: string };
              return (
                <li key={a.id}>
                  {t(locale, 'restaurant.home.alertLine', 'Ingrédient')} #{a.ingredient_id} — {a.quantity} ≤ {a.alert_threshold}
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
