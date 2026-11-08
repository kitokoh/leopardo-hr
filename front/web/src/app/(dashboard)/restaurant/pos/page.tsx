'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Banknote, Loader2, Plus, RefreshCw, ShoppingCart, Trash2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { ConnectionIndicator } from '@/components/offline/ConnectionIndicator';
import { useOfflineSync } from '@/lib/offline/use-offline-sync';
import {
  PosCatalogCache,
  buildPaymentOperation,
  createPosSender,
  type KeyValueStorage,
} from '@/lib/offline/pos-offline';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * RESTO-705 (#6218) — Prise de commande & encaissement (POS).
 *
 * Flux (spec §4.1) : ouverture de caisse → nouvelle commande → ajout
 * d'articles → soumission → confirmation (cuisine) → addition → encaissement
 * espèces. Tous les montants sont recalculés SERVEUR (jamais acceptés du
 * client) ; les actions idempotentes passent par les endpoints RESTO-401..407.
 *
 * #8449 — offline-first : le catalogue est mis en cache localement (par
 * succursale) et l'encaissement passe par la file hors-ligne partagée. Hors
 * réseau, le catalogue reste encaissable et le règlement est mis en file avec
 * une clé d'idempotence stable → zéro perte, zéro doublon au retour du réseau.
 */

interface PosSession {
  id: number;
  branch_id: number;
  status: string;
  opening_cash_minor: number;
}

interface RestaurantBranch {
  id: number;
  name: string;
  code: string;
}

interface Category {
  id: number;
  name: string;
}

interface Product {
  id: number;
  name: string;
  code: string;
  price_minor: number;
  currency: string;
  category_id: number | null;
  is_available: boolean;
}

/**
 * #9210 — Type de commande. Le POS web envoyait `order_type: 'dine_in'` et
 * `covers: 1` en dur : le restaurateur ne pouvait ni vendre a emporter, ni
 * livrer, ni declarer le nombre de couverts. Ces valeurs sont desormais
 * choisies en caisse et transmises au serveur.
 */
type OrderType = 'dine_in' | 'takeaway' | 'delivery';

const ORDER_TYPE_LABELS: Record<OrderType, string> = {
  dine_in: 'Sur place',
  takeaway: 'A emporter',
  delivery: 'Livraison',
};

interface RestaurantTable {
  id: number;
  label: string;
  seats: number;
  zone_id: number | null;
  is_active?: boolean;
}

interface OrderItem {
  id: number;
  product_id: number;
  name?: string | null;
  quantity: number;
  line_total_minor: number;
  status: string;
}

interface Order {
  id: number;
  reference: string;
  status: string;
  items: OrderItem[];
}

interface Bill {
  subtotal_minor: number;
  tax_minor: number;
  discount_minor: number;
  total_minor: number;
  currency: string;
}

const ORDER_STATUS_LABELS: Record<string, string> = {
  draft: 'Brouillon',
  open: 'Ouverte',
  in_preparation: 'En cuisine',
  ready: 'Prête',
  served: 'Servie',
  paid: 'Payée',
  closed: 'Clôturée',
  cancelled: 'Annulée',
  refunded: 'Remboursée',
};

export default function RestaurantPosPage() {
  const locale = getPreferredLocale();

  // #8449 — cache catalogue local (localStorage en navigateur, mémoire sinon).
  const catalogCache = useMemo(() => {
    const storage: KeyValueStorage =
      typeof window !== 'undefined' && window.localStorage
        ? window.localStorage
        : {
            getItem: () => null,
            setItem: () => undefined,
            removeItem: () => undefined,
          };
    return new PosCatalogCache<{ categories: Category[]; products: Product[] }>(storage);
  }, []);

  // #8449 — envoi réel d'une opération POS (encaissement).
  const sendPosOperation = useMemo(
    () =>
      createPosSender(async (path, body) => {
        await apiFetch(path, {
          method: 'POST',
          _idempotent: true,
          body: JSON.stringify(body),
        });
      }),
    [],
  );
  const { connectionState, pendingCount, dispatch, flush } = useOfflineSync(sendPosOperation);
  const [branches, setBranches] = useState<RestaurantBranch[]>([]);
  const [branchId, setBranchId] = useState<number | null>(null);
  const [session, setSession] = useState<PosSession | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [order, setOrder] = useState<Order | null>(null);
  const [bill, setBill] = useState<Bill | null>(null);
  // #9210 — choix de caisse : type de commande, table, couverts, code promo.
  const [orderType, setOrderType] = useState<OrderType>('dine_in');
  const [tableId, setTableId] = useState<number | null>(null);
  const [covers, setCovers] = useState(1);
  const [promotionCode, setPromotionCode] = useState('');
  const [tables, setTables] = useState<RestaurantTable[]>([]);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadCatalog = useCallback(async () => {
    try {
      const [catRes, prodRes] = await Promise.all([
        apiFetch('/restaurant/categories?per_page=200', { _cacheBust: true }),
        apiFetch('/restaurant/products?per_page=500', { _cacheBust: true }),
      ]);
      const cats = (await catRes.json()) as { data: Category[] };
      const prods = (await prodRes.json()) as { data: Product[] };
      const nextCategories = Array.isArray(cats.data) ? cats.data : [];
      const nextProducts = (Array.isArray(prods.data) ? prods.data : []).filter(
        (product) => product.is_available !== false,
      );
      setCategories(nextCategories);
      setProducts(nextProducts);

      // #8449 — le catalogue fraîchement chargé alimente le cache local, pour
      // que le POS reste encaissable si le réseau tombe en plein service.
      if (branchId !== null) {
        catalogCache.save(branchId, { categories: nextCategories, products: nextProducts });
      }
    } catch {
      // #8449 — hors ligne : on sert le dernier catalogue connu au lieu d'un
      // écran vide. On ne signale une erreur que s'il n'y a AUCUN cache.
      const cached = branchId === null ? null : catalogCache.load(branchId);
      if (cached !== null) {
        setCategories(cached.data.categories);
        setProducts(cached.data.products);
        return;
      }
      setError(t(locale, 'restaurant.pos.loadError'));
    }
  }, [branchId, catalogCache, locale]);

  const loadSession = useCallback(async () => {
    if (branchId === null) {
      setSession(null);
      return;
    }
    try {
      const res = await apiFetch(`/restaurant/pos-sessions/current?branch_id=${branchId}`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: PosSession | null };
      setSession(json.data ?? null);
    } catch {
      setError(t(locale, 'restaurant.pos.loadError'));
    }
  }, [branchId, locale]);

  const loadBranches = useCallback(async () => {
    try {
      const res = await apiFetch('/restaurant/branches?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data: RestaurantBranch[] };
      const list = Array.isArray(json.data) ? json.data : [];
      setBranches(list);
      setBranchId((current) => current ?? list[0]?.id ?? null);
    } catch {
      setError(t(locale, 'restaurant.pos.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  /**
   * #9210 — Plan de salle de la succursale : sans lui, une commande `dine_in`
   * ne peut pas etre rattachee a une table (le serveur la refuse).
   */
  const loadTables = useCallback(async () => {
    if (branchId === null) {
      setTables([]);
      return;
    }
    try {
      const res = await apiFetch(`/restaurant/tables?branch_id=${branchId}&per_page=200`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: RestaurantTable[] };
      const list = (Array.isArray(json.data) ? json.data : []).filter(
        (table) => table.is_active !== false,
      );
      setTables(list);
      setTableId((current) =>
        current !== null && list.some((table) => table.id === current) ? current : null,
      );
    } catch {
      // Le plan de salle est secondaire : son echec ne doit pas bloquer la
      // vente a emporter ni la livraison.
      setTables([]);
    }
  }, [branchId]);

  useEffect(() => {
    void loadBranches();
    void loadCatalog();
  }, [loadBranches, loadCatalog]);

  useEffect(() => {
    void loadSession();
  }, [loadSession]);

  useEffect(() => {
    void loadTables();
  }, [loadTables]);

  const refreshOrder = useCallback(
    async (orderId: number) => {
      const res = await apiFetch(`/restaurant/orders/${orderId}`, { _cacheBust: true });
      const json = (await res.json()) as { data: Order };
      setOrder(json.data);
      return json.data;
    },
    [],
  );

  const runAction = useCallback(
    async (action: () => Promise<unknown>, successMessage?: string) => {
      setActing(true);
      setError(null);
      try {
        await action();
      } catch {
        setError(t(locale, 'restaurant.pos.actionError'));
      } finally {
        setActing(false);
      }
    },
    [locale],
  );

  const openSession = useCallback(() => {
    if (branchId === null) {
      return;
    }
    void runAction(async () => {
      const res = await apiFetch('/restaurant/pos-sessions', {
        method: 'POST',
        _idempotent: true,
        body: JSON.stringify({ branch_id: branchId, opening_cash_minor: 0 }),
      });
      const json = (await res.json()) as { data: PosSession };
      setSession(json.data);
    });
  }, [branchId, runAction]);

  const createOrder = useCallback(() => {
    if (branchId === null) {
      return;
    }
    // #9210 — une commande sur place exige une table (meme regle que
    // StoreRestaurantOrderRequest) : on le dit avant l'appel plutot que de
    // laisser le serveur renvoyer une 422 incomprehensible en plein service.
    if (orderType === 'dine_in' && tableId === null) {
      setError(t(locale, 'restaurant.pos.tableRequired'));
      return;
    }
    void runAction(async () => {
      const res = await apiFetch('/restaurant/orders', {
        method: 'POST',
        _idempotent: true,
        body: JSON.stringify({
          branch_id: branchId,
          order_type: orderType,
          table_id: orderType === 'dine_in' ? tableId : null,
          covers: orderType === 'dine_in' ? covers : 1,
        }),
      });
      const json = (await res.json()) as { data: Order };
      setOrder(json.data);
      setBill(null);
    });
  }, [branchId, orderType, tableId, covers, runAction, locale]);

  const addItem = useCallback(
    (productId: number) => {
      if (order === null) {
        return;
      }
      void runAction(async () => {
        await apiFetch(`/restaurant/orders/${order.id}/items`, {
          method: 'POST',
          _idempotent: true,
          body: JSON.stringify({ product_id: productId, quantity: 1 }),
        });
        await refreshOrder(order.id);
      });
    },
    [order, refreshOrder, runAction],
  );

  const cancelItem = useCallback(
    (itemId: number) => {
      if (order === null) {
        return;
      }
      void runAction(async () => {
        await apiFetch(`/restaurant/orders/${order.id}/items/${itemId}/cancel`, {
          method: 'POST',
          _idempotent: true,
        });
        await refreshOrder(order.id);
      });
    },
    [order, refreshOrder, runAction],
  );

  const transitionOrder = useCallback(
    (action: 'submit' | 'confirm') => {
      if (order === null) {
        return;
      }
      void runAction(async () => {
        await apiFetch(`/restaurant/orders/${order.id}/${action}`, {
          method: 'POST',
          _idempotent: true,
        });
        await refreshOrder(order.id);
      });
    },
    [order, refreshOrder, runAction],
  );

  const loadBill = useCallback(() => {
    if (order === null) {
      return;
    }
    void runAction(async () => {
      // #9210 — le code promo est transmis au serveur, qui recalcule et borne
      // la remise (aucun montant n'est accepte du client).
      const code = promotionCode.trim();
      const query = code === '' ? '' : `?promotion_code=${encodeURIComponent(code)}`;
      const res = await apiFetch(`/restaurant/orders/${order.id}/bill${query}`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: Bill };
      setBill(json.data);
    });
  }, [order, promotionCode, runAction]);

  const payCash = useCallback(() => {
    if (order === null || bill === null) {
      return;
    }
    void runAction(async () => {
      // #8449 — l'encaissement passe par la file hors-ligne : en ligne il part
      // immédiatement, hors ligne il est conservé et rejoué à la reconnexion.
      // La clé d'idempotence (commande + montant + moyen) rend le rejeu sûr.
      const operation = buildPaymentOperation({
        orderId: order.id,
        amountMinor: bill.total_minor,
        providerCode: 'cash',
        reference: order.reference,
      });

      const result = await dispatch(operation.kind, operation.payload);

      if (result === 'queued') {
        setError(t(locale, 'offline.queued'));
        return;
      }

      await refreshOrder(order.id);
    });
  }, [order, bill, dispatch, refreshOrder, runAction, locale]);

  const groupedProducts = useMemo(() => {
    const grouped = new Map<number | null, Product[]>();
    for (const product of products) {
      const key = product.category_id;
      if (!grouped.has(key)) {
        grouped.set(key, []);
      }
      grouped.get(key)?.push(product);
    }
    return grouped;
  }, [products]);

  const categoryName = (categoryId: number | null): string => {
    if (categoryId === null) {
      return t(locale, 'restaurant.pos.uncategorized');
    }
    return categories.find((category) => category.id === categoryId)?.name ?? `#${categoryId}`;
  };

  const isOrderPayable = order !== null && !['draft', 'cancelled', 'refunded', 'paid', 'closed'].includes(order.status);

  return (
    <ModulePageShell
      title={t(locale, 'restaurant.pos.title')}
      subtitle={t(locale, 'restaurant.pos.subtitle')}
      accentClassName="bg-gradient-to-br from-emerald-100 via-white to-white"
    >
      <div className="flex flex-wrap items-center gap-3">
        <label className="text-sm font-bold text-slate-600">{t(locale, 'restaurant.branch')}</label>
        <select
          value={branchId ?? ''}
          onChange={(event) => setBranchId(event.target.value === '' ? null : Number(event.target.value))}
          className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium shadow-sm focus:border-emerald-400 focus:outline-none"
        >
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </select>

        <button
          type="button"
          onClick={() => {
            void loadSession();
            void loadCatalog();
          }}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
        >
          <RefreshCw className="h-3 w-3" />
          Refresh
        </button>

        {/* #8449 — état de connexion et de synchronisation, visible en caisse. */}
        <ConnectionIndicator
          state={connectionState}
          pendingCount={pendingCount}
          onRetry={() => {
            void flush();
          }}
        />
      </div>

      {error !== null && (
        <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
          {error}
        </p>
      )}

      {loading ? (
        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span className="text-sm">{t(locale, 'restaurant.pos.loading')}</span>
        </div>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
          {/* ── Catalogue ─────────────────────────────────────────────── */}
          <section className="rounded-2xl border border-white/20 bg-white/60 p-4 shadow-glass backdrop-blur-xl">
            <div className="mb-3 flex items-center gap-2">
              <h2 className="text-sm font-black uppercase tracking-widest text-slate-600">
                {t(locale, 'restaurant.pos.products')}
              </h2>
              {order !== null && (
                <span className="ml-auto rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                  {order.reference}
                </span>
              )}
            </div>

            {groupedProducts.size === 0 ? (
              <p className="py-6 text-center text-sm text-slate-400">{t(locale, 'restaurant.pos.noProducts')}</p>
            ) : (
              [...groupedProducts.entries()].map(([categoryId, items]) => (
                <div key={categoryId ?? 'none'} className="mb-4">
                  <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                    {categoryName(categoryId)}
                  </h3>
                  <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {items.map((product) => (
                      <button
                        key={product.id}
                        type="button"
                        onClick={() => addItem(product.id)}
                        disabled={order === null || acting}
                        className="flex flex-col items-start gap-1 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-emerald-300 hover:shadow-md disabled:opacity-40"
                      >
                        <span className="text-sm font-bold text-slate-700">{product.name}</span>
                        <span className="text-xs font-mono font-semibold text-emerald-700">
                          {(product.price_minor / 100).toFixed(2)} {product.currency}
                        </span>
                      </button>
                    ))}
                  </div>
                </div>
              ))
            )}
          </section>

          {/* ── Commande en cours ──────────────────────────────────────── */}
          <section className="rounded-2xl border border-white/20 bg-white/60 p-4 shadow-glass backdrop-blur-xl">
            <div className="mb-3 flex items-center gap-2">
              <h2 className="flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-600">
                <ShoppingCart className="h-4 w-4 text-emerald-500" />
                {t(locale, 'restaurant.pos.cart')}
              </h2>
            </div>

            {session === null ? (
              <div className="space-y-3">
                <p className="text-sm text-slate-500">{t(locale, 'restaurant.pos.noSession')}</p>
                <button
                  type="button"
                  onClick={openSession}
                  disabled={acting || branchId === null}
                  className="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                >
                  <Banknote className="h-4 w-4" />
                  {t(locale, 'restaurant.pos.openSession')}
                </button>
              </div>
            ) : order === null ? (
              <div className="space-y-3">
                {/* #9210 — choix du type de commande, de la table et des couverts. */}
                <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                  <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
                    {t(locale, 'restaurant.pos.orderType')}
                  </label>
                  <div className="grid grid-cols-3 gap-1">
                    {(Object.keys(ORDER_TYPE_LABELS) as OrderType[]).map((type) => (
                      <button
                        key={type}
                        type="button"
                        onClick={() => setOrderType(type)}
                        className={`rounded-lg px-2 py-1.5 text-xs font-bold transition ${
                          orderType === type
                            ? 'bg-emerald-600 text-white'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                        }`}
                      >
                        {ORDER_TYPE_LABELS[type]}
                      </button>
                    ))}
                  </div>

                  {orderType === 'dine_in' && (
                    <>
                      <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
                        {t(locale, 'restaurant.pos.table')}
                      </label>
                      <select
                        value={tableId ?? ''}
                        onChange={(event) =>
                          setTableId(event.target.value === '' ? null : Number(event.target.value))
                        }
                        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                      >
                        <option value="">{t(locale, 'restaurant.pos.selectTable')}</option>
                        {tables.map((table) => (
                          <option key={table.id} value={table.id}>
                            {table.label} ({table.seats})
                          </option>
                        ))}
                      </select>

                      <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
                        {t(locale, 'restaurant.pos.covers')}
                      </label>
                      <input
                        type="number"
                        min={1}
                        max={999}
                        value={covers}
                        onChange={(event) =>
                          setCovers(Math.max(1, Math.min(999, Number(event.target.value) || 1)))
                        }
                        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                      />
                    </>
                  )}
                </div>

                <p className="text-sm text-slate-500">{t(locale, 'restaurant.pos.noOrder')}</p>
                <button
                  type="button"
                  onClick={createOrder}
                  disabled={acting}
                  className="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                >
                  <Plus className="h-4 w-4" />
                  {t(locale, 'restaurant.pos.newOrder')}
                </button>
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex items-center justify-between text-xs">
                  <span className="rounded-md bg-slate-900 px-2 py-0.5 font-mono font-bold text-white">
                    {order.reference}
                  </span>
                  <span className="font-bold text-slate-500">
                    {ORDER_STATUS_LABELS[order.status] ?? order.status}
                  </span>
                </div>

                <ul className="space-y-1.5">
                  {order.items.map((item) => (
                    <li key={item.id} className="flex items-center justify-between gap-2 text-sm">
                      <span className="font-medium text-slate-700">
                        {item.quantity} × {item.name ?? `#${item.product_id}`}
                      </span>
                      <span className="flex items-center gap-2 font-mono text-xs text-slate-500">
                        {(item.line_total_minor / 100).toFixed(2)}
                        {item.status === 'active' && (
                          <button
                            type="button"
                            onClick={() => cancelItem(item.id)}
                            disabled={acting}
                            className="text-red-400 transition hover:text-red-600"
                            aria-label={t(locale, 'restaurant.pos.removeItem')}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        )}
                      </span>
                    </li>
                  ))}
                </ul>

                {order.status === 'draft' && (
                  <button
                    type="button"
                    onClick={() => transitionOrder('submit')}
                    disabled={acting || order.items.length === 0}
                    className="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                  >
                    {t(locale, 'restaurant.pos.submit')}
                  </button>
                )}

                {order.status === 'open' && (
                  <button
                    type="button"
                    onClick={() => transitionOrder('confirm')}
                    disabled={acting}
                    className="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-sky-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-sky-700 disabled:opacity-50"
                  >
                    {t(locale, 'restaurant.pos.confirm')}
                  </button>
                )}

                {isOrderPayable && (
                  <>
                    {/* #9210 — code promo : la remise est calculee et bornee serveur. */}
                    <div className="flex items-center gap-2">
                      <input
                        type="text"
                        value={promotionCode}
                        onChange={(event) => setPromotionCode(event.target.value)}
                        placeholder={t(locale, 'restaurant.pos.promotionCode')}
                        className="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                      />
                    </div>

                    <button
                      type="button"
                      onClick={loadBill}
                      disabled={acting}
                      className="inline-flex w-full items-center justify-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    >
                      {t(locale, 'restaurant.pos.bill')}
                    </button>

                    {bill !== null && (
                      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm">
                        <div className="flex justify-between text-slate-500">
                          <span>{t(locale, 'restaurant.pos.subtotal')}</span>
                          <span className="font-mono">{(bill.subtotal_minor / 100).toFixed(2)}</span>
                        </div>
                        <div className="flex justify-between text-slate-500">
                          <span>{t(locale, 'restaurant.pos.tax')}</span>
                          <span className="font-mono">{(bill.tax_minor / 100).toFixed(2)}</span>
                        </div>
                        {bill.discount_minor > 0 && (
                          <div className="flex justify-between text-emerald-700">
                            <span>{t(locale, 'restaurant.pos.discount')}</span>
                            <span className="font-mono">
                              -{(bill.discount_minor / 100).toFixed(2)}
                            </span>
                          </div>
                        )}
                        <div className="mt-1 flex justify-between border-t border-emerald-200 pt-1 font-bold text-emerald-800">
                          <span>{t(locale, 'restaurant.pos.total')}</span>
                          <span className="font-mono">{(bill.total_minor / 100).toFixed(2)}</span>
                        </div>

                        <button
                          type="button"
                          onClick={payCash}
                          disabled={acting}
                          className="mt-3 inline-flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 disabled:opacity-50"
                        >
                          <Banknote className="h-4 w-4" />
                          {t(locale, 'restaurant.pos.payCash')}
                        </button>
                      </div>
                    )}
                  </>
                )}

                {['paid', 'closed'].includes(order.status) && (
                  <p className="rounded-lg bg-emerald-50 px-3 py-2 text-center text-sm font-bold text-emerald-700">
                    {t(locale, 'restaurant.pos.paid')}
                  </p>
                )}

                <button
                  type="button"
                  onClick={createOrder}
                  disabled={acting}
                  className="inline-flex w-full items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-500 transition hover:bg-slate-50 disabled:opacity-50"
                >
                  <Plus className="h-3 w-3" />
                  {t(locale, 'restaurant.pos.newOrder')}
                </button>
              </div>
            )}
          </section>
        </div>
      )}
    </ModulePageShell>
  );
}