'use client';

import { useCallback, useMemo, useState } from 'react';
import {
  AlertCircle,
  Archive,
  ArrowLeft,
  CheckCircle2,
  Loader2,
  Save,
  Send,
  Trash2,
  Upload,
} from 'lucide-react';

import type { LibraryCopy } from '../data/copy';
import type { AdminResource, AdminResourceInput, UploadFailure } from '../lib/admin-api';
import {
  classifyUploadFailure,
  createAdminResource,
  deleteAdminResource,
  transitionAdminResource,
  updateAdminResource,
  uploadAdminResourceFile,
} from '../lib/admin-api';
import type {
  LibraryAccessModel,
  LibraryDocumentType,
  LibraryResourceStatus,
} from '../types';

const ACCESS_MODELS: LibraryAccessModel[] = ['free', 'read_only', 'purchase', 'subscription', 'private'];
const DOCUMENT_TYPES: LibraryDocumentType[] = ['book', 'ebook', 'guide', 'manual', 'report', 'article'];
const LANGUAGES = ['fr', 'en', 'tr', 'ar'];

/** Taille maximale acceptée côté client (miroir indicatif de la limite serveur). */
const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;

interface AdminResourcePanelProps {
  copy: LibraryCopy['admin'];
  /** `null` = création. */
  resource: AdminResource | null;
  onClose: () => void;
  onSaved: (resource: AdminResource) => void;
  onDeleted: () => void;
}

/** État local du formulaire (chaînes, pour ne pas perdre la saisie en cours). */
interface FormState {
  title: string;
  slug: string;
  description: string;
  document_type: LibraryDocumentType;
  language: string;
  access_model: LibraryAccessModel;
  price_minor: string;
  currency: string;
  license_id: string;
  rights_holder: string;
  allow_download: boolean;
  featured: boolean;
  is_new: boolean;
}

function toFormState(resource: AdminResource | null): FormState {
  return {
    title: resource?.title ?? '',
    slug: resource?.slug ?? '',
    description: resource?.description ?? '',
    document_type: resource?.document_type ?? 'book',
    language: resource?.language ?? 'fr',
    access_model: resource?.access_model ?? 'free',
    price_minor: resource?.price_minor === null || resource?.price_minor === undefined
      ? ''
      : String(resource.price_minor),
    currency: resource?.currency ?? '',
    license_id: resource?.license_id ?? '',
    rights_holder: resource?.rights_holder ?? '',
    allow_download: resource?.allow_download ?? false,
    featured: resource?.featured ?? false,
    is_new: resource?.is_new ?? false,
  };
}

/**
 * Panneau de détail d'une ressource : métadonnées, cycle éditorial, droits
 * d'accès et fichier.
 *
 * Toutes les décisions d'autorisation restent serveur : ce panneau propose des
 * actions, il ne les autorise pas. Un refus (403) remonte donc comme une erreur
 * normale, jamais comme un succès optimiste.
 */
export function AdminResourcePanel({
  copy,
  resource,
  onClose,
  onSaved,
  onDeleted,
}: AdminResourcePanelProps) {
  const [form, setForm] = useState<FormState>(() => toFormState(resource));
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [transitioning, setTransitioning] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [uploadMessage, setUploadMessage] = useState<string | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const isCreate = resource === null;

  const patch = useCallback(<K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((current) => ({ ...current, [key]: value }));
  }, []);

  /** Valide le prix : entier ≥ 0, jamais de décimales (minor units). */
  const priceError = useMemo(() => {
    if (form.price_minor.trim() === '') return null;
    return /^\d+$/.test(form.price_minor.trim()) ? null : copy.form.invalidPrice;
  }, [form.price_minor, copy.form.invalidPrice]);

  /** Valide la devise : ISO 4217, 3 lettres majuscules. */
  const currencyError = useMemo(() => {
    if (form.currency.trim() === '') return null;
    return /^[A-Z]{3}$/.test(form.currency.trim()) ? null : copy.form.invalidCurrency;
  }, [form.currency, copy.form.invalidCurrency]);

  const canSave = form.title.trim() !== '' && priceError === null && currencyError === null;

  const buildInput = useCallback((): AdminResourceInput => {
    const input: AdminResourceInput = {
      title: form.title.trim(),
      document_type: form.document_type,
      language: form.language,
      access_model: form.access_model,
      description: form.description.trim() === '' ? null : form.description.trim(),
      price_minor: form.price_minor.trim() === '' ? null : Number(form.price_minor.trim()),
      currency: form.currency.trim() === '' ? null : form.currency.trim(),
      license_id: form.license_id.trim() === '' ? null : form.license_id.trim(),
      rights_holder: form.rights_holder.trim() === '' ? null : form.rights_holder.trim(),
      allow_download: form.allow_download,
      featured: form.featured,
      is_new: form.is_new,
    };

    if (form.slug.trim() !== '') {
      input.slug = form.slug.trim();
    }

    return input;
  }, [form]);

  const handleSave = useCallback(async () => {
    if (!canSave) return;

    setSaving(true);
    setSaveError(null);

    try {
      const saved = isCreate
        ? await createAdminResource(buildInput())
        : await updateAdminResource(resource.id, buildInput());

      onSaved(saved);
    } catch {
      setSaveError(copy.upload.genericError);
    } finally {
      setSaving(false);
    }
  }, [canSave, isCreate, buildInput, resource, onSaved, copy.upload.genericError]);

  const handleTransition = useCallback(
    async (action: 'publish' | 'unpublish' | 'archive') => {
      if (isCreate) return;

      setTransitioning(true);
      setSaveError(null);

      try {
        const updated = await transitionAdminResource(resource.id, action);
        onSaved(updated);
      } catch {
        setSaveError(copy.upload.genericError);
      } finally {
        setTransitioning(false);
      }
    },
    [isCreate, resource, onSaved, copy.upload.genericError],
  );

  const handleDelete = useCallback(async () => {
    if (isCreate) return;
    if (typeof window !== 'undefined' && !window.confirm(copy.form.deleteConfirm)) return;

    setSaving(true);
    setSaveError(null);

    try {
      await deleteAdminResource(resource.id);
      onDeleted();
    } catch {
      setSaveError(copy.upload.genericError);
    } finally {
      setSaving(false);
    }
  }, [isCreate, resource, onDeleted, copy.form.deleteConfirm, copy.upload.genericError]);

  const handleUpload = useCallback(async () => {
    if (isCreate || file === null) {
      setUploadError(copy.upload.noFile);
      return;
    }

    if (file.size > MAX_UPLOAD_BYTES) {
      setUploadError(copy.upload.tooLarge);
      return;
    }

    setUploading(true);
    setUploadError(null);
    setUploadMessage(null);

    try {
      const updated = await uploadAdminResourceFile(resource.id, file);
      setFile(null);
      setUploadMessage(copy.upload.success);
      onSaved(updated);
    } catch (error) {
      const failure: UploadFailure = classifyUploadFailure(error);
      setUploadError(
        failure === 'too_large'
          ? copy.upload.tooLarge
          : failure === 'bad_type'
            ? copy.upload.badType
            : failure === 'storage_unavailable'
              ? copy.upload.storageUnavailable
              : copy.upload.genericError,
      );
    } finally {
      setUploading(false);
    }
  }, [isCreate, file, resource, onSaved, copy.upload]);

  const statusLabel = (status: LibraryResourceStatus): string => copy.workflow[status];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          type="button"
          onClick={onClose}
          className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        >
          <ArrowLeft className="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
          {copy.form.cancel}
        </button>
        <h2 className="text-lg font-semibold text-slate-900">
          {isCreate ? copy.form.create : copy.form.edit}
        </h2>
      </div>

      {saveError ? (
        <p
          role="alert"
          className="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
        >
          <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
          {saveError}
        </p>
      ) : null}

      {/* Métadonnées */}
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600 sm:col-span-2">
            {copy.form.title} *
            <input
              type="text"
              value={form.title}
              onChange={(event) => patch('title', event.target.value)}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            />
            {form.title.trim() === '' ? (
              <span className="text-xs font-normal text-rose-600">{copy.form.required}</span>
            ) : null}
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.slug}
            <input
              type="text"
              value={form.slug}
              onChange={(event) => patch('slug', event.target.value)}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            />
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.language}
            <select
              value={form.language}
              onChange={(event) => patch('language', event.target.value)}
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              {LANGUAGES.map((language) => (
                <option key={language} value={language}>
                  {language}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.documentType}
            <select
              value={form.document_type}
              onChange={(event) => patch('document_type', event.target.value as LibraryDocumentType)}
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              {DOCUMENT_TYPES.map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.accessModel}
            <select
              value={form.access_model}
              onChange={(event) => patch('access_model', event.target.value as LibraryAccessModel)}
              className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            >
              {ACCESS_MODELS.map((model) => (
                <option key={model} value={model}>
                  {model}
                </option>
              ))}
            </select>
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.priceMinor}
            <input
              type="text"
              inputMode="numeric"
              value={form.price_minor}
              onChange={(event) => patch('price_minor', event.target.value)}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            />
            <span className="text-xs font-normal text-slate-500">{copy.form.priceHint}</span>
            {priceError ? (
              <span className="text-xs font-normal text-rose-600">{priceError}</span>
            ) : null}
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.currency}
            <input
              type="text"
              maxLength={3}
              value={form.currency}
              onChange={(event) => patch('currency', event.target.value.toUpperCase())}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm uppercase text-slate-900 outline-none focus:border-emerald-500"
            />
            {currencyError ? (
              <span className="text-xs font-normal text-rose-600">{currencyError}</span>
            ) : null}
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.licenseId}
            <input
              type="text"
              value={form.license_id}
              onChange={(event) => patch('license_id', event.target.value)}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            />
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {copy.form.rightsHolder}
            <input
              type="text"
              value={form.rights_holder}
              onChange={(event) => patch('rights_holder', event.target.value)}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            />
          </label>

          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600 sm:col-span-2">
            {copy.form.description}
            <textarea
              rows={3}
              value={form.description}
              onChange={(event) => patch('description', event.target.value)}
              className="rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500"
            />
          </label>
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-4">
          <label className="inline-flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={form.featured}
              onChange={(event) => patch('featured', event.target.checked)}
              className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            />
            {copy.form.featured}
          </label>
          <label className="inline-flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={form.is_new}
              onChange={(event) => patch('is_new', event.target.checked)}
              className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            />
            {copy.form.isNew}
          </label>
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-2">
          <button
            type="button"
            disabled={!canSave || saving}
            onClick={() => void handleSave()}
            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {saving ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <Save className="h-4 w-4" aria-hidden="true" />
            )}
            {saving ? copy.form.saving : copy.form.save}
          </button>
          {!isCreate ? (
            <button
              type="button"
              disabled={saving}
              onClick={() => void handleDelete()}
              className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:opacity-50"
            >
              <Trash2 className="h-4 w-4" aria-hidden="true" />
              {copy.form.delete}
            </button>
          ) : null}
        </div>
      </section>

      {/* Cycle éditorial */}
      {!isCreate ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-slate-900">{copy.workflow.title}</h3>
          <p className="mt-1 text-xs text-slate-500">{copy.workflow.hint}</p>
          <p className="mt-2 text-sm text-slate-700">
            {copy.workflow.current} :{' '}
            <span className="font-semibold">{statusLabel(resource.status)}</span>
          </p>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <button
              type="button"
              disabled={transitioning || resource.status === 'published'}
              onClick={() => void handleTransition('publish')}
              className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
              <Send className="h-3.5 w-3.5" aria-hidden="true" />
              {transitioning ? copy.workflow.publishing : copy.workflow.publish}
            </button>
            <button
              type="button"
              disabled={transitioning || resource.status === 'draft'}
              onClick={() => void handleTransition('unpublish')}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {copy.workflow.unpublish}
            </button>
            <button
              type="button"
              disabled={transitioning || resource.status === 'archived'}
              onClick={() => void handleTransition('archive')}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
              <Archive className="h-3.5 w-3.5" aria-hidden="true" />
              {copy.workflow.archive}
            </button>
          </div>
        </section>
      ) : null}

      {/* Droits d'accès */}
      <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 className="text-sm font-semibold text-slate-900">{copy.rights.title}</h3>
        <p className="mt-1 text-xs text-slate-500">{copy.rights.hint}</p>
        <label className="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={form.allow_download}
            onChange={(event) => patch('allow_download', event.target.checked)}
            className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
          />
          {copy.form.allowDownload}
        </label>
        <p className="mt-2 text-xs text-slate-600">
          {form.allow_download ? copy.rights.downloadAllowed : copy.rights.downloadBlocked}
        </p>
        {form.access_model === 'read_only' ? (
          <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
            {copy.rights.readOnlyNotice}
          </p>
        ) : null}
      </section>

      {/* Fichier */}
      {!isCreate ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-slate-900">{copy.upload.title}</h3>
          <p className="mt-1 text-xs text-slate-500">{copy.upload.hint}</p>
          <p className="mt-2 text-sm text-slate-700">
            {copy.upload.currentFile} :{' '}
            <span className="font-medium">
              {resource.has_file ? (resource.file_format ?? copy.list.filePresent) : copy.upload.noCurrentFile}
            </span>
          </p>

          <div className="mt-3 flex flex-wrap items-center gap-2">
            <input
              type="file"
              accept=".pdf,.epub,.txt,.html,application/pdf,application/epub+zip,text/plain,text/html"
              onChange={(event) => {
                setFile(event.target.files?.[0] ?? null);
                setUploadError(null);
                setUploadMessage(null);
              }}
              className="block w-full max-w-sm text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
            />
            <button
              type="button"
              disabled={uploading || file === null}
              onClick={() => void handleUpload()}
              className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {uploading ? (
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              ) : (
                <Upload className="h-4 w-4" aria-hidden="true" />
              )}
              {uploading ? copy.upload.sending : copy.upload.send}
            </button>
          </div>

          {file ? (
            <p className="mt-2 text-xs text-slate-600">
              {copy.upload.selected} : {file.name}
            </p>
          ) : null}

          {uploadMessage ? (
            <p className="mt-2 flex items-center gap-1.5 text-sm text-emerald-700">
              <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
              {uploadMessage}
            </p>
          ) : null}

          {uploadError ? (
            <p
              role="alert"
              className="mt-2 flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
            >
              <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
              {uploadError}
            </p>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}
