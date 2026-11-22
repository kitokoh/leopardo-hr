'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Globe2, Package, RefreshCw, ShoppingCart, Store, Truck, Users } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { CounterpartyForm } from './components/CounterpartyForm';
import { CounterpartyList } from './components/CounterpartyList';
import type { TradeCounterparty } from './components/types';

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

type TradeTab = 'overview' | 'sourcing' | 'sales' | 'counterparties' | 'catalog' | 'costs';

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
  // Le répertoire des contreparties est l'outil du VENDEUR (et de l'acheteur) :
  // sans lui, une offre ne peut désigner ni client ni fournisseur (#9780).
  { key: 'counterparties', labelKey: 'trade.counterparties.title', icon: Users },
  // Le catalogue est l'outil du VENDEUR : sans lui, il ne peut pas alimenter
  // les lignes de ses offres (`trade_product_id` restait vide).
  { key: 'catalog', labelKey: 'trade.catalog.title', icon: Package },
  { key: 'costs', labelKey: 'trade.costs.title', icon: Truck },
];

/**
 * Champs qu'un client n'envoie JAMAIS pour une contrepartie (#9780).
 *
 * - `verification_status` / `verified_at` / `verified_by_employee_id` : la
 *   vérification est un acte distinct et tracé (`POST .../verify`). Les
 *   accepter ici permettrait de s'auto-déclarer « vérifié » en une requête.
 * - `company_id` : posé par le serveur depuis le contexte tenant (anti-spoof
 *   #7646) — un client ne choisit pas le tenant d'écriture.
 *
 * Le formulaire ne produit déjà aucun de ces champs ; ce filtre est une
 * seconde barrière, appliquée en création comme en édition.
 */
const FORBIDDEN_COUNTERPARTY_FIELDS = [
  'verification_status',
  'verified_at',
  'verified_by_employee_id',
  'company_id',
] as const;

function sanitizeCounterpartyPayload(payload: Record<string, unknown>): Record<string, unknown> {
  const forbidden = new Set<string>(FORBIDDEN_COUNTERPARTY_FIELDS);

  return Object.fromEntries(Object.entries(payload).filter(([key]) => !forbidden.has(key)));
}

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

  // ── Catalogue produits (#9767) ───────────────────────────────────────────
  const [products, setProducts] = useState<TradeProduct[]>([]);
  const [draft, setDraft] = useState<ProductDraft>(EMPTY_DRAFT);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // ── Contreparties (#9780) ────────────────────────────────────────────────
  const [counterparties, setCounterparties] = useState<TradeCounterparty[]>([]);
  const [counterpartyFormOpen, setCounterpartyFormOpen] = useState(false);
  // Contrepartie en cours d'édition : `null` = le formulaire est en création.
  const [counterpartyEditing, setCounterpartyEditing] = useState<TradeCounterparty | null>(null);
  const [counterpartySaving, setCounterpartySaving] = useState(false);
  const [counterpartyError, setCounterpartyError] = useState<string | null>(null);
  const [counterpartyNotice, setCounterpartyNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    setLoading(true);

    try {
      // Chaque appel est indépendant : un module partiellement déployé ne doit
      // pas vider tout l'écran. On tolère l'absence d'une source (404/403) et
      // on affiche « aucune donnée » plutôt qu'une erreur globale.
      const [rfqRes, quoteRes, costRes, metaRes, productRes, counterpartyRes] = await Promise.allSettled([
        apiFetch('/trade/rfqs'),
        apiFetch('/trade/quotes'),
        apiFetch('/trade/cost-estimates'),
        apiFetch('/trade/meta/integrations'),
        apiFetch('/trade/products'),
        apiFetch('/trade/counterparties?per_page=100'),
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

      // Les contreparties sont une source INDÉPENDANTE, comme le catalogue :
      // un 403 sur les RFQ ne doit pas vider le répertoire.
      if (counterpartyRes.status === 'fulfilled') {
        try {
          const payload = await counterpartyRes.value.json();
          setCounterparties(
            Array.isArray(payload?.data) ? (payload.data as TradeCounterparty[]) : [],
          );
        } catch {
          setCounterparties([]);
        }
      } else {
        setCounterparties([]);
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

  /**
   * Création (#9780) ou mise à jour (#9780, édition) d'une contrepartie.
   *
   * Le payload vient du formulaire, qui n'envoie **jamais**
   * `verification_status` ni `company_id` : le premier est un acte distinct
   * (`POST .../verify`), le second est posé par le serveur depuis le contexte
   * tenant. Les envoyer ici serait au mieux ignoré, au pire une faille —
   * `sanitizeCounterpartyPayload` les retire de toute façon, en création comme
   * en édition.
   *
   * En édition, la liste est mise à jour **en place** (remplacement de
   * l'élément) plutôt que rechargée : un `GET` complet ferait perdre le filtre
   * et la recherche en cours, et afficherait un état intermédiaire.
   */
  const submitCounterparty = async (payload: Record<string, unknown>) => {
    setCounterpartyError(null);
    setCounterpartyNotice(null);
    setCounterpartySaving(true);

    const editing = counterpartyEditing;
    const body = sanitizeCounterpartyPayload(payload);

    try {
      const response = await apiFetch(
        editing ? `/trade/counterparties/${editing.id}` : '/trade/counterparties',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify(body),
        },
      );

      const parsed = await response.json().catch(() => null);
      const saved = parsed?.data as TradeCounterparty | undefined;

      // `data` peut être un tableau vide (réponse inattendue) : on n'insère
      // dans la liste qu'un objet réellement identifié. Sans ce garde, une
      // réponse vide insérait `[]` dans la liste et faisait planter le rendu.
      if (saved && saved.id !== undefined && saved.id !== null) {
        setCounterparties((current) =>
          editing
            ? current.map((item) => (item.id === editing.id ? saved : item))
            : [saved, ...current],
        );
      }

      setCounterpartyFormOpen(false);
      setCounterpartyEditing(null);
      setCounterpartyNotice(t(locale, 'trade.counterparties.saved'));
    } catch {
      // Le formulaire reste ouvert et garde la saisie : une erreur réseau ne
      // doit pas faire perdre ce que l'utilisateur vient de taper.
      setCounterpartyError(t(locale, 'trade.counterparties.error'));
    } finally {
      setCounterpartySaving(false);
    }
  };

  /**
   * Ouvre le formulaire en mode édition sur une contrepartie existante.
   *
   * On ne modifie pas l'objet de la liste : le formulaire travaille sur un
   * brouillon, et la liste n'est remplacée qu'après un `PUT` réussi.
   */
  const startEditCounterparty = (counterparty: TradeCounterparty) => {
    setCounterpartyEditing(counterparty);
    setCounterpartyFormOpen(true);
    setCounterpartyError(null);
    setCounterpartyNotice(null);
  };

  /**
   * Suppression douce — le serveur pose `deleted_at`.
   *
   * Aucune suppression physique : les dossiers, RFQ et offres passés
   * référencent la contrepartie, un `DELETE` dur casserait leur historique.
   */
  const deleteCounterparty = async (counterparty: TradeCounterparty) => {
    setCounterpartyError(null);
    setCounterpartyNotice(null);

    try {
      await apiFetch(`/trade/counterparties/${counterparty.id}`, { method: 'DELETE' });
      setCounterparties((current) => current.filter((item) => item.id !== counterparty.id));
      setCounterpartyNotice(t(locale, 'trade.counterparties.deleted'));
    } catch {
      setCounterpartyError(t(locale, 'trade.counterparties.error'));
    }
  };

  /**
   * Vérification — acte réservé à l'admin de la verticale.
   *
   * Le motif est OBLIGATOIRE côté serveur pour `verified`/`rejected` : on le
   * demande ici plutôt que de laisser l'API refuser après coup. Un refus
   * silencieux (annulation du prompt) n'envoie rien.
   */
  const verifyCounterparty = async (counterparty: TradeCounterparty) => {
    setCounterpartyError(null);
    setCounterpartyNotice(null);

    const note = window.prompt(t(locale, 'trade.counterparties.verifyPrompt'));
    if (note === null) return;

    const trimmed = note.trim();
    if (!trimmed) {
      setCounterpartyError(t(locale, 'trade.counterparties.verifyNoteRequired'));
      return;
    }

    try {
      const response = await apiFetch(`/trade/counterparties/${counterparty.id}/verify`, {
        method: 'POST',
        body: JSON.stringify({ status: 'verified', note: trimmed }),
      });

      const body = await response.json().catch(() => null);
      const updated = body?.data as TradeCounterparty | undefined;

      if (updated) {
        setCounterparties((current) =>
          current.map((item) => (item.id === counterparty.id ? updated : item)),
        );
      }
      setCounterpartyNotice(t(locale, 'trade.counterparties.verified'));
    } catch {
      setCounterpartyError(t(locale, 'trade.counterparties.error'));
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

          {tab === 'counterparties' ? (
            <div className="rounded-2xl bg-white/70 px-5 py-4 ring-1 ring-slate-200">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-sm font-semibold text-slate-900">
                    {t(locale, 'trade.counterparties.title')}
                  </h2>
                  <p className="mt-1 text-xs text-slate-500">
                    {t(locale, 'trade.counterparties.subtitle')}
                  </p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    // « Nouvelle contrepartie » repasse TOUJOURS en création :
                    // sans cette remise à zéro, ouvrir le formulaire après une
                    // édition aurait envoyé un `PUT` sur la fiche précédente.
                    setCounterpartyEditing(null);
                    setCounterpartyFormOpen(true);
                    setCounterpartyError(null);
                    setCounterpartyNotice(null);
                  }}
                >
                  <Users className="h-4 w-4" />
                  {t(locale, 'trade.counterparties.new')}
                </Button>
              </div>

              {counterpartyNotice ? (
                <p className="mt-3 rounded-xl bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
                  {counterpartyNotice}
                </p>
              ) : null}

              {counterpartyError ? (
                <p className="mt-3 rounded-xl bg-rose-50 px-4 py-2 text-xs text-rose-700">
                  {counterpartyError}
                </p>
              ) : null}

              {counterpartyFormOpen ? (
                <CounterpartyForm
                  // La clé force un remontage quand on passe de la création à
                  // une édition (ou d'une fiche à une autre) : le brouillon
                  // repart des valeurs de la fiche visée, jamais des restes de
                  // la précédente.
                  key={counterpartyEditing ? `edit-${counterpartyEditing.id}` : 'create'}
                  locale={locale}
                  initial={counterpartyEditing}
                  saving={counterpartySaving}
                  error={null}
                  onSubmit={(payload) => void submitCounterparty(payload)}
                  onCancel={() => {
                    setCounterpartyFormOpen(false);
                    setCounterpartyEditing(null);
                    setCounterpartyError(null);
                  }}
                />
              ) : null}

              <div className="mt-4">
                <CounterpartyList
                  locale={locale}
                  counterparties={counterparties}
                  loading={false}
                  onDelete={(item) => void deleteCounterparty(item)}
                  onVerify={(item) => void verifyCounterparty(item)}
                  onEdit={startEditCounterparty}
                  // La vérification est réservée à l'admin de la verticale
                  // (`trade.role:admin`). On n'affiche pas un bouton qui
                  // renverrait 403 : le serveur reste l'autorité, l'UI ne
                  // promet pas une action qu'elle ne peut pas tenir.
                  canVerify={false}
                />
              </div>
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
