'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import {
  AlertCircle,
  Check,
  ExternalLink,
  Eye,
  Globe,
  Loader2,
  Palette,
  Plus,
  Rocket,
  Save,
  Trash2,
} from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, getStoredUser } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';
import {
  SHOWCASE_THEMES,
  createShowcase,
  createShowcaseSection,
  defaultShowcaseSections,
  deleteShowcaseSection,
  fetchShowcase,
  issueShowcasePreviewToken,
  listShowcaseSections,
  publishShowcase,
  unpublishShowcase,
  updateShowcaseSection,
  updateShowcaseSettings,
  EXTERNAL_LINK_REL,
  SHOWCASE_ITEMS_GRID_FEATURES,
  SHOWCASE_ITEMS_GRID_TESTIMONIALS,
  type Showcase,
  type ShowcaseSection,
  type ShowcaseSectionType,
} from '@/lib/showcase';

type CopyKey =
  | 'title'
  | 'subtitle'
  | 'loading'
  | 'createTitle'
  | 'createBody'
  | 'createCta'
  | 'creating'
  | 'statusDraft'
  | 'statusPublished'
  | 'publish'
  | 'publishing'
  | 'unpublish'
  | 'unpublishing'
  | 'viewSite'
  | 'preview'
  | 'previewLoading'
  | 'publicUrl'
  | 'themeTitle'
  | 'themeHint'
  | 'sectionsTitle'
  | 'sectionsHint'
  | 'addFeatures'
  | 'addTestimonials'
  | 'save'
  | 'saving'
  | 'saved'
  | 'delete'
  | 'fieldHeading'
  | 'fieldSubheading'
  | 'fieldCtaLabel'
  | 'fieldCtaUrl'
  | 'fieldTitle'
  | 'fieldEmail'
  | 'fieldPhone'
  | 'fieldAddress'
  | 'fieldText'
  | 'itemTitle'
  | 'itemDescription'
  | 'itemQuote'
  | 'itemAuthor'
  | 'advancedNote'
  | 'errorGeneric'
  | 'emptySections';

const COPY_KEYS: CopyKey[] = [
  'title', 'subtitle', 'loading', 'createTitle', 'createBody', 'createCta', 'creating',
  'statusDraft', 'statusPublished', 'publish', 'publishing', 'unpublish', 'unpublishing',
  'viewSite', 'preview', 'previewLoading', 'publicUrl', 'themeTitle', 'themeHint',
  'sectionsTitle', 'sectionsHint', 'addFeatures', 'addTestimonials', 'save', 'saving', 'saved',
  'delete', 'fieldHeading', 'fieldSubheading', 'fieldCtaLabel', 'fieldCtaUrl', 'fieldTitle',
  'fieldEmail', 'fieldPhone', 'fieldAddress', 'fieldText', 'itemTitle', 'itemDescription',
  'itemQuote', 'itemAuthor', 'advancedNote', 'errorGeneric', 'emptySections',
];

const SECTION_LABEL_KEY: Record<ShowcaseSectionType, string> = {
  hero: 'showcase.sectionHero',
  features: 'showcase.sectionFeatures',
  gallery: 'showcase.sectionGallery',
  testimonials: 'showcase.sectionTestimonials',
  products: 'showcase.sectionProducts',
  contact: 'showcase.sectionContact',
  footer: 'showcase.sectionFooter',
};

function buildCopy(locale: AppLocale): Record<CopyKey, string> {
  const copy = {} as Record<CopyKey, string>;
  for (const key of COPY_KEYS) {
    copy[key] = t(locale, `showcase.${key}`);
  }
  return copy;
}

function asString(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

function asItems(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value) ? value.filter((item): item is Record<string, unknown> => !!item && typeof item === 'object') : [];
}

const inputClass =
  'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';

export default function ShowcaseModulePage() {
  const locale = getPreferredLocale();
  const c = useMemo(() => buildCopy(locale), [locale]);

  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [showcase, setShowcase] = useState<Showcase | null>(null);
  const [sections, setSections] = useState<ShowcaseSection[]>([]);
  const [drafts, setDrafts] = useState<Record<number, Record<string, unknown>>>({});
  const [savedId, setSavedId] = useState<number | null>(null);

  const loadSections = useCallback(async () => {
    const list = await listShowcaseSections();
    setSections(list);
    setDrafts({});
  }, []);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const existing = await fetchShowcase();
        if (cancelled) return;
        setShowcase(existing);
        if (existing) {
          await loadSections();
        }
      } catch {
        if (!cancelled) setError(buildCopy(locale).errorGeneric);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [locale, loadSections]);

  const run = async (key: string, action: () => Promise<void>) => {
    setBusy(key);
    setError('');
    try {
      await action();
    } catch {
      setError(c.errorGeneric);
    } finally {
      setBusy(null);
    }
  };

  const handleCreate = () =>
    run('create', async () => {
      const created = await createShowcase();
      // US1 « 1 clic » : on amorce une page présentable (hero + contact +
      // footer) pour ne pas livrer une vitrine vide. Idempotent : si la
      // vitrine existait déjà, on ne ré-amorce pas.
      if (showcase === null && created.status === 'draft') {
        const user = getStoredUser();
        const companyName = user?.company?.name ?? '';
        const email = user?.email ?? '';
        for (const section of defaultShowcaseSections(companyName, email)) {
          await createShowcaseSection(section.type, section.content);
        }
      }
      setShowcase(created);
      await loadSections();
    });

  const handleTheme = (theme: string) =>
    run('theme', async () => {
      const updated = await updateShowcaseSettings({ theme });
      setShowcase(updated);
    });

  const handlePublishToggle = () =>
    run('publish', async () => {
      if (!showcase) return;
      const updated = showcase.status === 'published' ? await unpublishShowcase() : await publishShowcase();
      setShowcase(updated);
    });

  const handlePreview = () =>
    run('preview', async () => {
      const token = await issueShowcasePreviewToken();
      window.open(`/vitrine/${showcase?.slug ?? ''}?token=${encodeURIComponent(token.preview_token)}`, '_blank', 'noopener');
    });

  const handleSaveSection = (section: ShowcaseSection) =>
    run(`save-${section.id}`, async () => {
      const content = drafts[section.id] ?? section.content;
      const updated = await updateShowcaseSection(section.id, content);
      setSections((current) => current.map((item) => (item.id === updated.id ? updated : item)));
      setSavedId(updated.id);
      window.setTimeout(() => setSavedId((current) => (current === updated.id ? null : current)), 2500);
    });

  const handleDeleteSection = (section: ShowcaseSection) =>
    run(`delete-${section.id}`, async () => {
      await deleteShowcaseSection(section.id);
      setSections((current) => current.filter((item) => item.id !== section.id));
    });

  const handleAddSection = (type: ShowcaseSectionType, starter: Record<string, unknown>) =>
    run(`add-${type}`, async () => {
      const created = await createShowcaseSection(type, starter);
      setSections((current) => [...current, created]);
    });

  const setDraftField = (section: ShowcaseSection, field: string, value: unknown) => {
    setDrafts((current) => ({
      ...current,
      [section.id]: { ...(current[section.id] ?? section.content), [field]: value },
    }));
  };

  const valueOf = (section: ShowcaseSection, field: string): string =>
    asString((drafts[section.id] ?? section.content)[field]);

  const itemsOf = (section: ShowcaseSection, field: string): Record<string, unknown>[] =>
    asItems((drafts[section.id] ?? section.content)[field]);

  const setItemField = (section: ShowcaseSection, field: string, index: number, key: string, value: string) => {
    const items = itemsOf(section, field).map((item, i) => (i === index ? { ...item, [key]: value } : item));
    setDraftField(section, field, items);
  };

  const addItem = (section: ShowcaseSection, field: string, item: Record<string, unknown>) => {
    setDraftField(section, field, [...itemsOf(section, field), item]);
  };

  const removeItem = (section: ShowcaseSection, field: string, index: number) => {
    setDraftField(section, field, itemsOf(section, field).filter((_, i) => i !== index));
  };

  if (loading) {
    return (
      <ModulePageShell title={c.title} subtitle={c.subtitle}>
        <div className="flex items-center gap-3 rounded-3xl border border-white/20 bg-white/70 p-8 text-slate-500 shadow-premium backdrop-blur-xl">
          <Loader2 className="h-5 w-5 animate-spin" />
          {c.loading}
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell title={c.title} subtitle={c.subtitle} icon={Globe}>
      {error !== '' && (
        <div className="mb-4 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {showcase === null ? (
        <motion.section
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="relative overflow-hidden rounded-3xl border border-white/20 bg-white/70 p-8 shadow-premium backdrop-blur-xl"
        >
          <div className="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-cyan-500/5" />
          <div className="relative max-w-2xl space-y-4">
            <h2 className="text-2xl font-black tracking-tight text-slate-950">{c.createTitle}</h2>
            <p className="text-sm leading-6 text-slate-600">{c.createBody}</p>
            <button
              type="button"
              onClick={handleCreate}
              disabled={busy !== null}
              className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5 disabled:opacity-60"
            >
              {busy === 'create' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />}
              {busy === 'create' ? c.creating : c.createCta}
            </button>
          </div>
        </motion.section>
      ) : (
        <div className="space-y-6">
          {/* Statut + actions de publication */}
          <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div className="space-y-1">
                <span
                  className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${
                    showcase.status === 'published'
                      ? 'bg-emerald-100 text-emerald-700'
                      : 'bg-amber-100 text-amber-700'
                  }`}
                >
                  {showcase.status === 'published' ? <Check className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                  {showcase.status === 'published' ? c.statusPublished : c.statusDraft}
                </span>
                <p className="flex items-center gap-2 text-sm text-slate-500">
                  <Globe className="h-4 w-4" />
                  {c.publicUrl}
                  <code className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">/vitrine/{showcase.slug}</code>
                </p>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <a
                  href={`/vitrine/${showcase.slug}`}
                  target="_blank"
                  rel={EXTERNAL_LINK_REL}
                  className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-400"
                >
                  <ExternalLink className="h-4 w-4" />
                  {c.viewSite}
                </a>
                <button
                  type="button"
                  onClick={handlePreview}
                  disabled={busy !== null}
                  className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-400 disabled:opacity-60"
                >
                  {busy === 'preview' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Eye className="h-4 w-4" />}
                  {busy === 'preview' ? c.previewLoading : c.preview}
                </button>
                <button
                  type="button"
                  onClick={handlePublishToggle}
                  disabled={busy !== null}
                  className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white shadow-lg transition disabled:opacity-60 ${
                    showcase.status === 'published'
                      ? 'bg-slate-700 hover:bg-slate-800'
                      : 'bg-gradient-to-r from-emerald-500 to-cyan-600'
                  }`}
                >
                  {busy === 'publish' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />}
                  {showcase.status === 'published'
                    ? busy === 'publish' ? c.unpublishing : c.unpublish
                    : busy === 'publish' ? c.publishing : c.publish}
                </button>
              </div>
            </div>
          </section>

          {/* Thème */}
          <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
            <h2 className="flex items-center gap-2 text-lg font-black tracking-tight text-slate-950">
              <Palette className="h-5 w-5 text-emerald-500" />
              {c.themeTitle}
            </h2>
            <p className="mt-1 text-sm text-slate-500">{c.themeHint}</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-3">
              {SHOWCASE_THEMES.map((theme) => {
                const active = showcase.theme === theme.id;
                return (
                  <button
                    key={theme.id}
                    type="button"
                    onClick={() => handleTheme(theme.id)}
                    disabled={busy !== null}
                    aria-pressed={active}
                    className={`rounded-2xl border-2 p-4 text-left transition disabled:opacity-60 ${
                      active ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200 hover:border-emerald-300'
                    }`}
                  >
                    <span className="block text-sm font-black text-slate-900">{theme.label}</span>
                    <span className="mt-1 block text-xs leading-5 text-slate-500">{theme.description}</span>
                  </button>
                );
              })}
            </div>
          </section>

          {/* Sections */}
          <section className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div>
                <h2 className="text-lg font-black tracking-tight text-slate-950">{c.sectionsTitle}</h2>
                <p className="text-sm text-slate-500">{c.sectionsHint}</p>
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() =>
                    handleAddSection('features', { title: '', items: [{ title: '', description: '' }] })
                  }
                  disabled={busy !== null}
                  className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-400 disabled:opacity-60"
                >
                  <Plus className="h-3.5 w-3.5" />
                  {c.addFeatures}
                </button>
                <button
                  type="button"
                  onClick={() =>
                    handleAddSection('testimonials', { title: '', items: [{ quote: '', author: '' }] })
                  }
                  disabled={busy !== null}
                  className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-400 disabled:opacity-60"
                >
                  <Plus className="h-3.5 w-3.5" />
                  {c.addTestimonials}
                </button>
              </div>
            </div>

            {sections.length === 0 ? (
              <p className="rounded-3xl border border-dashed border-slate-300 bg-white/60 p-6 text-sm text-slate-500">
                {c.emptySections}
              </p>
            ) : (
              sections.map((section) => (
                <motion.article
                  key={section.id}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl"
                >
                  <header className="mb-4 flex items-center justify-between gap-3">
                    <h3 className="text-sm font-black uppercase tracking-wide text-slate-500">
                      {t(locale, SECTION_LABEL_KEY[section.type], section.type)}
                    </h3>
                    <div className="flex items-center gap-2">
                      {savedId === section.id && (
                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                          <Check className="h-3.5 w-3.5" />
                          {c.saved}
                        </span>
                      )}
                      <button
                        type="button"
                        onClick={() => handleSaveSection(section)}
                        disabled={busy !== null}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-800 disabled:opacity-60"
                      >
                        {busy === `save-${section.id}` ? (
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                          <Save className="h-3.5 w-3.5" />
                        )}
                        {busy === `save-${section.id}` ? c.saving : c.save}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDeleteSection(section)}
                        disabled={busy !== null}
                        aria-label={c.delete}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-60"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </header>

                  {section.type === 'hero' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <Field label={c.fieldHeading} value={valueOf(section, 'heading')} onChange={(v) => setDraftField(section, 'heading', v)} />
                      <Field label={c.fieldSubheading} value={valueOf(section, 'subheading')} onChange={(v) => setDraftField(section, 'subheading', v)} />
                      <Field label={c.fieldCtaLabel} value={valueOf(section, 'cta_label')} onChange={(v) => setDraftField(section, 'cta_label', v)} />
                      <Field label={c.fieldCtaUrl} value={valueOf(section, 'cta_url')} onChange={(v) => setDraftField(section, 'cta_url', v)} />
                    </div>
                  )}

                  {section.type === 'contact' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <Field label={c.fieldTitle} value={valueOf(section, 'title')} onChange={(v) => setDraftField(section, 'title', v)} />
                      <Field label={c.fieldEmail} value={valueOf(section, 'email')} onChange={(v) => setDraftField(section, 'email', v)} />
                      <Field label={c.fieldPhone} value={valueOf(section, 'phone')} onChange={(v) => setDraftField(section, 'phone', v)} />
                      <Field label={c.fieldAddress} value={valueOf(section, 'address')} onChange={(v) => setDraftField(section, 'address', v)} />
                    </div>
                  )}

                  {section.type === 'footer' && (
                    <Field label={c.fieldText} value={valueOf(section, 'text')} onChange={(v) => setDraftField(section, 'text', v)} />
                  )}

                  {section.type === 'features' && (
                    <div className="space-y-3">
                      <Field label={c.fieldTitle} value={valueOf(section, 'title')} onChange={(v) => setDraftField(section, 'title', v)} />
                      {itemsOf(section, 'items').map((item, index) => (
                        <div key={index} className={`grid gap-2 rounded-2xl border border-slate-200 p-3 ${SHOWCASE_ITEMS_GRID_FEATURES}`}>
                          <input
                            className={inputClass}
                            placeholder={c.itemTitle}
                            value={asString(item.title)}
                            onChange={(event) => setItemField(section, 'items', index, 'title', event.target.value)}
                          />
                          <input
                            className={inputClass}
                            placeholder={c.itemDescription}
                            value={asString(item.description)}
                            onChange={(event) => setItemField(section, 'items', index, 'description', event.target.value)}
                          />
                          <button
                            type="button"
                            onClick={() => removeItem(section, 'items', index)}
                            aria-label={c.delete}
                            className="rounded-xl border border-red-200 px-3 text-red-600 transition hover:bg-red-50"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      ))}
                      <button
                        type="button"
                        onClick={() => addItem(section, 'items', { title: '', description: '' })}
                        className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 hover:underline"
                      >
                        <Plus className="h-3.5 w-3.5" />
                        {c.itemTitle}
                      </button>
                    </div>
                  )}

                  {section.type === 'testimonials' && (
                    <div className="space-y-3">
                      <Field label={c.fieldTitle} value={valueOf(section, 'title')} onChange={(v) => setDraftField(section, 'title', v)} />
                      {itemsOf(section, 'items').map((item, index) => (
                        <div key={index} className={`grid gap-2 rounded-2xl border border-slate-200 p-3 ${SHOWCASE_ITEMS_GRID_TESTIMONIALS}`}>
                          <input
                            className={inputClass}
                            placeholder={c.itemQuote}
                            value={asString(item.quote)}
                            onChange={(event) => setItemField(section, 'items', index, 'quote', event.target.value)}
                          />
                          <input
                            className={inputClass}
                            placeholder={c.itemAuthor}
                            value={asString(item.author)}
                            onChange={(event) => setItemField(section, 'items', index, 'author', event.target.value)}
                          />
                          <input
                            className={inputClass}
                            placeholder={c.fieldCtaLabel}
                            value={asString(item.role)}
                            onChange={(event) => setItemField(section, 'items', index, 'role', event.target.value)}
                          />
                          <button
                            type="button"
                            onClick={() => removeItem(section, 'items', index)}
                            aria-label={c.delete}
                            className="rounded-xl border border-red-200 px-3 text-red-600 transition hover:bg-red-50"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      ))}
                      <button
                        type="button"
                        onClick={() => addItem(section, 'items', { quote: '', author: '' })}
                        className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 hover:underline"
                      >
                        <Plus className="h-3.5 w-3.5" />
                        {c.itemQuote}
                      </button>
                    </div>
                  )}

                  {!['hero', 'contact', 'footer', 'features', 'testimonials'].includes(section.type) && (
                    <p className="rounded-2xl bg-slate-50 p-3 text-xs text-slate-500">{c.advancedNote}</p>
                  )}
                </motion.article>
              ))
            )}
          </section>
        </div>
      )}
    </ModulePageShell>
  );
}

function Field({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-semibold text-slate-600">{label}</span>
      <input className={inputClass} value={value} onChange={(event) => onChange(event.target.value)} />
    </label>
  );
}
