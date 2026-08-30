'use client';

/**
 * RestaurantCrudTable — tableau CRUD générique config-driven du module
 * Restaurant (BC-25). Consomme l'API tenant `/restaurant/*` via `apiFetch`.
 *
 * Chaque ressource du référentiel déclare ses colonnes, ses champs de
 * formulaire (création/édition) et ses règles d'affichage ; le composant
 * gère liste paginée, recherche, création, édition et suppression avec
 * états de chargement/erreur localisés.
 */
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export type CrudField =
  | { name: string; label: string; type: 'text' | 'number' | 'textarea' | 'select' | 'datetime'; required?: boolean; options?: { value: string | number; label: string }[]; min?: number; step?: string }
  ;

export type CrudColumn = {
  key: string;
  label: string;
  render?: (row: Record<string, unknown>) => ReactNode;
};

export type CrudConfig = {
  endpoint: string; // ex. '/restaurant/branches'
  title: string;
  columns: CrudColumn[];
  fields: CrudField[];
  searchKeys: string[];
  canCreate?: boolean;
  canDelete?: boolean;
  defaultSort?: string;
};

function valueOf(row: Record<string, unknown>, key: string): unknown {
  return key.split('.').reduce<unknown>((acc, part) => (acc && typeof acc === 'object' ? (acc as Record<string, unknown>)[part] : undefined), row);
}

function displayValue(v: unknown): string {
  if (v === null || v === undefined) return '—';
  if (typeof v === 'boolean') return v ? '✓' : '✗';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}

export function RestaurantCrudTable({ config }: { config: CrudConfig }) {
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
      const res = await apiFetch(config.endpoint);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = await res.json();
      const list = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : [];
      setRows(list);
    } catch {
      setError(t(locale, 'restaurant.crud.loadError', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [config.endpoint, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    if (!search.trim()) return rows;
    const q = search.toLowerCase();
    return rows.filter((row) => config.searchKeys.some((k) => displayValue(valueOf(row, k)).toLowerCase().includes(q)));
  }, [rows, search, config.searchKeys]);

  const openCreate = () => {
    setEditing(null);
    const initial: Record<string, unknown> = {};
    for (const f of config.fields) initial[f.name] = f.type === 'number' ? 0 : '';
    setFormData(initial);
    setFormError('');
    setShowForm(true);
  };

  const openEdit = (row: Record<string, unknown>) => {
    setEditing(row);
    const initial: Record<string, unknown> = {};
    for (const f of config.fields) {
      initial[f.name] = row[f.name] ?? (f.type === 'number' ? 0 : '');
    }
    setFormData(initial);
    setFormError('');
    setShowForm(true);
  };

  const submit = async () => {
    setSaving(true);
    setFormError('');
    try {
      const id = editing?.id;
      const res = await apiFetch(id ? `${config.endpoint}/${String(id)}` : config.endpoint, {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(formData),
      });
      if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        const msg = (payload as { message?: string }).message;
        throw new Error(msg || `HTTP ${res.status}`);
      }
      setShowForm(false);
      await load();
    } catch (e) {
      setFormError(e instanceof Error ? e.message : t(locale, 'restaurant.crud.saveError', 'Erreur lors de la sauvegarde.'));
    } finally {
      setSaving(false);
    }
  };

  const remove = async (row: Record<string, unknown>) => {
    if (!config.canDelete) return;
    if (!window.confirm(t(locale, 'restaurant.crud.confirmDelete', 'Supprimer cet élément ?'))) return;
    try {
      const res = await apiFetch(`${config.endpoint}/${String(row.id)}`, { method: 'DELETE' });
      if (!res.ok && res.status !== 204) throw new Error(`HTTP ${res.status}`);
      await load();
    } catch {
      window.alert(t(locale, 'restaurant.crud.deleteError', 'Impossible de supprimer.'));
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
            placeholder={t(locale, 'restaurant.crud.search', 'Rechercher...')}
            className="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
            aria-label={t(locale, 'restaurant.crud.search', 'Rechercher...')}
          />
          {config.canCreate !== false ? (
            <button
              type="button"
              onClick={openCreate}
              className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            >
              {t(locale, 'restaurant.crud.create', 'Nouveau')}
            </button>
          ) : null}
        </div>
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              {config.columns.map((col) => (
                <th key={col.key} className="px-4 py-3 text-left font-semibold text-slate-700">
                  {col.label}
                </th>
              ))}
              <th className="px-4 py-3 text-right font-semibold text-slate-700" aria-label="Actions" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={config.columns.length + 1} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'restaurant.crud.loading', 'Chargement...')}
                </td>
              </tr>
            ) : filtered.length === 0 ? (
              <tr>
                <td colSpan={config.columns.length + 1} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'restaurant.crud.empty', 'Aucun résultat.')}
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
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-3">
                      <button
                        type="button"
                        className="font-medium text-emerald-600 hover:text-emerald-800"
                        onClick={() => openEdit(row)}
                      >
                        {t(locale, 'restaurant.crud.edit', 'Modifier')}
                      </button>
                      {config.canDelete !== false ? (
                        <button
                          type="button"
                          className="font-medium text-red-500 hover:text-red-700"
                          onClick={() => void remove(row)}
                        >
                          {t(locale, 'restaurant.crud.delete', 'Supprimer')}
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
              {editing ? t(locale, 'restaurant.crud.editTitle', 'Modifier') : t(locale, 'restaurant.crud.createTitle', 'Créer')} — {config.title}
            </h3>
            {formError ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p> : null}
            <div className="space-y-3">
              {config.fields.map((field) => (
                <label key={field.name} className="block text-sm">
                  <span className="mb-1 block font-medium text-slate-700">
                    {field.label}
                    {field.required ? <span className="text-red-500"> *</span> : null}
                  </span>
                  {field.type === 'textarea' ? (
                    <textarea
                      value={String(formData[field.name] ?? '')}
                      onChange={(e) => updateField(field.name, e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                      rows={3}
                    />
                  ) : field.type === 'select' ? (
                    <select
                      value={String(formData[field.name] ?? '')}
                      onChange={(e) => updateField(field.name, e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">—</option>
                      {(field.options ?? []).map((opt) => (
                        <option key={String(opt.value)} value={String(opt.value)}>
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <input
                      type={field.type === 'number' ? 'number' : field.type === 'datetime' ? 'datetime-local' : 'text'}
                      value={String(formData[field.name] ?? '')}
                      min={field.min}
                      step={field.step}
                      onChange={(e) => updateField(field.name, field.type === 'number' ? Number(e.target.value) : e.target.value)}
                      className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
                    />
                  )}
                </label>
              ))}
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
              >
                {t(locale, 'restaurant.crud.cancel', 'Annuler')}
              </button>
              <button
                type="button"
                onClick={() => void submit()}
                disabled={saving}
                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {saving ? t(locale, 'restaurant.crud.saving', 'Enregistrement...') : t(locale, 'restaurant.crud.save', 'Enregistrer')}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
