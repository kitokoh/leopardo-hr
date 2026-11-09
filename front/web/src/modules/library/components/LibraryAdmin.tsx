'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  AlertCircle,
  Archive,
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  FileText,
  Loader2,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Upload,
} from 'lucide-react';

import type { LibraryCopy } from '../data/copy';
import type {
  AdminFilters,
  AdminPageMeta,
  AdminResource,
} from '../lib/admin-api';
import { fetchAdminResources } from '../lib/admin-api';
import type {
  LibraryAccessModel,
  LibraryDocumentType,
  LibraryResourceStatus,
} from '../types';
import { AdminResourcePanel } from './AdminResourcePanel';

const STATUSES: LibraryResourceStatus[] = ['draft', 'pending', 'published', 'archived'];
const ACCESS_MODELS: LibraryAccessModel[] = ['free', 'read_only', 'purchase', 'subscription', 'private'];
const DOCUMENT_TYPES: LibraryDocumentType[] = ['book', 'ebook', 'guide', 'manual', 'report', 'article'];
const LANGUAGES = ['fr', 'en', 'tr', 'ar'];

/** Couleur de badge par statut éditorial (jamais la seule information portée). */
const STATUS_TONE: Record<LibraryResourceStatus, string> = {
  draft: 'bg-slate-100 text-slate-700 ring-slate-200',
  pending: 'bg-amber-50 text-amber-800 ring-amber-200',
  published: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  archived: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
};

interface LibraryAdminProps {
  copy: LibraryCopy['admin'];
}

/**
 * Back-office de la bibliothèque (`/library-admin`).
 *
 * Écran CLIENT : la liste, les filtres et la pagination sont pilotés par l'API
 * (`GET /library/resources`), qui applique elle-même l'isolation tenant et la
 * policy. Aucun filtrage local ne remplace le serveur — l'écran n'affiche que
 * ce que l'API a bien voulu renvoyer.
 *
 * Le panneau de détail (métadonnées, workflow, droits, fichier) est monté à la
 * demande : la liste reste légère et l'écran ne charge pas 20 formulaires.
 */
export function LibraryAdmin({ copy }: LibraryAdminProps) {
  const [filters, setFilters] = useState<AdminFilters>({ page: 1, per_page: 20 });
  const [draftQuery, setDraftQuery] = useState('');
  const [resources, setResources] = useState<AdminResource[]>([]);
  const [meta, setMeta] = useState<AdminPageMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [selected, setSelected] = useState<AdminResource | null>(null);
  const [creating, setCreating] = useState(false);

  const load = useCallback(async (next: AdminFilters) => {
    setLoading(true);
    setError(false);

    try {
      const page = await fetchAdminResources(next);
      setResources(page.data);
      setMeta(page.meta);
    } catch {
      setError(true);
      setResources([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(filters);
  }, [filters, load]);

  /** Applique un filtre en revenant systématiquement en page 1. */
  const patchFilters = useCallback((patch: Partial<AdminFilters>) => {
    setFilters((current) => ({ ...current, ...patch, page: 1 }));
  }, []);

  const resetFilters = useCallback(() => {
    setDraftQuery('');
    setFilters({ page: 1, per_page: 20 });
  }, []);

  const statusLabel = useMemo(
    () => (status: LibraryResourceStatus): string => copy.workflow[status],
    [copy],
  );

  const goToPage = useCallback((page: number) => {
    setFilters((current) => ({ ...current, page }));
  }, []);

  const handleSaved = useCallback(
    (resource: AdminResource) => {
      setSelected(resource);
      setCreating(false);
      void load(filters);
    },
    [filters, load],
  );

  const handleDeleted = useCallback(() => {
    setSelected(null);
    void load(filters);
  }, [filters, load]);

  if (selected || creating) {
    return (
      <AdminResourcePanel
        copy={copy}
        resource={selected}
        onClose={() => {
          setSelected(null);
          setCreating(false);
        }}
        onSaved={handleSaved}
        onDeleted={handleDeleted}
      />
    );
  }

  return (
    <div className="space-y-4">
      {/* Barre d'actions */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-lg font-semibold text-slate-900">{copy.list.title}</h2>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => void load(filters)}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          >
            <RefreshCw className="h-4 w-4" aria-hidden="true" />
            {copy.list.retry}
          </button>
          <button
            type="button"
            onClick={() => setCreating(true)}
            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
          >
            <Plus className="h-4 w-4" aria-hidden="true" />
            {copy.form.create}
          </button>
        </div>
      </div>

      {/* Filtres */}
      <section
        aria-label={copy.filters.title}
        className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
      >
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600 lg:col-span-2">
            {copy.filters.search}
            <div className="relative">
              <Search
                className="pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
              />
              <input
                type="search"
                value={draftQuery}
                onChange={(event) => setDraftQuery(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') patchFilters({ q: draftQuery });
                }}
                placeholder={copy.filters.searchPlaceholder}
                className="w-full rounded-lg border border-slate-200 bg-white py-2 pe-3 ps-8 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              />
            </div>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.filters.status}
            <select
              value={filters.status ?? ''}
              onChange={(event) =>
                patchFilters({ status: event.target.value as LibraryResourceStatus | '' })
              }
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              <option value="">{copy.filters.all}</option>
              {STATUSES.map((status) => (
                <option key={status} value={status}>
                  {statusLabel(status)}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.filters.access}
            <select
              value={filters.access_model ?? ''}
              onChange={(event) =>
                patchFilters({ access_model: event.target.value as LibraryAccessModel | '' })
              }
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              <option value="">{copy.filters.all}</option>
              {ACCESS_MODELS.map((model) => (
                <option key={model} value={model}>
                  {model}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.filters.type}
            <select
              value={filters.document_type ?? ''}
              onChange={(event) =>
                patchFilters({ document_type: event.target.value as LibraryDocumentType | '' })
              }
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              <option value="">{copy.filters.all}</option>
              {DOCUMENT_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.filters.language}
            <select
              value={filters.language ?? ''}
              onChange={(event) => patchFilters({ language: event.target.value })}
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              <option value="">{copy.filters.all}</option>
              {LANGUAGES.map((language) => (
                <option key={language} value={language}>
                  {language}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => patchFilters({ q: draftQuery })}
            className="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-800"
          >
            {copy.filters.apply}
          </button>
          <button
            type="button"
            onClick={resetFilters}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          >
            {copy.filters.reset}
          </button>
        </div>
      </section>

      {/* Liste */}
      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {loading ? (
          <div className="flex items-center justify-center gap-2 p-10 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            {copy.list.loading}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center gap-3 p-10 text-center">
            <AlertCircle className="h-6 w-6 text-rose-500" aria-hidden="true" />
            <p className="text-sm text-slate-700">{copy.list.error}</p>
            <button
              type="button"
              onClick={() => void load(filters)}
              className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              {copy.list.retry}
            </button>
          </div>
        ) : resources.length === 0 ? (
          <p className="p-10 text-center text-sm text-slate-500">{copy.list.empty}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[52rem] text-start text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th scope="col" className="px-4 py-2.5 text-start font-semibold">
                    {copy.list.columns.title}
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-start font-semibold">
                    {copy.list.columns.status}
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-start font-semibold">
                    {copy.list.columns.access}
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-start font-semibold">
                    {copy.list.columns.type}
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-start font-semibold">
                    {copy.list.columns.language}
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-start font-semibold">
                    {copy.list.columns.file}
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-end font-semibold">
                    {copy.list.columns.actions}
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {resources.map((resource) => (
                  <tr key={resource.id} className="hover:bg-slate-50/70">
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-900">{resource.title}</p>
                      <p className="text-xs text-slate-500">{resource.slug}</p>
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${STATUS_TONE[resource.status]}`}
                      >
                        {statusLabel(resource.status)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-slate-700">{resource.access_model}</td>
                    <td className="px-4 py-3 text-slate-700">{resource.document_type}</td>
                    <td className="px-4 py-3 uppercase text-slate-700">{resource.language}</td>
                    <td className="px-4 py-3">
                      {resource.has_file ? (
                        <span className="inline-flex items-center gap-1 text-xs text-emerald-700">
                          <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
                          {resource.file_format ?? copy.list.filePresent}
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-xs text-slate-400">
                          <FileText className="h-3.5 w-3.5" aria-hidden="true" />
                          {copy.list.fileMissing}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-end">
                      <button
                        type="button"
                        onClick={() => setSelected(resource)}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                      >
                        <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                        {copy.form.edit}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {meta && meta.total > 0 ? (
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
            <p>
              {meta.total} {copy.list.total}
            </p>
            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled={meta.current_page <= 1}
                onClick={() => goToPage(meta.current_page - 1)}
                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <ArrowLeft className="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true" />
                {copy.list.previous}
              </button>
              <span className="tabular-nums">
                {copy.list.page} {meta.current_page} {copy.list.of} {meta.last_page}
              </span>
              <button
                type="button"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => goToPage(meta.current_page + 1)}
                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              >
                {copy.list.next}
                <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true" />
              </button>
            </div>
          </div>
        ) : null}
      </section>

      <p className="flex items-center gap-1.5 text-xs text-slate-500">
        <Upload className="h-3.5 w-3.5" aria-hidden="true" />
        {copy.upload.hint}
      </p>
    </div>
  );
}
