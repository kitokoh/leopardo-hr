'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ArrowLeft,
  BadgeCheck,
  Loader2,
  Minus,
  Plus,
  ShoppingCart,
  Store,
  UtensilsCrossed,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * RESTO-807 (#6228) — Kiosque libre-service RestaurantManager.
 *
 * Terminal de commande public (aucune authentification utilisateur) : le
 * tenant est résolu par le jeton de boutique (`?token=`, hash SHA-256 en
 * base — RESTO-805/#6226). Le kiosque consomme exclusivement l'API publique
 * `/public/restaurant/*` : menu, commande (source online, totaux serveur) et
 * paiement (espèces immédiat / mobile money via callback signé).
 *
 * Étude : docs/specifications/ETUDE_KIOSQUE_RESTAURANT.md.
 */

interface KioskProduct {
  id: number;
  code: string;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
  image_asset_id: number | null;
}

interface KioskCategory {
  id: number;
  name: string;
  sort_order: number;
  products: KioskProduct[];
}

interface KioskOrder {
  reference: string;
  status: string;
  total_minor: number;
  currency: string;
  subtotal_minor: number;
  tax_minor: number;
}

interface KioskBranch {
  id: number;
  code: string;
  name: string;
}

type PaymentState =
  | { step: 'idle' }
  | { step: 'placing' }
  | { step: 'placed'; order: KioskOrder; payment: 'cash' | 'mobile_money' | null; paying: boolean }
  | { step: 'error'; message: string };

const formatPrice = (minor: number, currency: string): string =>
  `${(minor / 100).toFixed(2)} ${currency}`;

export default function RestaurantKioskPage() {
  const locale = getPreferredLocale();
  const [token, setToken] = useState<string>('');
  const [tokenError, setTokenError] = useState<string | null>(null);
  const [categories, setCategories] = useState<KioskCategory[]>([]);
  const [branches, setBranches] = useState<KioskBranch[]>([]);
  const [branchId, setBranchId] = useState<number | null>(null);
  const [menuLoading, setMenuLoading] = useState(false);
  const [menuError, setMenuError] = useState<string | null>(null);
  const [cart, setCart] = useState<Record<number, number>>({});
  const [orderType, setOrderType] = useState<'takeaway' | 'delivery'>('takeaway');
  const [payment, setPayment] = useState<PaymentState>({ step: 'idle' });

  useEffect(() => {
    const query = new URLSearchParams(window.location.search);
    const raw = query.get('token') ?? '';
    setToken(raw);

    if (raw === '') {
      setTokenError(t(locale, 'restaurant.kiosk.tokenInvalid'));
    }
  }, [locale]);

  const loadMenu = useCallback(async () => {
    if (token === '') {
      return;
    }

    setMenuLoading(true);
    setMenuError(null);

    try {
      const [menuRes, branchesRes] = await Promise.all([
        apiFetch('/public/restaurant/menu', {
          headers: { 'X-Restaurant-Shop-Token': token },
          _cacheBust: true,
        }),
        apiFetch('/public/restaurant/branches', {
          headers: { 'X-Restaurant-Shop-Token': token },
          _cacheBust: true,
        }),
      ]);

      const menuJson = (await menuRes.json()) as { data: KioskCategory[] };
      const branchesJson = (await branchesRes.json()) as { data: KioskBranch[] };

      const list = Array.isArray(menuJson?.data) ? menuJson.data : [];
      const branchList = Array.isArray(branchesJson?.data) ? branchesJson.data : [];

      setCategories(list);
      setBranches(branchList);
      setBranchId((current) => current ?? branchList[0]?.id ?? null);
    } catch {
      setMenuError(t(locale, 'restaurant.kiosk.loadError'));
    } finally {
      setMenuLoading(false);
    }
  }, [locale, token]);

  useEffect(() => {
    void loadMenu();
  }, [loadMenu]);

  const cartLines = useMemo(() => {
    const lines: { product: KioskProduct; quantity: number }[] = [];

    for (const category of categories) {
      for (const product of category.products) {
        const quantity = cart[product.id] ?? 0;

        if (quantity > 0) {
          lines.push({ product, quantity });
        }
      }
    }

    return lines;
  }, [cart, categories]);

  const cartTotal = useMemo(
    () => cartLines.reduce((sum, line) => sum + line.product.price_minor * line.quantity, 0),
    [cartLines],
  );

  const addToCart = (productId: number): void => {
    setCart((current) => ({ ...current, [productId]: (current[productId] ?? 0) + 1 }));
  };

  const removeFromCart = (productId: number): void => {
    setCart((current) => {
      const next = { ...current };
      const qty = (next[productId] ?? 0) - 1;

      if (qty <= 0) {
        delete next[productId];
      } else {
        next[productId] = qty;
      }

      return next;
    });
  };

  const placeOrder = async (): Promise<void> => {
    if (cartLines.length === 0) {
      return;
    }

    setPayment({ step: 'placing' });

    try {
      const res = await apiFetch('/public/restaurant/orders', {
        method: 'POST',
        headers: { 'X-Restaurant-Shop-Token': token },
        body: JSON.stringify({
          branch_id: branchId,
          order_type: orderType,
          items: cartLines.map((line) => ({
            product_id: line.product.id,
            quantity: line.quantity,
          })),
        }),
      });

      const json = (await res.json()) as { data?: KioskOrder };

      if (!res.ok || !json?.data?.reference) {
        setPayment({ step: 'error', message: t(locale, 'restaurant.kiosk.orderError') });
        return;
      }

      setPayment({ step: 'placed', order: json.data, payment: null, paying: false });
    } catch {
      setPayment({ step: 'error', message: t(locale, 'restaurant.kiosk.orderError') });
    }
  };

  const pay = async (provider: 'cash' | 'mobile_money'): Promise<void> => {
    setPayment((current) =>
      current.step === 'placed' && current.payment === null ? { ...current, paying: true } : current,
    );

    const current = payment;

    if (current.step !== 'placed' || current.payment !== null) {
      return;
    }

    try {
      const res = await apiFetch(
        `/public/restaurant/orders/${current.order.reference}/pay`,
        {
          method: 'POST',
          headers: { 'X-Restaurant-Shop-Token': token },
          body: JSON.stringify({
            provider_code: provider,
            amount_minor: current.order.total_minor,
          }),
        },
      );

      if (!res.ok) {
        setPayment({ step: 'error', message: t(locale, 'restaurant.kiosk.paymentError') });
        return;
      }

      setPayment((state) =>
        state.step === 'placed' ? { ...state, payment: provider, paying: false } : state,
      );
    } catch {
      setPayment({ step: 'error', message: t(locale, 'restaurant.kiosk.paymentError') });
    }
  };

  const reset = (): void => {
    setCart({});
    setPayment({ step: 'idle' });
    void loadMenu();
  };

  if (tokenError !== null) {
    return (
      <main className="flex min-h-screen flex-col items-center justify-center bg-slate-950 p-8 text-white">
        <Store className="mb-4 h-12 w-12 text-amber-400" />
        <h1 className="text-2xl font-semibold">{t(locale, 'restaurant.kiosk.title')}</h1>
        <p className="mt-3 text-slate-300">{tokenError}</p>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-slate-950 text-white">
      <header className="flex items-center justify-between border-b border-slate-800 px-6 py-4">
        <div className="flex items-center gap-3">
          <UtensilsCrossed className="h-7 w-7 text-amber-400" />
          <h1 className="text-xl font-semibold">{t(locale, 'restaurant.kiosk.title')}</h1>
        </div>
        <div className="flex items-center gap-4">
          {branches.length > 1 && (
            <select
              value={branchId ?? ''}
              onChange={(event) => setBranchId(Number(event.target.value))}
              aria-label={t(locale, 'restaurant.kiosk.branch')}
              className="rounded-full bg-slate-900 px-4 py-1.5 text-sm text-slate-200 outline-none"
            >
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </select>
          )}
          <div className="flex items-center gap-2 rounded-full bg-slate-900 px-4 py-1.5 text-sm">
            <ShoppingCart className="h-4 w-4 text-amber-400" />
            <span>
              {cartLines.reduce((sum, line) => sum + line.quantity, 0)}{' '}
              {t(locale, 'restaurant.kiosk.items')}
            </span>
            <span className="font-semibold">{formatPrice(cartTotal, categories[0]?.products[0]?.currency ?? 'DZD')}</span>
          </div>
        </div>
      </header>

      {payment.step === 'placed' ? (
        <section className="mx-auto flex max-w-2xl flex-col items-center px-6 py-20 text-center">
          <BadgeCheck className="mb-6 h-16 w-16 text-emerald-400" />
          <h2 className="text-3xl font-semibold">{t(locale, 'restaurant.kiosk.orderPlaced')}</h2>
          <p className="mt-4 text-lg text-slate-300">
            {t(locale, 'restaurant.kiosk.orderReference')} :{' '}
            <span className="font-mono font-semibold text-amber-400">{payment.order.reference}</span>
          </p>
          <p className="mt-2 text-slate-400">
            {t(locale, 'restaurant.kiosk.total')} : {formatPrice(payment.order.total_minor, payment.order.currency)}
          </p>

          {payment.payment === null && (
            <div className="mt-10 flex w-full max-w-md flex-col gap-3">
              <button
                type="button"
                onClick={() => void pay('cash')}
                disabled={payment.paying}
                className="rounded-2xl bg-emerald-500 px-6 py-4 text-lg font-semibold text-emerald-950 transition hover:bg-emerald-400 disabled:opacity-60"
              >
                {payment.paying ? (
                  <Loader2 className="mx-auto h-6 w-6 animate-spin" />
                ) : (
                  t(locale, 'restaurant.kiosk.payCash')
                )}
              </button>
              <button
                type="button"
                onClick={() => void pay('mobile_money')}
                disabled={payment.paying}
                className="rounded-2xl bg-amber-500 px-6 py-4 text-lg font-semibold text-amber-950 transition hover:bg-amber-400 disabled:opacity-60"
              >
                {t(locale, 'restaurant.kiosk.payMobileMoney')}
              </button>
            </div>
          )}

          {payment.payment === 'cash' && (
            <p className="mt-8 rounded-2xl bg-emerald-950 px-6 py-4 text-emerald-200">
              {t(locale, 'restaurant.kiosk.paid')}
            </p>
          )}
          {payment.payment === 'mobile_money' && (
            <p className="mt-8 rounded-2xl bg-amber-950 px-6 py-4 text-amber-200">
              {t(locale, 'restaurant.kiosk.pendingPayment')}
            </p>
          )}

          <button
            type="button"
            onClick={reset}
            className="mt-10 flex items-center gap-2 rounded-2xl bg-slate-800 px-6 py-3 text-lg transition hover:bg-slate-700"
          >
            <ArrowLeft className="h-5 w-5" />
            {t(locale, 'restaurant.kiosk.startNewOrder')}
          </button>
        </section>
      ) : (
        <div className="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-6 py-6 lg:grid-cols-[1fr_340px]">
          <section>
            {menuLoading && (
              <div className="flex items-center gap-3 py-12 text-slate-400">
                <Loader2 className="h-5 w-5 animate-spin" />
                {t(locale, 'restaurant.kiosk.loading')}
              </div>
            )}

            {menuError !== null && <p className="py-12 text-red-300">{menuError}</p>}

            {!menuLoading && menuError === null && categories.length === 0 && (
              <p className="py-12 text-slate-400">{t(locale, 'restaurant.kiosk.emptyMenu')}</p>
            )}

            {categories.map((category) => (
              <div key={category.id} className="mb-8">
                <h2 className="mb-3 text-lg font-semibold text-amber-400">{category.name}</h2>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {category.products.map((product) => (
                    <div
                      key={product.id}
                      className="flex flex-col rounded-2xl bg-slate-900 p-4"
                    >
                      <div className="flex-1">
                        <h3 className="text-lg font-semibold">{product.name}</h3>
                        {product.description !== null && product.description !== '' && (
                          <p className="mt-1 text-sm text-slate-400">{product.description}</p>
                        )}
                      </div>
                      <div className="mt-3 flex items-center justify-between">
                        <span className="font-semibold text-amber-400">
                          {formatPrice(product.price_minor, product.currency)}
                        </span>
                        <div className="flex items-center gap-2">
                          {(cart[product.id] ?? 0) > 0 && (
                            <>
                              <button
                                type="button"
                                onClick={() => removeFromCart(product.id)}
                                aria-label={t(locale, 'restaurant.kiosk.remove')}
                                className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 transition hover:bg-slate-700"
                              >
                                <Minus className="h-4 w-4" />
                              </button>
                              <span className="w-5 text-center font-semibold">{cart[product.id]}</span>
                            </>
                          )}
                          <button
                            type="button"
                            onClick={() => addToCart(product.id)}
                            aria-label={t(locale, 'restaurant.kiosk.add')}
                            className="flex h-9 w-9 items-center justify-center rounded-full bg-amber-500 text-amber-950 transition hover:bg-amber-400"
                          >
                            <Plus className="h-4 w-4" />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </section>

          <aside className="h-fit rounded-2xl bg-slate-900 p-5 lg:sticky lg:top-6">
            <h2 className="mb-3 text-lg font-semibold">{t(locale, 'restaurant.kiosk.cart')}</h2>

            <div className="mb-4 flex gap-2">
              {(['takeaway', 'delivery'] as const).map((type) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => setOrderType(type)}
                  className={`flex-1 rounded-xl px-3 py-2 text-sm font-medium transition ${
                    orderType === type
                      ? 'bg-amber-500 text-amber-950'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  }`}
                >
                  {type === 'takeaway'
                    ? t(locale, 'restaurant.kiosk.takeaway')
                    : t(locale, 'restaurant.kiosk.delivery')}
                </button>
              ))}
            </div>

            {cartLines.length === 0 ? (
              <p className="py-8 text-center text-slate-400">{t(locale, 'restaurant.kiosk.empty')}</p>
            ) : (
              <ul className="mb-4 space-y-2">
                {cartLines.map((line) => (
                  <li key={line.product.id} className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-slate-300">
                      {line.quantity} × {line.product.name}
                    </span>
                    <span className="font-medium">
                      {formatPrice(line.product.price_minor * line.quantity, line.product.currency)}
                    </span>
                  </li>
                ))}
              </ul>
            )}

            <div className="mb-4 flex items-center justify-between border-t border-slate-800 pt-3 text-lg font-semibold">
              <span>{t(locale, 'restaurant.kiosk.total')}</span>
              <span>{formatPrice(cartTotal, categories[0]?.products[0]?.currency ?? 'DZD')}</span>
            </div>

            {payment.step === 'error' && (
              <p className="mb-3 text-sm text-red-300">{payment.message}</p>
            )}

            <button
              type="button"
              onClick={() => void placeOrder()}
              disabled={cartLines.length === 0 || payment.step === 'placing'}
              className="w-full rounded-2xl bg-amber-500 px-6 py-4 text-lg font-semibold text-amber-950 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {payment.step === 'placing' ? (
                <Loader2 className="mx-auto h-6 w-6 animate-spin" />
              ) : (
                t(locale, 'restaurant.kiosk.checkout')
              )}
            </button>
          </aside>
        </div>
      )}
    </main>
  );
}
