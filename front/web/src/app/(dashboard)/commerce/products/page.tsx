'use client';

/**
 * Espace vendeur Commerce (BC-17 RETAIL, #7675) — gestion du catalogue :
 * catégories (`/retail/categories`, CRUD via CommerceCrudTable) et produits
 * (`/retail/products`, liste paginée serveur + filtre statut + formulaire
 * création/édition + publication/dépublication + suppression confirmée).
 *
 * Payloads alignés sur Store/UpdateRetailProductRequest : prix TOUJOURS en
 * minor units entières (`price_minor`/`cost_minor`, pattern Catalog #6880),
 * saisie en unités majeures convertie par `parseMajorToMinor`, devise dans
 * la whitelist `RetailPricePolicy::CURRENCIES`.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Tags } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  CommerceCrudTable,
  readApiError,
  type CommerceCrudConfig,
} from '@/components/commerce/CommerceCrudTable';
import { apiFetch } from '@/lib/api-client';
import { formatMinor, parseMajorToMinor } from '@/lib/commerce-format';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** Devises acceptées par le backend (RetailPricePolicy::CURRENCIES). */
const CURRENCIES = ['XOF', 'XAF', 'DZD', 'MAD', 'EUR', 'USD'] as const;

/** Payload de RetailCategoryController (enveloppe `{ data }`). */
type RetailCategory = {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
  position: number;
};

/** Payload de RetailProductController (enveloppe `{ data }`). */
type RetailProduct = {
  id: number;
  category_id: number | null;
  name: string;
  slug: string;
  sku: string;
  barcode: string | null;
  description: string | null;
  price_minor: number;
  cost_minor: number | null;
  currency: string;
  unit: string | null;
  status: string;
};

type PaginationMeta = { current_page: number; last_page: number; total: number };

type ProductFormState = {
  name: string;
  category_id: string;
  sku: string;
  barcode: string;
  price: string;
  currency: string;
  cost: string;
  unit: string;
  description: string;
};

const EMPTY_PRODUCT_FORM: ProductFormState = {
  name: '',
  category_id: '',
  sku: '',
  barcode: '',
  price: '',
  currency: 'XOF',
  cost: '',
  unit: '',
  description: '',
};

function statusLabel(locale: AppLocale, status: string): string {
  return t(locale, `commerce.productStatus.${status}`, status);
}

function ProductsPanel({ categories }: { categories: RetailCategory[] }) {
  const locale = getPreferredLocale();
  const [products, setProducts] = useState<RetailProduct[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<RetailProduct | null>(null);
  const [form, setForm] = useState<ProductFormState>(EMPTY_PRODUCT_FORM);
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [acting, setActing] = useState(false);

  const categoryName = useCallback(
    (categoryId: number | null): string => {
      if (categoryId === null) return '—';
      return categories.find((c) => c.id === categoryId)?.name ?? String(categoryId);
    },
    [categories],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '15' });
      if (statusFilter) params.set('status', statusFilter);
      const res = await apiFetch(`/retail/products?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: RetailProduct[]; meta?: PaginationMeta };
      setProducts(Array.isArray(payload.data) ? payload.data : []);
      setMeta(payload.meta ?? null);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY_PRODUCT_FORM);
    setFormError('');
    setShowForm(true);
  };

  const openEdit = (product: RetailProduct) => {
    setEditing(product);
    setForm({
      name: product.name,
      category_id: product.category_id === null ? '' : String(product.category_id),
      sku: product.sku,
      barcode: product.barcode ?? '',
      price: (product.price_minor / 100).toFixed(2),
      currency: product.currency,
      cost: product.cost_minor === null ? '' : (product.cost_minor / 100).toFixed(2),
      unit: product.unit ?? '',
      description: product.description ?? '',
    });
    setFormError('');
    setShowForm(true);
  };

  const submit = async () => {
    if (!form.name.trim() || !form.sku.trim()) {
      setFormError(t(locale, 'commerce.form.required', 'Ce champ est obligatoire.'));
      return;
    }
    const priceMinor = parseMajorToMinor(form.price);
    if (priceMinor === null || priceMinor < 0) {
      setFormError(t(locale, 'commerce.products.invalidPrice', 'Prix invalide.'));
      return;
    }
    const costMinor = form.cost.trim() === '' ? null : parseMajorToMinor(form.cost);
    if (form.cost.trim() !== '' && (costMinor === null || costMinor < 0)) {
      setFormError(t(locale, 'commerce.products.invalidCost', 'Coût invalide.'));
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      const payload: Record<string, unknown> = {
        name: form.name.trim(),
        sku: form.sku.trim(),
        barcode: form.barcode.trim() === '' ? null : form.barcode.trim(),
        category_id: form.category_id === '' ? null : Number(form.category_id),
        description: form.description.trim() === '' ? null : form.description.trim(),
        price_minor: priceMinor,
        currency: form.currency,
        unit: form.unit.trim() === '' ? null : form.unit.trim(),
      };
      if (costMinor !== null) payload.cost_minor = costMinor;
      const res = await apiFetch(editing ? `/retail/products/${editing.id}` : '/retail/products', {
        method: editing ? 'PUT' : 'POST',
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'commerce.error.saveFailed', "Échec de l'enregistrement."));
      }
      setShowForm(false);
      await load();
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

  const togglePublish = async (product: RetailProduct) => {
    setActing(true);
    setError('');
    try {
      const action = product.status === 'published' ? 'unpublish' : 'publish';
      const res = await apiFetch(`/retail/products/${product.id}/${action}`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'commerce.error.actionFailed', "L'action a échoué."));
    } finally {
      setActing(false);
    }
  };

  const remove = async (product: RetailProduct) => {
    if (!window.confirm(t(locale, 'commerce.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) {
      return;
    }
    setActing(true);
    setError('');
    try {
      const res = await apiFetch(`/retail/products/${product.id}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      setError(t(locale, 'commerce.error.deleteFailed', 'Échec de la suppression.'));
    } finally {
      setActing(false);
    }
  };

  const updateForm = (name: keyof ProductFormState, value: string) =>
    setForm((prev) => ({ ...prev, [name]: value }));

  const statusOptions = useMemo(
    () => [
      { value: '', label: t(locale, 'commerce.products.allStatuses', 'Tous les statuts') },
      { value: 'draft', label: statusLabel(locale, 'draft') },
      { value: 'published', label: statusLabel(locale, 'published') },
      { value: 'archived', label: statusLabel(locale, 'archived') },
    ],
    [locale],
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-bold text-slate-900">
            {t(locale, 'commerce.products.title', 'Produits')}
          </h2>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
        </div>
        <div className="flex items-center gap-2">
          <select
            value={statusFilter}
            onChange={(e) => {
              setPage(1);
              setStatusFilter(e.target.value);
            }}
            aria-label={t(locale, 'commerce.products.statusFilter', 'Filtrer par statut')}
            className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
          >
            {statusOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          <button
            type="button"
            onClick={openCreate}
            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
          >
            {t(locale, 'commerce.action.create', 'Créer')}
          </button>
        </div>
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.products.name', 'Nom')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.products.category', 'Catégorie')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">SKU</th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.products.price', 'Prix')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'commerce.common.status', 'Statut')}
              </th>
              <th className="px-4 py-3 text-end font-semibold text-slate-700">
                {t(locale, 'commerce.table.actions', 'Actions')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.loading', 'Chargement…')}
                </td>
              </tr>
            ) : products.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.table.empty', 'Aucun élément.')}
                </td>
              </tr>
            ) : (
              products.map((product) => (
                <tr key={product.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{product.name}</td>
                  <td className="px-4 py-3 text-slate-700">{categoryName(product.category_id)}</td>
                  <td className="px-4 py-3 text-slate-700">{product.sku}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {formatMinor(locale, product.price_minor, product.currency)}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                        product.status === 'published'
                          ? 'bg-emerald-100 text-emerald-700'
                          : product.status === 'archived'
                            ? 'bg-slate-200 text-slate-600'
                            : 'bg-amber-100 text-amber-700'
                      }`}
                    >
                      {statusLabel(locale, product.status)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-end">
                    <div className="flex justify-end gap-3">
                      <button
                        type="button"
                        disabled={acting}
                        className="font-medium text-cyan-700 hover:text-cyan-800 disabled:opacity-50"
                        onClick={() => void togglePublish(product)}
                      >
                        {product.status === 'published'
                          ? t(locale, 'commerce.products.unpublish', 'Dépublier')
                          : t(locale, 'commerce.products.publish', 'Publier')}
                      </button>
                      <button
                        type="button"
                        className="font-medium text-emerald-700 hover:text-emerald-800"
                        onClick={() => openEdit(product)}
                      >
                        {t(locale, 'commerce.action.edit', 'Modifier')}
                      </button>
                      <button
                        type="button"
                        disabled={acting}
                        className="font-medium text-red-500 hover:text-red-700 disabled:opacity-50"
                        onClick={() => void remove(product)}
                      >
                        {t(locale, 'commerce.action.delete', 'Supprimer')}
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between text-sm text-slate-600">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            {t(locale, 'commerce.pagination.previous', 'Précédent')}
          </button>
          <span>
            {t(locale, 'commerce.pagination.page', 'Page')} {meta.current_page} / {meta.last_page} —{' '}
            {meta.total} {t(locale, 'commerce.pagination.items', 'éléments')}
          </span>
          <button
            type="button"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-semibold hover:bg-slate-50 disabled:opacity-50"
          >
            {t(locale, 'commerce.pagination.next', 'Suivant')}
          </button>
        </div>
      ) : null}

      {showForm ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
          <div className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <h3 className="mb-4 text-lg font-bold text-slate-900">
              {editing
                ? t(locale, 'commerce.products.editTitle', 'Modifier le produit')
                : t(locale, 'commerce.products.createTitle', 'Créer un produit')}
            </h3>
            {formError ? (
              <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p>
            ) : null}
            <div className="space-y-3">
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.products.name', 'Nom')} <span className="text-red-500">*</span>
                </span>
                <input
                  type="text"
                  value={form.name}
                  maxLength={200}
                  onChange={(e) => updateForm('name', e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.products.category', 'Catégorie')}
                </span>
                <select
                  value={form.category_id}
                  onChange={(e) => updateForm('category_id', e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                >
                  <option value="">{t(locale, 'commerce.form.selectPlaceholder', '— Sélectionner —')}</option>
                  {categories.map((category) => (
                    <option key={category.id} value={String(category.id)}>
                      {category.name}
                    </option>
                  ))}
                </select>
              </label>
              <div className="grid grid-cols-2 gap-3">
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    SKU <span className="text-red-500">*</span>
                  </span>
                  <input
                    type="text"
                    value={form.sku}
                    maxLength={64}
                    onChange={(e) => updateForm('sku', e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {t(locale, 'commerce.products.barcode', 'Code-barres')}
                  </span>
                  <input
                    type="text"
                    value={form.barcode}
                    maxLength={64}
                    onChange={(e) => updateForm('barcode', e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {t(locale, 'commerce.products.price', 'Prix')} <span className="text-red-500">*</span>
                  </span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={form.price}
                    onChange={(e) => updateForm('price', e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {t(locale, 'commerce.products.currency', 'Devise')}
                  </span>
                  <select
                    value={form.currency}
                    onChange={(e) => updateForm('currency', e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  >
                    {CURRENCIES.map((currency) => (
                      <option key={currency} value={currency}>
                        {currency}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {t(locale, 'commerce.products.cost', 'Coût')}
                  </span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={form.cost}
                    onChange={(e) => updateForm('cost', e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                  />
                </label>
              </div>
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.products.unit', 'Unité')}
                </span>
                <input
                  type="text"
                  value={form.unit}
                  maxLength={30}
                  onChange={(e) => updateForm('unit', e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                />
              </label>
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-slate-700">
                  {t(locale, 'commerce.products.description', 'Description')}
                </span>
                <textarea
                  value={form.description}
                  rows={3}
                  maxLength={10000}
                  onChange={(e) => updateForm('description', e.target.value)}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                />
              </label>
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
              >
                {t(locale, 'commerce.action.cancel', 'Annuler')}
              </button>
              <button
                type="button"
                onClick={() => void submit()}
                disabled={saving}
                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {saving
                  ? t(locale, 'commerce.form.saving', 'Enregistrement…')
                  : t(locale, 'commerce.action.save', 'Enregistrer')}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

export default function CommerceProductsPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'products' | 'categories'>('products');
  const [categories, setCategories] = useState<RetailCategory[]>([]);

  const loadCategories = useCallback(async () => {
    try {
      const res = await apiFetch('/retail/categories?per_page=100');
      if (!res.ok) return;
      const payload = (await res.json()) as { data?: RetailCategory[] };
      setCategories(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      // Silencieux : le sélecteur de catégorie reste vide, la liste produits fonctionne.
    }
  }, []);

  useEffect(() => {
    void loadCategories();
  }, [loadCategories]);

  const categoriesConfig: CommerceCrudConfig = useMemo(
    () => ({
      endpoint: '/retail/categories',
      listQuery: 'per_page=100',
      title: t(locale, 'commerce.categories.title', 'Catégories'),
      columns: [
        { key: 'name', label: t(locale, 'commerce.categories.name', 'Nom') },
        { key: 'slug', label: t(locale, 'commerce.categories.slug', 'Slug') },
        { key: 'position', label: t(locale, 'commerce.categories.position', 'Position') },
      ],
      fields: [
        {
          name: 'name',
          label: t(locale, 'commerce.categories.name', 'Nom'),
          type: 'text',
          required: true,
          maxLength: 160,
        },
        {
          name: 'position',
          label: t(locale, 'commerce.categories.position', 'Position'),
          type: 'number',
          min: 0,
          nullable: true,
        },
      ],
      searchKeys: ['name', 'slug'],
    }),
    [locale],
  );

  const tabs = [
    { key: 'products' as const, label: t(locale, 'commerce.tab.products', 'Produits') },
    { key: 'categories' as const, label: t(locale, 'commerce.tab.categories', 'Catégories') },
  ];

  return (
    <ModulePageShell
      icon={Tags}
      title={t(locale, 'commerce.products.pageTitle', 'Catalogue produits')}
      description={t(locale, 'commerce.products.pageSubtitle', 'Catégories, fiches produits, prix en devise et publication.')}
    >
      <div className="mb-4 flex gap-2">
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

      {tab === 'products' ? (
        <ProductsPanel categories={categories} />
      ) : (
        <CommerceCrudTable config={categoriesConfig} />
      )}
    </ModulePageShell>
  );
}
