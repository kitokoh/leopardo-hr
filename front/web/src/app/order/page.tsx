'use client';

import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { PackageSearch, RefreshCw, Store } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';

/**
 * RESTO-903 (#7748) — dédup de `/order` : la page est réduite au SEUL suivi
 * de commande (le layout la titre déjà « Suivi de commande »).
 *
 * AVANT : un doublon quasi complet du parcours de /shop (menu, panier,
 * commande, paiement) — deux implémentations du même flux RESTO-805 à
 * maintenir. ICI : saisie d'une référence + jeton boutique existant
 * (`?token=`, deep-link conservé — le lien du gérant reste valide) →
 * `GET /public/restaurant/shop/orders/{ref}` (X-Restaurant-Shop-Token).
 * Le parcours de commande vit sur /shop (jeton) et /restaurants/{slug}
 * (public SEO) ; `?ref=` déclenche un suivi immédiat.
 */

interface ShopTrack {
  reference: string;
  status: string;
  subtotal_minor: number;
  tax_minor: number;
  total_minor: number;
  currency: string;
  items: { product_code: string; name: string; quantity: number; line_total_minor: number }[];
  updated_at: string | null;
}

const formatPrice = (minor: number, currency: string, locale: string): string => {
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      maximumFractionDigits: 2,
    }).format(minor / 100);
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`;
  }
};

export default function OrderTrackingPage() {
  const searchParams = useSearchParams();
  const locale = getPreferredLocale();
  const dir = catalogDirection(locale);

  const token = searchParams.get('token') ?? '';
  const initialRef = searchParams.get('ref') ?? searchParams.get('reference') ?? '';

  const [reference, setReference] = useState(initialRef);
  const [track, setTrack] = useState<ShopTrack | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const lookup = useCallback(
    async (ref: string) => {
      const trimmed = ref.trim();
      if (trimmed === '') {
        return;
      }
      if (token === '') {
        setError(t(locale, 'restaurant.shop.tokenInvalid'));
        return;
      }
      setLoading(true);
      setError(null);
      try {
        const res = await apiFetch(`/public/restaurant/shop/orders/${encodeURIComponent(trimmed)}`, {
          headers: { 'X-Restaurant-Shop-Token': token },
          _cacheBust: true,
        });
        const json = (await res.json()) as { data?: ShopTrack };
        if (!json.data) {
          setError(t(locale, 'restaurant.public.trackNotFound'));
          setTrack(null);
          return;
        }
        setTrack(json.data);
      } catch (err) {
        const status = (err as { status?: number })?.status;
        setError(
          t(
            locale,
            status === 404
              ? 'restaurant.public.trackNotFound'
              : status === 401
                ? 'restaurant.shop.tokenInvalid'
                : 'restaurant.shop.trackError',
          ),
        );
        setTrack(null);
      } finally {
        setLoading(false);
      }
    },
    [locale, token],
  );

  // Deep-link `?ref=` (compat) : suivi immédiat à l'arrivée sur la page.
  useEffect(() => {
    if (initialRef !== '') {
      void lookup(initialRef);
    }
  }, [initialRef, lookup]);

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    void lookup(reference);
  };

  return (
    <main
      dir={dir}
      className="min-h-screen bg-gradient-to-br from-slate-50 via-amber-50/40 to-orange-50/40 px-4 py-10"
    >
      <div className="mx-auto max-w-2xl">
        <header className="mb-8 flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/20">
            <Store className="h-6 w-6 text-white" aria-hidden="true" />
          </div>
          <div>
            <h1 className="text-2xl font-black tracking-tight text-slate-950">
              {t(locale, 'restaurant.shop.trackTitle')}
            </h1>
            <p className="text-sm text-slate-500">{t(locale, 'restaurant.public.trackHint')}</p>
          </div>
        </header>

        <form
          onSubmit={submit}
          className="mb-6 flex flex-wrap items-end gap-3 rounded-3xl border border-white/40 bg-white/80 p-5 shadow-sm backdrop-blur-xl"
        >
          <label className="block min-w-56 flex-1 space-y-1">
            <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">
              {t(locale, 'restaurant.public.referenceLabel')}
            </span>
            <input
              type="text"
              value={reference}
              onChange={(event) => setReference(event.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
              maxLength={40}
              required
            />
          </label>
          <button
            type="submit"
            disabled={loading}
            className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-2 text-sm font-black text-white shadow-md shadow-amber-500/20 hover:from-amber-600 hover:to-orange-700 disabled:opacity-50"
          >
            <PackageSearch className="h-4 w-4" aria-hidden="true" />
            {t(locale, 'restaurant.shop.track')}
          </button>
        </form>

        {error ? (
          <p className="rounded-2xl border border-rose-200 bg-rose-50/80 p-6 text-center text-sm font-bold text-rose-700" role="alert">
            {error}
          </p>
        ) : null}

        {track ? (
          <section
            className="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-6"
            aria-label={t(locale, 'restaurant.shop.trackTitle')}
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-mono font-black text-slate-900">{track.reference}</p>
                <p className="text-sm text-slate-600">
                  {t(locale, 'restaurant.shop.status')} :{' '}
                  <span className="font-black text-emerald-800">{track.status}</span>
                </p>
              </div>
              <button
                type="button"
                onClick={() => void lookup(track.reference)}
                disabled={loading}
                className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
              >
                <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
                {t(locale, 'restaurant.public.refresh')}
              </button>
            </div>
            <ul className="mt-4 space-y-2">
              {track.items.map((item) => (
                <li
                  key={item.product_code}
                  className="flex justify-between rounded-xl bg-white/80 p-3 text-sm shadow-sm"
                >
                  <span className="text-slate-600">
                    {item.quantity} × {item.name}
                  </span>
                  <span className="font-bold text-slate-800">
                    {formatPrice(item.line_total_minor, track.currency, locale)}
                  </span>
                </li>
              ))}
            </ul>
            <div className="mt-4 space-y-1 border-t border-emerald-100 pt-3 text-sm">
              <p className="flex justify-between">
                <span className="text-slate-500">{t(locale, 'restaurant.shop.subtotal')}</span>
                <span className="font-bold text-slate-800">
                  {formatPrice(track.subtotal_minor, track.currency, locale)}
                </span>
              </p>
              <p className="flex justify-between">
                <span className="text-slate-500">{t(locale, 'restaurant.shop.tax')}</span>
                <span className="font-bold text-slate-800">
                  {formatPrice(track.tax_minor, track.currency, locale)}
                </span>
              </p>
              <p className="flex justify-between text-base">
                <span className="font-bold text-slate-800">{t(locale, 'restaurant.shop.total')}</span>
                <span className="font-black text-emerald-800">
                  {formatPrice(track.total_minor, track.currency, locale)}
                </span>
              </p>
              {track.updated_at ? (
                <p className="pt-1 text-xs text-slate-400">
                  {t(locale, 'restaurant.public.updatedAt')} — {track.updated_at}
                </p>
              ) : null}
            </div>
          </section>
        ) : null}
      </div>
    </main>
  );
}
