'use client';

/**
 * Espace vendeur Commerce (BC-17 RETAIL, #7675) — gestion de stock :
 * emplacements (`/retail/locations`, CRUD via CommerceCrudTable), niveaux
 * (`/retail/stock/levels`, filtres emplacement/produit/sous seuil),
 * enregistrement d'un mouvement (`POST /retail/stock/movements` — seule
 * voie d'écriture des quantités, delta SIGNÉ déduit du motif via
 * `signedQuantityDelta`), journal des mouvements et alertes de stock bas.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Boxes } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  CommerceCrudTable,
  readApiError,
  type CommerceCrudConfig,
} from '@/components/commerce/CommerceCrudTable';
import { apiFetch } from '@/lib/api-client';
import {
  STOCK_REASON_CODES,
  reasonDirection,
  signedQuantityDelta,
  type StockReasonCode,
} from '@/lib/commerce-format';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** Payload de RetailLocationController (enveloppe `{ data }`). */
type RetailLocation = {
  id: number;
  name: string;
  code: string;
  type: string;
  is_active: boolean;
};

type RetailProductLite = {
  id: number;
  name: string;
  sku: string;
};

/** `levelPayload` de RetailStockController. */
type StockLevel = {
  id: number;
  location_id: number;
  product_id: number;
  quantity: number | string;
  alert_threshold: number | string | null;
  reorder_level: number | string | null;
};

/** `movementPayload` de RetailStockController. */
type StockMovement = {
  id: number;
  location_id: number;
  product_id: number;
  quantity_delta: number | string;
  reason_code: string;
  note: string | null;
  created_at: string | null;
};

type MovementFormState = {
  location_id: string;
  product_id: string;
  reason_code: StockReasonCode;
  quantity: string;
  note: string;
};

const EMPTY_MOVEMENT_FORM: MovementFormState = {
  location_id: '',
  product_id: '',
  reason_code: 'purchase',
  quantity: '',
  note: '',
};

function reasonLabel(locale: AppLocale, reason: string): string {
  return t(locale, `commerce.stockReason.${reason}`, reason);
}

function formatDateTime(locale: AppLocale, iso: string | null): string {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale);
}

function LevelsTable({
  levels,
  loading,
  locationName,
  productName,
  emptyLabel,
}: {
  levels: StockLevel[];
  loading: boolean;
  locationName: (id: number) => string;
  productName: (id: number) => string;
  emptyLabel: string;
}) {
  const locale = getPreferredLocale();
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
      <table className="min-w-full divide-y divide-slate-200 text-sm">
        <thead className="bg-slate-50">
          <tr>
            <th className="px-4 py-3 text-start font-semibold text-slate-700">
              {t(locale, 'commerce.stock.location', 'Emplacement')}
            </th>
            <th className="px-4 py-3 text-start font-semibold text-slate-700">
              {t(locale, 'commerce.stock.product', 'Produit')}
            </th>
            <th className="px-4 py-3 text-start font-semibold text-slate-700">
              {t(locale, 'commerce.stock.quantity', 'Quantité')}
            </th>
            <th className="px-4 py-3 text-start font-semibold text-slate-700">
              {t(locale, 'commerce.stock.threshold', 'Seuil d’alerte')}
            </th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {loading ? (
            <tr>
              <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                {t(locale, 'commerce.loading', 'Chargement…')}
              </td>
            </tr>
          ) : levels.length === 0 ? (
            <tr>
              <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                {emptyLabel}
              </td>
            </tr>
          ) : (
            levels.map((level) => (
              <tr key={level.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 text-slate-700">{locationName(level.location_id)}</td>
                <td className="px-4 py-3 text-slate-700">{productName(level.product_id)}</td>
                <td className="px-4 py-3 font-semibold text-slate-900">{String(level.quantity)}</td>
                <td className="px-4 py-3 text-slate-700">
                  {level.alert_threshold === null ? '—' : String(level.alert_threshold)}
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

export default function CommerceStockPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'levels' | 'movements' | 'locations' | 'alerts'>('levels');
  const [locations, setLocations] = useState<RetailLocation[]>([]);
  const [products, setProducts] = useState<RetailProductLite[]>([]);
  const [error, setError] = useState('');

  // Niveaux + filtres.
  const [levels, setLevels] = useState<StockLevel[]>([]);
  const [levelsLoading, setLevelsLoading] = useState(true);
  const [filterLocation, setFilterLocation] = useState('');
  const [filterProduct, setFilterProduct] = useState('');
  const [belowThreshold, setBelowThreshold] = useState(false);

  // Journal des mouvements.
  const [movements, setMovements] = useState<StockMovement[]>([]);
  const [movementsLoading, setMovementsLoading] = useState(true);

  // Alertes.
  const [alerts, setAlerts] = useState<StockLevel[]>([]);
  const [alertsLoading, setAlertsLoading] = useState(true);

  // Formulaire de mouvement.
  const [form, setForm] = useState<MovementFormState>(EMPTY_MOVEMENT_FORM);
  const [formError, setFormError] = useState('');
  const [formSuccess, setFormSuccess] = useState('');
  const [saving, setSaving] = useState(false);

  const locationName = useCallback(
    (id: number): string => locations.find((l) => l.id === id)?.name ?? `#${id}`,
    [locations],
  );

  const productName = useCallback(
    (id: number): string => products.find((p) => p.id === id)?.name ?? `#${id}`,
    [products],
  );

  const loadReferentials = useCallback(async () => {
    try {
      const [locationsRes, productsRes] = await Promise.all([
        apiFetch('/retail/locations?per_page=100'),
        apiFetch('/retail/products?per_page=100'),
      ]);
      if (locationsRes.ok) {
        const payload = (await locationsRes.json()) as { data?: RetailLocation[] };
        setLocations(Array.isArray(payload.data) ? payload.data : []);
      }
      if (productsRes.ok) {
        const payload = (await productsRes.json()) as { data?: RetailProductLite[] };
        setProducts(Array.isArray(payload.data) ? payload.data : []);
      }
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    }
  }, [locale]);

  const loadLevels = useCallback(async () => {
    setLevelsLoading(true);
    try {
      const params = new URLSearchParams({ per_page: '100' });
      if (filterLocation) params.set('location_id', filterLocation);
      if (filterProduct) params.set('product_id', filterProduct);
      if (belowThreshold) params.set('below_threshold', '1');
      const res = await apiFetch(`/retail/stock/levels?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: StockLevel[] };
      setLevels(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLevelsLoading(false);
    }
  }, [filterLocation, filterProduct, belowThreshold, locale]);

  const loadMovements = useCallback(async () => {
    setMovementsLoading(true);
    try {
      const res = await apiFetch('/retail/stock/movements?per_page=50');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: StockMovement[] };
      setMovements(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setMovementsLoading(false);
    }
  }, [locale]);

  const loadAlerts = useCallback(async () => {
    setAlertsLoading(true);
    try {
      const res = await apiFetch('/retail/stock/alerts?per_page=100');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: StockLevel[] };
      setAlerts(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setAlertsLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadReferentials();
    void loadMovements();
    void loadAlerts();
  }, [loadReferentials, loadMovements, loadAlerts]);

  useEffect(() => {
    void loadLevels();
  }, [loadLevels]);

  const submitMovement = async () => {
    setFormSuccess('');
    if (!form.location_id || !form.product_id || form.quantity.trim() === '') {
      setFormError(t(locale, 'commerce.form.required', 'Ce champ est obligatoire.'));
      return;
    }
    const quantity = Number(form.quantity.replace(',', '.'));
    if (!Number.isFinite(quantity) || quantity === 0) {
      setFormError(t(locale, 'commerce.stock.invalidQuantity', 'Quantité invalide.'));
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      const payload: Record<string, unknown> = {
        location_id: Number(form.location_id),
        product_id: Number(form.product_id),
        quantity_delta: signedQuantityDelta(form.reason_code, quantity),
        reason_code: form.reason_code,
      };
      if (form.note.trim() !== '') payload.note = form.note.trim();
      const res = await apiFetch('/retail/stock/movements', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.saveFailed', "Échec de l'enregistrement."));
      }
      setForm(EMPTY_MOVEMENT_FORM);
      setFormSuccess(t(locale, 'commerce.stock.movementSaved', 'Mouvement enregistré.'));
      await Promise.all([loadLevels(), loadMovements(), loadAlerts()]);
    } catch (e) {
      setFormError(
        e instanceof Error && e.message
          ? e.message
          : t(locale, 'commerce.error.saveFailed', "Échec de l'enregistrement."),
      );
    } finally {
      setSaving(false);
    }
  };

  const locationsConfig: CommerceCrudConfig = useMemo(
    () => ({
      endpoint: '/retail/locations',
      listQuery: 'per_page=100',
      title: t(locale, 'commerce.locations.title', 'Emplacements'),
      columns: [
        { key: 'name', label: t(locale, 'commerce.locations.name', 'Nom') },
        { key: 'code', label: t(locale, 'commerce.locations.code', 'Code') },
        {
          key: 'type',
          label: t(locale, 'commerce.locations.type', 'Type'),
          render: (row) => t(locale, `commerce.locationType.${String(row.type)}`, String(row.type)),
        },
        { key: 'is_active', label: t(locale, 'commerce.locations.active', 'Actif') },
      ],
      fields: [
        {
          name: 'name',
          label: t(locale, 'commerce.locations.name', 'Nom'),
          type: 'text',
          required: true,
          maxLength: 160,
        },
        {
          name: 'code',
          label: t(locale, 'commerce.locations.code', 'Code'),
          type: 'text',
          required: true,
          maxLength: 40,
        },
        {
          name: 'type',
          label: t(locale, 'commerce.locations.type', 'Type'),
          type: 'select',
          options: [
            { value: 'store', label: t(locale, 'commerce.locationType.store', 'Boutique') },
            { value: 'warehouse', label: t(locale, 'commerce.locationType.warehouse', 'Entrepôt') },
          ],
        },
        {
          name: 'is_active',
          label: t(locale, 'commerce.locations.active', 'Actif'),
          type: 'checkbox',
        },
      ],
      searchKeys: ['name', 'code'],
    }),
    [locale],
  );

  const tabs = [
    { key: 'levels' as const, label: t(locale, 'commerce.tab.levels', 'Niveaux') },
    { key: 'movements' as const, label: t(locale, 'commerce.tab.movements', 'Mouvements') },
    { key: 'locations' as const, label: t(locale, 'commerce.tab.locations', 'Emplacements') },
    { key: 'alerts' as const, label: t(locale, 'commerce.tab.alerts', 'Alertes') },
  ];

  const direction = reasonDirection(form.reason_code);

  return (
    <ModulePageShell
      icon={Boxes}
      title={t(locale, 'commerce.stock.pageTitle', 'Stock')}
      description={t(locale, 'commerce.stock.pageSubtitle', 'Emplacements, niveaux, mouvements tracés et alertes de stock bas.')}
    >
      {error ? (
        <p className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>
      ) : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {tabs.map((entry) => (
          <button
            key={entry.key}
            type="button"
            onClick={() => setTab(entry.key)}
            className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
              tab === entry.key
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'bg-white text-slate-600 hover:bg-slate-100'
            }`}
          >
            {entry.label}
          </button>
        ))}
      </div>

      {tab === 'locations' ? <CommerceCrudTable config={locationsConfig} /> : null}

      {tab === 'levels' ? (
        <div className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'commerce.stock.location', 'Emplacement')}
              </span>
              <select
                value={filterLocation}
                onChange={(e) => setFilterLocation(e.target.value)}
                className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'commerce.stock.allLocations', 'Tous les emplacements')}</option>
                {locations.map((location) => (
                  <option key={location.id} value={String(location.id)}>
                    {location.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'commerce.stock.product', 'Produit')}
              </span>
              <select
                value={filterProduct}
                onChange={(e) => setFilterProduct(e.target.value)}
                className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
              >
                <option value="">{t(locale, 'commerce.stock.allProducts', 'Tous les produits')}</option>
                {products.map((product) => (
                  <option key={product.id} value={String(product.id)}>
                    {product.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={belowThreshold}
                onChange={(e) => setBelowThreshold(e.target.checked)}
                className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
              />
              {t(locale, 'commerce.stock.belowThreshold', 'Sous le seuil d’alerte uniquement')}
            </label>
          </div>
          <LevelsTable
            levels={levels}
            loading={levelsLoading}
            locationName={locationName}
            productName={productName}
            emptyLabel={t(locale, 'commerce.stock.noLevels', 'Aucun niveau de stock.')}
          />
        </div>
      ) : null}

      {tab === 'movements' ? (
        <div className="space-y-6">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-bold text-slate-900">
              {t(locale, 'commerce.stock.newMovement', 'Enregistrer un mouvement')}
            </h2>
            {formError ? (
              <p className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p>
            ) : null}
            {formSuccess ? (
              <p className="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{formSuccess}</p>
            ) : null}
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.stock.location', 'Emplacement')} <span className="text-red-500">*</span>
                </span>
                <select
                  value={form.location_id}
                  onChange={(e) => setForm((prev) => ({ ...prev, location_id: e.target.value }))}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
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
                  {t(locale, 'commerce.stock.product', 'Produit')} <span className="text-red-500">*</span>
                </span>
                <select
                  value={form.product_id}
                  onChange={(e) => setForm((prev) => ({ ...prev, product_id: e.target.value }))}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                >
                  <option value="">{t(locale, 'commerce.form.selectPlaceholder', '— Sélectionner —')}</option>
                  {products.map((product) => (
                    <option key={product.id} value={String(product.id)}>
                      {product.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.stock.reason', 'Motif')} <span className="text-red-500">*</span>
                </span>
                <select
                  value={form.reason_code}
                  onChange={(e) =>
                    setForm((prev) => ({ ...prev, reason_code: e.target.value as StockReasonCode }))
                  }
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                >
                  {STOCK_REASON_CODES.map((reason) => (
                    <option key={reason} value={reason}>
                      {reasonLabel(locale, reason)}
                    </option>
                  ))}
                </select>
                <span className="mt-1 block text-xs text-slate-500">
                  {direction === 'in'
                    ? t(locale, 'commerce.stockDirection.in', 'Entrée de stock (quantité ajoutée).')
                    : direction === 'out'
                      ? t(locale, 'commerce.stockDirection.out', 'Sortie de stock (quantité retirée).')
                      : t(locale, 'commerce.stockDirection.signed', 'Ajustement signé : saisissez le delta (+/−).')}
                </span>
              </label>
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.stock.quantity', 'Quantité')} <span className="text-red-500">*</span>
                </span>
                <input
                  type="text"
                  inputMode="decimal"
                  value={form.quantity}
                  onChange={(e) => setForm((prev) => ({ ...prev, quantity: e.target.value }))}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                />
              </label>
            </div>
            <label className="mt-3 block text-sm">
              <span className="mb-1 block font-medium text-slate-700">
                {t(locale, 'commerce.stock.note', 'Note')}
              </span>
              <input
                type="text"
                value={form.note}
                maxLength={2000}
                onChange={(e) => setForm((prev) => ({ ...prev, note: e.target.value }))}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
              />
            </label>
            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={() => void submitMovement()}
                disabled={saving}
                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {saving
                  ? t(locale, 'commerce.form.saving', 'Enregistrement…')
                  : t(locale, 'commerce.stock.saveMovement', 'Enregistrer le mouvement')}
              </button>
            </div>
          </section>

          <section>
            <h2 className="mb-3 text-lg font-bold text-slate-900">
              {t(locale, 'commerce.stock.history', 'Historique des mouvements')}
            </h2>
            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-start font-semibold text-slate-700">
                      {t(locale, 'commerce.stock.date', 'Date')}
                    </th>
                    <th className="px-4 py-3 text-start font-semibold text-slate-700">
                      {t(locale, 'commerce.stock.location', 'Emplacement')}
                    </th>
                    <th className="px-4 py-3 text-start font-semibold text-slate-700">
                      {t(locale, 'commerce.stock.product', 'Produit')}
                    </th>
                    <th className="px-4 py-3 text-start font-semibold text-slate-700">
                      {t(locale, 'commerce.stock.delta', 'Delta')}
                    </th>
                    <th className="px-4 py-3 text-start font-semibold text-slate-700">
                      {t(locale, 'commerce.stock.reason', 'Motif')}
                    </th>
                    <th className="px-4 py-3 text-start font-semibold text-slate-700">
                      {t(locale, 'commerce.stock.note', 'Note')}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {movementsLoading ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                        {t(locale, 'commerce.loading', 'Chargement…')}
                      </td>
                    </tr>
                  ) : movements.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                        {t(locale, 'commerce.stock.noMovements', 'Aucun mouvement enregistré.')}
                      </td>
                    </tr>
                  ) : (
                    movements.map((movement) => (
                      <tr key={movement.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3 text-slate-700">
                          {formatDateTime(locale, movement.created_at)}
                        </td>
                        <td className="px-4 py-3 text-slate-700">{locationName(movement.location_id)}</td>
                        <td className="px-4 py-3 text-slate-700">{productName(movement.product_id)}</td>
                        <td
                          className={`px-4 py-3 font-semibold ${
                            Number(movement.quantity_delta) < 0 ? 'text-red-600' : 'text-emerald-700'
                          }`}
                        >
                          {String(movement.quantity_delta)}
                        </td>
                        <td className="px-4 py-3 text-slate-700">
                          {reasonLabel(locale, movement.reason_code)}
                        </td>
                        <td className="px-4 py-3 text-slate-700">{movement.note ?? '—'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </div>
      ) : null}

      {tab === 'alerts' ? (
        <LevelsTable
          levels={alerts}
          loading={alertsLoading}
          locationName={locationName}
          productName={productName}
          emptyLabel={t(locale, 'commerce.stock.noAlerts', 'Aucune alerte de stock bas.')}
        />
      ) : null}
    </ModulePageShell>
  );
}
