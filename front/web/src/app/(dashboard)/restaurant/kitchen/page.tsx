'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { ChefHat, Loader2, RefreshCw, UtensilsCrossed } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * RESTO-707 (#6220) — Écran cuisine : file de commandes temps réel.
 *
 * Affiche les commandes `in_preparation` et `ready` de la branche sélectionnée
 * (file cuisine, spec §5.2 — GET /restaurant/kitchen/orders?branch_id=X) et
 * permet les transitions start/ready (POST .../start, .../ready). La file est
 * rafraîchie par polling (15 s) — le backend interdit toute transition hors
 * workflow (409) et n'expose que les commandes de la branche du cuisinier.
 */

interface KitchenOrderItem {
  id: number;
  product_id: number;
  name: string | null;
  quantity: number;
  line_index: number;
  status: string;
}

interface KitchenOrder {
  id: number;
  reference: string;
  branch_id: number;
  table_id: number | null;
  order_type: string;
  status: string;
  covers: number | null;
  created_at: string;
  items: KitchenOrderItem[];
}

interface RestaurantBranch {
  id: number;
  name: string;
  code: string;
}

const POLLING_MS = 15_000;

export default function RestaurantKitchenPage() {
  const locale = getPreferredLocale();
  const [branches, setBranches] = useState<RestaurantBranch[]>([]);
  const [branchId, setBranchId] = useState<number | null>(null);
  const [orders, setOrders] = useState<KitchenOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [actingOrderId, setActingOrderId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const loadBranches = useCallback(async () => {
    try {
      const res = await apiFetch('/restaurant/branches?per_page=200', { _cacheBust: true });
      const json = (await res.json()) as { data: RestaurantBranch[] };
      const list = Array.isArray(json?.data) ? json.data : [];
      setBranches(list);
      setBranchId((current) => current ?? list[0]?.id ?? null);
    } catch {
      setError(t(locale, 'restaurant.loadError'));
    }
  }, [locale]);

  const loadOrders = useCallback(async () => {
    if (branchId === null) {
      setOrders([]);
      setLoading(false);
      return;
    }

    try {
      const res = await apiFetch(`/restaurant/kitchen/orders?branch_id=${branchId}`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: KitchenOrder[] };
      setOrders(Array.isArray(json?.data) ? json.data : []);
      setError(null);
    } catch {
      setError(t(locale, 'restaurant.loadError'));
    } finally {
      setLoading(false);
    }
  }, [branchId, locale]);

  useEffect(() => {
    void loadBranches();
  }, [loadBranches]);

  useEffect(() => {
    void loadOrders();
    const timer = window.setInterval(() => {
      void loadOrders();
    }, POLLING_MS);

    return () => window.clearInterval(timer);
  }, [loadOrders]);

  const transition = useCallback(
    async (orderId: number, action: 'start' | 'ready') => {
      setActingOrderId(orderId);
      setActionError(null);
      try {
        await apiFetch(`/restaurant/kitchen/orders/${orderId}/${action}`, {
          method: 'POST',
          _idempotent: true,
        });
        await loadOrders();
      } catch {
        setActionError(t(locale, 'restaurant.actionError'));
      } finally {
        setActingOrderId(null);
      }
    },
    [loadOrders, locale],
  );

  const { inPreparation, ready } = useMemo(() => {
    const inPrep: KitchenOrder[] = [];
    const done: KitchenOrder[] = [];
    for (const order of orders) {
      if (order.status === 'in_preparation') {
        inPrep.push(order);
      } else if (order.status === 'ready') {
        done.push(order);
      }
    }
    return { inPreparation: inPrep, ready: done };
  }, [orders]);

  // Les commandes « ready » attendent le service : aucune action cuisine.
  const readyAction: 'start' | 'ready' | null = null;

  const orderTypeLabel = (orderType: string): string =>
    orderType === 'takeaway'
      ? t(locale, 'restaurant.takeaway')
      : orderType === 'delivery'
        ? t(locale, 'restaurant.delivery')
        : t(locale, 'restaurant.dineIn');

  const renderColumn = (
    titleKey: 'inPreparation' | 'ready',
    list: KitchenOrder[],
    action: 'start' | 'ready' | null,
  ) => (
    <section className="rounded-2xl border border-white/20 bg-white/60 p-4 shadow-glass backdrop-blur-xl">
      <h2 className="mb-3 flex items-center gap-2 text-sm font-black uppercase tracking-widest text-slate-600">
        <ChefHat className="h-4 w-4 text-brand-500" />
        {t(locale, `restaurant.${titleKey}`)}
        <span className="ml-auto rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">
          {list.length}
        </span>
      </h2>

      {list.length === 0 ? (
        <p className="py-6 text-center text-sm text-slate-400">{t(locale, 'restaurant.empty')}</p>
      ) : (
        <ul className="space-y-3">
          {list.map((order) => (
            <li
              key={order.id}
              className="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:shadow-md"
            >
              <div className="mb-2 flex items-center gap-2 text-xs">
                <span className="rounded-md bg-slate-900 px-2 py-0.5 font-mono font-bold text-white">
                  {order.reference}
                </span>
                <span className="font-bold text-slate-500">{orderTypeLabel(order.order_type)}</span>
                {order.table_id !== null && (
                  <span className="text-slate-400">
                    {t(locale, 'restaurant.table')} #{order.table_id}
                  </span>
                )}
                {order.covers !== null && (
                  <span className="text-slate-400">
                    {order.covers} {t(locale, 'restaurant.covers')}
                  </span>
                )}
                {action !== null && (
                  <button
                    type="button"
                    onClick={() => void transition(order.id, action)}
                    disabled={actingOrderId === order.id}
                    className="ml-auto inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-700 disabled:opacity-50"
                  >
                    {actingOrderId === order.id ? (
                      <Loader2 className="h-3 w-3 animate-spin" />
                    ) : (
                      <UtensilsCrossed className="h-3 w-3" />
                    )}
                    {action === 'start'
                      ? t(locale, 'restaurant.start')
                      : t(locale, 'restaurant.markReady')}
                  </button>
                )}
              </div>

              <ul className="space-y-1">
                {order.items.map((item) => (
                  <li key={item.id} className="flex justify-between text-sm">
                    <span className="font-medium text-slate-700">
                      {item.quantity} × {item.name ?? `#${item.product_id}`}
                    </span>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ul>
      )}
    </section>
  );

  return (
    <ModulePageShell
      title={t(locale, 'restaurant.kitchenTitle')}
      subtitle={t(locale, 'restaurant.kitchenSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      <div className="flex flex-wrap items-center gap-3">
        <label className="text-sm font-bold text-slate-600">{t(locale, 'restaurant.branch')}</label>
        <select
          value={branchId ?? ''}
          onChange={(event) => setBranchId(event.target.value === '' ? null : Number(event.target.value))}
          className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium shadow-sm focus:border-brand-400 focus:outline-none"
        >
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </select>

        <button
          type="button"
          onClick={() => void loadOrders()}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50"
        >
          <RefreshCw className="h-3 w-3" />
          Refresh
        </button>
      </div>

      {error !== null && (
        <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
          {error}
        </p>
      )}

      {actionError !== null && (
        <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700" role="alert">
          {actionError}
        </p>
      )}

      {loading && orders.length === 0 ? (
        <div className="flex items-center justify-center gap-2 py-16 text-slate-400">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span className="text-sm">{t(locale, 'restaurant.loading')}</span>
        </div>
      ) : (
        <div className="grid gap-6 lg:grid-cols-2">
          {renderColumn('inPreparation', inPreparation, 'ready')}
          {renderColumn('ready', ready, readyAction)}
        </div>
      )}
    </ModulePageShell>
  );
}
