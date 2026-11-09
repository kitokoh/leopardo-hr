'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  AlertCircle,
  Archive,
  BookOpen,
  Check,
  FileUp,
  Loader2,
  Pencil,
  Plus,
  Rocket,
  Trash2,
  Undo2,
  X,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';
import {
  createLibraryResource,
  deleteLibraryResource,
  libraryErrorMessage,
  listLibraryResources,
  transitionLibraryResource,
  updateLibraryResource,
  uploadLibraryResourceFile,
  type LibraryAccessModel,
  type LibraryDocumentType,
  type LibraryResource,
  type LibraryResourceInput,
  type LibraryResourceStatus,
} from '@/lib/library-admin-api';

/**
 * BC-40 LIBRARY — administration des ressources (#9241).
 *
 * Le backend exposait déjà la création, l'édition, l'upload et le workflow
 * éditorial, mais AUCUNE interface : un administrateur devait appeler l'API à
 * la main. Cet écran couvre les quatre gestes du quotidien :
 *
 *  1. **lister** les ressources du tenant (brouillons inclus) ;
 *  2. **créer / éditer** les métadonnées ;
 *  3. **téléverser** le fichier (multipart, jamais de faux fichier : un 501
 *     « stockage non configuré » est affiché tel quel) ;
 *  4. **piloter le workflow** brouillon → publié → archivé.
 *
 * Le RBAC est vérifié CÔTÉ SERVEUR (`LibraryResourcePolicy`) : masquer un
 * bouton ne protège rien, un appel non autorisé répond 403 et le message est
 * affiché. Aucune permission n'est inventée côté client.
 */

type Copy = {
  title: string;
  subtitle: string;
  loading: string;
  empty: string;
  create: string;
  creating: string;
  save: string;
  saving: string;
  cancel: string;
  edit: string;
  remove: string;
  upload: string;
  uploading: string;
  publish: string;
  unpublish: string;
  archive: string;
  statusDraft: string;
  statusPending: string;
  statusPublished: string;
  statusArchived: string;
  fieldTitle: string;
  fieldDescription: string;
  fieldType: string;
  fieldLanguage: string;
  fieldAccess: string;
  fieldPrice: string;
  fieldRightsHolder: string;
  fieldAllowDownload: string;
  fieldFeatured: string;
  hasFile: string;
  noFile: string;
  errorGeneric: string;
  errorForbidden: string;
  confirmDelete: string;
};

const COPY: Record<AppLocale, Copy> = {
  fr: {
    title: 'Bibliothèque — administration',
    subtitle: 'Créez, éditez, téléversez et publiez les ressources de votre entreprise.',
    loading: 'Chargement des ressources…',
    empty: 'Aucune ressource pour le moment. Créez la première.',
    create: 'Nouvelle ressource',
    creating: 'Création…',
    save: 'Enregistrer',
    saving: 'Enregistrement…',
    cancel: 'Annuler',
    edit: 'Modifier',
    remove: 'Supprimer',
    upload: 'Téléverser le fichier',
    uploading: 'Téléversement…',
    publish: 'Publier',
    unpublish: 'Repasser en brouillon',
    archive: 'Archiver',
    statusDraft: 'Brouillon',
    statusPending: 'En attente',
    statusPublished: 'Publié',
    statusArchived: 'Archivé',
    fieldTitle: 'Titre',
    fieldDescription: 'Description',
    fieldType: 'Type de document',
    fieldLanguage: 'Langue',
    fieldAccess: 'Modèle d’accès',
    fieldPrice: 'Prix (centimes)',
    fieldRightsHolder: 'Ayant droit',
    fieldAllowDownload: 'Téléchargement autorisé',
    fieldFeatured: 'Mise en avant',
    hasFile: 'Fichier présent',
    noFile: 'Aucun fichier',
    errorGeneric: 'Une erreur est survenue. Réessayez.',
    errorForbidden: 'Vous n’avez pas les droits pour cette action.',
    confirmDelete: 'Supprimer définitivement cette ressource ?',
  },
  en: {
    title: 'Library — administration',
    subtitle: 'Create, edit, upload and publish your company resources.',
    loading: 'Loading resources…',
    empty: 'No resources yet. Create the first one.',
    create: 'New resource',
    creating: 'Creating…',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    edit: 'Edit',
    remove: 'Delete',
    upload: 'Upload file',
    uploading: 'Uploading…',
    publish: 'Publish',
    unpublish: 'Back to draft',
    archive: 'Archive',
    statusDraft: 'Draft',
    statusPending: 'Pending',
    statusPublished: 'Published',
    statusArchived: 'Archived',
    fieldTitle: 'Title',
    fieldDescription: 'Description',
    fieldType: 'Document type',
    fieldLanguage: 'Language',
    fieldAccess: 'Access model',
    fieldPrice: 'Price (minor units)',
    fieldRightsHolder: 'Rights holder',
    fieldAllowDownload: 'Download allowed',
    fieldFeatured: 'Featured',
    hasFile: 'File present',
    noFile: 'No file',
    errorGeneric: 'Something went wrong. Please retry.',
    errorForbidden: 'You are not allowed to perform this action.',
    confirmDelete: 'Permanently delete this resource?',
  },
  tr: {
    title: 'Kütüphane — yönetim',
    subtitle: 'Şirket kaynaklarınızı oluşturun, düzenleyin, yükleyin ve yayınlayın.',
    loading: 'Kaynaklar yükleniyor…',
    empty: 'Henüz kaynak yok. İlkini oluşturun.',
    create: 'Yeni kaynak',
    creating: 'Oluşturuluyor…',
    save: 'Kaydet',
    saving: 'Kaydediliyor…',
    cancel: 'İptal',
    edit: 'Düzenle',
    remove: 'Sil',
    upload: 'Dosya yükle',
    uploading: 'Yükleniyor…',
    publish: 'Yayınla',
    unpublish: 'Taslağa döndür',
    archive: 'Arşivle',
    statusDraft: 'Taslak',
    statusPending: 'Beklemede',
    statusPublished: 'Yayınlandı',
    statusArchived: 'Arşivlendi',
    fieldTitle: 'Başlık',
    fieldDescription: 'Açıklama',
    fieldType: 'Belge türü',
    fieldLanguage: 'Dil',
    fieldAccess: 'Erişim modeli',
    fieldPrice: 'Fiyat (küçük birim)',
    fieldRightsHolder: 'Hak sahibi',
    fieldAllowDownload: 'İndirmeye izin ver',
    fieldFeatured: 'Öne çıkan',
    hasFile: 'Dosya mevcut',
    noFile: 'Dosya yok',
    errorGeneric: 'Bir hata oluştu. Tekrar deneyin.',
    errorForbidden: 'Bu işlem için yetkiniz yok.',
    confirmDelete: 'Bu kaynak kalıcı olarak silinsin mi?',
  },
  ar: {
    title: 'المكتبة — الإدارة',
    subtitle: 'أنشئ موارد شركتك وحرّرها وارفعها وانشرها.',
    loading: 'جارٍ تحميل الموارد…',
    empty: 'لا توجد موارد بعد. أنشئ الأول.',
    create: 'مورد جديد',
    creating: 'جارٍ الإنشاء…',
    save: 'حفظ',
    saving: 'جارٍ الحفظ…',
    cancel: 'إلغاء',
    edit: 'تعديل',
    remove: 'حذف',
    upload: 'رفع الملف',
    uploading: 'جارٍ الرفع…',
    publish: 'نشر',
    unpublish: 'إرجاع إلى المسودة',
    archive: 'أرشفة',
    statusDraft: 'مسودة',
    statusPending: 'قيد الانتظار',
    statusPublished: 'منشور',
    statusArchived: 'مؤرشف',
    fieldTitle: 'العنوان',
    fieldDescription: 'الوصف',
    fieldType: 'نوع المستند',
    fieldLanguage: 'اللغة',
    fieldAccess: 'نموذج الوصول',
    fieldPrice: 'السعر (بالسنت)',
    fieldRightsHolder: 'صاحب الحقوق',
    fieldAllowDownload: 'السماح بالتنزيل',
    fieldFeatured: 'مميّز',
    hasFile: 'الملف موجود',
    noFile: 'لا يوجد ملف',
    errorGeneric: 'حدث خطأ. حاول مرة أخرى.',
    errorForbidden: 'ليست لديك صلاحية لهذا الإجراء.',
    confirmDelete: 'حذف هذا المورد نهائيًا؟',
  },
};

const DOCUMENT_TYPES: LibraryDocumentType[] = ['book', 'guide', 'report', 'article', 'template', 'other'];
const ACCESS_MODELS: LibraryAccessModel[] = ['free', 'paid', 'subscription', 'internal'];

const inputClass =
  'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';

const EMPTY_FORM: LibraryResourceInput = {
  title: '',
  description: '',
  document_type: 'guide',
  language: 'fr',
  access_model: 'free',
  price_minor: null,
  rights_holder: '',
  allow_download: true,
  featured: false,
};

export default function LibraryAdminPage() {
  const locale = getPreferredLocale();
  const c = useMemo(() => COPY[locale] ?? COPY.fr, [locale]);

  const [resources, setResources] = useState<LibraryResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const [form, setForm] = useState<LibraryResourceInput>(EMPTY_FORM);

  const load = useCallback(async () => {
    try {
      setResources(await listLibraryResources());
      setError('');
    } catch (cause) {
      setError(libraryErrorMessage(cause, c.errorGeneric));
    } finally {
      setLoading(false);
    }
  }, [c.errorGeneric]);

  useEffect(() => {
    void load();
  }, [load]);

  const run = async (key: string, action: () => Promise<void>) => {
    setBusy(key);
    setError('');
    try {
      await action();
    } catch (cause) {
      setError(libraryErrorMessage(cause, c.errorGeneric));
    } finally {
      setBusy(null);
    }
  };

  const startCreate = () => {
    setForm(EMPTY_FORM);
    setEditing('new');
  };

  const startEdit = (resource: LibraryResource) => {
    setForm({
      title: resource.title,
      description: resource.description ?? '',
      document_type: resource.document_type,
      language: resource.language,
      access_model: resource.access_model,
      price_minor: resource.price_minor,
      rights_holder: resource.rights_holder ?? '',
      allow_download: resource.allow_download,
      featured: resource.featured,
    });
    setEditing(resource.id);
  };

  const submit = () =>
    run('save', async () => {
      if (editing === 'new') {
        const created = await createLibraryResource(form);
        setResources((current) => [created, ...current]);
      } else if (typeof editing === 'number') {
        const updated = await updateLibraryResource(editing, form);
        setResources((current) => current.map((item) => (item.id === updated.id ? updated : item)));
      }
      setEditing(null);
    });

  const remove = (resource: LibraryResource) =>
    run(`delete-${resource.id}`, async () => {
      if (!window.confirm(c.confirmDelete)) return;
      await deleteLibraryResource(resource.id);
      setResources((current) => current.filter((item) => item.id !== resource.id));
    });

  const upload = (resource: LibraryResource, file: File) =>
    run(`upload-${resource.id}`, async () => {
      const updated = await uploadLibraryResourceFile(resource.id, file);
      setResources((current) => current.map((item) => (item.id === updated.id ? updated : item)));
    });

  const transition = (resource: LibraryResource, action: 'publish' | 'unpublish' | 'archive') =>
    run(`${action}-${resource.id}`, async () => {
      const updated = await transitionLibraryResource(resource.id, action);
      setResources((current) => current.map((item) => (item.id === updated.id ? updated : item)));
    });

  const statusLabel = (status: LibraryResourceStatus): string => {
    switch (status) {
      case 'draft':
        return c.statusDraft;
      case 'pending':
        return c.statusPending;
      case 'published':
        return c.statusPublished;
      case 'archived':
        return c.statusArchived;
    }
  };

  const statusClass = (status: LibraryResourceStatus): string => {
    switch (status) {
      case 'published':
        return 'bg-emerald-100 text-emerald-700';
      case 'archived':
        return 'bg-slate-200 text-slate-600';
      case 'pending':
        return 'bg-amber-100 text-amber-700';
      default:
        return 'bg-slate-100 text-slate-600';
    }
  };

  if (loading) {
    return (
      <ModulePageShell title={c.title} subtitle={c.subtitle} icon={BookOpen}>
        <div className="flex items-center gap-3 rounded-3xl border border-white/20 bg-white/70 p-8 text-slate-500 shadow-premium backdrop-blur-xl">
          <Loader2 className="h-5 w-5 animate-spin" aria-hidden="true" />
          {c.loading}
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell title={c.title} subtitle={c.subtitle} icon={BookOpen}>
      {error !== '' && (
        <div
          role="alert"
          className="mb-4 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {error}
        </div>
      )}

      <div className="mb-4 flex justify-end">
        <button
          type="button"
          onClick={startCreate}
          disabled={busy !== null}
          className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5 disabled:opacity-60"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          {c.create}
        </button>
      </div>

      {editing !== null && (
        <section className="mb-6 rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
          <h2 className="mb-4 text-lg font-black tracking-tight text-slate-950">
            {editing === 'new' ? c.create : c.edit}
          </h2>

          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block sm:col-span-2">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldTitle}</span>
              <input
                className={inputClass}
                value={form.title}
                onChange={(event) => setForm({ ...form, title: event.target.value })}
                required
              />
            </label>

            <label className="block sm:col-span-2">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldDescription}</span>
              <textarea
                className={`${inputClass} min-h-24`}
                value={form.description ?? ''}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
            </label>

            <label className="block">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldType}</span>
              <select
                className={inputClass}
                value={form.document_type}
                onChange={(event) =>
                  setForm({ ...form, document_type: event.target.value as LibraryDocumentType })
                }
              >
                {DOCUMENT_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {type}
                  </option>
                ))}
              </select>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldLanguage}</span>
              <input
                className={inputClass}
                value={form.language ?? ''}
                onChange={(event) => setForm({ ...form, language: event.target.value })}
              />
            </label>

            <label className="block">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldAccess}</span>
              <select
                className={inputClass}
                value={form.access_model}
                onChange={(event) =>
                  setForm({ ...form, access_model: event.target.value as LibraryAccessModel })
                }
              >
                {ACCESS_MODELS.map((model) => (
                  <option key={model} value={model}>
                    {model}
                  </option>
                ))}
              </select>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldPrice}</span>
              <input
                type="number"
                min={0}
                className={inputClass}
                value={form.price_minor ?? ''}
                onChange={(event) =>
                  setForm({
                    ...form,
                    price_minor: event.target.value === '' ? null : Number(event.target.value),
                  })
                }
              />
            </label>

            <label className="block sm:col-span-2">
              <span className="mb-1 block text-xs font-semibold text-slate-600">{c.fieldRightsHolder}</span>
              <input
                className={inputClass}
                value={form.rights_holder ?? ''}
                onChange={(event) => setForm({ ...form, rights_holder: event.target.value })}
              />
            </label>

            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={form.allow_download ?? false}
                onChange={(event) => setForm({ ...form, allow_download: event.target.checked })}
              />
              {c.fieldAllowDownload}
            </label>

            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={form.featured ?? false}
                onChange={(event) => setForm({ ...form, featured: event.target.checked })}
              />
              {c.fieldFeatured}
            </label>
          </div>

          <div className="mt-4 flex items-center gap-2">
            <button
              type="button"
              onClick={submit}
              disabled={busy !== null || form.title.trim() === ''}
              className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-60"
            >
              {busy === 'save' ? (
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              ) : (
                <Check className="h-4 w-4" aria-hidden="true" />
              )}
              {busy === 'save' ? c.saving : c.save}
            </button>
            <button
              type="button"
              onClick={() => setEditing(null)}
              disabled={busy !== null}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 disabled:opacity-60"
            >
              <X className="h-4 w-4" aria-hidden="true" />
              {c.cancel}
            </button>
          </div>
        </section>
      )}

      {resources.length === 0 ? (
        <p className="rounded-3xl border border-dashed border-slate-300 bg-white/60 p-6 text-sm text-slate-500">
          {c.empty}
        </p>
      ) : (
        <ul className="space-y-3">
          {resources.map((resource) => (
            <li
              key={resource.id}
              className="rounded-3xl border border-white/20 bg-white/70 p-5 shadow-premium backdrop-blur-xl"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="truncate text-base font-black text-slate-950">{resource.title}</h3>
                    <span
                      className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide ${statusClass(resource.status)}`}
                    >
                      {statusLabel(resource.status)}
                    </span>
                    <span
                      className={`rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${
                        resource.has_file ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                      }`}
                    >
                      {resource.has_file ? c.hasFile : c.noFile}
                    </span>
                  </div>
                  <p className="mt-1 text-xs text-slate-500">
                    /{resource.slug} · {resource.document_type} · {resource.language} · {resource.access_model}
                  </p>
                  {resource.description && (
                    <p className="mt-2 line-clamp-2 text-sm text-slate-600">{resource.description}</p>
                  )}
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                  <button
                    type="button"
                    onClick={() => startEdit(resource)}
                    disabled={busy !== null}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-400 disabled:opacity-60"
                  >
                    <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                    {c.edit}
                  </button>

                  <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-400">
                    {busy === `upload-${resource.id}` ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
                    ) : (
                      <FileUp className="h-3.5 w-3.5" aria-hidden="true" />
                    )}
                    {busy === `upload-${resource.id}` ? c.uploading : c.upload}
                    <input
                      type="file"
                      className="sr-only"
                      disabled={busy !== null}
                      onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (file) void upload(resource, file);
                        event.target.value = '';
                      }}
                    />
                  </label>

                  {resource.status !== 'published' && resource.status !== 'archived' && (
                    <button
                      type="button"
                      onClick={() => transition(resource, 'publish')}
                      disabled={busy !== null}
                      className="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-3 py-2 text-xs font-bold text-white transition disabled:opacity-60"
                    >
                      <Rocket className="h-3.5 w-3.5" aria-hidden="true" />
                      {c.publish}
                    </button>
                  )}

                  {resource.status === 'published' && (
                    <button
                      type="button"
                      onClick={() => transition(resource, 'unpublish')}
                      disabled={busy !== null}
                      className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-amber-400 disabled:opacity-60"
                    >
                      <Undo2 className="h-3.5 w-3.5" aria-hidden="true" />
                      {c.unpublish}
                    </button>
                  )}

                  {resource.status !== 'archived' && (
                    <button
                      type="button"
                      onClick={() => transition(resource, 'archive')}
                      disabled={busy !== null}
                      className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-slate-400 disabled:opacity-60"
                    >
                      <Archive className="h-3.5 w-3.5" aria-hidden="true" />
                      {c.archive}
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => remove(resource)}
                    disabled={busy !== null}
                    aria-label={c.remove}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
                  >
                    <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </ModulePageShell>
  );
}
