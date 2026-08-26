'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Circle,
  Coins,
  FileBadge2,
  FileText,
  Languages,
  Landmark,
  ListOrdered,
  Loader2,
  Percent,
  Plus,
  RefreshCw,
  Rocket,
  ScrollText,
  Settings2,
  Trash2,
  UserRound,
} from 'lucide-react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface ActivationStatus {
  completed: boolean;
  steps: {
    settings: boolean;
    contact: boolean;
    example_invoice: boolean;
  };
  contact: { id: number; name: string } | null;
  example_invoice: { id: number; number: string } | null;
}

interface TvaRate {
  label: string;
  rate: string;
}

/** Types de documents acceptés par `number_series` (DocumentType côté API). */
const DOCUMENT_TYPES = ['invoice', 'proforma', 'quote', 'credit_note', 'delivery_note', 'receipt'] as const;

/** Devises ISO 4217 courantes du registre — la devise reste optionnelle :
 *  absente, le backend dérive la devise du pays de l'entreprise. */
const CURRENCIES = ['DZD', 'EUR', 'USD', 'MAD', 'XOF', 'XAF', 'GBP', 'CAD', 'TRY', 'AED', 'SAR', 'TND', 'EGP'] as const;

const LANGUAGES = [
  { code: 'fr', label: 'Français' },
  { code: 'en', label: 'English' },
  { code: 'ar', label: 'العربية' },
  { code: 'tr', label: 'Türkçe' },
] as const;

const EMPTY_SERIES: Record<string, string> = Object.fromEntries(DOCUMENT_TYPES.map((d) => [d, '']));

/**
 * #5626 — Wizard d'activation Comptabilité guidé (4 étapes) dans front/web.
 *
 * L'admin-dashboard Vue a son wizard (#5288/#5539) ; front/web (interface
 * principale des managers) n'avait qu'une check-list + bouton unique.
 * Ce wizard collecte le paramétrage réel et l'envoie à
 * `POST /accounting/activation/complete` (payload optionnel : le backend
 * dérive les défauts du pays de l'entreprise quand un champ est absent) :
 *
 *   Step 1 — Identité & langue des documents (document_language, currency)
 *   Step 2 — TVA & séries de numérotation   (tva_rates[], number_series{})
 *   Step 3 — Modèle PDF & mentions légales  (template_style, payment_terms,
 *            legal_mentions)
 *   Step 4 — Finaliser → POST + état complet
 */
export default function AccountingActivationPage() {
  const locale = getPreferredLocale();
  const [status, setStatus] = useState<ActivationStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [step, setStep] = useState(1);
  const [form, setForm] = useState({
    currency: '',
    document_language: '',
    tva_rates: [{ label: '', rate: '' }] as TvaRate[],
    number_series: { ...EMPTY_SERIES } as Record<string, string>,
    template_style: '',
    payment_terms: '',
    legal_mentions: '',
  });

  const loadStatus = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await apiFetch('/accounting/activation');
      const body = await res.json();
      setStatus(body.data as ActivationStatus);
    } catch {
      setLoadError(t(locale, 'accountingActivation.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadStatus();
  }, [loadStatus]);

  const completeActivation = async () => {
    setSubmitting(true);
    setSubmitError(null);
    try {
      // Payload : seuls les champs remplis sont envoyés — les absents
      // gardent les défauts dérivés du pays (contrat UpdateAccountingSettings).
      const payload: Record<string, unknown> = {};
      if (form.currency.trim() !== '') payload.currency = form.currency.trim().toUpperCase();
      if (form.document_language !== '') payload.document_language = form.document_language;

      const tvaRates = form.tva_rates
        .map((r) => ({ label: r.label.trim(), rate: Number.parseFloat(r.rate) }))
        .filter((r) => r.label !== '' && !Number.isNaN(r.rate));
      if (tvaRates.length > 0) payload.tva_rates = tvaRates;

      const numberSeries = Object.fromEntries(
        Object.entries(form.number_series).filter(([, v]) => v.trim() !== ''),
      );
      if (Object.keys(numberSeries).length > 0) payload.number_series = numberSeries;

      if (form.template_style.trim() !== '') payload.template_style = form.template_style.trim();
      if (form.payment_terms.trim() !== '') payload.payment_terms = form.payment_terms.trim();
      if (form.legal_mentions.trim() !== '') payload.legal_mentions = form.legal_mentions.trim();

      const res = await apiFetch('/accounting/activation/complete', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      const body = await res.json();
      setStatus(body.data as ActivationStatus);
    } catch {
      setSubmitError(t(locale, 'accountingActivation.completeError'));
    } finally {
      setSubmitting(false);
    }
  };

  const updateSeries = (docType: string, value: string) => {
    setForm((f) => ({ ...f, number_series: { ...f.number_series, [docType]: value } }));
  };

  const updateTvaRate = (index: number, field: keyof TvaRate, value: string) => {
    setForm((f) => ({
      ...f,
      tva_rates: f.tva_rates.map((r, i) => (i === index ? { ...r, [field]: value } : r)),
    }));
  };

  const addTvaRate = () => setForm((f) => ({ ...f, tva_rates: [...f.tva_rates, { label: '', rate: '' }] }));
  const removeTvaRate = (index: number) =>
    setForm((f) => ({ ...f, tva_rates: f.tva_rates.filter((_, i) => i !== index) }));

  const stepsMeta = useMemo(
    () => [
      { icon: Languages, titleKey: 'accountingActivation.wizardStep1Title', subtitleKey: 'accountingActivation.wizardStep1Subtitle' },
      { icon: Percent, titleKey: 'accountingActivation.wizardStep2Title', subtitleKey: 'accountingActivation.wizardStep2Subtitle' },
      { icon: FileBadge2, titleKey: 'accountingActivation.wizardStep3Title', subtitleKey: 'accountingActivation.wizardStep3Subtitle' },
      { icon: Rocket, titleKey: 'accountingActivation.wizardStep4Title', subtitleKey: 'accountingActivation.wizardStep4Subtitle' },
    ],
    [],
  );

  const stepTitles = stepsMeta.map((s) => t(locale, s.titleKey));

  if (loadError) {
    return (
      <ModulePageShell
        title={t(locale, 'accountingActivation.title')}
        subtitle={t(locale, 'accountingActivation.subtitle')}
        accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
      >
        <div className="rounded-2xl border border-red-200 bg-red-50 p-8 text-center" role="alert">
          <p className="text-sm font-medium text-red-700">{loadError}</p>
          <button
            onClick={() => void loadStatus()}
            className="mt-3 inline-flex items-center gap-1 rounded-lg bg-red-100 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-200"
          >
            <RefreshCw className="h-3 w-3" />
            {t(locale, 'accountingActivation.retry')}
          </button>
        </div>
      </ModulePageShell>
    );
  }

  if (loading) {
    return (
      <ModulePageShell
        title={t(locale, 'accountingActivation.title')}
        subtitle={t(locale, 'accountingActivation.subtitle')}
        accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
      >
        <div className="flex items-center justify-center gap-2 rounded-2xl border border-app-border bg-white p-10 text-sm text-slate-400">
          <Loader2 className="h-4 w-4 animate-spin" />
          {t(locale, 'accountingActivation.loading')}
        </div>
      </ModulePageShell>
    );
  }

  return (
    <ModulePageShell
      title={t(locale, 'accountingActivation.title')}
      subtitle={t(locale, 'accountingActivation.subtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      {status?.completed ? (
        <motion.section
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-3xl border border-emerald-200 bg-white p-10 text-center shadow-sm"
        >
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
            <CheckCircle2 className="h-8 w-8 text-emerald-600" />
          </div>
          <h2 className="text-xl font-black text-slate-950">{t(locale, 'accountingActivation.completedTitle')}</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-slate-600">{t(locale, 'accountingActivation.completedBody')}</p>
          <Link
            href="/accounting"
            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-600"
          >
            {t(locale, 'accountingActivation.goToModule')}
            <ArrowRight className="h-4 w-4" />
          </Link>
        </motion.section>
      ) : (
        <div className="space-y-5">
          {/* Indicateur d'étapes */}
          <nav aria-label={t(locale, 'accountingActivation.wizardStepsAria')} className="flex items-center gap-2">
            {stepsMeta.map((meta, i) => {
              const n = i + 1;
              const Icon = meta.icon;
              const active = n === step;
              const done = n < step;
              return (
                <div key={meta.titleKey} className="flex flex-1 items-center gap-2">
                  <div
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-black transition ${
                      active
                        ? 'bg-amber-500 text-white shadow'
                        : done
                          ? 'bg-emerald-100 text-emerald-700'
                          : 'bg-slate-100 text-slate-400'
                    }`}
                  >
                    {done ? <CheckCircle2 className="h-4 w-4" /> : <Icon className="h-4 w-4" />}
                  </div>
                  <span
                    className={`hidden truncate text-xs font-bold sm:block ${active ? 'text-slate-900' : 'text-slate-400'}`}
                  >
                    {t(locale, meta.titleKey)}
                  </span>
                  {n < stepsMeta.length && <div className="h-px flex-1 bg-slate-200" />}
                </div>
              );
            })}
          </nav>

          <motion.section
            key={step}
            initial={{ opacity: 0, x: 12 }}
            animate={{ opacity: 1, x: 0 }}
            className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm"
          >
            <div className="border-b border-app-border px-6 py-5">
              <p className="text-[11px] font-black uppercase tracking-widest text-amber-600">
                {t(locale, 'accountingActivation.wizardStepIndicator')
                  .replace('{current}', String(step))
                  .replace('{total}', String(stepsMeta.length))}
              </p>
              <h2 className="mt-1 flex items-center gap-2 text-base font-black text-slate-950">
                {t(locale, stepsMeta[step - 1].titleKey)}
              </h2>
              <p className="mt-1 text-sm text-slate-500">{t(locale, stepsMeta[step - 1].subtitleKey)}</p>
            </div>

            <div className="space-y-5 px-6 py-6">
              {step === 1 && (
                <>
                  <label className="block">
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldDocumentLanguage')}
                    </span>
                    <select
                      value={form.document_language}
                      onChange={(e) => setForm((f) => ({ ...f, document_language: e.target.value }))}
                      className="w-full rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                    >
                      <option value="">{t(locale, 'accountingActivation.fieldDefaultDerived')}</option>
                      {LANGUAGES.map((l) => (
                        <option key={l.code} value={l.code}>
                          {l.label}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label className="block">
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldCurrency')}
                    </span>
                    <select
                      value={form.currency}
                      onChange={(e) => setForm((f) => ({ ...f, currency: e.target.value }))}
                      className="w-full rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                    >
                      <option value="">{t(locale, 'accountingActivation.fieldDefaultDerived')}</option>
                      {CURRENCIES.map((c) => (
                        <option key={c} value={c}>
                          {c}
                        </option>
                      ))}
                    </select>
                    <span className="mt-1.5 block text-xs text-slate-400">{t(locale, 'accountingActivation.fieldCurrencyHint')}</span>
                  </label>
                </>
              )}

              {step === 2 && (
                <>
                  <div>
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldTvaRates')}
                    </span>
                    <div className="space-y-2">
                      {form.tva_rates.map((rate, i) => (
                        <div key={i} className="flex items-center gap-2">
                          <input
                            aria-label={`${t(locale, 'accountingActivation.tvaRateLabel')} ${i + 1}`}
                            value={rate.label}
                            onChange={(e) => updateTvaRate(i, 'label', e.target.value)}
                            placeholder={t(locale, 'accountingActivation.tvaRateLabelPlaceholder')}
                            className="min-w-0 flex-1 rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                          />
                          <div className="flex w-32 items-center gap-1">
                            <input
                              aria-label={`${t(locale, 'accountingActivation.tvaRateValue')} ${i + 1}`}
                              type="number"
                              min={0}
                              max={100}
                              step="0.01"
                              value={rate.rate}
                              onChange={(e) => updateTvaRate(i, 'rate', e.target.value)}
                              placeholder={t(locale, 'accountingActivation.tvaRateValuePlaceholder')}
                              className="w-full rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                            />
                            <span className="text-xs font-bold text-slate-400">%</span>
                          </div>
                          <button
                            type="button"
                            onClick={() => removeTvaRate(i)}
                            disabled={form.tva_rates.length <= 1}
                            aria-label={t(locale, 'accountingActivation.removeTvaRate')}
                            className="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600 disabled:opacity-30"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      ))}
                    </div>
                    <button
                      type="button"
                      onClick={addTvaRate}
                      className="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-app-border px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-amber-300 hover:text-amber-600"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      {t(locale, 'accountingActivation.addTvaRate')}
                    </button>
                  </div>

                  <div>
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldNumberSeries')}
                    </span>
                    <p className="mb-2 text-xs text-slate-400">{t(locale, 'accountingActivation.fieldNumberSeriesHint')}</p>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      {DOCUMENT_TYPES.map((docType) => (
                        <label key={docType} className="flex items-center gap-2">
                          <span className="w-28 shrink-0 text-xs font-semibold text-slate-600">
                            {t(locale, `accountingActivation.series_${docType}`)}
                          </span>
                          <input
                            value={form.number_series[docType] ?? ''}
                            onChange={(e) => updateSeries(docType, e.target.value)}
                            placeholder={t(locale, 'accountingActivation.seriesPlaceholder')}
                            maxLength={20}
                            className="min-w-0 flex-1 rounded-xl border border-app-border bg-white px-3 py-2 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                          />
                        </label>
                      ))}
                    </div>
                  </div>
                </>
              )}

              {step === 3 && (
                <>
                  <label className="block">
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldTemplateStyle')}
                    </span>
                    <input
                      value={form.template_style}
                      onChange={(e) => setForm((f) => ({ ...f, template_style: e.target.value }))}
                      placeholder={t(locale, 'accountingActivation.fieldTemplateStylePlaceholder')}
                      maxLength={60}
                      className="w-full rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                    />
                  </label>

                  <label className="block">
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldPaymentTerms')}
                    </span>
                    <input
                      value={form.payment_terms}
                      onChange={(e) => setForm((f) => ({ ...f, payment_terms: e.target.value }))}
                      placeholder={t(locale, 'accountingActivation.fieldPaymentTermsPlaceholder')}
                      maxLength={60}
                      className="w-full rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                    />
                  </label>

                  <label className="block">
                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                      {t(locale, 'accountingActivation.fieldLegalMentions')}
                    </span>
                    <textarea
                      value={form.legal_mentions}
                      onChange={(e) => setForm((f) => ({ ...f, legal_mentions: e.target.value }))}
                      placeholder={t(locale, 'accountingActivation.fieldLegalMentionsPlaceholder')}
                      maxLength={2000}
                      rows={4}
                      className="w-full resize-y rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                    />
                  </label>
                </>
              )}

              {step === 4 && (
                <div className="space-y-4">
                  <div className="flex items-start gap-3 rounded-2xl border border-amber-100 bg-amber-50/60 px-5 py-4">
                    <Rocket className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <p className="text-sm text-amber-800">{t(locale, 'accountingActivation.wizardStep4Body')}</p>
                  </div>

                  <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl border border-app-border p-4">
                      <dt className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <Languages className="h-3.5 w-3.5" /> {t(locale, 'accountingActivation.fieldDocumentLanguage')}
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-slate-800">
                        {form.document_language === ''
                          ? t(locale, 'accountingActivation.fieldDefaultDerived')
                          : (LANGUAGES.find((l) => l.code === form.document_language)?.label ?? form.document_language)}
                      </dd>
                    </div>
                    <div className="rounded-2xl border border-app-border p-4">
                      <dt className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <Coins className="h-3.5 w-3.5" /> {t(locale, 'accountingActivation.fieldCurrency')}
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-slate-800">
                        {form.currency === '' ? t(locale, 'accountingActivation.fieldDefaultDerived') : form.currency}
                      </dd>
                    </div>
                    <div className="rounded-2xl border border-app-border p-4">
                      <dt className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <Percent className="h-3.5 w-3.5" /> {t(locale, 'accountingActivation.fieldTvaRates')}
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-slate-800">
                        {form.tva_rates.filter((r) => r.label.trim() !== '' && r.rate.trim() !== '').length === 0
                          ? t(locale, 'accountingActivation.fieldDefaultDerived')
                          : form.tva_rates
                              .filter((r) => r.label.trim() !== '' && r.rate.trim() !== '')
                              .map((r) => `${r.label} (${r.rate}%)`)
                              .join(', ')}
                      </dd>
                    </div>
                    <div className="rounded-2xl border border-app-border p-4">
                      <dt className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <ListOrdered className="h-3.5 w-3.5" /> {t(locale, 'accountingActivation.fieldNumberSeries')}
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-slate-800">
                        {DOCUMENT_TYPES.filter((d) => (form.number_series[d] ?? '').trim() !== '').length === 0
                          ? t(locale, 'accountingActivation.fieldDefaultDerived')
                          : DOCUMENT_TYPES.filter((d) => (form.number_series[d] ?? '').trim() !== '')
                              .map((d) => `${t(locale, `accountingActivation.series_${d}`)} : ${form.number_series[d]}`)
                              .join(' · ')}
                      </dd>
                    </div>
                    <div className="rounded-2xl border border-app-border p-4">
                      <dt className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <FileBadge2 className="h-3.5 w-3.5" /> {t(locale, 'accountingActivation.fieldTemplateStyle')}
                      </dt>
                      <dd className="mt-1 text-sm font-bold text-slate-800">
                        {form.template_style.trim() === '' ? t(locale, 'accountingActivation.fieldDefaultDerived') : form.template_style}
                      </dd>
                    </div>
                    <div className="rounded-2xl border border-app-border p-4">
                      <dt className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <ScrollText className="h-3.5 w-3.5" /> {t(locale, 'accountingActivation.fieldLegalMentions')}
                      </dt>
                      <dd className="mt-1 line-clamp-2 text-sm font-bold text-slate-800">
                        {form.legal_mentions.trim() === '' ? t(locale, 'accountingActivation.fieldDefaultDerived') : form.legal_mentions}
                      </dd>
                    </div>
                  </dl>
                </div>
              )}
            </div>
          </motion.section>

          {submitError && (
            <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700" role="alert">
              {submitError}
            </div>
          )}

          <div className="flex items-center justify-between gap-3">
            <button
              type="button"
              onClick={() => setStep((s) => Math.max(1, s - 1))}
              disabled={step === 1}
              className="inline-flex items-center gap-2 rounded-xl border border-app-border bg-white px-5 py-2.5 text-sm font-bold text-slate-600 transition hover:border-slate-300 disabled:opacity-40"
            >
              <ArrowLeft className="h-4 w-4" />
              {t(locale, 'accountingActivation.wizardBack')}
            </button>

            {step < 4 ? (
              <button
                type="button"
                onClick={() => setStep((s) => Math.min(4, s + 1))}
                className="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600"
              >
                {t(locale, 'accountingActivation.wizardNext')}
                <ArrowRight className="h-4 w-4" />
              </button>
            ) : (
              <button
                type="button"
                onClick={() => void completeActivation()}
                disabled={submitting}
                className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-600 disabled:opacity-60"
              >
                {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Rocket className="h-4 w-4" />}
                {submitting ? t(locale, 'accountingActivation.activating') : t(locale, 'accountingActivation.wizardFinish')}
              </button>
            )}
          </div>
        </div>
      )}
    </ModulePageShell>
  );
}
