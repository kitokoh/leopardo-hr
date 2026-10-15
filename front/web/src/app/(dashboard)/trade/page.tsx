'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Globe2, RefreshCw, ShoppingCart, Store, Truck } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';

/**
 * BC-36 TRADE (TRADE-009/#8484) — dashboard `/trade` de la verticale
 * International Trade OS.
 *
 * ARCHITECTURE : les vues « acheteur » (sourcing) et « vendeur » (ventes) sont
 * deux LECTURES du MÊME noyau commercial partagé — pas deux applications. Une
 * entreprise peut importer ET exporter : le rôle métier n'est jamais
 * irréversible, on ne fait que changer d'onglet.
 *
 * Sources (endpoints réels, aucune donnée mock) :
 *   - `GET /trade/rfqs`              → demandes de prix (vue acheteur)
 *   - `GET /trade/quotes`            → offres commerciales (vue vendeur)
 *   - `GET /trade/cost-estimates`    → estimations de coût rendu
 *   - `GET /trade/meta/integrations` → état RÉEL des adaptateurs externes
 *
 * SÉCURITÉ : la page est réservée aux tenants dont le flag `trade` est actif —
 * un 403 `MODULE_DISABLED` (gate serveur fail-closed) affiche l'état dédié au
 * lieu d'une erreur générique.
 *
 * HONNÊTETÉ PRODUIT : une intégration non configurée est affichée comme
 * INDISPONIBLE. Aucune donnée n'est simulée pour combler un adaptateur absent.
 */

type TradeTab = 'overview' | 'sourcing' | 'sales' | 'costs';

type TradeRow = {
  id?: string | number;
  reference?: string;
  title?: string;
  status?: string;
  destination?: string;
  incoterm?: string;
  created_at?: string;
};

type TradeIntegration = {
  key: string;
  label: string;
  purpose: string;
  mode: string;
  configured: boolean;
  available: boolean;
  reason?: string | null;
};

const TABS: { key: TradeTab; labelKey: string; icon: typeof Globe2 }[] = [
  { key: 'overview', labelKey: 'trade.overview.title', icon: Globe2 },
  { key: 'sourcing', labelKey: 'trade.sourcing.title', icon: ShoppingCart },
  { key: 'sales', labelKey: 'trade.sales.title', icon: Store },
  { key: 'costs', labelKey: 'trade.costs.title', icon: Truck },
];

export default function TradeDashboardPage() {
  const locale = getPreferredLocale();

  const [tab, setTab] = useState<TradeTab>('overview');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [moduleDisabled, setModuleDisabled] = useState(false);

  const [rfqs, setRfqs] = useState<TradeRow[]>([]);
  const [quotes, setQuotes] = useState<TradeRow[]>([]);
  const [costEstimates, setCostEstimates] = useState<TradeRow[]>([]);
  const [integrations, setIntegrations] = useState<TradeIntegration[]>([]);
  const [degraded, setDegraded] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    setLoading(true);

    try {
      // Chaque appel est indépendant : un module partiellement déployé ne doit
      // pas vider tout l'écran. On tolère l'absence d'une source (404/403) et
      // on affiche « aucune donnée » plutôt qu'une erreur globale.
      const [rfqRes, quoteRes, costRes, metaRes] = await Promise.allSettled([
        apiFetch('/trade/rfqs'),
        apiFetch('/trade/quotes'),
        apiFetch('/trade/cost-estimates'),
        apiFetch('/trade/meta/integrations'),
      ]);

      const firstRejection = [rfqRes, quoteRes, costRes, metaRes].find(
        (r) => r.status === 'rejected',
      );

      if (firstRejection && firstRejection.status === 'rejected') {
        const reason = firstRejection.reason;
        if (reason instanceof ApiError && reason.status === 403) {
          setModuleDisabled(true);
          return;
        }
      }

      const readList = async (settled: PromiseSettledResult<Response>): Promise<TradeRow[]> => {
        if (settled.status !== 'fulfilled') return [];
        try {
          const payload = await settled.value.json();
          return Array.isArray(payload?.data) ? payload.data : [];
        } catch {
          return [];
        }
      };

      setRfqs(await readList(rfqRes));
      setQuotes(await readList(quoteRes));
      setCostEstimates(await readList(costRes));

      if (metaRes.status === 'fulfilled') {
        try {
          const payload = await metaRes.value.json();
          const data = payload?.data ?? {};
          setIntegrations(Array.isArray(data.integrations) ? data.integrations : []);
          setDegraded(Boolean(data.degraded));
        } catch {
          setIntegrations([]);
        }
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 403) {
        setModuleDisabled(true);
      } else {
        setError(t(locale, 'trade.loadError'));
      }
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const unconfigured = useMemo(
    () => integrations.filter((i) => !(i.configured && i.available)),
    [integrations],
  );

  const renderTable = (rows: TradeRow[], emptyKey: string) => {
    if (!rows.length) {
      return (
        <p className="px-1 py-6 text-sm text-slate-500">{t(locale, 'trade.noData')}</p>
      );
    }

    return (
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
              <th className="px-3 py-2">{t(locale, 'trade.columns.reference')}</th>
              <th className="px-3 py-2">{t(locale, 'trade.columns.title')}</th>
              <th className="px-3 py-2">{t(locale, 'trade.columns.status')}</th>
              <th className="px-3 py-2">{t(locale, 'trade.columns.destination')}</th>
              <th className="px-3 py-2">{t(locale, 'trade.columns.incoterm')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={String(row.id ?? index)} className="border-t border-slate-100">
                <td className="px-3 py-3 font-mono text-xs text-slate-600">
                  {row.reference ?? '—'}
                </td>
                <td className="px-3 py-3 text-slate-900">{row.title ?? '—'}</td>
                <td className="px-3 py-3 text-slate-600">{row.status ?? '—'}</td>
                <td className="px-3 py-3 text-slate-600">{row.destination ?? '—'}</td>
                <td className="px-3 py-3 text-slate-600">{row.incoterm ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <span className="sr-only">{emptyKey}</span>
      </div>
    );
  };

  if (moduleDisabled) {
    return (
      <ModulePageShell
        title={t(locale, 'trade.title')}
        subtitle={t(locale, 'trade.subtitle')}
        icon={Globe2}
      >
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-sm text-amber-800">
          <p className="flex items-center gap-2 font-medium">
            <AlertTriangle className="h-4 w-4" />
            {t(locale, 'trade.moduleDisabled')}
          </p>
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell
      title={t(locale, 'trade.title')}
      subtitle={t(locale, 'trade.subtitle')}
      icon={Globe2}
      actions={
        <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          {t(locale, 'trade.retry')}
        </Button>
      }
    >
      {/* Onglets : deux vues métier sur le MÊME noyau partagé. */}
      <div className="flex flex-wrap gap-2" role="tablist">
        {TABS.map(({ key, labelKey, icon: Icon }) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={`inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition ${
              tab === key
                ? 'bg-slate-900 text-white'
                : 'bg-white/70 text-slate-700 ring-1 ring-slate-200 hover:bg-white'
            }`}
          >
            <Icon className="h-4 w-4" />
            {t(locale, labelKey)}
          </button>
        ))}
      </div>

      {error ? (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-6 py-5 text-sm text-rose-700">
          <p className="font-medium">{error}</p>
          <Button variant="outline" size="sm" className="mt-3" onClick={() => void load()}>
            {t(locale, 'trade.retry')}
          </Button>
        </div>
      ) : null}

      {loading ? (
        <p className="py-10 text-center text-sm text-slate-500">{t(locale, 'trade.loading')}</p>
      ) : (
        <>
          {tab === 'overview' ? (
            <div className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  { label: t(locale, 'trade.overview.rfqs'), value: rfqs.length },
                  { label: t(locale, 'trade.overview.quotes'), value: quotes.length },
                  { label: t(locale, 'trade.overview.orders'), value: costEstimates.length },
                  { label: t(locale, 'trade.overview.integrations'), value: integrations.length },
                ].map((card) => (
                  <div
                    key={card.label}
                    className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200"
                  >
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                      {card.label}
                    </p>
                    <p className="mt-2 text-3xl font-bold text-slate-900">{card.value}</p>
                  </div>
                ))}
              </div>

              {degraded ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                  <p className="flex items-center gap-2 font-medium">
                    <AlertTriangle className="h-4 w-4" />
                    {t(locale, 'trade.overview.integrationsDegraded')}
                  </p>
                </div>
              ) : null}

              <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
                <h2 className="text-sm font-semibold text-slate-900">
                  {t(locale, 'trade.integrations.title')}
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  {t(locale, 'trade.integrations.notice')}
                </p>
                <ul className="mt-3 space-y-2">
                  {integrations.map((integration) => {
                    const operational = integration.configured && integration.available;
                    return (
                      <li
                        key={integration.key}
                        className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 text-sm"
                      >
                        <span className="text-slate-700">{integration.label}</span>
                        <span
                          className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                            operational
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-slate-200 text-slate-700'
                          }`}
                        >
                          {operational
                            ? t(locale, 'trade.integrations.stateOperational')
                            : t(locale, 'trade.integrations.stateUnconfigured')}
                        </span>
                      </li>
                    );
                  })}
                  {!integrations.length ? (
                    <li className="text-sm text-slate-500">{t(locale, 'trade.noData')}</li>
                  ) : null}
                </ul>
                {unconfigured.length ? (
                  <p className="mt-3 text-xs text-slate-500">
                    {unconfigured.map((i) => i.reason).filter(Boolean).join(' ')}
                  </p>
                ) : null}
              </div>
            </div>
          ) : null}

          {tab === 'sourcing' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.sourcing.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                {t(locale, 'trade.sourcing.subtitle')}
              </p>
              <div className="mt-3">{renderTable(rfqs, 'trade-rfqs-empty')}</div>
            </div>
          ) : null}

          {tab === 'sales' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.sales.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">{t(locale, 'trade.sales.subtitle')}</p>
              <div className="mt-3">{renderTable(quotes, 'trade-quotes-empty')}</div>
            </div>
          ) : null}

          {tab === 'costs' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.costs.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">{t(locale, 'trade.costs.subtitle')}</p>
              <div className="mt-3">{renderTable(costEstimates, 'trade-costs-empty')}</div>
              {/* Cadre légal : jamais présenté comme une décision douanière. */}
              <p className="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                {t(locale, 'trade.costs.disclaimer')}
              </p>
            </div>
          ) : null}
        </>
      )}
    </ModulePageShell>
  );
}
