'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { AlertTriangle, CheckCircle2, Minus, Plus, ShoppingCart, Store, Trash2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';

/**
 * RESTO-805-front (#6404) — Commande en ligne publique.
 *
 * Page publique (hors dashboard, aucune session employé) : le tenant est
 * résolu par le jeton boutique (`X-Restaurant-Shop-Token`) transmis via
 * `?token=` (lien généré par le gérant, middleware `restaurant.public.shop`).
 * Le jeton est conservé en sessionStorage (jamais persisté durablement) et
 * injecté en en-tête sur chaque appel `/public/restaurant/shop/*`.
 *
 * Parcours : menu (catégories + produits) → panier → commande idempotente
 * (`idempotency_key` uuid, rejeu sans doublon) → suivi par référence →
 * paiement (cash à l'encaissement / mobile money). RWD mobile-first, i18n
 * ×4 + RTL.
 */

const SHOP_TOKEN_KEY = 'restaurant_shop_token';

type ShopCategory = { id: number; name: string };
type ShopProduct = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  price_minor: number;
  currency: string;
  category_id?: number | null;
  available: boolean;
};
type ShopMenu = { categories: ShopCategory[]; products: ShopProduct[] };
type OrderResult = {
  reference: string;
  status: string;
  total_minor: number;
  currency: string;
  created: boolean;
  track_url: string;
};
type OrderTrack = {
  reference: string;
  status: string;
  subtotal_minor: number;
  tax_minor: number;
  total_minor: number;
  currency: string;
  items: { product_code: string; name: string; quantity: number; line_total_minor: number }[];
};

function uuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `shop-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

function money(minor: number, currency: string, locale: string): string {
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency, maximumFractionDigits: 2 }).format(minor / 100);
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`;
  }
}

export default function ShopPage() {
  const searchParams = useSearchParams();
  const locale = getPreferredLocale();
  const dir = catalogDirection(locale);

  const [token, setToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') {
      return null;
    }
    return window.sessionStorage.getItem(SHOP_TOKEN_KEY);
  });

  const [menu, setMenu] = useState<ShopMenu | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeCategory, setActiveCategory] = useState<number | null>(null);
  const [cart, setCart] = useState<Record<string, number>>({});
  const [cartOpen, setCartOpen] = useState(false);
  const [phone, setPhone] = useState('');
  const [ordering, setOrdering] = useState(false);
  const [order, setOrder] = useState<OrderResult | null>(null);
  const [tracking, setTracking] = useState<OrderTrack | null>(null);
  const [paying, setPaying] = useState(false);
  const [payInfo, setPayInfo] = useState<string | null>(null);

  // Capture du jeton depuis l'URL (?token=) — lien du gérant.
  useEffect(() => {
    const urlToken = searchParams.get('token');
    if (urlToken) {
      window.sessionStorage.setItem(SHOP_TOKEN_KEY, urlToken);
      setToken(urlToken);
    }
  }, [searchParams]);

  const shopFetch = useCallback(
    async (endpoint: string, options: RequestInit = {}) => {
      const headers = new Headers(options.headers);
      if (token) {
        headers.set('X-Restaurant-Shop-Token', token);
      }
      return apiFetch(endpoint, { ...options, headers });
    },
    [token],
  );

  const loadMenu = useCallback(async () => {
    if (!token) {
      setLoading(false);
      setError(t(locale, 'restaurant.shop.missingToken'));
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await shopFetch('/public/restaurant/shop/menu?per_page=100', { _cacheBust: true });
      const json = (await res.json()) as { data?: ShopMenu };
      setMenu(json.data ?? { categories: [], products: [] });
    } catch (err) {
      const status = (err as { status?: number })?.status;
      setError(t(locale, status === 401 ? 'restaurant.shop.invalidToken' : 'restaurant.shop.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale, shopFetch, token]);

  useEffect(() => {
    void loadMenu();
  }, [loadMenu]);

  const filteredProducts = useMemo(() => {
    const items = menu?.products ?? [];
    return activeCategory === null ? items : items.filter((p) => p.category_id === activeCategory);
  }, [activeCategory, menu]);

  const cartCount = useMemo(() => Object.values(cart).reduce((sum, qty) => sum + qty, 0), [cart]);

  const cartLines = useMemo(
    () =>
      Object.entries(cart)
        .map(([code, quantity]) => {
          const product = menu?.products.find((p) => p.code === code);
          return product ? { product, quantity } : null;
        })
        .filter((line): line is { product: ShopProduct; quantity: number } => line !== null),
    [cart, menu],
  );

  const totalMinor = useMemo(
    () => cartLines.reduce((sum, { product, quantity }) => sum + product.price_minor * quantity, 0),
    [cartLines],
  );

  const addToCart = (code: string) => setCart((prev) => ({ ...prev, [code]: (prev[code] ?? 0) + 1 }));
  const removeFromCart = (code: string) => setCart((prev) => {
    const next = { ...prev };
    const qty = (next[code] ?? 0) - 1;
    if (qty <= 0) {
      delete next[code];
    } else {
      next[code] = qty;
    }
    return next;
  });

  const checkout = async () => {
    setOrdering(true);
    setError(null);
    try {
      const res = await shopFetch('/public/restaurant/shop/orders', {
        method: 'POST',
        body: JSON.stringify({
          customer_phone: phone.trim() || undefined,
          idempotency_key: uuid(),
          items: cartLines.map(({ product, quantity }) => ({ product_code: product.code, quantity })),
        }),
      });
      if (!res.ok) {
        throw Object.assign(new Error(String(res.status)), { status: res.status });
      }
      const json = (await res.json()) as { data?: OrderResult };
      if (!json.data) {
        throw Object.assign(new Error('empty'), { status: 422 });
      }
      setOrder(json.data);
      setCart({});
      setCartOpen(false);
      void track(json.data.reference);
    } catch (err) {
      setError(t(locale, (err as { status?: number })?.status === 401 ? 'restaurant.shop.invalidToken' : 'restaurant.shop.loadError'));
    } finally {
      setOrdering(false);
    }
  };

  const track = async (reference: string) => {
    try {
      const res = await shopFetch(`/public/restaurant/shop/orders/${reference}`, { _cacheBust: true });
      const json = (await res.json()) as { data?: OrderTrack };
      setTracking(json.data ?? null);
    } catch {
      setTracking(null);
    }
  };

  const pay = async () => {
    if (!order) {
      return;
    }
    setPaying(true);
    setPayInfo(null);
    try {
      const res = await shopFetch(`/public/restaurant/shop/orders/${order.reference}/pay`, {
        method: 'POST',
        body: JSON.stringify({ provider_code: 'cash' }),
      });
      const json = (await res.json()) as { data?: { provider_code: string; status: string; instruction: string } };
      setPayInfo(json.data?.instruction ?? 'pay_at_pickup');
    } catch {
      setError(t(locale, 'restaurant.shop.loadError'));
    } finally {
      setPaying(false);
    }
  };

  return (
    <main className={`min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10 ${dir === 'rtl' ? 'rtl' : ''}`} dir={dir}>
      <div className="mx-auto max-w-4xl">
        <header className="mb-8 flex items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/20">
              <Store className="h-6 w-6 text-white" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-black tracking-tight text-slate-950">{t(locale, 'restaurant.shop.title')}</h1>
              <p className="text-sm text-slate-500">{t(locale, 'restaurant.shop.subtitle')}</p>
            </div>
          </div>
          {menu ? (
            <button
              type="button"
              onClick={() => setCartOpen(true)}
              className="relative inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm hover:border-amber-300"
              aria-label={`${t(locale, 'restaurant.shop.cart')} (${cartCount})`}
            >
              <ShoppingCart className="h-4 w-4 text-amber-600" aria-hidden="true" />
              {cartCount > 0 ? (
                <span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-[10px] font-black text-white">
                  {cartCount}
                </span>
              ) : null}
            </button>
          ) : null}
        </header>

        {error ? (
          <div className="flex flex-col items-center gap-3 rounded-3xl border border-rose-200 bg-rose-50/80 p-10 text-center">
            <AlertTriangle className="h-8 w-8 text-rose-500" aria-hidden="true" />
            <p className="max-w-md text-sm font-bold text-rose-700">{error}</p>
            {token ? (
              <button
                type="button"
                onClick={() => void loadMenu()}
                className="rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50"
              >
                {t(locale, 'restaurant.shop.retry')}
              </button>
            ) : null}
          </div>
        ) : loading ? (
          <div className="rounded-3xl border border-white/20 bg-white/70 p-10 text-center shadow-premium backdrop-blur-xl">
            <p className="text-sm font-medium text-slate-500">{t(locale, 'restaurant.shop.menu')}…</p>
          </div>
        ) : menu ? (
          <div className="space-y-6">
            {/* Catégories */}
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => setActiveCategory(null)}
                className={`rounded-xl px-4 py-2 text-sm font-bold transition-colors ${
                  activeCategory === null ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-600 hover:border-amber-300'
                }`}
              >
                {t(locale, 'restaurant.shop.categoryAll')}
              </button>
              {menu.categories.map((category) => (
                <button
                  key={category.id}
                  type="button"
                  onClick={() => setActiveCategory(category.id)}
                  className={`rounded-xl px-4 py-2 text-sm font-bold transition-colors ${
                    activeCategory === category.id ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-600 hover:border-amber-300'
                  }`}
                >
                  {category.name}
                </button>
              ))}
            </div>

            {/* Produits */}
            {filteredProducts.length === 0 ? (
              <p className="rounded-2xl border border-dashed border-slate-300 bg-white/50 p-10 text-center text-sm font-medium text-slate-500">
                {t(locale, 'restaurant.shop.menu')} — ∅
              </p>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {filteredProducts.map((product) => (
                  <div key={product.id} className="flex flex-col justify-between rounded-2xl border border-slate-200/70 bg-white/80 p-4 shadow-sm backdrop-blur-xl">
                    <div>
                      <p className="font-black tracking-tight text-slate-950">{product.name}</p>
                      {product.description ? <p className="mt-1 line-clamp-2 text-xs text-slate-500">{product.description}</p> : null}
                    </div>
                    <div className="mt-3 flex items-center justify-between">
                      <span className="text-sm font-black text-amber-700">{money(product.price_minor, product.currency, locale)}</span>
                      <button
                        type="button"
                        onClick={() => addToCart(product.code)}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-3 py-1.5 text-xs font-black text-white shadow-md shadow-amber-500/20 hover:from-amber-600 hover:to-orange-700"
                        aria-label={`${t(locale, 'restaurant.shop.add')} ${product.name}`}
                      >
                        <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                        {t(locale, 'restaurant.shop.add')}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Confirmation / suivi */}
            {order ? (
              <div className="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-6" role="status">
                <div className="flex items-center gap-2 text-emerald-800">
                  <CheckCircle2 className="h-5 w-5" aria-hidden="true" />
                  <p className="font-black">{t(locale, 'restaurant.shop.orderCreated')}</p>
                </div>
                <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                  <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest text-emerald-600">{t(locale, 'restaurant.shop.orderRef')}</dt>
                    <dd className="font-mono font-black text-slate-900">{order.reference}</dd>
                  </div>
                  <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest text-emerald-600">{t(locale, 'restaurant.shop.orderStatus')}</dt>
                    <dd className="font-bold text-slate-800">{order.status}</dd>
                  </div>
                  <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest text-emerald-600">{t(locale, 'restaurant.shop.total')}</dt>
                    <dd className="font-black text-slate-900">{money(order.total_minor, order.currency, locale)}</dd>
                  </div>
                </dl>
                <div className="mt-4 flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={() => void track(order.reference)}
                    className="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-100"
                  >
                    {t(locale, 'restaurant.shop.track')}
                  </button>
                  <button
                    type="button"
                    onClick={() => void pay()}
                    disabled={paying}
                    className="rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-4 py-2 text-sm font-bold text-white shadow-md disabled:opacity-50"
                  >
                    {paying ? t(locale, 'restaurant.shop.paying') : t(locale, 'restaurant.shop.pay')}
                  </button>
                </div>
                {payInfo ? <p className="mt-3 text-xs font-medium text-emerald-700">{t(locale, 'restaurant.shop.payHint')} — {payInfo}</p> : null}
                {tracking ? (
                  <div className="mt-4 rounded-2xl border border-emerald-100 bg-white/70 p-4 text-sm">
                    <p className="font-bold text-slate-800">{t(locale, 'restaurant.shop.track')} — {tracking.status}</p>
                    <ul className="mt-2 space-y-1 text-xs text-slate-500">
                      {tracking.items.map((item) => (
                        <li key={item.product_code} className="flex justify-between">
                          <span>{item.quantity} × {item.name}</span>
                          <span>{money(item.line_total_minor, tracking.currency, locale)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : null}
              </div>
            ) : null}
          </div>
        ) : null}

        {/* Panier */}
        {cartOpen ? (
          <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label={t(locale, 'restaurant.shop.cart')}>
            <button type="button" aria-label="Fermer" className="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" onClick={() => setCartOpen(false)} />
            <div className="relative max-h-[85vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-6 shadow-premium sm:rounded-3xl">
              <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-black tracking-tight text-slate-950">{t(locale, 'restaurant.shop.cart')}</h2>
                <button type="button" onClick={() => setCartOpen(false)} className="rounded-xl px-2 py-1 text-sm font-bold text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                  ✕
                </button>
              </div>

              {cartLines.length === 0 ? (
                <p className="py-8 text-center text-sm font-medium text-slate-500">{t(locale, 'restaurant.shop.emptyCart')}</p>
              ) : (
                <div className="space-y-3">
                  {cartLines.map(({ product, quantity }) => (
                    <div key={product.code} className="flex items-center justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-bold text-slate-800">{product.name}</p>
                        <p className="text-xs text-slate-400">{money(product.price_minor, product.currency, locale)}</p>
                      </div>
                      <div className="flex items-center gap-1.5">
                        <button type="button" onClick={() => removeFromCart(product.code)} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label={`${t(locale, 'restaurant.shop.remove')} ${product.name}`}>
                          {quantity === 1 ? <Trash2 className="h-4 w-4" aria-hidden="true" /> : <Minus className="h-4 w-4" aria-hidden="true" />}
                        </button>
                        <span className="w-6 text-center text-sm font-black text-slate-800">{quantity}</span>
                        <button type="button" onClick={() => addToCart(product.code)} className="rounded-lg p-1 text-amber-600 hover:bg-amber-50" aria-label={`${t(locale, 'restaurant.shop.add')} ${product.name}`}>
                          <Plus className="h-4 w-4" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                  ))}

                  <div className="border-t border-slate-100 pt-3 text-sm">
                    <p className="flex justify-between"><span className="text-slate-500">{t(locale, 'restaurant.shop.subtotal')}</span><span className="font-bold text-slate-900">{money(totalMinor, cartLines[0]?.product.currency ?? 'XOF', locale)}</span></p>
                    <p className="mt-1 flex justify-between"><span className="text-slate-500">{t(locale, 'restaurant.shop.tax')}</span><span className="font-bold text-slate-900">—</span></p>
                    <p className="mt-1 flex justify-between text-base"><span className="font-bold text-slate-800">{t(locale, 'restaurant.shop.total')}</span><span className="font-black text-amber-700">{money(totalMinor, cartLines[0]?.product.currency ?? 'XOF', locale)}</span></p>
                  </div>

                  <label className="block space-y-1.5 pt-1">
                    <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'restaurant.shop.phone')}</span>
                    <input
                      type="tel"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="+000 00 00 00 00"
                      className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                      aria-label={t(locale, 'restaurant.shop.phone')}
                    />
                    <span className="block text-xs text-slate-400">{t(locale, 'restaurant.shop.phoneHint')}</span>
                  </label>

                  <button
                    type="button"
                    onClick={() => void checkout()}
                    disabled={ordering}
                    className="w-full rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-3 text-sm font-black text-white shadow-md shadow-amber-500/20 hover:from-amber-600 hover:to-orange-700 disabled:opacity-50"
                  >
                    {ordering ? t(locale, 'restaurant.shop.ordering') : `${t(locale, 'restaurant.shop.checkout')} — ${money(totalMinor, cartLines[0]?.product.currency ?? 'XOF', locale)}`}
                  </button>
                </div>
              )}
            </div>
          </div>
        ) : null}
      </div>
    </main>
  );
}
