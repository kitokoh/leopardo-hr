'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Banknote, CreditCard, Loader2, LogOut, Pause, Phone, Play, Plus, Printer, RefreshCw, Search, ShoppingCart, Trash2 } from 'lucide-react';
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
import { RESTAURANT_POS_FR } from '@/lib/i18n/restaurant-pos';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * RESTO-705 (#6218) — Prise de commande & encaissement (POS).
 *
 * Flux (spec §4.1) : ouverture de caisse → nouvelle commande → ajout
 * d'articles → soumission → confirmation (cuisine) → addition → encaissement.
 * Tous les montants sont recalculés SERVEUR (jamais acceptés du client) ;
 * les actions idempotentes passent par les endpoints RESTO-401..407.
 *
 * #8449 — offline-first : le catalogue est mis en cache localement (par
 * succursale) et l'encaissement passe par la file hors-ligne partagée.
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

type OrderType = 'dine_in' | 'takeaway' | 'delivery';

const ORDER_TYPE_LABELS: Record<OrderType, string> = {
  dine_in: RESTAURANT_POS_FR.orderTypeDineIn,
  takeaway: RESTAURANT_POS_FR.orderTypeTakeaway,
  delivery: RESTAURANT_POS_FR.orderTypeDelivery,
};

type PaymentMethod = 'cash' | 'card' | 'mobile_money';

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
  const [openingCash, setOpeningCash] = useState('0');
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [countedCash, setCountedCash] = useState('');
  const [varianceReason, setVarianceReason] = useState('');

  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [tables, setTables] = useState<RestaurantTable[]>([]);
  const [orderType, setOrderType] = useState<OrderType>('dine_in');
  const [tableId, setTableId] = useState<number | null>(null);
  const [covers, setCovers] = useState<number>(2);
  const [promotionCode, setPromotionCode] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
  const [tenderedCash, setTenderedCash] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [parkedOrders, setParkedOrders] = useState<
    Array<{
      order: Order;
      bill: Bill | null;
      parkedAt: string;
    }>
  >([]);

  const [order, setOrder] = useState<Order | null>(null);
  const [bill, setBill] = useState<Bill | null>(null);
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

      if (branchId !== null) {
        catalogCache.save(branchId, { categories: nextCategories, products: nextProducts });
      }
    } catch {
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
      const list = Array.isArray(json.data) ? json.data : [];
      setTables(list);
      setTableId((current) => current ?? list[0]?.id ?? null);
    } catch {
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
    async (action: () => Promise<unknown>) => {
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
      const openingMinor = Math.round((parseFloat(openingCash) || 0) * 100);
      const res = await apiFetch('/restaurant/pos-sessions', {
        method: 'POST',
        _idempotent: true,
        body: JSON.stringify({ branch_id: branchId, opening_cash_minor: openingMinor }),
      });
      const json = (await res.json()) as { data: PosSession };
      setSession(json.data);
    });
  }, [branchId, openingCash, runAction]);

  const handleCloseSession = useCallback(() => {
    if (session === null) return;
    void runAction(async () => {
      const countedMinor = Math.round((parseFloat(countedCash) || 0) * 100);
      await apiFetch(`/restaurant/pos-sessions/${session.id}/close`, {
        method: 'POST',
        _idempotent: true,
        body: JSON.stringify({
          counted_cash_minor: countedMinor,
          variance_reason: varianceReason.trim() === '' ? undefined : varianceReason.trim(),
        }),
      });
      setSession(null);
      setOrder(null);
      setBill(null);
      setShowCloseModal(false);
      setCountedCash('');
      setVarianceReason('');
    });
  }, [session, countedCash, varianceReason, runAction]);

  const createOrder = useCallback(() => {
    if (branchId === null) {
      return;
    }
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
      setTenderedCash('');
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
      const code = promotionCode.trim();
      const query = code === '' ? '' : `?promotion_code=${encodeURIComponent(code)}`;
      const res = await apiFetch(`/restaurant/orders/${order.id}/bill${query}`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: Bill };
      setBill(json.data);
      setTenderedCash((json.data.total_minor / 100).toFixed(2));
    });
  }, [order, promotionCode, runAction]);

  const payOrder = useCallback(() => {
    if (order === null || bill === null) {
      return;
    }
    void runAction(async () => {
      const operation = buildPaymentOperation({
        orderId: order.id,
        amountMinor: bill.total_minor,
        providerCode: paymentMethod,
        reference: order.reference,
      });

      const result = await dispatch(operation.kind, operation.payload);

      if (result === 'queued') {
        setError(t(locale, 'offline.queued'));
        return;
      }

      await refreshOrder(order.id);
    });
  }, [order, bill, paymentMethod, dispatch, refreshOrder, runAction, locale]);

  const parkCurrentOrder = useCallback(() => {
    if (order === null) return;
    setParkedOrders((prev) => [
      ...prev,
      {
        order,
        bill,
        parkedAt: new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }),
      },
    ]);
    setOrder(null);
    setBill(null);
  }, [order, bill, locale]);

  const recallParkedOrder = useCallback((index: number) => {
    setParkedOrders((prev) => {
      const target = prev[index];
      if (!target) return prev;
      setOrder(target.order);
      setBill(target.bill);
      return prev.filter((_, i) => i !== index);
    });
  }, []);

  const filteredProducts = useMemo(() => {
    return products.filter((p) => {
      if (selectedCategoryId !== null && p.category_id !== selectedCategoryId) {
        return false;
      }
      if (searchQuery.trim() !== '') {
        const q = searchQuery.toLowerCase().trim();
        return p.name.toLowerCase().includes(q);
      }
      return true;
    });
  }, [products, selectedCategoryId, searchQuery]);

  const groupedProducts = useMemo(() => {
    const grouped = new Map<number | null, Product[]>();
    for (const product of filteredProducts) {
      const key = product.category_id;
      if (!grouped.has(key)) {
        grouped.set(key, []);
      }
      grouped.get(key)?.push(product);
    }
    return grouped;
  }, [filteredProducts]);

  const categoryName = (categoryId: number | null): string => {
    if (categoryId === null) {
      return t(locale, 'restaurant.pos.uncategorized');
    }
    return categories.find((category) => category.id === categoryId)?.name ?? `#${categoryId}`;
  };

  const isOrderPayable = order !== null && !['draft', 'cancelled', 'refunded', 'paid', 'closed'].includes(order.status);

  const tenderedMinor = Math.round((parseFloat(tenderedCash) || 0) * 100);
  const changeDueMinor = bill !== null && tenderedMinor > bill.total_minor ? tenderedMinor - bill.total_minor : 0;

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
            void loadTables();
          }}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
        >
          <RefreshCw className="h-3 w-3" />
          Refresh
        </button>

        {session !== null && (
          <button
            type="button"
            onClick={() => setShowCloseModal(true)}
            className="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-50"
          >
            <LogOut className="h-3 w-3" />
            {t(locale, 'restaurant.pos.closeSession', RESTAURANT_POS_FR.closeSession)}
          </button>
        )}

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
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-sm font-black uppercase tracking-widest text-slate-600">
                {t(locale, 'restaurant.pos.products')}
              </h2>
              {order !== null && (
                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                  {order.reference}
                </span>
              )}
            </div>

            <div className="relative mb-3">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={t(locale, 'restaurant.pos.searchPlaceholder', RESTAURANT_POS_FR.searchPlaceholder)}
                className="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm font-medium shadow-sm focus:border-emerald-400 focus:outline-none"
              />
            </div>

            <div className="mb-4 flex flex-wrap gap-1.5">
              <button
                type="button"
                onClick={() => setSelectedCategoryId(null)}
                className={`rounded-lg px-2.5 py-1 text-xs font-bold transition ${
                  selectedCategoryId === null
                    ? 'bg-emerald-600 text-white shadow-sm'
                    : 'bg-white border border-slate-200 text-slate-600 hover:border-emerald-300'
                }`}
              >
                {t(locale, 'restaurant.pos.allCategories', RESTAURANT_POS_FR.allCategories)}
              </button>
              {categories.map((cat) => (
                <button
                  key={cat.id}
                  type="button"
                  onClick={() => setSelectedCategoryId(cat.id === selectedCategoryId ? null : cat.id)}
                  className={`rounded-lg px-2.5 py-1 text-xs font-bold transition ${
                    selectedCategoryId === cat.id
                      ? 'bg-emerald-600 text-white shadow-sm'
                      : 'bg-white border border-slate-200 text-slate-600 hover:border-emerald-300'
                  }`}
                >
                  {cat.name}
                </button>
              ))}
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
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-600">
                <ShoppingCart className="h-4 w-4 text-emerald-500" />
                {t(locale, 'restaurant.pos.cart')}
              </h2>
              {order !== null && (
                <button
                  type="button"
                  onClick={parkCurrentOrder}
                  className="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-bold text-amber-800 hover:bg-amber-100 transition"
                >
                  <Pause className="h-3 w-3" />
                  {t(locale, 'restaurant.pos.parkOrder', RESTAURANT_POS_FR.parkOrder)}
                </button>
              )}
            </div>

            {parkedOrders.length > 0 && (
              <div className="mb-3 rounded-xl border border-amber-200 bg-amber-50/70 p-2.5 space-y-1.5">
                <span className="text-xs font-bold text-amber-900 uppercase tracking-wide">
                  {t(locale, 'restaurant.pos.parkedOrders', RESTAURANT_POS_FR.parkedOrders)} ({parkedOrders.length})
                </span>
                <div className="space-y-1">
                  {parkedOrders.map((parked, idx) => (
                    <div
                      key={parked.order.id}
                      className="flex items-center justify-between rounded-lg bg-white p-2 text-xs shadow-sm"
                    >
                      <div>
                        <span className="font-bold text-slate-700">{parked.order.reference}</span>
                        <span className="ml-1.5 text-slate-400">{parked.parkedAt}</span>
                      </div>
                      <button
                        type="button"
                        onClick={() => recallParkedOrder(idx)}
                        className="inline-flex items-center gap-1 rounded bg-emerald-600 px-2 py-1 font-bold text-white hover:bg-emerald-700"
                      >
                        <Play className="h-3 w-3" />
                        {t(locale, 'restaurant.pos.recallOrder', RESTAURANT_POS_FR.recallOrder)}
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {session === null ? (
              <div className="space-y-3">
                <p className="text-sm text-slate-500">{t(locale, 'restaurant.pos.noSession')}</p>
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">
                    {t(locale, 'restaurant.pos.openingCashLabel', RESTAURANT_POS_FR.openingCashLabel)}
                  </label>
                  <input
                    type="number"
                    min={0}
                    step="any"
                    value={openingCash}
                    onChange={(e) => setOpeningCash(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                    placeholder="0.00"
                  />
                </div>
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
                        aria-label={t(locale, 'restaurant.pos.table')}
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
                      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm space-y-2">
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
                          <span className="font-mono">{(bill.total_minor / 100).toFixed(2)} {bill.currency}</span>
                        </div>

                        {/* Choix du moyen de paiement */}
                        <div className="pt-2">
                          <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">
                            {t(locale, 'restaurant.pos.paymentMethod', RESTAURANT_POS_FR.paymentMethod)}
                          </label>
                          <div className="grid grid-cols-3 gap-1">
                            {(['cash', 'card', 'mobile_money'] as const).map((method) => (
                              <button
                                key={method}
                                type="button"
                                onClick={() => setPaymentMethod(method)}
                                className={`rounded-lg px-2 py-1.5 text-xs font-bold transition ${
                                  paymentMethod === method
                                    ? 'bg-emerald-700 text-white shadow-sm'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                }`}
                              >
                                {method === 'cash'
                                  ? t(locale, 'restaurant.pos.paymentCash', RESTAURANT_POS_FR.paymentCash)
                                  : method === 'card'
                                  ? t(locale, 'restaurant.pos.paymentCard', RESTAURANT_POS_FR.paymentCard)
                                  : t(locale, 'restaurant.pos.paymentMobile', RESTAURANT_POS_FR.paymentMobile)}
                              </button>
                            ))}
                          </div>
                        </div>

                        {/* Calcul monnaie rendue pour espèces */}
                        {paymentMethod === 'cash' && (
                          <div className="space-y-1 pt-1">
                            <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
                              {t(locale, 'restaurant.pos.tenderedAmount', RESTAURANT_POS_FR.tenderedAmount)}
                            </label>
                            <input
                              type="number"
                              min={0}
                              step="any"
                              value={tenderedCash}
                              onChange={(e) => setTenderedCash(e.target.value)}
                              className="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                            />
                            {changeDueMinor > 0 && (
                              <div className="flex justify-between text-xs font-bold text-emerald-800 bg-emerald-100 rounded-lg px-2.5 py-1">
                                <span>{t(locale, 'restaurant.pos.changeDue', RESTAURANT_POS_FR.changeDue)}</span>
                                <span className="font-mono">{(changeDueMinor / 100).toFixed(2)} {bill.currency}</span>
                              </div>
                            )}
                          </div>
                        )}

                        <button
                          type="button"
                          onClick={payOrder}
                          disabled={acting}
                          className="mt-2 inline-flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 disabled:opacity-50"
                        >
                          <Banknote className="h-4 w-4" />
                          {paymentMethod === 'cash'
                            ? t(locale, 'restaurant.pos.payCash')
                            : `${t(locale, 'restaurant.pos.payWithMethod', RESTAURANT_POS_FR.payWithMethod)} (${
                                paymentMethod === 'card'
                                  ? t(locale, 'restaurant.pos.paymentCard', RESTAURANT_POS_FR.paymentCard)
                                  : t(locale, 'restaurant.pos.paymentMobile', RESTAURANT_POS_FR.paymentMobile)
                              })`}
                        </button>
                      </div>
                    )}
                  </>
                )}

                {order.items.length > 0 && (
                  <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex w-full items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
                  >
                    <Printer className="h-3.5 w-3.5" />
                    {['paid', 'closed'].includes(order.status)
                      ? t(locale, 'restaurant.pos.printReceipt', RESTAURANT_POS_FR.printReceipt)
                      : t(locale, 'restaurant.pos.printBill', RESTAURANT_POS_FR.printBill)}
                  </button>
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

      {/* ── Modal de clôture de caisse ──────────────────────────────────── */}
      {showCloseModal && session && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4">
            <h3 className="text-base font-bold text-slate-800">
              {t(locale, 'restaurant.pos.closeSessionTitle', RESTAURANT_POS_FR.closeSessionTitle)}
            </h3>
            <p className="text-xs text-slate-500">
              {t(locale, 'restaurant.pos.openingCashLabel', RESTAURANT_POS_FR.openingCashLabel)} :{' '}
              <span className="font-mono font-bold">{(session.opening_cash_minor / 100).toFixed(2)}</span>
            </p>
            <div>
              <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">
                {t(locale, 'restaurant.pos.countedCashLabel', RESTAURANT_POS_FR.countedCashLabel)}
              </label>
              <input
                type="number"
                min={0}
                step="any"
                value={countedCash}
                onChange={(e) => setCountedCash(e.target.value)}
                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                placeholder="0.00"
                autoFocus
              />
            </div>
            <div>
              <label className="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">
                {t(locale, 'restaurant.pos.varianceReasonLabel', RESTAURANT_POS_FR.varianceReasonLabel)}
              </label>
              <input
                type="text"
                value={varianceReason}
                onChange={(e) => setVarianceReason(e.target.value)}
                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium focus:border-emerald-400 focus:outline-none"
                placeholder="..."
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setShowCloseModal(false)}
                className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50"
              >
                {t(locale, 'restaurant.pos.cancel', RESTAURANT_POS_FR.cancel)}
              </button>
              <button
                type="button"
                disabled={acting}
                onClick={handleCloseSession}
                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
              >
                {t(locale, 'restaurant.pos.confirmClose', RESTAURANT_POS_FR.confirmClose)}
              </button>
            </div>
          </div>
        </div>
      )}
    </ModulePageShell>
  );
}
