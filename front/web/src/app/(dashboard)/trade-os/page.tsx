'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Globe2, Handshake, Package, RefreshCw, ShoppingCart, Store, Truck, ClipboardList } from 'lucide-react';
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

type TradeTab = 'overview' | 'sourcing' | 'sales' | 'catalog' | 'orders' | 'counterparties' | 'costs';

/**
 * Commande B2B (#9779).
 *
 * `direction` distingue ce que l'entreprise ACHETE (`purchase`) de ce qu'elle
 * VEND (`sale`) : meme objet, deux flux — c'est la meme entreprise.
 *
 * `total_minor` vient de l'offre acceptee, jamais d'une saisie : le montant
 * engage est celui que le serveur avait fige.
 */
type TradeOrderRow = {
  id: number | string;
  reference?: string | null;
  direction?: 'purchase' | 'sale' | string;
  status?: string | null;
  currency?: string | null;
  incoterm?: string | null;
  total_minor?: number | null;
};

/**
 * Contrepartie B2B (#9779) — annuaire clients / fournisseurs / transporteurs.
 *
 * `verification_status` est un etat INTERNE : `verified` signifie « verifie par
 * un membre de l'equipe », jamais « verifie automatiquement » — aucun service
 * de verification d'entreprise n'est souscrit.
 */
type TradeCounterpartyRow = {
  id: number | string;
  name?: string | null;
  country?: string | null;
  roles?: string[] | null;
  verification_status?: string | null;
};

/**
 * Produit du catalogue B2B (#9767).
 *
 * `indicative_unit_price_minor` est un entier en minor units : le catalogue
 * n'est pas une offre commerciale, il ne porte ni remise ni total. Un prix
 * ferme n'existe que dans une offre (`TradeQuote`), où le serveur recalcule
 * les totaux depuis les lignes.
 */
type TradeProduct = {
  id: number | string;
  name: string;
  description?: string | null;
  category?: string | null;
  hs_code?: string | null;
  origin_country?: string | null;
  unit?: string | null;
  moq_quantity?: string | number | null;
  indicative_unit_price_minor?: number | null;
  currency?: string | null;
  status?: string;
};

/** Brouillon du formulaire de création — tout est texte, converti à l'envoi. */
type ProductDraft = {
  name: string;
  category: string;
  unit: string;
  moq: string;
  price: string;
  origin: string;
  hsCode: string;
};

const EMPTY_DRAFT: ProductDraft = {
  name: '',
  category: '',
  unit: '',
  moq: '',
  price: '',
  origin: '',
  hsCode: '',
};

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
  // Le catalogue est l'outil du VENDEUR : sans lui, il ne peut pas alimenter
  // les lignes de ses offres (`trade_product_id` restait vide).
  { key: 'catalog', labelKey: 'trade.catalog.title', icon: Package },
  // Les commandes et l'annuaire sont les deux outils qui manquaient au
  // "GERER" : sans eux, une offre acceptee ne produisait rien de visible et
  // aucune RFQ ne pouvait etre diffusee (il faut une contrepartie).
  { key: 'orders', labelKey: 'trade.orders.title', icon: ClipboardList },
  { key: 'counterparties', labelKey: 'trade.counterparties.title', icon: Handshake },
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

  // ── Commandes & contreparties (#9779) ──────────────────────────────────
  const [orders, setOrders] = useState<TradeOrderRow[]>([]);
  const [counterparties, setCounterparties] = useState<TradeCounterpartyRow[]>([]);
  const [counterpartyError, setCounterpartyError] = useState<string | null>(null);
  const [orderError, setOrderError] = useState<string | null>(null);

  // ── Catalogue produits (#9767) ───────────────────────────────────────────
  const [products, setProducts] = useState<TradeProduct[]>([]);
  const [draft, setDraft] = useState<ProductDraft>(EMPTY_DRAFT);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    setLoading(true);

    try {
      // Chaque appel est indépendant : un module partiellement déployé ne doit
      // pas vider tout l'écran. On tolère l'absence d'une source (404/403) et
      // on affiche « aucune donnée » plutôt qu'une erreur globale.
      const [rfqRes, quoteRes, costRes, metaRes, productRes, orderRes, counterpartyRes] = await Promise.allSettled([
        apiFetch('/trade/rfqs'),
        apiFetch('/trade/quotes'),
        apiFetch('/trade/cost-estimates'),
        apiFetch('/trade/meta/integrations'),
        apiFetch('/trade/products'),
        // #9779 : deux sources de plus, elles aussi INDEPENDANTES — un 403 sur
        // l'une ne doit pas vider les autres onglets.
        apiFetch('/trade/orders'),
        apiFetch('/trade/counterparties'),
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
      setOrders((await readList(orderRes)) as unknown as TradeOrderRow[]);
      setCounterparties((await readList(counterpartyRes)) as unknown as TradeCounterpartyRow[]);

      // #9779 — une erreur PROPRE a ces deux sources est affichee dans son
      // onglet : l'ecran ne doit pas laisser croire que le vendeur n'a aucune
      // contrepartie alors que c'est la lecture qui a echoue.
      if (orderRes.status === 'rejected') {
        setOrderError(t(locale, 'trade.orders.loadError'));
      }
      if (counterpartyRes.status === 'rejected') {
        setCounterpartyError(t(locale, 'trade.counterparties.loadError'));
      }

      // Le catalogue est une source INDÉPENDANTE : un 403/404 sur les autres
      // listes ne doit pas vider l'onglet Catalogue, et inversement.
      if (productRes.status === 'fulfilled') {
        try {
          const payload = await productRes.value.json();
          setProducts(Array.isArray(payload?.data) ? (payload.data as TradeProduct[]) : []);
        } catch {
          setProducts([]);
        }
      } else {
        setProducts([]);
      }

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

  /**
   * Création d'un produit (#9767).
   *
   * ## Ce que le formulaire refuse de faire
   *
   * Il n'envoie **jamais** de `hs_code` inventé : vide = `null`, et le serveur
   * ne le déduit pas non plus. Un code douanier faux ferait croire à une
   * conformité qui n'existe pas.
   *
   * Le prix saisi est converti en **minor units entiers** (`Math.round`) :
   * aucun flottant ne part vers l'API pour représenter de l'argent.
   */
  const submitProduct = async () => {
    setFormError(null);
    setNotice(null);

    const name = draft.name.trim();
    if (!name) {
      setFormError(t(locale, 'trade.catalog.nameRequired'));
      return;
    }

    const hsCode = draft.hsCode.trim();
    if (hsCode && !/^[0-9]{6,10}$/.test(hsCode)) {
      setFormError(t(locale, 'trade.catalog.hsInvalid'));
      return;
    }

    const price = draft.price.trim();
    const moq = draft.moq.trim();

    setSaving(true);
    try {
      const response = await apiFetch('/trade/products', {
        method: 'POST',
        body: JSON.stringify({
          name,
          category: draft.category.trim() || null,
          unit: draft.unit.trim() || null,
          origin_country: draft.origin.trim().toUpperCase() || null,
          moq_quantity: moq ? moq : null,
          // Saisie en unité majeure → minor units entiers.
          indicative_unit_price_minor: price ? Math.round(Number(price) * 100) : null,
          hs_code: hsCode || null,
        }),
      });

      const payload = await response.json().catch(() => null);
      const created = payload?.data as TradeProduct | undefined;

      if (created) {
        setProducts((current) => [created, ...current]);
      }
      setDraft(EMPTY_DRAFT);
      setFormOpen(false);
      setNotice(t(locale, 'trade.catalog.saved'));
    } catch {
      setFormError(t(locale, 'trade.catalog.error'));
    } finally {
      setSaving(false);
    }
  };

  /**
   * Archivage logique — jamais de suppression : les offres passées référencent
   * le produit, un `DELETE` casserait leur historique.
   */
  const archiveProduct = async (product: TradeProduct) => {
    setFormError(null);
    setNotice(null);

    try {
      const response = await apiFetch(`/trade/products/${product.id}/archive`, {
        method: 'POST',
      });
      const payload = await response.json().catch(() => null);
      const updated = payload?.data as TradeProduct | undefined;

      setProducts((current) =>
        current.map((item) =>
          item.id === product.id ? { ...item, status: updated?.status ?? 'archived' } : item,
        ),
      );
    } catch {
      setFormError(t(locale, 'trade.catalog.error'));
    }
  };

  const formatPrice = (product: TradeProduct): string => {
    if (product.indicative_unit_price_minor === null || product.indicative_unit_price_minor === undefined) {
      return t(locale, 'trade.catalog.priceOnRequest');
    }
    const amount = (product.indicative_unit_price_minor / 100).toLocaleString(locale);
    return `${t(locale, 'trade.catalog.priceIndicative')} — ${amount}${product.currency ? ` ${product.currency}` : ''}`;
  };

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
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.reference')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.title')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.status')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.destination')}</th>
              <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.incoterm')}</th>
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

          {tab === 'orders' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.orders.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">{t(locale, 'trade.orders.subtitle')}</p>

              {orderError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {orderError}
                </p>
              ) : null}

              {!orders.length ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.orders.empty')}
                </p>
              ) : (
                <div className="mt-3 overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.reference')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.orders.direction')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.status')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.orders.total')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.columns.incoterm')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {orders.map((order) => (
                        <tr key={String(order.id)} className="border-t border-slate-100">
                          <td className="px-3 py-3 font-mono text-xs text-slate-600">
                            {order.reference ?? `#${order.id}`}
                          </td>
                          <td className="px-3 py-3 text-slate-600">
                            {order.direction === 'purchase'
                              ? t(locale, 'trade.orders.purchase')
                              : order.direction === 'sale'
                                ? t(locale, 'trade.orders.sale')
                                : '—'}
                          </td>
                          <td className="px-3 py-3 text-slate-600">
                            {/* Le statut est un enum serveur (`confirmed`,
                                `in_transit`…) : on affiche son libellé traduit,
                                jamais la valeur brute de la base. Une valeur
                                inconnue retombe sur elle-même plutôt que sur un
                                vide qui laisserait croire à une absence. */}
                            {order.status
                              ? t(locale, `trade.orders.status.${order.status}`, order.status)
                              : '—'}
                          </td>
                          <td className="px-3 py-3 text-slate-900">
                            {order.total_minor === null || order.total_minor === undefined
                              ? '—'
                              : `${(order.total_minor / 100).toLocaleString(locale)}${order.currency ? ` ${order.currency}` : ''}`}
                          </td>
                          <td className="px-3 py-3 text-slate-600">{order.incoterm ?? '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ) : null}

          {tab === 'counterparties' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <h2 className="text-sm font-semibold text-slate-900">
                {t(locale, 'trade.counterparties.title')}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                {t(locale, 'trade.counterparties.subtitle')}
              </p>

              {counterpartyError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {counterpartyError}
                </p>
              ) : null}

              {!counterparties.length ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.counterparties.empty')}
                </p>
              ) : (
                <div className="mt-3 overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.name')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.roles')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.country')}</th>
                        <th scope="col" className="px-3 py-2">{t(locale, 'trade.counterparties.verification')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {counterparties.map((party) => (
                        <tr key={String(party.id)} className="border-t border-slate-100">
                          <td className="px-3 py-3 text-slate-900">{party.name ?? '—'}</td>
                          <td className="px-3 py-3 text-slate-600">
                            {/* `roles` est un tableau d'enums serveur
                                (`buyer`, `supplier`, `carrier`…) : chaque rôle
                                est traduit, une valeur inconnue reste lisible. */}
                            {party.roles?.length
                              ? party.roles
                                  .map((role) => t(locale, `trade.counterparties.role.${role}`, role))
                                  .join(' · ')
                              : '—'}
                          </td>
                          <td className="px-3 py-3 text-slate-600">{party.country ?? '—'}</td>
                          <td className="px-3 py-3">
                            {/* `verified` veut dire « verifie par un membre de
                                l'equipe ». On ne l'affiche jamais comme un
                                controle automatique : il n'en existe pas. */}
                            <span
                              className={
                                party.verification_status === 'verified'
                                  ? 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700'
                                  : 'rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600'
                              }
                            >
                              {party.verification_status === 'verified'
                                ? t(locale, 'trade.counterparties.verifiedByTeam')
                                : t(locale, 'trade.counterparties.notVerified')}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ) : null}

          {tab === 'catalog' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-semibold text-slate-900">
                    {t(locale, 'trade.catalog.title')}
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    {t(locale, 'trade.catalog.subtitle')}
                  </p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setFormOpen((open) => !open);
                    setFormError(null);
                    setNotice(null);
                  }}
                >
                  <Package className="h-4 w-4" />
                  {t(locale, 'trade.catalog.new')}
                </Button>
              </div>

              {notice ? (
                <p className="mt-3 rounded-xl bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
                  {notice}
                </p>
              ) : null}

              {formError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {formError}
                </p>
              ) : null}

              {formOpen ? (
                <form
                  className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2"
                  onSubmit={(event) => {
                    event.preventDefault();
                    void submitProduct();
                  }}
                >
                  <label className="text-xs font-medium text-slate-700 sm:col-span-2">
                    {t(locale, 'trade.catalog.name')}
                    <input
                      required
                      value={draft.name}
                      onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.category')}
                    <input
                      value={draft.category}
                      onChange={(e) => setDraft({ ...draft, category: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.unit')}
                    <input
                      value={draft.unit}
                      onChange={(e) => setDraft({ ...draft, unit: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.moq')}
                    <input
                      type="number"
                      min="0"
                      step="any"
                      value={draft.moq}
                      onChange={(e) => setDraft({ ...draft, moq: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.price')}
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={draft.price}
                      onChange={(e) => setDraft({ ...draft, price: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.origin')}
                    <input
                      maxLength={2}
                      value={draft.origin}
                      onChange={(e) => setDraft({ ...draft, origin: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
                    />
                  </label>

                  <label className="text-xs font-medium text-slate-700">
                    {t(locale, 'trade.catalog.hsCode')}
                    <input
                      inputMode="numeric"
                      value={draft.hsCode}
                      onChange={(e) => setDraft({ ...draft, hsCode: e.target.value })}
                      className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    {/* Le code n'est JAMAIS deviné : on le dit à l'écran. */}
                    <span className="mt-1 block text-[11px] font-normal text-slate-500">
                      {t(locale, 'trade.catalog.hsHint')}
                    </span>
                  </label>

                  <div className="flex items-end gap-2 sm:col-span-2">
                    <Button type="submit" size="sm" disabled={saving}>
                      {t(locale, 'trade.catalog.save')}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setFormOpen(false);
                        setDraft(EMPTY_DRAFT);
                        setFormError(null);
                      }}
                    >
                      {t(locale, 'trade.catalog.cancel')}
                    </Button>
                  </div>
                </form>
              ) : null}

              {products.length === 0 ? (
                <p className="px-1 py-6 text-sm text-slate-500">
                  {t(locale, 'trade.catalog.empty')}
                </p>
              ) : (
                <ul className="mt-4 space-y-2">
                  {products.map((product) => {
                    const archived = product.status === 'archived';
                    const meta = [
                      product.category,
                      product.origin_country,
                      product.moq_quantity
                        ? `${t(locale, 'trade.catalog.moq')}: ${product.moq_quantity}${product.unit ? ` ${product.unit}` : ''}`
                        : null,
                    ].filter((part): part is string => typeof part === 'string' && part !== '');

                    return (
                      <li
                        key={String(product.id)}
                        className="flex flex-wrap items-start justify-between gap-3 border-t border-slate-100 pt-3"
                      >
                        <div className="min-w-0">
                          <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">
                            {product.name}
                            <span
                              className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                archived
                                  ? 'bg-slate-200 text-slate-700'
                                  : 'bg-emerald-100 text-emerald-800'
                              }`}
                            >
                              {archived
                                ? t(locale, 'trade.catalog.archived')
                                : t(locale, 'trade.catalog.active')}
                            </span>
                          </p>
                          {meta.length ? (
                            <p className="mt-1 text-xs text-slate-500">{meta.join(' · ')}</p>
                          ) : null}
                          <p className="mt-1 text-xs font-medium text-sky-700">
                            {formatPrice(product)}
                          </p>
                        </div>

                        {!archived ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              if (window.confirm(t(locale, 'trade.catalog.archiveConfirm'))) {
                                void archiveProduct(product);
                              }
                            }}
                          >
                            {t(locale, 'trade.catalog.archive')}
                          </Button>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              )}
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
