'use client';

/**
 * CommerceCrudTable (BC-17 RETAIL, #7675) — tableau CRUD générique
 * config-driven de l'espace vendeur Commerce. Consomme l'API tenant
 * `/retail/*` via `apiFetch` (réponses paginées Laravel enveloppées
 * `{ data: [...] }`).
 *
 * Calqué sur `TravelCrudTable` (BC-24, #7634) mais adapté aux FormRequests
 * du module Retail : sélecteurs à valeurs numériques (catégories,
 * emplacements…), cases à cocher booléennes (`is_active`), champs optionnels
 * omis du payload quand ils sont vides (les règles `sometimes`/`nullable`
 * du backend rejettent les chaînes vides), validation locale des champs
 * requis et messages localisés via les clés imbriquées `commerce.*`.
 */
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export type CommerceCrudOption = { value: string | number; label: string };

export type CommerceCrudField = {
  name: string;
  label: string;
  type: 'text' | 'number' | 'select' | 'checkbox' | 'date' | 'time';
  required?: boolean;
  /** Options du select ; `numeric` force la coercition en nombre. */
  options?: CommerceCrudOption[];
  numeric?: boolean;
  /** Champ `nullable` côté backend : vide → `null` envoyé (efface la valeur). */
  nullable?: boolean;
  min?: number;
  max?: number;
  maxLength?: number;
  hint?: string;
};

export type CommerceCrudColumn = {
  key: string;
  label: string;
  render?: (row: Record<string, unknown>) => ReactNode;
};

export type CommerceRowAction = {
  key: string;
  label: string;
  onClick: (row: Record<string, unknown>) => void;
};

export type CommerceCrudConfig = {
  /** Endpoint relatif, ex. `/retail/products`. */
  endpoint: string;
  /** Query string de liste (sans `?`), ex. `per_page=100`. */
  listQuery?: string;
  title: string;
  columns: CommerceCrudColumn[];
  fields: CommerceCrudField[];
  searchKeys: string[];
  searchPlaceholder?: string;
  canCreate?: boolean;
  canDelete?: boolean;
  rowActions?: CommerceRowAction[];
  /** Incrémenté par le parent pour forcer un rechargement. */
  reloadToken?: number;
};

function valueOf(row: Record<string, unknown>, key: string): unknown {
  return key
    .split('.')
    .reduce<unknown>(
      (acc, part) => (acc && typeof acc === 'object' ? (acc as Record<string, unknown>)[part] : undefined),
      row,
    );
}

function displayValue(v: unknown): string {
  if (v === null || v === undefined) return '—';
  if (typeof v === 'boolean') return v ? '✓' : '✗';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}

/** Premier message d'une erreur de validation Laravel (422) ou `message`. */
export async function readApiError(res: Response): Promise<string | null> {
  const payload = (await res.json().catch(() => null)) as
    | { message?: string; errors?: Record<string, string[]> }
    | null;
  if (!payload) return null;
  const firstError = payload.errors ? Object.values(payload.errors)[0]?.[0] : undefined;
  return firstError ?? payload.message ?? null;
}

/**
 * Construit le payload envoyé au backend depuis l'état du formulaire :
 * coercition numérique, booléens tels quels, champs optionnels vides omis
 * (ou `null` si `nullable`).
 */
export function buildCrudPayload(
  fields: CommerceCrudField[],
  formData: Record<string, unknown>,
): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const field of fields) {
    const raw = formData[field.name];
    if (field.type === 'checkbox') {
      payload[field.name] = raw === true;
      continue;
    }
    const str = raw === null || raw === undefined ? '' : String(raw).trim();
    if (str === '') {
      if (field.nullable) payload[field.name] = null;
      continue;
    }
    if (field.type === 'number' || (field.type === 'select' && field.numeric)) {
      payload[field.name] = Number(str);
    } else {
      payload[field.name] = str;
    }
  }
  return payload;
}

export function CommerceCrudTable({ config }: { config: CommerceCrudConfig }) {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Record<string, unknown> | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [formData, setFormData] = useState<Record<string, unknown>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const url = config.listQuery ? `${config.endpoint}?${config.listQuery}` : config.endpoint;
      const res = await apiFetch(url);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: unknown };
      setRows(Array.isArray(payload.data) ? (payload.data as Record<string, unknown>[]) : []);
    } catch {
      setError(t(locale, 'commerce.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [config.endpoint, config.listQuery, locale]);

  useEffect(() => {
    void load();
  }, [load, config.reloadToken]);

  const filtered = useMemo(() => {
    if (!search.trim()) return rows;
    const q = search.toLowerCase();
    return rows.filter((row) =>
      config.searchKeys.some((k) => displayValue(valueOf(row, k)).toLowerCase().includes(q)),
    );
  }, [rows, search, config.searchKeys]);

  const openCreate = () => {
    setEditing(null);
    const initial: Record<string, unknown> = {};
    for (const f of config.fields) initial[f.name] = f.type === 'checkbox' ? false : '';
    setFormData(initial);
    setFormError('');
    setShowForm(true);
  };

  const openEdit = (row: Record<string, unknown>) => {
    setEditing(row);
    const initial: Record<string, unknown> = {};
    for (const f of config.fields) {
      const v = row[f.name];
      initial[f.name] = f.type === 'checkbox' ? v === true : v ?? '';
    }
    setFormData(initial);
    setFormError('');
    setShowForm(true);
  };

  const submit = async () => {
    const missing = config.fields.find((f) => {
      if (!f.required || f.type === 'checkbox') return false;
      const v = formData[f.name];
      return v === null || v === undefined || String(v).trim() === '';
    });
    if (missing) {
      setFormError(
        `${missing.label} — ${t(locale, 'commerce.form.required', 'Ce champ est obligatoire.')}`,
      );
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      const id = editing?.id;
      const res = await apiFetch(id ? `${config.endpoint}/${String(id)}` : config.endpoint, {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(buildCrudPayload(config.fields, formData)),
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

  const remove = async (row: Record<string, unknown>) => {
    if (config.canDelete === false) return;
    if (!window.confirm(t(locale, 'commerce.confirm.deleteMessage', 'Supprimer définitivement cet élément ?'))) {
      return;
    }
    try {
      const res = await apiFetch(`${config.endpoint}/${String(row.id)}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      window.alert(t(locale, 'commerce.error.deleteFailed', 'Échec de la suppression.'));
    }
  };

  const updateField = (name: string, value: unknown) => setFormData((prev) => ({ ...prev, [name]: value }));

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-bold text-slate-900">{config.title}</h2>
          {error ? <p className="text-sm text-red-600">{error}</p> : null}
        </div>
        <div className="flex items-center gap-2">
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={config.searchPlaceholder ?? t(locale, 'commerce.search.placeholder', 'Rechercher…')}
            aria-label={config.searchPlaceholder ?? t(locale, 'commerce.search.placeholder', 'Rechercher…')}
            className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
          />
          {config.canCreate !== false ? (
            <button
              type="button"
              onClick={openCreate}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            >
              {t(locale, 'commerce.action.create', 'Créer')}
            </button>
          ) : null}
        </div>
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              {config.columns.map((col) => (
                <th key={col.key} className="px-4 py-3 text-start font-semibold text-slate-700">
                  {col.label}
                </th>
              ))}
              <th className="px-4 py-3 text-end font-semibold text-slate-700">
                {t(locale, 'commerce.table.actions', 'Actions')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={config.columns.length + 1} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.loading', 'Chargement…')}
                </td>
              </tr>
            ) : filtered.length === 0 ? (
              <tr>
                <td colSpan={config.columns.length + 1} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'commerce.table.empty', 'Aucun élément.')}
                </td>
              </tr>
            ) : (
              filtered.map((row) => (
                <tr key={String(row.id)} className="hover:bg-slate-50">
                  {config.columns.map((col) => (
                    <td key={col.key} className="px-4 py-3 text-slate-700">
                      {col.render ? col.render(row) : displayValue(valueOf(row, col.key))}
                    </td>
                  ))}
                  <td className="px-4 py-3 text-end">
                    <div className="flex justify-end gap-3">
                      {(config.rowActions ?? []).map((action) => (
                        <button
                          key={action.key}
                          type="button"
                          className="font-medium text-cyan-700 hover:text-cyan-800"
                          onClick={() => action.onClick(row)}
                        >
                          {action.label}
                        </button>
                      ))}
                      <button
                        type="button"
                        className="font-medium text-emerald-700 hover:text-emerald-800"
                        onClick={() => openEdit(row)}
                      >
                        {t(locale, 'commerce.action.edit', 'Modifier')}
                      </button>
                      {config.canDelete !== false ? (
                        <button
                          type="button"
                          className="font-medium text-red-500 hover:text-red-700"
                          onClick={() => void remove(row)}
                        >
                          {t(locale, 'commerce.action.delete', 'Supprimer')}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {showForm ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
          <div className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <h3 className="mb-4 text-lg font-bold text-slate-900">
              {editing
                ? t(locale, 'commerce.action.editTitle', 'Modifier')
                : t(locale, 'commerce.action.createTitle', 'Créer un élément')}{' '}
              — {config.title}
            </h3>
            {formError ? (
              <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p>
            ) : null}
            <div className="space-y-3">
              {config.fields.map((field) => (
                <label key={field.name} className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {field.label}
                    {field.required ? <span className="text-red-500"> *</span> : null}
                  </span>
                  {field.type === 'select' ? (
                    <select
                      value={String(formData[field.name] ?? '')}
                      onChange={(e) => updateField(field.name, e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">
                        {t(locale, 'commerce.form.selectPlaceholder', '— Sélectionner —')}
                      </option>
                      {(field.options ?? []).map((opt) => (
                        <option key={String(opt.value)} value={String(opt.value)}>
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  ) : field.type === 'checkbox' ? (
                    <span className="inline-flex items-center gap-2">
                      <input
                        type="checkbox"
                        checked={formData[field.name] === true}
                        onChange={(e) => updateField(field.name, e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                      />
                      <span className="text-slate-600">{field.hint ?? field.label}</span>
                    </span>
                  ) : (
                    <input
                      type={field.type === 'number' ? 'number' : field.type}
                      value={String(formData[field.name] ?? '')}
                      min={field.min}
                      max={field.max}
                      maxLength={field.maxLength}
                      onChange={(e) => updateField(field.name, e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  )}
                  {field.hint && field.type !== 'checkbox' ? (
                    <span className="mt-1 block text-xs text-slate-500">{field.hint}</span>
                  ) : null}
                </label>
              ))}
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
