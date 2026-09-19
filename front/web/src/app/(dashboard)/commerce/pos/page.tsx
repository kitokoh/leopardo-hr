'use client';

/**
 * Espace vendeur Commerce (BC-17 RETAIL, #7675) — caisse (POS v1, #7674) :
 * ouverture de session (`POST /retail/pos/sessions`, une seule session
 * ouverte par emplacement), construction d'une commande depuis le catalogue
 * publié, encaissement multi-moyens (`POST /retail/pos/orders/{id}/payments`,
 * `idempotency_key` générée côté client via `crypto.randomUUID()` — rejeu
 * serveur sans doublon), ticket de caisse imprimable, annulation et clôture
 * (fonds compté, écart signé + motif). Totaux TOUJOURS calculés serveur.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Printer, ShoppingBag } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { readApiError } from '@/components/commerce/CommerceCrudTable';
import { apiFetch } from '@/lib/api-client';
import { formatMinor, parseMajorToMinor } from '@/lib/commerce-format';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** Payload de RetailPosSessionController. */
type PosSession = {
  id: number;
  location_id: number;
  status: string;
  opened_at: string | null;
  opening_cash_minor: number;
  expected_cash_minor: number | null;
  counted_cash_minor: number | null;
  variance_minor: number | null;
  variance_reason: string | null;
};

type RetailLocation = {
  id: number;
  name: string;
  is_active: boolean;
};

type RetailProductLite = {
  id: number;
  name: string;
  sku: string;
  price_minor: number;
  currency: string;
  status: string;
};

/** `receiptPayload` de RetailOrderController : commande + lignes + paiements. */
type ReceiptItem = {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number | string;
  unit_price_minor: number;
  line_total_minor: number;
  line_index: number;
};

type ReceiptPayment = {
  id: number;
  method: string;
  amount_minor: number;
  currency: string;
  status: string;
  paid_at: string | null;
  reference: string | null;
};

type Receipt = {
  id: number;
  reference: string;
  status: string;
  subtotal_minor: number;
  total_minor: number;
  currency: string;
  created_at: string | null;
  items: ReceiptItem[];
  payments: ReceiptPayment[];
};

type CartLine = { product: RetailProductLite; quantity: number };

const PAYMENT_METHODS = ['cash', 'card', 'mobile'] as const;

function methodLabel(locale: AppLocale, method: string): string {
  return t(locale, `commerce.paymentMethod.${method}`, method);
}

export default function CommercePosPage() {
  const locale = getPreferredLocale();
  const [locations, setLocations] = useState<RetailLocation[]>([]);
  const [products, setProducts] = useState<RetailProductLite[]>([]);
  const [session, setSession] = useState<PosSession | null>(null);
  const [closedSession, setClosedSession] = useState<PosSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);
  const [error, setError] = useState('');

  // Ouverture de session.
  const [openLocationId, setOpenLocationId] = useState('');
  const [openingCash, setOpeningCash] = useState('');

  // Panier + commande en cours.
  const [cart, setCart] = useState<CartLine[]>([]);
  const [receipt, setReceipt] = useState<Receipt | null>(null);

  // Encaissement.
  const [paymentMethod, setPaymentMethod] = useState<(typeof PAYMENT_METHODS)[number]>('cash');
  const [paymentAmount, setPaymentAmount] = useState('');

  // Clôture.
  const [showClose, setShowClose] = useState(false);
  const [countedCash, setCountedCash] = useState('');
  const [varianceReason, setVarianceReason] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [locationsRes, productsRes, sessionsRes] = await Promise.all([
        apiFetch('/retail/locations?is_active=1&per_page=100'),
        apiFetch('/retail/products?status=published&per_page=100'),
        apiFetch('/retail/pos/sessions?status=open&per_page=100'),
      ]);
      if (!locationsRes.ok || !productsRes.ok || !sessionsRes.ok) {
        throw new Error(`HTTP ${productsRes.status}`);
      }
      const locationsPayload = (await locationsRes.json()) as { data?: RetailLocation[] };
      const productsPayload = (await productsRes.json()) as { data?: RetailProductLite[] };
      const sessionsPayload = (await sessionsRes.json()) as { data?: PosSession[] };
      setLocations(Array.isArray(locationsPayload.data) ? locationsPayload.data : []);
      setProducts(Array.isArray(productsPayload.data) ? productsPayload.data : []);
      const open = Array.isArray(sessionsPayload.data) ? sessionsPayload.data : [];
      setSession(open[0] ?? null);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const locationName = useCallback(
    (id: number): string => locations.find((l) => l.id === id)?.name ?? `#${id}`,
    [locations],
  );

  const paidMinor = useMemo(
    () =>
      (receipt?.payments ?? [])
        .filter((payment) => payment.status !== 'failed')
        .reduce((sum, payment) => sum + payment.amount_minor, 0),
    [receipt],
  );

  const remainingMinor = receipt ? Math.max(0, receipt.total_minor - paidMinor) : 0;

  const runAction = useCallback(
    async (action: () => Promise<void>) => {
      setActing(true);
      setError('');
      try {
        await action();
      } catch (e) {
        setError(
          e instanceof Error && e.message
            ? e.message
            : t(locale, 'commerce.error.actionFailed', "L'action a échoué."),
        );
      } finally {
        setActing(false);
      }
    },
    [locale],
  );

  const openSession = () => {
    if (!openLocationId) {
      setError(t(locale, 'commerce.pos.selectLocation', 'Sélectionnez un emplacement.'));
      return;
    }
    const openingMinor = openingCash.trim() === '' ? 0 : parseMajorToMinor(openingCash);
    if (openingMinor === null || openingMinor < 0) {
      setError(t(locale, 'commerce.pos.invalidAmount', 'Montant invalide.'));
      return;
    }
    void runAction(async () => {
      const res = await apiFetch('/retail/pos/sessions', {
        method: 'POST',
        body: JSON.stringify({ location_id: Number(openLocationId), opening_cash_minor: openingMinor }),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
      }
      const payload = (await res.json()) as { data: PosSession };
      setSession(payload.data);
      setClosedSession(null);
      setOpeningCash('');
    });
  };

  const addToCart = (product: RetailProductLite) => {
    setCart((prev) => {
      const existing = prev.find((line) => line.product.id === product.id);
      if (existing) {
        return prev.map((line) =>
          line.product.id === product.id ? { ...line, quantity: line.quantity + 1 } : line,
        );
      }
      return [...prev, { product, quantity: 1 }];
    });
  };

  const setQuantity = (productId: number, quantity: number) => {
    setCart((prev) =>
      quantity <= 0
        ? prev.filter((line) => line.product.id !== productId)
        : prev.map((line) => (line.product.id === productId ? { ...line, quantity } : line)),
    );
  };

  const cartTotalMinor = useMemo(
    () => cart.reduce((sum, line) => sum + line.product.price_minor * line.quantity, 0),
    [cart],
  );

  const submitOrder = () => {
    if (!session || cart.length === 0) return;
    void runAction(async () => {
      const res = await apiFetch('/retail/pos/orders', {
        method: 'POST',
        body: JSON.stringify({
          pos_session_id: session.id,
          lines: cart.map((line) => ({ product_id: line.product.id, quantity: line.quantity })),
          idempotency_key: crypto.randomUUID(),
        }),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
      }
      const payload = (await res.json()) as { data: Receipt };
      setReceipt(payload.data);
      setCart([]);
      setPaymentAmount((payload.data.total_minor / 100).toFixed(2));
    });
  };

  const addPayment = () => {
    if (!receipt) return;
    const amountMinor = parseMajorToMinor(paymentAmount);
    if (amountMinor === null || amountMinor <= 0) {
      setError(t(locale, 'commerce.pos.invalidAmount', 'Montant invalide.'));
      return;
    }
    void runAction(async () => {
      const res = await apiFetch(`/retail/pos/orders/${receipt.id}/payments`, {
        method: 'POST',
        body: JSON.stringify({
          method: paymentMethod,
          amount_minor: amountMinor,
          idempotency_key: crypto.randomUUID(),
        }),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
      }
      const payload = (await res.json()) as { data: Receipt };
      setReceipt(payload.data);
      const remaining = Math.max(
        0,
        payload.data.total_minor -
          payload.data.payments
            .filter((payment) => payment.status !== 'failed')
            .reduce((sum, payment) => sum + payment.amount_minor, 0),
      );
      setPaymentAmount(remaining > 0 ? (remaining / 100).toFixed(2) : '');
    });
  };

  const cancelOrder = () => {
    if (!receipt) return;
    if (!window.confirm(t(locale, 'commerce.pos.cancelConfirm', 'Annuler cette commande ?'))) {
      return;
    }
    void runAction(async () => {
      const res = await apiFetch(`/retail/pos/orders/${receipt.id}/cancel`, {
        method: 'POST',
        body: JSON.stringify({}),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
      }
      const payload = (await res.json()) as { data: Receipt };
      setReceipt(payload.data);
    });
  };

  const closeSession = () => {
    if (!session) return;
    const countedMinor = parseMajorToMinor(countedCash);
    if (countedMinor === null || countedMinor < 0) {
      setError(t(locale, 'commerce.pos.invalidAmount', 'Montant invalide.'));
      return;
    }
    void runAction(async () => {
      const body: Record<string, unknown> = { counted_cash_minor: countedMinor };
      if (varianceReason.trim() !== '') body.variance_reason = varianceReason.trim();
      const res = await apiFetch(`/retail/pos/sessions/${session.id}/close`, {
        method: 'POST',
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
      }
      const payload = (await res.json()) as { data: PosSession };
      setClosedSession(payload.data);
      setSession(null);
      setReceipt(null);
      setCart([]);
      setShowClose(false);
      setCountedCash('');
      setVarianceReason('');
    });
  };

  const currency = products[0]?.currency ?? 'XOF';

  return (
    <ModulePageShell
      icon={ShoppingBag}
      title={t(locale, 'commerce.pos.pageTitle', 'Caisse')}
      description={t(locale, 'commerce.pos.pageSubtitle', 'Sessions de caisse, ventes, encaissements et tickets.')}
    >
      {error ? (
        <p className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>
      ) : null}

      {loading ? (
        <p className="rounded-2xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
          {t(locale, 'commerce.loading', 'Chargement…')}
        </p>
      ) : null}

      {!loading && closedSession ? (
        <section className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-slate-900">
            {t(locale, 'commerce.pos.closedTitle', 'Session clôturée')} — {locationName(closedSession.location_id)}
          </h2>
          <dl className="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div>
              <dt className="text-slate-500">{t(locale, 'commerce.pos.expectedCash', 'Espèces attendues')}</dt>
              <dd className="font-semibold text-slate-900">
                {formatMinor(locale, closedSession.expected_cash_minor ?? 0, currency)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{t(locale, 'commerce.pos.countedCash', 'Espèces comptées')}</dt>
              <dd className="font-semibold text-slate-900">
                {formatMinor(locale, closedSession.counted_cash_minor ?? 0, currency)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{t(locale, 'commerce.pos.variance', 'Écart')}</dt>
              <dd
                className={`font-semibold ${
                  (closedSession.variance_minor ?? 0) === 0 ? 'text-emerald-700' : 'text-red-600'
                }`}
              >
                {formatMinor(locale, closedSession.variance_minor ?? 0, currency)}
              </dd>
            </div>
            <div>
              <dt className="text-slate-500">{t(locale, 'commerce.pos.varianceReason', 'Motif de l’écart')}</dt>
              <dd className="font-semibold text-slate-900">{closedSession.variance_reason ?? '—'}</dd>
            </div>
          </dl>
        </section>
      ) : null}

      {!loading && !session ? (
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-slate-900">
            {t(locale, 'commerce.pos.openTitle', 'Ouvrir une session de caisse')}
          </h2>
          <p className="mt-1 text-sm text-slate-600">
            {t(locale, 'commerce.pos.openHint', 'Une seule session ouverte par emplacement.')}
          </p>
          <div className="mt-3 flex flex-wrap items-end gap-3">
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'commerce.stock.location', 'Emplacement')} <span className="text-red-500">*</span>
              </span>
              <select
                value={openLocationId}
                onChange={(e) => setOpenLocationId(e.target.value)}
                className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'commerce.form.selectPlaceholder', '— Sélectionner —')}</option>
                {locations.map((location) => (
                  <option key={location.id} value={String(location.id)}>
                    {location.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'commerce.pos.openingCash', 'Fonds de caisse')}
              </span>
              <input
                type="text"
                inputMode="decimal"
                value={openingCash}
                onChange={(e) => setOpeningCash(e.target.value)}
                className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <button
              type="button"
              onClick={openSession}
              disabled={acting}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
            >
              {t(locale, 'commerce.pos.open', 'Ouvrir la caisse')}
            </button>
          </div>
        </section>
      ) : null}

      {!loading && session ? (
        <div className="space-y-6">
          <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <p className="text-sm font-semibold text-emerald-800">
              {t(locale, 'commerce.pos.sessionBanner', 'Session ouverte')} — {locationName(session.location_id)} —{' '}
              {t(locale, 'commerce.pos.openingCash', 'Fonds de caisse')} :{' '}
              {formatMinor(locale, session.opening_cash_minor, currency)}
            </p>
            <button
              type="button"
              onClick={() => setShowClose((v) => !v)}
              className="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"
            >
              {t(locale, 'commerce.pos.close', 'Clôturer la caisse')}
            </button>
          </section>

          {showClose ? (
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <h2 className="text-lg font-bold text-slate-900">
                {t(locale, 'commerce.pos.closeTitle', 'Clôture de session')}
              </h2>
              <p className="mt-1 text-sm text-slate-600">
                {t(locale, 'commerce.pos.closeHint', 'L’attendu et l’écart sont calculés côté serveur (fonds d’ouverture + espèces encaissées).')}
              </p>
              <div className="mt-3 flex flex-wrap items-end gap-3">
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {t(locale, 'commerce.pos.countedCash', 'Espèces comptées')} <span className="text-red-500">*</span>
                  </span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={countedCash}
                    onChange={(e) => setCountedCash(e.target.value)}
                    className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <label className="block grow text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {t(locale, 'commerce.pos.varianceReason', 'Motif de l’écart')}
                  </span>
                  <input
                    type="text"
                    value={varianceReason}
                    maxLength={2000}
                    onChange={(e) => setVarianceReason(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <button
                  type="button"
                  onClick={closeSession}
                  disabled={acting}
                  className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                >
                  {t(locale, 'commerce.pos.confirmClose', 'Confirmer la clôture')}
                </button>
              </div>
            </section>
          ) : null}

          {!receipt ? (
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <section>
                <h2 className="mb-3 text-lg font-bold text-slate-900">
                  {t(locale, 'commerce.pos.catalog', 'Catalogue publié')}
                </h2>
                {products.length === 0 ? (
                  <p className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
                    {t(locale, 'commerce.pos.noProducts', 'Aucun produit publié.')}
                  </p>
                ) : (
                  <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {products.map((product) => (
                      <button
                        key={product.id}
                        type="button"
                        onClick={() => addToCart(product)}
                        className="rounded-xl border border-slate-200 bg-white p-3 text-start shadow-sm transition hover:border-emerald-400 hover:shadow-md"
                      >
                        <p className="font-semibold text-slate-900">{product.name}</p>
                        <p className="mt-1 text-xs text-slate-500">{product.sku}</p>
                        <p className="mt-2 text-sm font-bold text-emerald-700">
                          {formatMinor(locale, product.price_minor, product.currency)}
                        </p>
                      </button>
                    ))}
                  </div>
                )}
              </section>

              <section>
                <h2 className="mb-3 text-lg font-bold text-slate-900">
                  {t(locale, 'commerce.pos.cart', 'Commande en cours')}
                </h2>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                  {cart.length === 0 ? (
                    <p className="py-6 text-center text-sm text-slate-500">
                      {t(locale, 'commerce.pos.emptyCart', 'Aucun article. Cliquez sur un produit pour l’ajouter.')}
                    </p>
                  ) : (
                    <ul className="divide-y divide-slate-100">
                      {cart.map((line) => (
                        <li key={line.product.id} className="flex items-center justify-between gap-3 py-2">
                          <div className="min-w-0">
                            <p className="truncate font-medium text-slate-900">{line.product.name}</p>
                            <p className="text-xs text-slate-500">
                              {formatMinor(locale, line.product.price_minor, line.product.currency)}
                            </p>
                          </div>
                          <div className="flex items-center gap-2">
                            <input
                              type="number"
                              min={0}
                              value={line.quantity}
                              aria-label={t(locale, 'commerce.pos.quantity', 'Quantité')}
                              onChange={(e) => setQuantity(line.product.id, Number(e.target.value))}
                              className="w-20 rounded-lg border border-slate-200 px-2 py-1 text-sm focus:border-emerald-500 focus:outline-none"
                            />
                            <button
                              type="button"
                              onClick={() => setQuantity(line.product.id, 0)}
                              className="text-sm font-medium text-red-500 hover:text-red-700"
                            >
                              {t(locale, 'commerce.pos.removeLine', 'Retirer')}
                            </button>
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                  <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-3">
                    <p className="text-sm text-slate-600">
                      {t(locale, 'commerce.pos.estimatedTotal', 'Total estimé (le total final est calculé serveur)')}
                    </p>
                    <p className="text-lg font-black text-slate-900">
                      {formatMinor(locale, cartTotalMinor, currency)}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={submitOrder}
                    disabled={acting || cart.length === 0}
                    className="mt-3 w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                  >
                    {t(locale, 'commerce.pos.submitOrder', 'Valider la commande')}
                  </button>
                </div>
              </section>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:border-0 print:shadow-none">
                <div className="flex items-center justify-between">
                  <h2 className="text-lg font-bold text-slate-900">
                    {t(locale, 'commerce.pos.receipt', 'Ticket de caisse')} — {receipt.reference}
                  </h2>
                  <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 print:hidden"
                  >
                    <Printer className="h-4 w-4" /> {t(locale, 'commerce.pos.print', 'Imprimer')}
                  </button>
                </div>
                <p className="mt-1 text-sm text-slate-500">
                  {t(locale, 'commerce.common.status', 'Statut')} :{' '}
                  {t(locale, `commerce.orderStatus.${receipt.status}`, receipt.status)}
                </p>
                <table className="mt-4 min-w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-start">
                      <th className="py-2 text-start font-semibold text-slate-700">
                        {t(locale, 'commerce.pos.item', 'Article')}
                      </th>
                      <th className="py-2 text-start font-semibold text-slate-700">
                        {t(locale, 'commerce.pos.quantity', 'Quantité')}
                      </th>
                      <th className="py-2 text-end font-semibold text-slate-700">
                        {t(locale, 'commerce.pos.lineTotal', 'Total ligne')}
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {receipt.items.map((item) => (
                      <tr key={item.id}>
                        <td className="py-2 text-slate-900">{item.product_name}</td>
                        <td className="py-2 text-slate-700">{String(item.quantity)}</td>
                        <td className="py-2 text-end text-slate-900">
                          {formatMinor(locale, item.line_total_minor, receipt.currency)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <div className="mt-3 space-y-1 border-t border-slate-200 pt-3 text-sm">
                  <p className="flex justify-between">
                    <span className="text-slate-600">{t(locale, 'commerce.pos.total', 'Total')}</span>
                    <span className="font-black text-slate-900">
                      {formatMinor(locale, receipt.total_minor, receipt.currency)}
                    </span>
                  </p>
                  <p className="flex justify-between">
                    <span className="text-slate-600">{t(locale, 'commerce.pos.paid', 'Payé')}</span>
                    <span className="font-semibold text-slate-900">
                      {formatMinor(locale, paidMinor, receipt.currency)}
                    </span>
                  </p>
                  <p className="flex justify-between">
                    <span className="text-slate-600">{t(locale, 'commerce.pos.remaining', 'Reste à payer')}</span>
                    <span className="font-semibold text-slate-900">
                      {formatMinor(locale, remainingMinor, receipt.currency)}
                    </span>
                  </p>
                </div>
                {receipt.payments.length > 0 ? (
                  <ul className="mt-3 space-y-1 border-t border-slate-200 pt-3 text-sm text-slate-700">
                    {receipt.payments.map((payment) => (
                      <li key={payment.id} className="flex justify-between">
                        <span>{methodLabel(locale, payment.method)}</span>
                        <span>{formatMinor(locale, payment.amount_minor, payment.currency)}</span>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </section>

              <section className="space-y-4 print:hidden">
                {receipt.status === 'draft' ? (
                  <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-bold text-slate-900">
                      {t(locale, 'commerce.pos.payment', 'Encaissement')}
                    </h2>
                    <div className="mt-3 flex flex-wrap items-end gap-3">
                      <label className="block text-sm">
                        <span className="mb-1 block font-medium text-slate-700">
                          {t(locale, 'commerce.pos.method', 'Moyen de paiement')}
                        </span>
                        <select
                          value={paymentMethod}
                          onChange={(e) =>
                            setPaymentMethod(e.target.value as (typeof PAYMENT_METHODS)[number])
                          }
                          className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                        >
                          {PAYMENT_METHODS.map((method) => (
                            <option key={method} value={method}>
                              {methodLabel(locale, method)}
                            </option>
                          ))}
                        </select>
                      </label>
                      <label className="block text-sm">
                        <span className="mb-1 block font-medium text-slate-700">
                          {t(locale, 'commerce.pos.amount', 'Montant')}
                        </span>
                        <input
                          type="text"
                          inputMode="decimal"
                          value={paymentAmount}
                          onChange={(e) => setPaymentAmount(e.target.value)}
                          className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                        />
                      </label>
                      <button
                        type="button"
                        onClick={addPayment}
                        disabled={acting}
                        className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                      >
                        {t(locale, 'commerce.pos.addPayment', 'Encaisser')}
                      </button>
                    </div>
                  </div>
                ) : null}

                {receipt.status === 'completed' ? (
                  <p className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {t(locale, 'commerce.pos.completed', 'Commande encaissée : le stock a été décrémenté.')}
                  </p>
                ) : null}
                {receipt.status === 'cancelled' ? (
                  <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {t(locale, 'commerce.pos.cancelled', 'Commande annulée : le stock a été restauré.')}
                  </p>
                ) : null}

                <div className="flex flex-wrap gap-2">
                  {receipt.status !== 'cancelled' ? (
                    <button
                      type="button"
                      onClick={cancelOrder}
                      disabled={acting}
                      className="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                    >
                      {t(locale, 'commerce.pos.cancelOrder', 'Annuler la commande')}
                    </button>
                  ) : null}
                  <button
                    type="button"
                    onClick={() => {
                      setReceipt(null);
                      setPaymentAmount('');
                    }}
                    className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                  >
                    {t(locale, 'commerce.pos.newOrder', 'Nouvelle vente')}
                  </button>
                </div>
              </section>
            </div>
          )}
        </div>
      ) : null}
    </ModulePageShell>
  );
}
