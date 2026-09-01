'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  BadgeCheck,
  Loader2,
  Minus,
  Phone,
  Plus,
  ShoppingCart,
  Store,
  UtensilsCrossed,
} from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

/**
 * RESTO-805-front (#6404) — Commande en ligne publique RestaurantManager.
 *
 * Page publique par tenant (jeton `?token=`, hash SHA-256 en base) : menu
 * (catégories + produits), panier, création de commande, suivi par référence
 * et paiement (cash à l'encaissement / mobile money). Consomme l'API
 * canonique `/public/restaurant/shop/*` — aucun accès inter-tenant possible
 * (scope BelongsToCompany posé par le middleware `restaurant.public.shop`).
 */

interface ShopProduct {
  id: number;
  code: string;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
  category_id: number | null;
  available: boolean;
}

interface ShopCategory {
  id: number;
  name: string;
}

interface ShopMenu {
  categories: ShopCategory[];
  products: ShopProduct[];
}

interface ShopOrder {
  reference: string;
  status: string;
  total_minor: number;
  currency: string;
  created: boolean;
}

interface ShopTrack {
  reference: string;
  status: string;
  subtotal_minor: number;
  tax_minor: number;
  total_minor: number;
  currency: string;
  items: { product_code: string; name: string; quantity: number; line_total_minor: number }[];
  updated_at: string;
}

type ShopState =
  | { step: 'idle' }
  | { step: 'placing' }
  | { step: 'placed'; order: ShopOrder }
  | { step: 'tracking'; track: ShopTrack }
  | { step: 'paying'; order: ShopOrder }
  | { step: 'paid'; order: ShopOrder; provider: 'cash' | 'mobile_money' }
  | { step: 'error'; message: string };

const formatPrice = (minor: number, currency: string): string =>
  `${(minor / 100).toFixed(2)} ${currency}`;

export default function RestaurantShopPage() {
  const locale = getPreferredLocale();
  const [token, setToken] = useState<string>('');
  const [tokenError, setTokenError] = useState<string | null>(null);
  const [menu, setMenu] = useState<ShopMenu | null>(null);
  const [menuLoading, setMenuLoading] = useState(false);
  const [menuError, setMenuError] = useState<string | null>(null);
  const [cart, setCart] = useState<Record<string, number>>({});
  const [phone, setPhone] = useState('');
  const [state, setState] = useState<ShopState>({ step: 'idle' });

  useEffect(() => {
    const query = new URLSearchParams(window.location.search);
    const raw = query.get('token') ?? '';
    setToken(raw);

    if (raw === '') {
      setTokenError(t(locale, 'restaurant.shop.tokenInvalid'));
    }
  }, [locale]);

  const loadMenu = useCallback(async () => {
    if (token === '') {
      return;
    }

    setMenuLoading(true);
    setMenuError(null);

    try {
      const res = await apiFetch('/public/restaurant/shop/menu', {
        headers: { 'X-Restaurant-Shop-Token': token },
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: ShopMenu };
      setMenu(json?.data ?? null);
    } catch {
      setMenuError(t(locale, 'restaurant.shop.loadError'));
    } finally {
      setMenuLoading(false);
    }
  }, [locale, token]);

  useEffect(() => {
    void loadMenu();
  }, [loadMenu]);

  const productsByCategory = useMemo(() => {
    const products = menu?.products ?? [];
    const categories = menu?.categories ?? [];
    const available = products.filter((product) => product.available);

    return categories
      .map((category) => ({
        category,
        products: available.filter((product) => product.category_id === category.id),
      }))
      .filter((group) => group.products.length > 0);
  }, [menu]);

  const cartLines = useMemo(() => {
    const products = menu?.products ?? [];
    return products
      .filter((product) => (cart[product.code] ?? 0) > 0)
      .map((product) => ({ product, quantity: cart[product.code] }));
  }, [cart, menu]);

  const cartTotal = useMemo(
    () => cartLines.reduce((sum, line) => sum + line.product.price_minor * line.quantity, 0),
    [cartLines],
  );

  const currency = menu?.products[0]?.currency ?? 'DZD';

  const addToCart = (code: string): void => {
    setCart((current) => ({ ...current, [code]: (current[code] ?? 0) + 1 }));
  };

  const removeFromCart = (code: string): void => {
    setCart((current) => {
      const next = { ...current };
      const qty = (next[code] ?? 0) - 1;

      if (qty <= 0) {
        delete next[code];
      } else {
        next[code] = qty;
      }

      return next;
    });
  };

  const placeOrder = async (): Promise<void> => {
    if (cartLines.length === 0) {
      return;
    }

    setState({ step: 'placing' });

    try {
      const res = await apiFetch('/public/restaurant/shop/orders', {
        method: 'POST',
        headers: { 'X-Restaurant-Shop-Token': token },
        body: JSON.stringify({
          customer_phone: phone !== '' ? phone : undefined,
          items: cartLines.map((line) => ({
            product_code: line.product.code,
            quantity: line.quantity,
          })),
        }),
      });

      const json = (await res.json()) as { data?: ShopOrder };

      if (!res.ok || !json?.data?.reference) {
        setState({ step: 'error', message: t(locale, 'restaurant.shop.orderError') });
        return;
      }

      setState({ step: 'placed', order: json.data });
    } catch {
      setState({ step: 'error', message: t(locale, 'restaurant.shop.orderError') });
    }
  };

  const track = async (): Promise<void> => {
    if (state.step !== 'placed') {
      return;
    }

    try {
      const res = await apiFetch(`/public/restaurant/shop/orders/${state.order.reference}`, {
        headers: { 'X-Restaurant-Shop-Token': token },
        _cacheBust: true,
      });
      const json = (await res.json()) as { data?: ShopTrack };

      if (!res.ok || !json?.data) {
        setState({ step: 'error', message: t(locale, 'restaurant.shop.trackError') });
        return;
      }

      setState({ step: 'tracking', track: json.data });
    } catch {
      setState({ step: 'error', message: t(locale, 'restaurant.shop.trackError') });
    }
  };

  const pay = async (provider: 'cash' | 'mobile_money'): Promise<void> => {
    if (state.step !== 'placed') {
      return;
    }

    const order = state.order;
    setState({ step: 'paying', order });

    try {
      const res = await apiFetch(`/public/restaurant/shop/orders/${order.reference}/pay`, {
        method: 'POST',
        headers: { 'X-Restaurant-Shop-Token': token },
        body: JSON.stringify({ provider_code: provider }),
      });

      if (!res.ok) {
        setState({ step: 'error', message: t(locale, 'restaurant.shop.paymentError') });
        return;
      }

      setState({ step: 'paid', order, provider });
    } catch {
      setState({ step: 'error', message: t(locale, 'restaurant.shop.paymentError') });
    }
  };

  const reset = (): void => {
    setCart({});
    setPhone('');
    setState({ step: 'idle' });
    void loadMenu();
  };

  if (tokenError !== null) {
    return (
      <main className="flex min-h-screen flex-col items-center justify-center bg-white p-8">
        <Store className="mb-4 h-12 w-12 text-amber-500" />
        <h1 className="text-2xl font-semibold">{t(locale, 'restaurant.shop.title')}</h1>
        <p className="mt-3 text-slate-500">{tokenError}</p>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-slate-50 text-slate-900">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-3">
            <UtensilsCrossed className="h-7 w-7 text-amber-500" />
            <h1 className="text-xl font-semibold">{t(locale, 'restaurant.shop.title')}</h1>
          </div>
          <div className="flex items-center gap-2 rounded-full bg-slate-100 px-4 py-1.5 text-sm">
            <ShoppingCart className="h-4 w-4 text-amber-500" />
            <span>
              {cartLines.reduce((sum, line) => sum + line.quantity, 0)} {t(locale, 'restaurant.shop.items')}
            </span>
            <span className="font-semibold">{formatPrice(cartTotal, currency)}</span>
          </div>
        </div>
      </header>

      {state.step === 'placed' || state.step === 'paying' ? (
        <section className="mx-auto flex max-w-2xl flex-col items-center px-6 py-16 text-center">
          <BadgeCheck className="mb-6 h-16 w-16 text-emerald-500" />
          <h2 className="text-3xl font-semibold">{t(locale, 'restaurant.shop.orderPlaced')}</h2>
          <p className="mt-4 text-lg text-slate-600">
            {t(locale, 'restaurant.shop.orderReference')} :{' '}
            <span className="font-mono font-semibold text-amber-600">{state.order.reference}</span>
          </p>
          <p className="mt-2 text-slate-500">
            {t(locale, 'restaurant.shop.total')} : {formatPrice(state.order.total_minor, state.order.currency)}
          </p>

          {state.step === 'placed' && (
            <div className="mt-10 flex w-full max-w-md flex-col gap-3">
              <button
                type="button"
                onClick={() => void track()}
                className="rounded-2xl bg-slate-900 px-6 py-4 text-lg font-semibold text-white transition hover:bg-slate-700"
              >
                {t(locale, 'restaurant.shop.track')}
              </button>
              <button
                type="button"
                onClick={() => void pay('cash')}
                className="rounded-2xl bg-emerald-500 px-6 py-4 text-lg font-semibold text-emerald-950 transition hover:bg-emerald-400"
              >
                {t(locale, 'restaurant.shop.payCashAtPickup')}
              </button>
              <button
                type="button"
                onClick={() => void pay('mobile_money')}
                className="rounded-2xl bg-amber-500 px-6 py-4 text-lg font-semibold text-amber-950 transition hover:bg-amber-400"
              >
                {t(locale, 'restaurant.shop.payMobileMoney')}
              </button>
            </div>
          )}

          {state.step === 'paying' && (
            <p className="mt-8 flex items-center gap-2 text-slate-500">
              <Loader2 className="h-5 w-5 animate-spin" />
              {t(locale, 'restaurant.shop.pendingPayment')}
            </p>
          )}
        </section>
      ) : state.step === 'tracking' ? (
        <section className="mx-auto max-w-2xl px-6 py-16">
          <h2 className="text-2xl font-semibold">{t(locale, 'restaurant.shop.trackTitle')}</h2>
          <p className="mt-2 font-mono text-amber-600">{state.track.reference}</p>
          <p className="mt-4 text-slate-600">
            {t(locale, 'restaurant.shop.status')} :{' '}
            <span className="font-semibold">{state.track.status}</span>
          </p>
          <ul className="mt-6 space-y-2">
            {state.track.items.map((item) => (
              <li key={item.product_code} className="flex justify-between rounded-xl bg-white p-3 shadow-sm">
                <span>
                  {item.quantity} × {item.name}
                </span>
                <span>{formatPrice(item.line_total_minor, state.track.currency)}</span>
              </li>
            ))}
          </ul>
          <div className="mt-4 flex justify-between text-lg font-semibold">
            <span>{t(locale, 'restaurant.shop.total')}</span>
            <span>{formatPrice(state.track.total_minor, state.track.currency)}</span>
          </div>
          <button
            type="button"
            onClick={reset}
            className="mt-8 rounded-2xl bg-slate-900 px-6 py-3 text-white transition hover:bg-slate-700"
          >
            {t(locale, 'restaurant.shop.backToMenu')}
          </button>
        </section>
      ) : state.step === 'paid' ? (
        <section className="mx-auto flex max-w-2xl flex-col items-center px-6 py-16 text-center">
          <BadgeCheck className="mb-6 h-16 w-16 text-emerald-500" />
          <h2 className="text-3xl font-semibold">{t(locale, 'restaurant.shop.paid')}</h2>
          <p className="mt-4 text-lg text-slate-600">
            {t(locale, 'restaurant.shop.orderReference')} :{' '}
            <span className="font-mono font-semibold text-amber-600">{state.order.reference}</span>
          </p>
          {state.provider === 'cash' && (
            <p className="mt-6 rounded-2xl bg-emerald-50 px-6 py-4 text-emerald-700">
              {t(locale, 'restaurant.shop.payAtPickup')}
            </p>
          )}
          {state.provider === 'mobile_money' && (
            <p className="mt-6 rounded-2xl bg-amber-50 px-6 py-4 text-amber-700">
              {t(locale, 'restaurant.shop.pendingPayment')}
            </p>
          )}
          <button
            type="button"
            onClick={reset}
            className="mt-10 rounded-2xl bg-slate-900 px-6 py-3 text-white transition hover:bg-slate-700"
          >
            {t(locale, 'restaurant.shop.startNewOrder')}
          </button>
        </section>
      ) : (
        <div className="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-6 py-6 lg:grid-cols-[1fr_340px]">
          <section>
            {menuLoading && (
              <div className="flex items-center gap-3 py-12 text-slate-400">
                <Loader2 className="h-5 w-5 animate-spin" />
                {t(locale, 'restaurant.shop.loading')}
              </div>
            )}

            {menuError !== null && <p className="py-12 text-red-400">{menuError}</p>}

            {!menuLoading && menuError === null && (menu?.products.length ?? 0) === 0 && (
              <p className="py-12 text-slate-400">{t(locale, 'restaurant.shop.emptyMenu')}</p>
            )}

            {productsByCategory.map(({ category, products }) => (
              <div key={category.id} className="mb-8">
                <h2 className="mb-3 text-lg font-semibold text-amber-600">{category.name}</h2>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {products.map((product) => (
                    <div key={product.id} className="flex flex-col rounded-2xl bg-white p-4 shadow-sm">
                      <div className="flex-1">
                        <h3 className="text-lg font-semibold">{product.name}</h3>
                        {product.description !== null && product.description !== '' && (
                          <p className="mt-1 text-sm text-slate-500">{product.description}</p>
                        )}
                      </div>
                      <div className="mt-3 flex items-center justify-between">
                        <span className="font-semibold text-amber-600">
                          {formatPrice(product.price_minor, product.currency)}
                        </span>
                        <div className="flex items-center gap-2">
                          {(cart[product.code] ?? 0) > 0 && (
                            <>
                              <button
                                type="button"
                                onClick={() => removeFromCart(product.code)}
                                aria-label={t(locale, 'restaurant.shop.remove')}
                                className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 transition hover:bg-slate-200"
                              >
                                <Minus className="h-4 w-4" />
                              </button>
                              <span className="w-5 text-center font-semibold">{cart[product.code]}</span>
                            </>
                          )}
                          <button
                            type="button"
                            onClick={() => addToCart(product.code)}
                            aria-label={t(locale, 'restaurant.shop.add')}
                            className="flex h-9 w-9 items-center justify-center rounded-full bg-amber-500 text-white transition hover:bg-amber-400"
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

          <aside className="h-fit rounded-2xl bg-white p-5 shadow-sm lg:sticky lg:top-20">
            <h2 className="mb-3 text-lg font-semibold">{t(locale, 'restaurant.shop.cart')}</h2>

            {cartLines.length === 0 ? (
              <p className="py-8 text-center text-slate-400">{t(locale, 'restaurant.shop.empty')}</p>
            ) : (
              <ul className="mb-4 space-y-2">
                {cartLines.map((line) => (
                  <li key={line.product.code} className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-slate-600">
                      {line.quantity} × {line.product.name}
                    </span>
                    <span className="font-medium">
                      {formatPrice(line.product.price_minor * line.quantity, line.product.currency)}
                    </span>
                  </li>
                ))}
              </ul>
            )}

            <label className="mb-1 block text-sm text-slate-500">{t(locale, 'restaurant.shop.phone')}</label>
            <div className="mb-4 flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
              <Phone className="h-4 w-4 text-slate-400" />
              <input
                type="tel"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                placeholder={t(locale, 'restaurant.shop.phonePlaceholder')}
                className="w-full outline-none"
              />
            </div>

            <div className="mb-4 flex items-center justify-between border-t border-slate-100 pt-3 text-lg font-semibold">
              <span>{t(locale, 'restaurant.shop.total')}</span>
              <span>{formatPrice(cartTotal, currency)}</span>
            </div>

            {state.step === 'error' && <p className="mb-3 text-sm text-red-400">{state.message}</p>}

            <button
              type="button"
              onClick={() => void placeOrder()}
              disabled={cartLines.length === 0 || state.step === 'placing'}
              className="w-full rounded-2xl bg-amber-500 px-6 py-4 text-lg font-semibold text-white transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {state.step === 'placing' ? (
                <Loader2 className="mx-auto h-6 w-6 animate-spin" />
              ) : (
                t(locale, 'restaurant.shop.checkout')
              )}
            </button>
          </aside>
        </div>
      )}
    </main>
  );
}
