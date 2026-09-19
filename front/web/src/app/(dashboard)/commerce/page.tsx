'use client';

/**
 * Espace vendeur Commerce (BC-17 RETAIL, #7675) — hub calqué sur le hub
 * gérant Travel (BC-24, #7633). Affiche les chiffres rapides (nombre de
 * produits, alertes de stock bas, session de caisse ouverte) via l'API
 * `/retail/*` et les tuiles de navigation vers les sous-espaces
 * (produits, stock, caisse).
 */
import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { Boxes, LayoutGrid, ShoppingBag, Store, Tags } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** Enveloppe paginée Laravel des listes `/retail/*`. */
type PaginatedMeta = { meta?: { total?: number } };

type CommerceOverview = {
  productsCount: number;
  alertsCount: number;
  openSessions: number;
};

export default function CommerceHomePage() {
  const locale = getPreferredLocale();
  const [overview, setOverview] = useState<CommerceOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [productsRes, alertsRes, sessionsRes] = await Promise.all([
        apiFetch('/retail/products?per_page=1'),
        apiFetch('/retail/stock/alerts?per_page=1'),
        apiFetch('/retail/pos/sessions?status=open&per_page=1'),
      ]);
      if (!productsRes.ok || !alertsRes.ok || !sessionsRes.ok) {
        throw new Error(`HTTP ${productsRes.status}`);
      }
      const [products, alerts, sessions] = (await Promise.all([
        productsRes.json(),
        alertsRes.json(),
        sessionsRes.json(),
      ])) as [PaginatedMeta, PaginatedMeta, PaginatedMeta];
      setOverview({
        productsCount: products.meta?.total ?? 0,
        alertsCount: alerts.meta?.total ?? 0,
        openSessions: sessions.meta?.total ?? 0,
      });
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const kpis = overview
    ? [
        {
          label: t(locale, 'commerce.home.kpiProducts', 'Produits au catalogue'),
          value: overview.productsCount.toLocaleString(locale),
        },
        {
          label: t(locale, 'commerce.home.kpiAlerts', 'Alertes de stock bas'),
          value: overview.alertsCount.toLocaleString(locale),
        },
        {
          label: t(locale, 'commerce.home.kpiSession', 'Caisse'),
          value:
            overview.openSessions > 0
              ? t(locale, 'commerce.home.sessionOpen', 'Session ouverte')
              : t(locale, 'commerce.home.sessionClosed', 'Aucune session ouverte'),
        },
      ]
    : [];

  const tiles = [
    {
      href: '/commerce/products',
      icon: Tags,
      title: t(locale, 'commerce.home.products', 'Produits'),
      description: t(locale, 'commerce.home.productsDesc', 'Catégories, fiches produits, prix et publication.'),
      accent: 'from-emerald-500 to-teal-600',
    },
    {
      href: '/commerce/stock',
      icon: Boxes,
      title: t(locale, 'commerce.home.stock', 'Stock'),
      description: t(locale, 'commerce.home.stockDesc', 'Emplacements, niveaux, mouvements tracés et alertes.'),
      accent: 'from-cyan-500 to-blue-600',
    },
    {
      href: '/commerce/pos',
      icon: ShoppingBag,
      title: t(locale, 'commerce.home.pos', 'Caisse'),
      description: t(locale, 'commerce.home.posDesc', 'Sessions de caisse, ventes, encaissements et tickets.'),
      accent: 'from-amber-500 to-orange-600',
    },
  ];

  return (
    <ModulePageShell
      icon={Store}
      title={t(locale, 'commerce.section.title', 'Commerce')}
      description={t(locale, 'commerce.section.subtitle', 'Espace vendeur : produits, stock et caisse de la verticale Commerce.')}
    >
      {error ? (
        <div className="flex items-center justify-between gap-4 rounded-lg bg-red-50 px-4 py-3">
          <p className="text-sm font-semibold text-red-700">{error}</p>
          <button
            type="button"
            onClick={() => void load()}
            className="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100"
          >
            {t(locale, 'commerce.gate.retry', 'Réessayer')}
          </button>
        </div>
      ) : null}

      <section aria-label={t(locale, 'commerce.home.kpisTitle', 'Aperçu rapide')}>
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {t(locale, 'commerce.home.kpisTitle', 'Aperçu rapide')}
        </h2>
        {loading ? (
          <p className="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
            {t(locale, 'commerce.loading', 'Chargement…')}
          </p>
        ) : overview ? (
          <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {kpis.map((kpi) => (
              <div key={kpi.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p className="text-sm text-slate-500">{kpi.label}</p>
                <p className="mt-1 text-2xl font-black text-slate-900">{kpi.value}</p>
              </div>
            ))}
          </div>
        ) : !error ? (
          <p className="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
            {t(locale, 'commerce.home.empty', 'Aucune donnée disponible.')}
          </p>
        ) : null}
      </section>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
              <LayoutGrid className="h-4 w-4" /> {t(locale, 'commerce.home.open', 'Ouvrir')}
            </p>
          </Link>
        ))}
      </div>
    </ModulePageShell>
  );
}
