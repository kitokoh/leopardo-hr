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
 * RESTO-807-front (#6405) — Kiosque libre-service RestaurantManager.
 *
 * Borne de commande publique (aucune authentification utilisateur) : le
 * tenant est résolu par le jeton de boutique (`?token=`, hash SHA-256 en
 * base — RESTO-805/#6226). Consomme l'API kiosque canonique
 * (`/public/restaurant/kiosk/*`, branche mobile-public) :
 * menu → panier → commande (items par `product_code`, idempotente) → ticket
 * affiché à l'écran. Paiement à l'encaissement (cash, contrat backend) ;
 * le suivi se fait par `ticket_number`/référence.
 *
 * Étude : docs/restaurant/KIOSK_ETUDE.md (décision v1 ; hors-ligne complet
 * différé — file idempotente mobile RESTO-804).
 */

interface KioskProduct {
  id: number;
  code: string;
  name: string;
  price_minor: number;
  currency: string;
  category_id: number | null;
}

interface KioskMenu {
  products: KioskProduct[];
  pagination: { per_page: number; total: number };
}

interface KioskOrder {
  reference: string;
  ticket_number: string;
  status: string;
  total_minor: number;
  currency: string;
  created: boolean;
}

type KioskState =
  | { step: 'idle' }
  | { step: 'placing' }
  | { step: 'placed'; order: KioskOrder }
  | { step: 'error'; message: string };

const formatPrice = (minor: number, currency: string): string =>
  `${(minor / 100).toFixed(2)} ${currency}`;

export default function RestaurantKioskPage() {
  const locale = getPreferredLocale();
  const [token, setToken] = useState<string>('');
  const [tokenError, setTokenError] = useState<string | null>(null);
  const [menu, setMenu] = useState<KioskMenu | null>(null);
  const [menuLoading, setMenuLoading] = useState(false);
  const [menuError, setMenuError] = useState<string | null>(null);
  const [cart, setCart] = useState<Record<string, number>>({});
  const [state, setState] = useState<KioskState>({ step: 'idle' });

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
      const res = await apiFetch('/public/restaurant/kiosk/menu', {
        headers: { 'X-Restaurant-Shop-Token': token },
        _cacheBust: true,
      });
      const json = (await res.json()) as { data: KioskMenu };
      setMenu(json?.data ?? null);
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
      const res = await apiFetch('/public/restaurant/kiosk/orders', {
        method: 'POST',
        headers: { 'X-Restaurant-Shop-Token': token },
        body: JSON.stringify({
          items: cartLines.map((line) => ({
            product_code: line.product.code,
            quantity: line.quantity,
          })),
        }),
      });

      const json = (await res.json()) as { data?: KioskOrder };

      if (!res.ok || !json?.data?.reference) {
        setState({ step: 'error', message: t(locale, 'restaurant.kiosk.orderError') });
        return;
      }

      setState({ step: 'placed', order: json.data });
    } catch {
      setState({ step: 'error', message: t(locale, 'restaurant.kiosk.orderError') });
    }
  };

  const reset = (): void => {
    setCart({});
    setState({ step: 'idle' });
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
        <div className="flex items-center gap-2 rounded-full bg-slate-900 px-4 py-1.5 text-sm">
          <ShoppingCart className="h-4 w-4 text-amber-400" />
          <span>
            {cartLines.reduce((sum, line) => sum + line.quantity, 0)} {t(locale, 'restaurant.kiosk.items')}
          </span>
          <span className="font-semibold">{formatPrice(cartTotal, currency)}</span>
        </div>
      </header>

      {state.step === 'placed' ? (
        <section className="mx-auto flex max-w-2xl flex-col items-center px-6 py-20 text-center">
          <BadgeCheck className="mb-6 h-16 w-16 text-emerald-400" />
          <h2 className="text-3xl font-semibold">{t(locale, 'restaurant.kiosk.orderPlaced')}</h2>
          <p className="mt-8 text-7xl font-bold tracking-widest text-amber-400">
            {state.order.ticket_number}
          </p>
          <p className="mt-3 text-lg text-slate-300">
            {t(locale, 'restaurant.kiosk.orderReference')} :{' '}
            <span className="font-mono font-semibold">{state.order.reference}</span>
          </p>
          <p className="mt-2 text-slate-400">
            {t(locale, 'restaurant.kiosk.total')} : {formatPrice(state.order.total_minor, state.order.currency)}
          </p>
          <p className="mt-8 rounded-2xl bg-emerald-950 px-6 py-4 text-emerald-200">
            {t(locale, 'restaurant.kiosk.payAtPickup')}
          </p>
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

            {!menuLoading && menuError === null && (menu?.products.length ?? 0) === 0 && (
              <p className="py-12 text-slate-400">{t(locale, 'restaurant.kiosk.emptyMenu')}</p>
            )}

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {(menu?.products ?? []).map((product) => (
                <div key={product.id} className="flex flex-col rounded-2xl bg-slate-900 p-4">
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold">{product.name}</h3>
                  </div>
                  <div className="mt-3 flex items-center justify-between">
                    <span className="font-semibold text-amber-400">
                      {formatPrice(product.price_minor, product.currency)}
                    </span>
                    <div className="flex items-center gap-2">
                      {(cart[product.code] ?? 0) > 0 && (
                        <>
                          <button
                            type="button"
                            onClick={() => removeFromCart(product.code)}
                            aria-label={t(locale, 'restaurant.kiosk.remove')}
                            className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 transition hover:bg-slate-700"
                          >
                            <Minus className="h-4 w-4" />
                          </button>
                          <span className="w-5 text-center font-semibold">{cart[product.code]}</span>
                        </>
                      )}
                      <button
                        type="button"
                        onClick={() => addToCart(product.code)}
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
          </section>

          <aside className="h-fit rounded-2xl bg-slate-900 p-5 lg:sticky lg:top-6">
            <h2 className="mb-3 text-lg font-semibold">{t(locale, 'restaurant.kiosk.cart')}</h2>

            {cartLines.length === 0 ? (
              <p className="py-8 text-center text-slate-400">{t(locale, 'restaurant.kiosk.empty')}</p>
            ) : (
              <ul className="mb-4 space-y-2">
                {cartLines.map((line) => (
                  <li key={line.product.code} className="flex items-center justify-between gap-2 text-sm">
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
              <span>{formatPrice(cartTotal, currency)}</span>
            </div>

            {state.step === 'error' && <p className="mb-3 text-sm text-red-300">{state.message}</p>}

            <button
              type="button"
              onClick={() => void placeOrder()}
              disabled={cartLines.length === 0 || state.step === 'placing'}
              className="w-full rounded-2xl bg-amber-500 px-6 py-4 text-lg font-semibold text-amber-950 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {state.step === 'placing' ? (
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
