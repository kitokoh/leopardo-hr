'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { CheckCircle2, Minus, Plus, RefreshCw, Star, Trash2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import type {
  PublicListMeta,
  PublicMenuCategory,
  PublicMenuProduct,
  PublicReview,
} from '@/lib/restaurants-public-api';
import { formatMoney } from '../format';

/**
 * RESTO-903 (#7748) — parcours transactionnel de la page publique
 * `/restaurants/{slug}` (modèle : /shop, RESTO-805 #6404).
 *
 * Menu par catégories → panier (quantités + note par article) → commande
 * idempotente (`idempotency_key` uuid, rejeu → `created:false`) → confirmation
 * (référence) → paiement (cash à réception / mobile money — jamais de carte,
 * 422 serveur) → suivi par référence (polling léger 15 s + refresh manuel) →
 * avis post-commande (order_ref pré-rempli, statut pending côté serveur).
 * Tous les appels passent par le proxy same-origin `/api/v1` (apiFetch),
 * endpoints publics par slug (RESTO-902 #7747) — AUCUN jeton.
 */

const TRACK_POLL_MS = 15_000;

interface CartLine {
  quantity: number;
  note: string;
}

interface PublicOrderResult {
  reference: string;
  status: string;
  order_type: string;
  subtotal_minor: number;
  tax_minor: number;
  total_minor: number;
  currency: string;
  items_count: number;
  created: boolean;
}

interface PublicOrderTrack {
  reference: string;
  status: string;
  order_type: string;
  subtotal_minor: number;
  tax_minor: number;
  total_minor: number;
  currency: string;
  items: { name: string; quantity: number; unit_price_minor: number; line_total_minor: number }[];
  updated_at: string | null;
}

interface RestaurantOrderPanelProps {
  locale: AppLocale;
  slug: string;
  menu: PublicMenuCategory[];
  initialReviews: PublicReview[];
  initialReviewsMeta: PublicListMeta | null;
}

function uuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `resto-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

export default function RestaurantOrderPanel({
  locale,
  slug,
  menu,
  initialReviews,
  initialReviewsMeta,
}: RestaurantOrderPanelProps) {
  const base = `/public/restaurants/${encodeURIComponent(slug)}`;

  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [cart, setCart] = useState<Record<string, CartLine>>({});

  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [orderType, setOrderType] = useState<'pickup' | 'takeaway' | 'delivery'>('pickup');
  const [orderNote, setOrderNote] = useState('');
  const [ordering, setOrdering] = useState(false);
  const [orderError, setOrderError] = useState<string | null>(null);
  const [order, setOrder] = useState<PublicOrderResult | null>(null);

  const [tracking, setTracking] = useState<PublicOrderTrack | null>(null);
  const [paying, setPaying] = useState(false);
  const [payDone, setPayDone] = useState<'cash' | 'mobile_money' | null>(null);
  const [payError, setPayError] = useState<string | null>(null);

  const [reviews, setReviews] = useState<PublicReview[]>(initialReviews);
  const [reviewsMeta, setReviewsMeta] = useState<PublicListMeta | null>(initialReviewsMeta);
  const [reviewsPage, setReviewsPage] = useState(initialReviewsMeta?.current_page ?? 1);
  const [reviewOrderRef, setReviewOrderRef] = useState('');
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewComment, setReviewComment] = useState('');
  const [reviewAuthor, setReviewAuthor] = useState('');
  const [reviewSubmitting, setReviewSubmitting] = useState(false);
  const [reviewMessage, setReviewMessage] = useState<{ ok: boolean; text: string } | null>(null);

  const products = useMemo(() => {
    const map = new Map<string, PublicMenuProduct>();
    for (const category of menu) {
      for (const product of category.products) {
        map.set(product.code, product);
      }
    }
    return map;
  }, [menu]);

  const visibleCategories = useMemo(
    () => (activeCategory === null ? menu : menu.filter((category) => category.name === activeCategory)),
    [activeCategory, menu],
  );

  const cartLines = useMemo(
    () =>
      Object.entries(cart)
        .map(([code, line]) => {
          const product = products.get(code);
          return product ? { product, ...line } : null;
        })
        .filter((line): line is { product: PublicMenuProduct; quantity: number; note: string } => line !== null),
    [cart, products],
  );

  const totalMinor = useMemo(
    () => cartLines.reduce((sum, line) => sum + line.product.price_minor * line.quantity, 0),
    [cartLines],
  );
  const currency = cartLines[0]?.product.currency ?? menu[0]?.products[0]?.currency ?? 'XOF';

  const addToCart = (code: string) =>
    setCart((prev) => ({
      ...prev,
      [code]: { quantity: (prev[code]?.quantity ?? 0) + 1, note: prev[code]?.note ?? '' },
    }));

  const removeFromCart = (code: string) =>
    setCart((prev) => {
      const next = { ...prev };
      const quantity = (next[code]?.quantity ?? 0) - 1;
      if (quantity <= 0) {
        delete next[code];
      } else {
        next[code] = { ...next[code], quantity };
      }
      return next;
    });

  const setItemNote = (code: string, note: string) =>
    setCart((prev) => (prev[code] ? { ...prev, [code]: { ...prev[code], note } } : prev));

  const track = useCallback(
    async (reference: string) => {
      try {
        const res = await apiFetch(`${base}/orders/${encodeURIComponent(reference)}`, {
          _cacheBust: true,
        });
        const json = (await res.json()) as { data?: PublicOrderTrack };
        setTracking(json.data ?? null);
      } catch {
        // Suivi best-effort : l'échec d'un poll ne casse pas la confirmation.
      }
    },
    [base],
  );

  // Polling léger du suivi tant qu'une commande est affichée (pattern /shop).
  const orderRef = order?.reference ?? null;
  const trackRef = useRef(track);
  trackRef.current = track;
  useEffect(() => {
    if (!orderRef) {
      return undefined;
    }
    const interval = window.setInterval(() => void trackRef.current(orderRef), TRACK_POLL_MS);
    return () => window.clearInterval(interval);
  }, [orderRef]);

  const checkout = async () => {
    if (customerName.trim() === '' || customerPhone.trim() === '') {
      setOrderError(t(locale, 'restaurant.public.formError'));
      return;
    }
    setOrdering(true);
    setOrderError(null);
    try {
      const res = await apiFetch(`${base}/orders`, {
        method: 'POST',
        body: JSON.stringify({
          customer_name: customerName.trim(),
          customer_phone: customerPhone.trim(),
          order_type: orderType,
          note: orderNote.trim() || undefined,
          idempotency_key: uuid(),
          items: cartLines.map((line) => ({
            product_code: line.product.code,
            quantity: line.quantity,
            note: line.note.trim() || undefined,
          })),
        }),
      });
      const json = (await res.json()) as { data?: PublicOrderResult };
      if (!json.data) {
        throw Object.assign(new Error('empty'), { status: 422 });
      }
      setOrder(json.data);
      setReviewOrderRef(json.data.reference);
      setCart({});
      setPayDone(null);
      setPayError(null);
      void track(json.data.reference);
    } catch (err) {
      const status = (err as { status?: number })?.status;
      setOrderError(
        t(
          locale,
          status === 422 ? 'restaurant.public.productUnavailable' : 'restaurant.shop.orderError',
        ),
      );
    } finally {
      setOrdering(false);
    }
  };

  const pay = async (provider: 'cash' | 'mobile_money') => {
    if (!order) {
      return;
    }
    setPaying(true);
    setPayError(null);
    try {
      await apiFetch(`${base}/orders/${encodeURIComponent(order.reference)}/pay`, {
        method: 'POST',
        body: JSON.stringify({ provider_code: provider, idempotency_key: uuid() }),
      });
      setPayDone(provider);
    } catch (err) {
      const status = (err as { status?: number })?.status;
      setPayError(
        t(locale, status === 409 ? 'restaurant.public.payNotPayable' : 'restaurant.shop.paymentError'),
      );
    } finally {
      setPaying(false);
    }
  };

  const loadReviews = async (page: number) => {
    try {
      const res = await apiFetch(`${base}/reviews?per_page=10${page > 1 ? `&page=${page}` : ''}`, {
        _cacheBust: true,
      });
      const json = (await res.json()) as { data?: PublicReview[]; meta?: PublicListMeta };
      setReviews(Array.isArray(json.data) ? json.data : []);
      setReviewsMeta(json.meta ?? null);
      setReviewsPage(page);
    } catch {
      setReviewMessage({ ok: false, text: t(locale, 'restaurant.public.reviewError') });
    }
  };

  const submitReview = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setReviewSubmitting(true);
    setReviewMessage(null);
    try {
      await apiFetch(`${base}/reviews`, {
        method: 'POST',
        body: JSON.stringify({
          order_ref: reviewOrderRef.trim(),
          rating: reviewRating,
          comment: reviewComment.trim() || undefined,
          author_name: reviewAuthor.trim(),
        }),
      });
      setReviewMessage({ ok: true, text: t(locale, 'restaurant.public.reviewSubmitted') });
      setReviewComment('');
    } catch (err) {
      const status = (err as { status?: number })?.status;
      const key =
        status === 409
          ? 'restaurant.public.reviewDuplicate'
          : status === 422
            ? 'restaurant.public.reviewNotEligible'
            : status === 404
              ? 'restaurant.public.trackNotFound'
              : 'restaurant.public.reviewError';
      setReviewMessage({ ok: false, text: t(locale, key) });
    } finally {
      setReviewSubmitting(false);
    }
  };

  const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100';
  const labelClass = 'text-[10px] font-black uppercase tracking-widest text-slate-500';

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
      {/* Menu */}
      <section aria-label={t(locale, 'restaurant.public.menuTitle')}>
        <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
          {t(locale, 'restaurant.public.menuTitle')}
        </h2>
        {menu.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-slate-300 bg-white/50 p-10 text-center text-sm font-medium text-slate-500">
            {t(locale, 'restaurant.public.emptyMenu')}
          </p>
        ) : (
          <>
            <div className="mb-4 flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => setActiveCategory(null)}
                className={`rounded-xl px-4 py-2 text-sm font-bold transition-colors ${
                  activeCategory === null
                    ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-md'
                    : 'border border-slate-200 bg-white text-slate-600 hover:border-amber-300'
                }`}
              >
                {t(locale, 'restaurant.shop.categoryAll')}
              </button>
              {menu.map((category) => (
                <button
                  key={category.name}
                  type="button"
                  onClick={() => setActiveCategory(category.name)}
                  className={`rounded-xl px-4 py-2 text-sm font-bold transition-colors ${
                    activeCategory === category.name
                      ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-md'
                      : 'border border-slate-200 bg-white text-slate-600 hover:border-amber-300'
                  }`}
                >
                  {category.name}
                </button>
              ))}
            </div>
            <div className="space-y-6">
              {visibleCategories.map((category) => (
                <div key={category.name}>
                  <h3 className="mb-2 text-sm font-black uppercase tracking-widest text-amber-700">
                    {category.name}
                  </h3>
                  <div className="grid gap-3 sm:grid-cols-2">
                    {category.products.map((product) => (
                      <article
                        key={product.code}
                        className="flex flex-col justify-between rounded-2xl border border-slate-200/70 bg-white/80 p-4 shadow-sm backdrop-blur-xl"
                      >
                        <div>
                          <p className="font-black tracking-tight text-slate-950">{product.name}</p>
                          {product.description ? (
                            <p className="mt-1 line-clamp-2 text-xs text-slate-500">{product.description}</p>
                          ) : null}
                        </div>
                        <div className="mt-3 flex items-center justify-between">
                          <span className="text-sm font-black text-amber-700">
                            {formatMoney(product.price_minor, product.currency, locale)}
                          </span>
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
                      </article>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </>
        )}

        {/* Avis clients */}
        <section aria-label={t(locale, 'restaurant.public.reviewsTitle')} className="mt-10">
          <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
            {t(locale, 'restaurant.public.reviewsTitle')}
          </h2>
          {reviews.length === 0 ? (
            <p className="rounded-2xl border border-dashed border-slate-300 bg-white/50 p-6 text-center text-sm font-medium text-slate-500">
              {t(locale, 'restaurant.public.noReviews')}
            </p>
          ) : (
            <ul className="space-y-3">
              {reviews.map((review, index) => (
                <li
                  key={`${review.author_name}-${review.date}-${index}`}
                  className="rounded-2xl border border-slate-200/70 bg-white/80 p-4 shadow-sm"
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-bold text-slate-800">{review.author_name}</p>
                    <p className="flex items-center gap-1 text-sm font-black text-amber-700">
                      <Star className="h-3.5 w-3.5" aria-hidden="true" />
                      {review.rating}/5
                    </p>
                  </div>
                  {review.comment ? <p className="mt-1 text-sm text-slate-600">{review.comment}</p> : null}
                  {review.date ? <p className="mt-1 text-xs text-slate-400">{review.date}</p> : null}
                </li>
              ))}
            </ul>
          )}
          {reviewsMeta && reviewsMeta.last_page > 1 ? (
            <nav className="mt-3 flex items-center justify-center gap-3 text-sm font-bold">
              <button
                type="button"
                disabled={reviewsPage <= 1}
                onClick={() => void loadReviews(reviewsPage - 1)}
                className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:border-amber-300 disabled:opacity-40"
              >
                {t(locale, 'restaurant.public.previous')}
              </button>
              <span className="text-slate-500">
                {t(locale, 'restaurant.public.pageLabel')} {reviewsPage} / {reviewsMeta.last_page}
              </span>
              <button
                type="button"
                disabled={reviewsPage >= reviewsMeta.last_page}
                onClick={() => void loadReviews(reviewsPage + 1)}
                className="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:border-amber-300 disabled:opacity-40"
              >
                {t(locale, 'restaurant.public.next')}
              </button>
            </nav>
          ) : null}

          {/* Formulaire d'avis post-commande (order_ref = preuve d'achat) */}
          <form
            onSubmit={submitReview}
            className="mt-6 space-y-3 rounded-3xl border border-white/40 bg-white/80 p-5 shadow-sm backdrop-blur-xl"
            aria-label={t(locale, 'restaurant.public.reviewFormTitle')}
          >
            <h3 className="font-black tracking-tight text-slate-950">
              {t(locale, 'restaurant.public.reviewFormTitle')}
            </h3>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block space-y-1">
                <span className={labelClass}>{t(locale, 'restaurant.public.referenceLabel')}</span>
                <input
                  type="text"
                  required
                  value={reviewOrderRef}
                  onChange={(e) => setReviewOrderRef(e.target.value)}
                  className={inputClass}
                  maxLength={40}
                />
                <span className="block text-xs text-slate-400">
                  {t(locale, 'restaurant.public.reviewOrderRefHint')}
                </span>
              </label>
              <label className="block space-y-1">
                <span className={labelClass}>{t(locale, 'restaurant.public.reviewAuthorLabel')}</span>
                <input
                  type="text"
                  required
                  value={reviewAuthor}
                  onChange={(e) => setReviewAuthor(e.target.value)}
                  className={inputClass}
                  maxLength={120}
                />
              </label>
            </div>
            <fieldset>
              <legend className={labelClass}>{t(locale, 'restaurant.public.reviewRatingLabel')}</legend>
              <div className="mt-1 flex gap-1" role="radiogroup">
                {[1, 2, 3, 4, 5].map((value) => (
                  <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={reviewRating === value}
                    aria-label={`${t(locale, 'restaurant.public.reviewRatingLabel')} ${value}`}
                    onClick={() => setReviewRating(value)}
                    className={`rounded-lg p-1.5 ${value <= reviewRating ? 'text-amber-500' : 'text-slate-300'}`}
                  >
                    <Star className="h-6 w-6 fill-current" aria-hidden="true" />
                  </button>
                ))}
              </div>
            </fieldset>
            <label className="block space-y-1">
              <span className={labelClass}>{t(locale, 'restaurant.public.reviewCommentLabel')}</span>
              <textarea
                value={reviewComment}
                onChange={(e) => setReviewComment(e.target.value)}
                className={inputClass}
                rows={3}
                maxLength={1000}
              />
            </label>
            {reviewMessage ? (
              <p
                role={reviewMessage.ok ? 'status' : 'alert'}
                className={`text-sm font-bold ${reviewMessage.ok ? 'text-emerald-700' : 'text-rose-600'}`}
              >
                {reviewMessage.text}
              </p>
            ) : null}
            <button
              type="submit"
              disabled={reviewSubmitting}
              className="rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-2 text-sm font-black text-white shadow-md disabled:opacity-50"
            >
              {reviewSubmitting
                ? t(locale, 'restaurant.public.reviewSubmitting')
                : t(locale, 'restaurant.public.reviewSubmit')}
            </button>
          </form>
        </section>
      </section>

      {/* Panier + checkout + confirmation + suivi */}
      <aside className="h-fit space-y-4 lg:sticky lg:top-6">
        <div className="rounded-3xl border border-white/40 bg-white/80 p-5 shadow-sm backdrop-blur-xl">
          <h2 className="mb-3 text-lg font-black tracking-tight text-slate-950">
            {t(locale, 'restaurant.shop.cart')}
          </h2>
          {cartLines.length === 0 ? (
            <p className="py-6 text-center text-sm font-medium text-slate-500">
              {t(locale, 'restaurant.shop.emptyCart')}
            </p>
          ) : (
            <div className="space-y-3">
              {cartLines.map(({ product, quantity, note }) => (
                <div key={product.code} className="space-y-1.5 border-b border-slate-100 pb-3">
                  <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-bold text-slate-800">{product.name}</p>
                      <p className="text-xs text-slate-400">
                        {formatMoney(product.price_minor, product.currency, locale)}
                      </p>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <button
                        type="button"
                        onClick={() => removeFromCart(product.code)}
                        className="rounded-lg p-1 text-slate-400 hover:bg-slate-100"
                        aria-label={`${t(locale, 'restaurant.shop.remove')} ${product.name}`}
                      >
                        {quantity === 1 ? (
                          <Trash2 className="h-4 w-4" aria-hidden="true" />
                        ) : (
                          <Minus className="h-4 w-4" aria-hidden="true" />
                        )}
                      </button>
                      <span className="w-6 text-center text-sm font-black text-slate-800">{quantity}</span>
                      <button
                        type="button"
                        onClick={() => addToCart(product.code)}
                        className="rounded-lg p-1 text-amber-600 hover:bg-amber-50"
                        aria-label={`${t(locale, 'restaurant.shop.add')} ${product.name}`}
                      >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                      </button>
                    </div>
                  </div>
                  <input
                    type="text"
                    value={note}
                    onChange={(e) => setItemNote(product.code, e.target.value)}
                    placeholder={t(locale, 'restaurant.public.itemNotePlaceholder')}
                    aria-label={`${t(locale, 'restaurant.public.itemNoteLabel')} — ${product.name}`}
                    className={inputClass}
                    maxLength={200}
                  />
                </div>
              ))}

              <p className="flex justify-between text-sm">
                <span className="text-slate-500">{t(locale, 'restaurant.shop.total')}</span>
                <span className="font-black text-amber-700">{formatMoney(totalMinor, currency, locale)}</span>
              </p>

              {/* Checkout */}
              <div className="space-y-3 pt-2">
                <h3 className="font-black tracking-tight text-slate-950">
                  {t(locale, 'restaurant.public.checkoutTitle')}
                </h3>
                <label className="block space-y-1">
                  <span className={labelClass}>{t(locale, 'restaurant.public.nameLabel')}</span>
                  <input
                    type="text"
                    required
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    className={inputClass}
                    maxLength={120}
                  />
                </label>
                <label className="block space-y-1">
                  <span className={labelClass}>{t(locale, 'restaurant.public.phoneLabel')}</span>
                  <input
                    type="tel"
                    required
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    className={inputClass}
                    maxLength={30}
                  />
                </label>
                <label className="block space-y-1">
                  <span className={labelClass}>{t(locale, 'restaurant.public.orderTypeLabel')}</span>
                  <select
                    value={orderType}
                    onChange={(e) => setOrderType(e.target.value as 'pickup' | 'takeaway' | 'delivery')}
                    className={inputClass}
                  >
                    <option value="pickup">{t(locale, 'restaurant.public.orderTypePickup')}</option>
                    <option value="takeaway">{t(locale, 'restaurant.public.orderTypeTakeaway')}</option>
                    <option value="delivery">{t(locale, 'restaurant.public.orderTypeDelivery')}</option>
                  </select>
                </label>
                <label className="block space-y-1">
                  <span className={labelClass}>{t(locale, 'restaurant.public.orderNoteLabel')}</span>
                  <textarea
                    value={orderNote}
                    onChange={(e) => setOrderNote(e.target.value)}
                    className={inputClass}
                    rows={2}
                    maxLength={500}
                  />
                </label>
                {orderError ? (
                  <p className="text-sm font-bold text-rose-600" role="alert">
                    {orderError}
                  </p>
                ) : null}
                <button
                  type="button"
                  onClick={() => void checkout()}
                  disabled={ordering}
                  className="w-full rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-3 text-sm font-black text-white shadow-md shadow-amber-500/20 hover:from-amber-600 hover:to-orange-700 disabled:opacity-50"
                >
                  {ordering
                    ? t(locale, 'restaurant.shop.ordering')
                    : `${t(locale, 'restaurant.shop.checkout')} — ${formatMoney(totalMinor, currency, locale)}`}
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Confirmation + paiement + suivi */}
        {order ? (
          <div className="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-5" role="status">
            <div className="flex items-center gap-2 text-emerald-800">
              <CheckCircle2 className="h-5 w-5" aria-hidden="true" />
              <p className="font-black">{t(locale, 'restaurant.shop.orderCreated')}</p>
            </div>
            {!order.created ? (
              <p className="mt-1 text-xs font-medium text-emerald-700">
                {t(locale, 'restaurant.public.orderReplayed')}
              </p>
            ) : null}
            <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-[10px] font-black uppercase tracking-widest text-emerald-700">
                  {t(locale, 'restaurant.shop.orderRef')}
                </dt>
                <dd className="font-mono font-black text-slate-900">{order.reference}</dd>
              </div>
              <div>
                <dt className="text-[10px] font-black uppercase tracking-widest text-emerald-700">
                  {t(locale, 'restaurant.shop.orderStatus')}
                </dt>
                <dd className="font-bold text-slate-800">{tracking?.status ?? order.status}</dd>
              </div>
              <div>
                <dt className="text-[10px] font-black uppercase tracking-widest text-emerald-700">
                  {t(locale, 'restaurant.shop.total')}
                </dt>
                <dd className="font-black text-slate-900">
                  {formatMoney(order.total_minor, order.currency, locale)}
                </dd>
              </div>
            </dl>

            {/* Paiement — cash / mobile money uniquement (jamais de carte). */}
            {payDone ? (
              <p className="mt-3 text-sm font-bold text-emerald-800">
                {payDone === 'cash'
                  ? t(locale, 'restaurant.shop.payAtPickup')
                  : t(locale, 'restaurant.shop.pendingPayment')}
              </p>
            ) : (
              <div className="mt-4 space-y-2">
                <p className="text-[10px] font-black uppercase tracking-widest text-emerald-700">
                  {t(locale, 'restaurant.public.payTitle')}
                </p>
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={() => void pay('cash')}
                    disabled={paying}
                    className="rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-4 py-2 text-sm font-bold text-white shadow-md disabled:opacity-50"
                  >
                    {t(locale, 'restaurant.public.payCash')}
                  </button>
                  <button
                    type="button"
                    onClick={() => void pay('mobile_money')}
                    disabled={paying}
                    className="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
                  >
                    {t(locale, 'restaurant.public.payMobileMoney')}
                  </button>
                </div>
              </div>
            )}
            {payError ? (
              <p className="mt-2 text-sm font-bold text-rose-600" role="alert">
                {payError}
              </p>
            ) : null}

            {/* Suivi */}
            <div className="mt-4 rounded-2xl border border-emerald-100 bg-white/70 p-4 text-sm">
              <div className="flex items-center justify-between gap-2">
                <p className="font-bold text-slate-800">
                  {t(locale, 'restaurant.shop.trackTitle')} — {tracking?.status ?? order.status}
                </p>
                <button
                  type="button"
                  onClick={() => void track(order.reference)}
                  className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                >
                  <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
                  {t(locale, 'restaurant.public.refresh')}
                </button>
              </div>
              {tracking ? (
                <ul className="mt-2 space-y-1 text-xs text-slate-500">
                  {tracking.items.map((item, index) => (
                    <li key={`${item.name}-${index}`} className="flex justify-between">
                      <span>
                        {item.quantity} × {item.name}
                      </span>
                      <span>{formatMoney(item.line_total_minor, tracking.currency, locale)}</span>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          </div>
        ) : null}
      </aside>
    </div>
  );
}
