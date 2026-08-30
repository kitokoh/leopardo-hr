'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button, Card, DataTable, Field, Modal, SelectInput, StatusBadge, TextInput, type Column } from './edu-ui';

/**
 * Générateur de pages CRUD pour les référentiels EduManager (EDU-011, #5827).
 *
 * Un objet `CrudResource` décrit une entité (campus, année scolaire, matière,
 * classe, élève) : champs du formulaire, colonnes du tableau, labels i18n.
 * La page gère liste paginée, création, édition, suppression avec
 * confirmation, états vide/erreur/chargement et badges de statut.
 */

export type FieldOption = { value: string; label: string };

export type FieldDef =
  | {
      kind: 'text' | 'number' | 'date';
      name: string;
      label: string;
      required?: boolean;
      placeholder?: string;
      min?: number;
      max?: number;
      hint?: string;
    }
  | {
      kind: 'select';
      name: string;
      label: string;
      required?: boolean;
      options?: FieldOption[];
      optionsLoader?: () => Promise<FieldOption[]>;
      placeholder?: string;
      hint?: string;
    };

export type CrudResource = {
  path: string;
  titleKey: string;
  subtitleKey: string;
  emptyKey: string;
  createKey: string;
  editKey: string;
  fields: FieldDef[];
  columns: Column<Record<string, unknown>>[];
  rowKey: (row: Record<string, unknown>) => string | number;
  statusField?: string;
};

function statusBadge(row: Record<string, unknown>, statusField?: string) {
  if (!statusField) {
    return null;
  }
  const value = String(row[statusField] ?? '');
  return <StatusBadge status={value} />;
}

export default function CrudPage({ resource }: { resource: CrudResource }) {
  const locale = getPreferredLocale();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Record<string, unknown> | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [options, setOptions] = useState<Record<string, FieldOption[]>>({});
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`${resource.path}?per_page=200`, { _cacheBust: true });
      const json = (await res.json()) as { data?: Record<string, unknown>[] };
      setRows(Array.isArray(json.data) ? json.data : []);
    } catch {
      setError(t(locale, 'edu.common.error'));
    } finally {
      setLoading(false);
    }
  }, [locale, resource.path]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = async () => {
    setEditing(null);
    const next: Record<string, string> = {};
    for (const field of resource.fields) {
      next[field.name] = field.kind === 'select' ? (field.options?.[0]?.value ?? '') : '';
    }
    setForm(next);
    await loadOptions(resource.fields);
    setSaveError(null);
    setModalOpen(true);
  };

  const openEdit = async (row: Record<string, unknown>) => {
    setEditing(row);
    const next: Record<string, string> = {};
    for (const field of resource.fields) {
      next[field.name] = String(row[field.name] ?? '');
    }
    setForm(next);
    await loadOptions(resource.fields);
    setSaveError(null);
    setModalOpen(true);
  };

  const loadOptions = async (fields: FieldDef[]) => {
    for (const field of fields) {
      if (field.kind === 'select' && field.optionsLoader) {
        try {
          const loaded = await field.optionsLoader();
          setOptions((prev) => ({ ...prev, [field.name]: loaded }));
        } catch {
          setOptions((prev) => ({ ...prev, [field.name]: [] }));
        }
      }
    }
  };

  const save = async () => {
    setSaving(true);
    setSaveError(null);
    try {
      const body = { ...form };
      for (const field of resource.fields) {
        if (field.kind === 'number') {
          const value = Number(form[field.name]);
          body[field.name] = Number.isFinite(value) ? String(value) : '';
        }
      }

      const url = editing
        ? `${resource.path}/${String(editing.id)}`
        : resource.path;
      const res = await apiFetch(url, {
        method: editing ? 'PUT' : 'POST',
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        let message = t(locale, 'edu.common.error');
        try {
          const payload = (await res.json()) as { message?: string };
          if (payload.message) {
            message = payload.message;
          }
        } catch {
          // garde le message par défaut
        }
        setSaveError(message);
        setSaving(false);
        return;
      }

      setModalOpen(false);
      await load();
    } catch {
      setSaveError(t(locale, 'edu.common.error'));
    } finally {
      setSaving(false);
    }
  };

  const remove = async (row: Record<string, unknown>) => {
    if (!window.confirm(t(locale, 'edu.common.deleteConfirm'))) {
      return;
    }
    try {
      await apiFetch(`${resource.path}/${String(row.id)}`, { method: 'DELETE' });
      await load();
    } catch {
      setError(t(locale, 'edu.common.error'));
    }
  };

  const tableColumns: Column<Record<string, unknown>>[] = useMemo(
    () => [
      ...resource.columns.map((column) => ({
        ...column,
        header: t(locale, column.header),
      })),
      {
        key: 'status',
        header: t(locale, 'edu.campuses.status'),
        render: (row) => statusBadge(row, resource.statusField),
      },
      {
        key: '__actions',
        header: t(locale, 'edu.common.actions'),
        render: (row) => (
          <div className="flex items-center gap-1">
            <button
              type="button"
              title={t(locale, 'edu.common.edit')}
              onClick={() => void openEdit(row)}
              className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            >
              <Pencil className="h-4 w-4" aria-hidden="true" />
            </button>
            <button
              type="button"
              title={t(locale, 'edu.common.delete')}
              onClick={() => void remove(row)}
              className="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
            >
              <Trash2 className="h-4 w-4" aria-hidden="true" />
            </button>
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [locale, resource],
  );

  return (
    <ModulePageShell
      title={t(locale, resource.titleKey)}
      subtitle={t(locale, resource.subtitleKey)}
      accentClassName="border-cyan-500/10 bg-cyan-500/5"
    >
      <div className="flex items-center justify-between">
        <div />
        <Button onClick={() => void openCreate()}>
          <Plus className="h-4 w-4" aria-hidden="true" />
          {t(locale, resource.createKey)}
        </Button>
      </div>

      <DataTable
        columns={tableColumns}
        rows={rows}
        rowKey={resource.rowKey}
        emptyLabel={t(locale, resource.emptyKey)}
        loading={loading}
        error={error}
        onRetry={() => void load()}
      />

      <Modal open={modalOpen} title={t(locale, editing ? resource.editKey : resource.createKey)} onClose={() => setModalOpen(false)}>
        <div className="space-y-4">
          {resource.fields.map((field) => {
            const current = form[field.name] ?? '';
            if (field.kind === 'select') {
              const fieldOptions = field.options ?? options[field.name] ?? [];
              return (
                <Field key={field.name} label={t(locale, field.label)} hint={field.hint ? t(locale, field.hint) : undefined}>
                  <SelectInput
                    value={current}
                    onChange={(event) => setForm((prev) => ({ ...prev, [field.name]: event.target.value }))}
                    required={field.required}
                  >
                    {field.placeholder ? <option value="">{field.placeholder}</option> : null}
                    {fieldOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </SelectInput>
                </Field>
              );
            }

            return (
              <Field key={field.name} label={t(locale, field.label)} hint={field.hint ? t(locale, field.hint) : undefined}>
                <TextInput
                  type={field.kind === 'number' ? 'number' : field.kind}
                  value={current}
                  min={field.kind === 'number' ? field.min : undefined}
                  max={field.kind === 'number' ? field.max : undefined}
                  placeholder={field.placeholder}
                  required={field.required}
                  onChange={(event) => setForm((prev) => ({ ...prev, [field.name]: event.target.value }))}
                />
              </Field>
            );
          })}

          {saveError ? <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600">{saveError}</p> : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setModalOpen(false)}>
              {t(locale, 'edu.common.cancel')}
            </Button>
            <Button onClick={() => void save()} disabled={saving}>
              {t(locale, 'edu.common.save')}
            </Button>
          </div>
        </div>
      </Modal>
    </ModulePageShell>
  );
}

export { Card };
