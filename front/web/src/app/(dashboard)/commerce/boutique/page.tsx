'use client';

/**
 * Espace vendeur Commerce (BC-17 RETAIL) — « Boutique en ligne » (#7810,
 * marketplace Leopardo Marché, spec docs/specifications/
 * MARKETPLACE_RETAIL_PUBLIC.md §5). Trois panneaux :
 *
 *   1. Réglages boutique : opt-in marketplace + fiche publique (nom,
 *      description, ville, téléphone, email, devise) via
 *      GET/PUT `/retail/online/settings` (create-or-update).
 *   2. Produits publiés : bascule « En ligne » par produit
 *      (`POST /retail/products/{id}/publish-online` · `/unpublish-online`,
 *      champ `online_visible`, indépendant du `status`).
 *   3. Commandes web : liste `GET /retail/online/orders` (filtre
 *      `fulfillment_status`), détail, actions `confirm|ready|ship|deliver|
 *      cancel` avec confirmation utilisateur ; un 422 `INVALID_TRANSITION`
 *      (état périmé côté serveur) affiche un message dédié et recharge la
 *      liste. Machine d'états pure dans `commerce-format.ts` (testée).
 *
 * Patterns repris de #7675/#7718 : `apiFetch`, minor units (`formatMinor`),
 * i18n `t()` sur les registres partagés (clés `commerce.shop.*`), styles
 * Tailwind des pages `/commerce/*`.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Globe } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { readApiError } from '@/components/commerce/CommerceCrudTable';
import { apiFetch } from '@/lib/api-client';
import {
  FULFILLMENT_STATUSES,
  formatMinor,
  fulfillmentActions,
  orderPaymentStatus,
  type FulfillmentAction,
  type OrderPayment,
} from '@/lib/commerce-format';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** Devises acceptées par le backend (RetailPricePolicy::CURRENCIES). */
const CURRENCIES = ['XOF', 'XAF', 'DZD', 'MAD', 'EUR', 'USD'] as const;

/** Payload de GET/PUT `/retail/online/settings` (enveloppe `{ data }`). */
type OnlineSettings = {
  enabled: boolean;
  shop_name: string | null;
  shop_description: string | null;
  city: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  currency: string | null;
};

/** Payload de RetailProductController (champ #7810 : `online_visible`). */
type RetailProduct = {
  id: number;
  name: string;
  sku: string;
  price_minor: number;
  currency: string;
  status: string;
  online_visible?: boolean;
};

/** Commande web (`/retail/online/orders`) — liste + détail. */
type OnlineOrderItem = {
  id: number;
  product_name: string;
  quantity: number | string;
  unit_price_minor: number;
  line_total_minor: number;
};

type OnlineOrder = {
  id: number;
  reference: string;
  fulfillment_status: string;
  total_minor: number;
  currency: string;
  customer_name: string | null;
  customer_phone: string | null;
  customer_email: string | null;
  delivery_address: string | null;
  delivery_city: string | null;
  delivery_notes: string | null;
  /** Handoff BC-26 (#7811) : référence DLV-… de la livraison créée à la confirmation. */
  delivery_reference?: string | null;
  created_at: string | null;
  items?: OnlineOrderItem[];
  /** Paiements capturés (#7812) : en ligne (PSP) ou encaissements. */
  payments?: OrderPayment[];
};

type PaginationMeta = { current_page: number; last_page: number; total: number };

type SettingsFormState = {
  enabled: boolean;
  shop_name: string;
  shop_description: string;
  city: string;
  contact_phone: string;
  contact_email: string;
  currency: string;
};

const EMPTY_SETTINGS_FORM: SettingsFormState = {
  enabled: false,
  shop_name: '',
  shop_description: '',
  city: '',
  contact_phone: '',
  contact_email: '',
  currency: 'DZD',
};

function fulfillmentLabel(locale: AppLocale, status: string): string {
  return t(locale, `commerce.shop.status.${status}`, status);
}

function fulfillmentBadgeClass(status: string): string {
  switch (status) {
    case 'pending':
      return 'bg-amber-100 text-amber-700';
    case 'confirmed':
      return 'bg-cyan-100 text-cyan-700';
    case 'ready':
      return 'bg-blue-100 text-blue-700';
    case 'shipped':
      return 'bg-indigo-100 text-indigo-700';
    case 'delivered':
      return 'bg-emerald-100 text-emerald-700';
    case 'cancelled':
      return 'bg-slate-200 text-slate-600';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

/** Panneau 1 — réglages boutique (GET/PUT /retail/online/settings). */
function SettingsPanel() {
  const locale = getPreferredLocale();
  const [form, setForm] = useState<SettingsFormState>(EMPTY_SETTINGS_FORM);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch('/retail/online/settings');
      if (res.status === 404) {
        // Boutique jamais configurée : formulaire vierge (create-or-update PUT).
        setForm(EMPTY_SETTINGS_FORM);
        return;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: OnlineSettings };
      const settings = payload.data;
      if (settings) {
        setForm({
          enabled: settings.enabled === true,
          shop_name: settings.shop_name ?? '',
          shop_description: settings.shop_description ?? '',
          city: settings.city ?? '',
          contact_phone: settings.contact_phone ?? '',
          contact_email: settings.contact_email ?? '',
          currency: settings.currency ?? 'DZD',
        });
      }
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const submit = async () => {
    if (!form.shop_name.trim()) {
      setError(t(locale, 'commerce.form.required', 'Ce champ est obligatoire.'));
      return;
    }
    setSaving(true);
    setError('');
    setSaved(false);
    try {
      const payload = {
        enabled: form.enabled,
        shop_name: form.shop_name.trim(),
        shop_description: form.shop_description.trim() === '' ? null : form.shop_description.trim(),
        city: form.city.trim() === '' ? null : form.city.trim(),
        contact_phone: form.contact_phone.trim() === '' ? null : form.contact_phone.trim(),
        contact_email: form.contact_email.trim() === '' ? null : form.contact_email.trim(),
        currency: form.currency,
      };
      const res = await apiFetch('/retail/online/settings', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.saveFailed', "Échec de l'enregistrement."));
      }
      setSaved(true);
      await load();
    } catch (e) {
      setError(
        e instanceof Error && e.message
          ? e.message
          : t(locale, 'commerce.error.saveFailed', "Échec de l'enregistrement."),
      );
    } finally {
      setSaving(false);
    }
  };

  const updateForm = (name: keyof SettingsFormState, value: string | boolean) =>
    setForm((prev) => ({ ...prev, [name]: value }));

  if (loading) {
    return (
      <p className="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
        {t(locale, 'commerce.loading', 'Chargement…')}
      </p>
    );
  }

  return (
    <div className="max-w-2xl space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-bold text-slate-900">
          {t(locale, 'commerce.shop.settings.title', 'Réglages de la boutique')}
        </h2>
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            form.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'
          }`}
        >
          {form.enabled
            ? t(locale, 'commerce.shop.settings.enabledBadge', 'Activée')
            : t(locale, 'commerce.shop.settings.disabledBadge', 'Désactivée')}
        </span>
      </div>

      {error ? <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      {saved ? (
        <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
          {t(locale, 'commerce.shop.settings.saved', 'Réglages enregistrés.')}
        </p>
      ) : null}

      <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <label className="flex items-start gap-3 text-sm">
          <input
            type="checkbox"
            checked={form.enabled}
            onChange={(e) => updateForm('enabled', e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
          />
          <span>
            <span className="block font-medium text-slate-700">
              {t(locale, 'commerce.shop.settings.enabled', 'Boutique activée sur la marketplace')}
            </span>
            <span className="block text-xs text-slate-500">
              {t(
                locale,
                'commerce.shop.settings.enabledHint',
                "Tant que la boutique est désactivée, aucun de vos produits n'est visible sur la marketplace.",
              )}
            </span>
          </span>
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'commerce.shop.settings.name', 'Nom de la boutique')}{' '}
            <span className="text-red-500">*</span>
          </span>
          <input
            type="text"
            value={form.shop_name}
            maxLength={200}
            onChange={(e) => updateForm('shop_name', e.target.value)}
            className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          />
        </label>

        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'commerce.shop.settings.description', 'Description')}
          </span>
          <textarea
            value={form.shop_description}
            rows={3}
            maxLength={10000}
            onChange={(e) => updateForm('shop_description', e.target.value)}
            className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          />
        </label>

        <div className="grid grid-cols-2 gap-3">
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'commerce.shop.settings.city', 'Ville')}
            </span>
            <input
              type="text"
              value={form.city}
              maxLength={120}
              onChange={(e) => updateForm('city', e.target.value)}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'commerce.shop.settings.currency', 'Devise')}
            </span>
            <select
              value={form.currency}
              onChange={(e) => updateForm('currency', e.target.value)}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            >
              {CURRENCIES.map((currency) => (
                <option key={currency} value={currency}>
                  {currency}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'commerce.shop.settings.phone', 'Téléphone de contact')}
            </span>
            <input
              type="tel"
              value={form.contact_phone}
              maxLength={40}
              onChange={(e) => updateForm('contact_phone', e.target.value)}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'commerce.shop.settings.email', 'Email de contact')}
            </span>
            <input
              type="email"
              value={form.contact_email}
              maxLength={190}
              onChange={(e) => updateForm('contact_email', e.target.value)}
              className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
        </div>

        <div className="flex justify-end">
          <button
            type="button"
            onClick={() => void submit()}
            disabled={saving}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
          >
            {saving
              ? t(locale, 'commerce.form.saving', 'Enregistrement…')
              : t(locale, 'commerce.action.save', 'Enregistrer')}
          </button>
        </div>
      </div>
    </div>
  );
}

/** Panneau 2 — bascule « En ligne » des produits publiés. */
function ProductsPanel() {
  const locale = getPreferredLocale();
  const [products, setProducts] = useState<RetailProduct[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: '15',
        status: 'published',
      });
      const res = await apiFetch(`/retail/products?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: RetailProduct[]; meta?: PaginationMeta };
      setProducts(Array.isArray(payload.data) ? payload.data : []);
      setMeta(payload.meta ?? null);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [page, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggleOnline = async (product: RetailProduct) => {
    setActing(true);
    setError('');
    try {
      const action = product.online_visible === true ? 'unpublish-online' : 'publish-online';
      const res = await apiFetch(`/retail/products/${product.id}/${action}`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
    } finally {
      setActing(false);
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-bold text-slate-900">
          {t(locale, 'commerce.shop.products.title', 'Produits publiés')}
        </h2>
        <p className="text-sm text-slate-500">
          {t(
            locale,
            'commerce.shop.products.hint',
            "Un produit n'apparaît sur la marketplace que s'il est publié, mis en ligne et que la boutique est activée.",
          )}
        </p>
        {error ? <p className="mt-1 text-sm text-red-600">{error}</p> : null}
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.products.name', 'Nom')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">SKU</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.products.price', 'Prix')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.shop.products.online', 'En ligne')}
              </th>
              <th className="px-4 py-3 text-end font-semibold text-slate-700">
                {t(locale, 'commerce.table.actions', 'Actions')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.loading', 'Chargement…')}
                </td>
              </tr>
            ) : products.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.shop.products.empty', 'Aucun produit publié.')}
                </td>
              </tr>
            ) : (
              products.map((product) => (
                <tr key={product.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{product.name}</td>
                  <td className="px-4 py-3 text-slate-700">{product.sku}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {formatMinor(locale, product.price_minor, product.currency)}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                        product.online_visible === true
                          ? 'bg-emerald-100 text-emerald-700'
                          : 'bg-slate-200 text-slate-600'
                      }`}
                    >
                      {product.online_visible === true
                        ? t(locale, 'commerce.shop.products.online', 'En ligne')
                        : t(locale, 'commerce.shop.products.offline', 'Hors ligne')}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-end">
                    <button
                      type="button"
                      disabled={acting}
                      className="font-medium text-cyan-700 hover:text-cyan-800 disabled:opacity-50"
                      onClick={() => void toggleOnline(product)}
                    >
                      {product.online_visible === true
                        ? t(locale, 'commerce.shop.products.takeOffline', 'Retirer')
                        : t(locale, 'commerce.shop.products.putOnline', 'Mettre en ligne')}
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between text-sm text-slate-600">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            {t(locale, 'commerce.pagination.previous', 'Précédent')}
          </button>
          <span>
            {t(locale, 'commerce.pagination.page', 'Page')} {meta.current_page} / {meta.last_page} —{' '}
            {meta.total} {t(locale, 'commerce.pagination.items', 'éléments')}
          </span>
          <button
            type="button"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            {t(locale, 'commerce.pagination.next', 'Suivant')}
          </button>
        </div>
      ) : null}
    </div>
  );
}

/** Panneau 3 — commandes web (liste, détail, transitions de suivi). */
function OrdersPanel() {
  const locale = getPreferredLocale();
  const [orders, setOrders] = useState<OnlineOrder[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);
  const [error, setError] = useState('');
  const [detail, setDetail] = useState<OnlineOrder | null>(null);
  const [detailError, setDetailError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '15' });
      if (statusFilter) params.set('fulfillment_status', statusFilter);
      const res = await apiFetch(`/retail/online/orders?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: OnlineOrder[]; meta?: PaginationMeta };
      setOrders(Array.isArray(payload.data) ? payload.data : []);
      setMeta(payload.meta ?? null);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDetail = async (order: OnlineOrder) => {
    setDetailError('');
    setDetail(order);
    try {
      const res = await apiFetch(`/retail/online/orders/${order.id}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: OnlineOrder };
      if (payload.data) setDetail(payload.data);
    } catch {
      setDetailError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    }
  };

  const actionLabel = useCallback(
    (action: FulfillmentAction): string =>
      t(locale, `commerce.shop.action.${action}`, action),
    [locale],
  );

  const applyAction = async (order: OnlineOrder, action: FulfillmentAction) => {
    const prompt = t(locale, `commerce.shop.confirmPrompt.${action}`, actionLabel(action));
    if (!window.confirm(prompt)) return;
    setActing(true);
    setError('');
    setDetailError('');
    try {
      const res = await apiFetch(`/retail/online/orders/${order.id}/${action}`, { method: 'POST' });
      if (!res.ok) {
        if (res.status === 422) {
          // 422 INVALID_TRANSITION : l'état a changé côté serveur — message
          // dédié + rechargement de la liste (l'UI se resynchronise).
          const message = t(
            locale,
            'commerce.shop.orders.invalidTransition',
            "Transition impossible : la commande a déjà changé d'état. La liste a été rechargée.",
          );
          if (detail) setDetailError(message);
          else setError(message);
          await load();
          if (detail) await openDetail(order);
          return;
        }
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
      }
      await load();
      if (detail) await openDetail(order);
    } catch (e) {
      const message =
        e instanceof Error && e.message
          ? e.message
          : t(locale, 'commerce.error.actionFailed', "L'action a échoué.");
      if (detail) setDetailError(message);
      else setError(message);
    } finally {
      setActing(false);
    }
  };

  const statusOptions = useMemo(
    () => [
      { value: '', label: t(locale, 'commerce.shop.orders.allStatuses', 'Tous les statuts') },
      ...FULFILLMENT_STATUSES.map((status) => ({
        value: status,
        label: fulfillmentLabel(locale, status),
      })),
    ],
    [locale],
  );

  /**
   * Facture PDF (#7813) : `GET /retail/orders/{id}/invoice` assigne le
   * numéro légal FAC-… à la première génération (immuable au rejeu) —
   * téléchargement blob, pattern billing/page.tsx.
   */
  const downloadInvoice = async (order: OnlineOrder) => {
    setDetailError('');
    try {
      const res = await apiFetch(`/retail/orders/${order.id}/invoice`);
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(
          msg ?? t(locale, 'commerce.shop.orders.invoiceError', 'La facture ne peut pas être générée.'),
        );
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `facture-${order.reference}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (e) {
      setDetailError(
        e instanceof Error && e.message
          ? e.message
          : t(locale, 'commerce.shop.orders.invoiceError', 'La facture ne peut pas être générée.'),
      );
    }
  };

  const renderActions = (order: OnlineOrder) =>
    fulfillmentActions(order.fulfillment_status).map((action) => (
      <button
        key={action}
        type="button"
        disabled={acting}
        onClick={() => void applyAction(order, action)}
        className={`font-medium disabled:opacity-50 ${
          action === 'cancel'
            ? 'text-red-500 hover:text-red-700'
            : 'text-emerald-700 hover:text-emerald-800'
        }`}
      >
        {actionLabel(action)}
      </button>
    ));

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-bold text-slate-900">
            {t(locale, 'commerce.shop.orders.title', 'Commandes web')}
          </h2>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
        </div>
        <select
          value={statusFilter}
          onChange={(e) => {
            setPage(1);
            setStatusFilter(e.target.value);
          }}
          aria-label={t(locale, 'commerce.shop.orders.statusFilter', 'Filtrer par statut de suivi')}
          className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
        >
          {statusOptions.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.shop.orders.reference', 'Référence')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.shop.orders.customer', 'Client')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.shop.orders.city', 'Ville')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.shop.orders.total', 'Total')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.shop.orders.fulfillment', 'Suivi')}
              </th>
              <th className="px-4 py-3 text-end font-semibold text-slate-700">
                {t(locale, 'commerce.table.actions', 'Actions')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.loading', 'Chargement…')}
                </td>
              </tr>
            ) : orders.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.shop.orders.empty', 'Aucune commande web.')}
                </td>
              </tr>
            ) : (
              orders.map((order) => (
                <tr key={order.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{order.reference}</td>
                  <td className="px-4 py-3 text-slate-700">{order.customer_name ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-700">{order.delivery_city ?? '—'}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {formatMinor(locale, order.total_minor, order.currency)}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${fulfillmentBadgeClass(order.fulfillment_status)}`}
                    >
                      {fulfillmentLabel(locale, order.fulfillment_status)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-end">
                    <div className="flex flex-wrap justify-end gap-3">
                      <button
                        type="button"
                        className="font-medium text-cyan-700 hover:text-cyan-800"
                        onClick={() => void openDetail(order)}
                      >
                        {t(locale, 'commerce.shop.orders.detail', 'Détail')}
                      </button>
                      {renderActions(order)}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between text-sm text-slate-600">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            {t(locale, 'commerce.pagination.previous', 'Précédent')}
          </button>
          <span>
            {t(locale, 'commerce.pagination.page', 'Page')} {meta.current_page} / {meta.last_page} —{' '}
            {meta.total} {t(locale, 'commerce.pagination.items', 'éléments')}
          </span>
          <button
            type="button"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            {t(locale, 'commerce.pagination.next', 'Suivant')}
          </button>
        </div>
      ) : null}

      {detail ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
          role="dialog"
          aria-modal="true"
        >
          <div className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <div className="mb-4 flex items-center justify-between gap-3">
              <h3 className="text-lg font-bold text-slate-900">
                {t(locale, 'commerce.shop.orders.detailTitle', 'Commande')} {detail.reference}
              </h3>
              <span
                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${fulfillmentBadgeClass(detail.fulfillment_status)}`}
              >
                {fulfillmentLabel(locale, detail.fulfillment_status)}
              </span>
            </div>

            {detailError ? (
              <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{detailError}</p>
            ) : null}

            <div className="space-y-4 text-sm">
              <div>
                <h4 className="font-semibold text-slate-700">
                  {t(locale, 'commerce.shop.orders.customer', 'Client')}
                </h4>
                <p className="text-slate-700">{detail.customer_name ?? '—'}</p>
                <p className="text-slate-500">
                  {t(locale, 'commerce.shop.orders.phone', 'Téléphone')} : {detail.customer_phone ?? '—'}
                </p>
                <p className="text-slate-500">
                  {t(locale, 'commerce.shop.orders.email', 'Email')} : {detail.customer_email ?? '—'}
                </p>
              </div>

              <div>
                <h4 className="font-semibold text-slate-700">
                  {t(locale, 'commerce.shop.orders.deliveryTitle', 'Livraison')}
                </h4>
                <p className="text-slate-700">
                  {t(locale, 'commerce.shop.orders.address', 'Adresse')} : {detail.delivery_address ?? '—'}
                </p>
                <p className="text-slate-500">
                  {t(locale, 'commerce.shop.orders.city', 'Ville')} : {detail.delivery_city ?? '—'}
                </p>
                {detail.delivery_notes ? (
                  <p className="text-slate-500">
                    {t(locale, 'commerce.shop.orders.notes', 'Notes')} : {detail.delivery_notes}
                  </p>
                ) : null}
                {detail.delivery_reference ? (
                  <p className="text-slate-500">
                    {t(locale, 'commerce.shop.orders.deliveryReference', 'Livraison BC-26')} :{' '}
                    <span className="font-semibold text-slate-700">{detail.delivery_reference}</span>
                  </p>
                ) : null}
              </div>

              <div>
                <h4 className="font-semibold text-slate-700">
                  {t(locale, 'commerce.shop.orders.paymentsTitle', 'Paiement')}
                </h4>
                <p className="text-slate-700">
                  {t(
                    locale,
                    `commerce.shop.orders.payment.${orderPaymentStatus(detail.payments, detail.total_minor)}`,
                    orderPaymentStatus(detail.payments, detail.total_minor),
                  )}
                </p>
                {(detail.payments ?? [])
                  .filter((payment) => payment.status === 'captured')
                  .map((payment, index) => (
                    <p key={index} className="text-slate-500">
                      {payment.method} — {formatMinor(locale, payment.amount_minor, payment.currency)}
                      {payment.paid_at ? ` — ${new Date(payment.paid_at).toLocaleString(locale)}` : ''}
                    </p>
                  ))}
              </div>

              <div>
                <h4 className="font-semibold text-slate-700">
                  {t(locale, 'commerce.shop.orders.items', 'Articles')}
                </h4>
                {Array.isArray(detail.items) && detail.items.length > 0 ? (
                  <table className="mt-1 min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                      <tr>
                        <th className="py-1.5 text-start font-semibold text-slate-600">
                          {t(locale, 'commerce.products.name', 'Nom')}
                        </th>
                        <th className="py-1.5 text-start font-semibold text-slate-600">
                          {t(locale, 'commerce.shop.orders.quantity', 'Quantité')}
                        </th>
                        <th className="py-1.5 text-start font-semibold text-slate-600">
                          {t(locale, 'commerce.shop.orders.unitPrice', 'Prix unitaire')}
                        </th>
                        <th className="py-1.5 text-end font-semibold text-slate-600">
                          {t(locale, 'commerce.shop.orders.lineTotal', 'Total ligne')}
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {detail.items.map((item) => (
                        <tr key={item.id}>
                          <td className="py-1.5 text-slate-700">{item.product_name}</td>
                          <td className="py-1.5 text-slate-700">{Number(item.quantity)}</td>
                          <td className="py-1.5 text-slate-700">
                            {formatMinor(locale, item.unit_price_minor, detail.currency)}
                          </td>
                          <td className="py-1.5 text-end text-slate-700">
                            {formatMinor(locale, item.line_total_minor, detail.currency)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                ) : (
                  <p className="text-slate-500">—</p>
                )}
                <p className="mt-2 text-end font-bold text-slate-900">
                  {t(locale, 'commerce.shop.orders.total', 'Total')} :{' '}
                  {formatMinor(locale, detail.total_minor, detail.currency)}
                </p>
              </div>
            </div>

            <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
              <div className="flex flex-wrap gap-3">
                {renderActions(detail)}
                <button
                  type="button"
                  onClick={() => void downloadInvoice(detail)}
                  className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                  {t(locale, 'commerce.shop.orders.invoicePdf', 'Facture PDF')}
                </button>
              </div>
              <button
                type="button"
                onClick={() => {
                  setDetail(null);
                  setDetailError('');
                }}
                className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
              >
                {t(locale, 'commerce.shop.orders.close', 'Fermer')}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

export default function CommerceShopPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'settings' | 'products' | 'orders'>('settings');

  const tabs = [
    { key: 'settings' as const, label: t(locale, 'commerce.shop.tab.settings', 'Réglages') },
    { key: 'products' as const, label: t(locale, 'commerce.shop.tab.products', 'Produits en ligne') },
    { key: 'orders' as const, label: t(locale, 'commerce.shop.tab.orders', 'Commandes web') },
  ];

  return (
    <ModulePageShell
      icon={Globe}
      title={t(locale, 'commerce.shop.pageTitle', 'Boutique en ligne')}
      description={t(
        locale,
        'commerce.shop.pageSubtitle',
        'Activez votre boutique sur la marketplace, publiez vos produits et gérez les commandes web.',
      )}
    >
      <div className="mb-4 flex gap-2">
        {tabs.map((entry) => (
          <button
            key={entry.key}
            type="button"
            onClick={() => setTab(entry.key)}
            className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
              tab === entry.key
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'bg-white text-slate-600 hover:bg-slate-100'
            }`}
          >
            {entry.label}
          </button>
        ))}
      </div>

      {tab === 'settings' ? <SettingsPanel /> : tab === 'products' ? <ProductsPanel /> : <OrdersPanel />}
    </ModulePageShell>
  );
}
