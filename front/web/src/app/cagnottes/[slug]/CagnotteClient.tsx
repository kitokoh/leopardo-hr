'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  CheckCircle2,
  Copy,
  Check,
  Gift,
  HeartHandshake,
  Loader2,
  MessageSquare,
  QrCode,
  Share2,
  Smartphone,
  CreditCard,
  Banknote,
  Landmark,
  Users,
} from 'lucide-react';
import QRCode from 'qrcode';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';

/**
 * FUND-100 (#8402) — Page publique d'une cagnotte solidaire.
 *
 * Surface publique (hors dashboard, aucune session) adossée à l'API publique
 * isolée `throttle:shop-public` :
 *   GET  /public/fundraisers/{slug}            → DTO zéro donnée interne (RGPD)
 *   GET  /public/fundraisers/{slug}/supporters → mur des soutiens (si activé)
 *   POST /public/fundraisers/{slug}/contribute → initiation (honeypot inclus)
 *   GET  /public/contributions/{reference}     → polling de statut
 *
 * Parcours : découverte (titre, bénéficiaire, progression) → don (montants
 * suggérés ou libre, méthodes offertes par le paramétrage tenant) →
 * redirection Stripe Checkout (carte) ou instructions mobile money (USSD
 * sandbox/production) + polling de confirmation → remerciement.
 * Partage : copie du lien + QR code (partage OG via generateMetadata).
 */

type PaymentMethod = 'card' | 'mobile_money' | 'bank_transfer' | 'cash';

type PublicFundraiser = {
  slug: string;
  title: string;
  description: string | null;
  beneficiary_name: string | null;
  category: string;
  category_label: string;
  goal_amount: number | null;
  collected_amount: number;
  progress_percent: number | null;
  contributions_count: number;
  currency: string;
  suggested_amounts: number[] | null;
  min_amount: number | null;
  max_amount: number | null;
  cover_image_path: string | null;
  status: string;
  accepts_contributions: boolean;
  ends_at: string | null;
  published_at: string | null;
  public_path: string;
  enabled_methods: string[];
  allow_anonymous: boolean;
  allow_messages: boolean;
  show_supporters: boolean;
};

type Supporter = {
  name: string;
  amount: number | null;
  currency: string | null;
  message: string | null;
  paid_at: string | null;
};

type ContributionInitiation = {
  reference: string;
  amount: number;
  currency: string;
  payment_method: PaymentMethod;
  status: string;
  payment: {
    provider_reference: string;
    redirect_url: string | null;
    ussd_code: string | null;
    instructions: string | null;
    status: string;
  };
  // Fourni par l'API mais inutilisé côté front : contient le préfixe `/api/v1`
  // qu'apiFetch ajoute déjà — le polling reconstruit le chemin (voir plus bas).
  status_url: string;
};

type ContributionStatus = {
  reference: string;
  status: 'pending' | 'completed' | 'failed' | 'refunded';
  paid_at: string | null;
};

type Step =
  | { kind: 'loading' }
  | { kind: 'not_found' }
  | { kind: 'ready'; fundraiser: PublicFundraiser }
  | { kind: 'pending'; fundraiser: PublicFundraiser; initiation: ContributionInitiation }
  | { kind: 'success'; fundraiser: PublicFundraiser; reference: string }
  | { kind: 'failed'; fundraiser: PublicFundraiser; reference: string };

function money(amount: number, currency: string, locale: string): string {
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency, maximumFractionDigits: 2 }).format(amount);
  } catch {
    return `${amount.toFixed(2)} ${currency}`;
  }
}

const METHOD_ICONS: Record<PaymentMethod, typeof CreditCard> = {
  card: CreditCard,
  mobile_money: Smartphone,
  bank_transfer: Landmark,
  cash: Banknote,
};

const POLL_INTERVAL_MS = 4000;
const POLL_TIMEOUT_MS = 120000;

export default function CagnotteClient({ slug }: { slug: string }) {
  const locale = getPreferredLocale();
  const dir = catalogDirection(locale);

  const [step, setStep] = useState<Step>({ kind: 'loading' });
  const [supporters, setSupporters] = useState<Supporter[] | null>(null);
  const [amount, setAmount] = useState<number | null>(null);
  const [customAmount, setCustomAmount] = useState('');
  const [method, setMethod] = useState<PaymentMethod>('card');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [anonymous, setAnonymous] = useState(false);
  const [message, setMessage] = useState('');
  const [honeypot, setHoneypot] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const pollTimer = useRef<ReturnType<typeof setInterval> | null>(null);

  const stopPolling = useCallback(() => {
    if (pollTimer.current !== null) {
      clearInterval(pollTimer.current);
      pollTimer.current = null;
    }
  }, []);

  useEffect(() => stopPolling, [stopPolling]);

  // Chargement de la cagnotte + mur des soutiens.
  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const response = await apiFetch(`/public/fundraisers/${encodeURIComponent(slug)}`);
        const payload = (await response.json()) as { data: PublicFundraiser };
        if (cancelled) return;
        const fundraiser = payload.data;
        setStep({ kind: 'ready', fundraiser });
        const offered = (fundraiser.enabled_methods ?? []).filter((m): m is PaymentMethod =>
          ['card', 'mobile_money', 'bank_transfer', 'cash'].includes(m),
        );
        if (offered.length > 0) setMethod(offered[0]);
        const suggested = fundraiser.suggested_amounts ?? [];
        if (suggested.length > 0) setAmount(suggested[0]);

        if (fundraiser.show_supporters) {
          try {
            const supportersResponse = await apiFetch(`/public/fundraisers/${encodeURIComponent(slug)}/supporters`);
            const supportersPayload = (await supportersResponse.json()) as { data: Supporter[] };
            if (!cancelled) setSupporters(supportersPayload.data);
          } catch {
            if (!cancelled) setSupporters([]);
          }
        }
      } catch {
        if (!cancelled) setStep({ kind: 'not_found' });
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [slug]);

  // QR code de partage (lien courant).
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const url = window.location.href;
    QRCode.toDataURL(url, { width: 320, margin: 1 })
      .then((dataUrl: string) => setQrDataUrl(dataUrl))
      .catch(() => setQrDataUrl(null));
  }, []);

  const fundraiser = step.kind === 'ready' || step.kind === 'pending' || step.kind === 'success' || step.kind === 'failed'
    ? step.fundraiser
    : null;

  const effectiveAmount = useMemo(() => {
    if (customAmount.trim() !== '') {
      const parsed = Number(customAmount.replace(',', '.'));
      return Number.isFinite(parsed) ? parsed : null;
    }
    return amount;
  }, [amount, customAmount]);

  const startPolling = useCallback(
    (initiation: ContributionInitiation, current: PublicFundraiser) => {
      stopPolling();
      const startedAt = Date.now();
      pollTimer.current = setInterval(() => {
        void (async () => {
          if (Date.now() - startedAt > POLL_TIMEOUT_MS) {
            stopPolling();
            return; // keep the pending screen visible on slow operator confirmations
          }
          try {
            // Le backend renvoie `status_url` AVEC le préfixe `/api/v1` —
            // l'injecter tel quel dans apiFetch produirait
            // `/api/v1/api/v1/public/contributions/…` (404 avalé par le catch,
            // écran d'attente jamais résolu). Comme shop/page.tsx, on
            // reconstruit le chemin SANS préfixe depuis la référence.
            const response = await apiFetch(`/public/contributions/${encodeURIComponent(initiation.reference)}`, { _cacheBust: true });
            const payload = (await response.json()) as { data: ContributionStatus };
            if (payload.data.status === 'completed') {
              stopPolling();
              setStep({ kind: 'success', fundraiser: current, reference: initiation.reference });
            } else if (payload.data.status === 'failed') {
              stopPolling();
              setStep({ kind: 'failed', fundraiser: current, reference: initiation.reference });
            }
          } catch {
            // Une erreur réseau transitoire ne casse pas le polling.
          }
        })();
      }, POLL_INTERVAL_MS);
    },
    [stopPolling],
  );

  const submit = useCallback(async () => {
    if (!fundraiser || effectiveAmount === null || submitting) return;
    setSubmitting(true);
    setError(null);

    try {
      const response = await apiFetch(`/public/fundraisers/${encodeURIComponent(slug)}/contribute`, {
        method: 'POST',
        body: JSON.stringify({
          amount: effectiveAmount,
          payment_method: method,
          contributor_name: name.trim() !== '' ? name.trim() : null,
          contributor_email: email.trim() !== '' ? email.trim() : null,
          contributor_phone: method === 'mobile_money' ? phone.trim() : null,
          is_anonymous: anonymous,
          message: message.trim() !== '' ? message.trim() : null,
          website: honeypot, // honeypot — toujours vide pour un humain
        }),
      });
      const payload = (await response.json()) as { data: ContributionInitiation };
      const initiation = payload.data;

      if (initiation.payment.redirect_url) {
        window.location.assign(initiation.payment.redirect_url);
        return;
      }
      if (initiation.status === 'completed') {
        setStep({ kind: 'success', fundraiser, reference: initiation.reference });
        return;
      }
      setStep({ kind: 'pending', fundraiser, initiation });
      startPolling(initiation, fundraiser);
    } catch (exception) {
      const apiMessage = (exception as { message?: string }).message;
      setError(apiMessage && apiMessage !== '' ? apiMessage : t(locale, 'cagnotte.errors.generic'));
    } finally {
      setSubmitting(false);
    }
  }, [anonymous, effectiveAmount, email, fundraiser, honeypot, locale, message, method, name, phone, slug, startPolling, submitting]);

  const copyLink = useCallback(() => {
    if (typeof window === 'undefined') return;
    void navigator.clipboard?.writeText(window.location.href).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }, []);

  if (step.kind === 'loading') {
    return (
      <main dir={dir} className="flex min-h-screen items-center justify-center bg-slate-50">
        <Loader2 className="h-8 w-8 animate-spin text-teal-600" aria-label={t(locale, 'cagnotte.loading')} />
      </main>
    );
  }

  if (step.kind === 'not_found') {
    return (
      <main dir={dir} className="flex min-h-screen flex-col items-center justify-center gap-3 bg-slate-50 px-4 text-center">
        <Gift className="h-10 w-10 text-slate-300" />
        <h1 className="text-xl font-black text-slate-950">{t(locale, 'cagnotte.notFound.title')}</h1>
        <p className="max-w-md text-sm text-slate-500">{t(locale, 'cagnotte.notFound.body')}</p>
      </main>
    );
  }

  if (!fundraiser) return null;

  return (
    <main dir={dir} className="min-h-screen bg-slate-50 pb-16">
      <div className="mx-auto max-w-2xl px-4 pt-8">
        {/* En-tête cagnotte */}
        <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
          {fundraiser.cover_image_path && (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={fundraiser.cover_image_path} alt="" className="h-48 w-full object-cover" />
          )}
          <div className="p-6">
            <div className="mb-2 flex items-center gap-2 text-xs font-bold text-teal-700">
              <HeartHandshake className="h-4 w-4" />
              <span>{fundraiser.category_label}</span>
            </div>
            <h1 className="text-2xl font-black tracking-tight text-slate-950">{fundraiser.title}</h1>
            {fundraiser.beneficiary_name && (
              <p className="mt-1 text-sm text-slate-500">
                {t(locale, 'cagnotte.for')} <span className="font-bold text-slate-700">{fundraiser.beneficiary_name}</span>
              </p>
            )}
            {fundraiser.description && <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{fundraiser.description}</p>}

            {/* Progression */}
            <div className="mt-5">
              <div className="flex items-end justify-between gap-2">
                <p className="text-lg font-black text-slate-950">
                  {money(fundraiser.collected_amount, fundraiser.currency, locale)}
                  {fundraiser.goal_amount !== null && (
                    <span className="text-sm font-bold text-slate-400"> / {money(fundraiser.goal_amount, fundraiser.currency, locale)}</span>
                  )}
                </p>
                <p className="flex items-center gap-1 text-xs font-bold text-slate-500">
                  <Users className="h-3.5 w-3.5" />
                  {fundraiser.contributions_count} {t(locale, 'cagnotte.contributors')}
                </p>
              </div>
              {fundraiser.progress_percent !== null && (
                <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full rounded-full bg-teal-500 transition-all" style={{ width: `${Math.min(100, fundraiser.progress_percent)}%` }} />
                </div>
              )}
            </div>

            {/* Partage */}
            <div className="mt-5 flex items-center gap-2">
              <button
                type="button"
                onClick={copyLink}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm hover:border-teal-300"
              >
                {copied ? <Check className="h-3.5 w-3.5 text-teal-600" /> : <Copy className="h-3.5 w-3.5" />}
                {copied ? t(locale, 'cagnotte.share.copied') : t(locale, 'cagnotte.share.copy')}
              </button>
              {qrDataUrl && (
                <details className="relative">
                  <summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm hover:border-teal-300">
                    <QrCode className="h-3.5 w-3.5" />
                    {t(locale, 'cagnotte.share.qr')}
                  </summary>
                  <div className="absolute z-10 mt-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={qrDataUrl} alt={t(locale, 'cagnotte.share.qrAlt')} className="h-40 w-40" />
                  </div>
                </details>
              )}
              <span className="ms-auto inline-flex items-center gap-1 text-xs font-bold text-slate-400">
                <Share2 className="h-3 w-3" />
                {t(locale, 'cagnotte.share.hint')}
              </span>
            </div>
          </div>
        </div>

        {/* États du don */}
        {step.kind === 'success' && (
          <div className="mt-6 rounded-3xl border border-teal-200 bg-teal-50 p-6 text-center">
            <CheckCircle2 className="mx-auto h-10 w-10 text-teal-600" />
            <h2 className="mt-3 text-lg font-black text-slate-950">{t(locale, 'cagnotte.success.title')}</h2>
            <p className="mt-1 text-sm text-slate-600">{t(locale, 'cagnotte.success.body')}</p>
            <p className="mt-2 text-xs font-bold text-slate-400">{t(locale, 'cagnotte.reference')} : {step.reference}</p>
          </div>
        )}

        {step.kind === 'failed' && (
          <div className="mt-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-center">
            <h2 className="text-lg font-black text-slate-950">{t(locale, 'cagnotte.failed.title')}</h2>
            <p className="mt-1 text-sm text-slate-600">{t(locale, 'cagnotte.failed.body')}</p>
            <button
              type="button"
              onClick={() => setStep({ kind: 'ready', fundraiser })}
              className="mt-4 rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white"
            >
              {t(locale, 'cagnotte.failed.retry')}
            </button>
          </div>
        )}

        {step.kind === 'pending' && (
          <div className="mt-6 rounded-3xl border border-amber-200 bg-amber-50 p-6">
            <div className="flex items-center gap-2">
              <Loader2 className="h-5 w-5 animate-spin text-amber-600" />
              <h2 className="text-lg font-black text-slate-950">{t(locale, 'cagnotte.pending.title')}</h2>
            </div>
            {step.initiation.payment.ussd_code && (
              <p className="mt-3 rounded-2xl bg-white px-4 py-3 text-center text-xl font-black tracking-widest text-slate-950">
                {step.initiation.payment.ussd_code}
              </p>
            )}
            {step.initiation.payment.instructions && (
              <p className="mt-3 text-sm leading-relaxed text-slate-700">{step.initiation.payment.instructions}</p>
            )}
            <p className="mt-3 text-xs text-slate-500">{t(locale, 'cagnotte.pending.hint')}</p>
          </div>
        )}

        {step.kind === 'ready' && fundraiser.accepts_contributions && (
          <div className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-black text-slate-950">{t(locale, 'cagnotte.form.title')}</h2>

            {/* Montants */}
            <div className="mt-4">
              <label className="text-xs font-bold uppercase tracking-wide text-slate-500">{t(locale, 'cagnotte.form.amount')}</label>
              {(fundraiser.suggested_amounts ?? []).length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                  {(fundraiser.suggested_amounts ?? []).map((value) => (
                    <button
                      key={value}
                      type="button"
                      onClick={() => { setAmount(value); setCustomAmount(''); }}
                      className={`rounded-xl px-4 py-2 text-sm font-bold shadow-sm ${amount === value && customAmount === '' ? 'bg-teal-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:border-teal-300'}`}
                    >
                      {money(value, fundraiser.currency, locale)}
                    </button>
                  ))}
                </div>
              )}
              <input
                type="text"
                inputMode="decimal"
                value={customAmount}
                onChange={(event) => setCustomAmount(event.target.value)}
                placeholder={t(locale, 'cagnotte.form.customAmount')}
                className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-950 outline-none focus:border-teal-400"
              />
              {(fundraiser.min_amount !== null || fundraiser.max_amount !== null) && (
                <p className="mt-1 text-xs text-slate-400">
                  {fundraiser.min_amount !== null && `${t(locale, 'cagnotte.form.min')} ${money(fundraiser.min_amount, fundraiser.currency, locale)}`}
                  {fundraiser.min_amount !== null && fundraiser.max_amount !== null && ' · '}
                  {fundraiser.max_amount !== null && `${t(locale, 'cagnotte.form.max')} ${money(fundraiser.max_amount, fundraiser.currency, locale)}`}
                </p>
              )}
            </div>

            {/* Méthodes */}
            <div className="mt-5">
              <label className="text-xs font-bold uppercase tracking-wide text-slate-500">{t(locale, 'cagnotte.form.method')}</label>
              <div className="mt-2 grid grid-cols-2 gap-2">
                {(fundraiser.enabled_methods as PaymentMethod[]).filter((m) => ['card', 'mobile_money', 'bank_transfer', 'cash'].includes(m)).map((m) => {
                  const Icon = METHOD_ICONS[m];
                  return (
                    <button
                      key={m}
                      type="button"
                      onClick={() => setMethod(m)}
                      className={`flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold shadow-sm ${method === m ? 'bg-teal-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:border-teal-300'}`}
                    >
                      <Icon className="h-4 w-4" />
                      {t(locale, `cagnotte.methods.${m}`, m)}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Coordonnées */}
            <div className="mt-5 grid gap-3">
              <input
                type="text"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder={t(locale, 'cagnotte.form.name')}
                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 outline-none focus:border-teal-400"
              />
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder={t(locale, 'cagnotte.form.email')}
                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 outline-none focus:border-teal-400"
              />
              {method === 'mobile_money' && (
                <input
                  type="tel"
                  value={phone}
                  onChange={(event) => setPhone(event.target.value)}
                  placeholder={t(locale, 'cagnotte.form.phone')}
                  className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 outline-none focus:border-teal-400"
                />
              )}
              {fundraiser.allow_anonymous && (
                <label className="flex items-center gap-2 text-sm text-slate-600">
                  <input type="checkbox" checked={anonymous} onChange={(event) => setAnonymous(event.target.checked)} className="h-4 w-4 rounded border-slate-300" />
                  {t(locale, 'cagnotte.form.anonymous')}
                </label>
              )}
              {fundraiser.allow_messages && (
                <div className="relative">
                  <MessageSquare className="pointer-events-none absolute start-3 top-3 h-4 w-4 text-slate-300" />
                  <textarea
                    value={message}
                    onChange={(event) => setMessage(event.target.value)}
                    placeholder={t(locale, 'cagnotte.form.message')}
                    rows={2}
                    maxLength={500}
                    className="w-full rounded-xl border border-slate-200 py-2.5 pe-4 ps-10 text-sm text-slate-950 outline-none focus:border-teal-400"
                  />
                </div>
              )}
              {/* Honeypot anti-bot : invisible, toujours vide pour un humain. */}
              <input
                type="text"
                name="website"
                value={honeypot}
                onChange={(event) => setHoneypot(event.target.value)}
                tabIndex={-1}
                autoComplete="off"
                aria-hidden="true"
                className="absolute left-[-9999px] h-0 w-0 opacity-0"
              />
            </div>

            {error && <p className="mt-3 rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-700">{error}</p>}

            <button
              type="button"
              onClick={() => void submit()}
              disabled={submitting || effectiveAmount === null || effectiveAmount <= 0}
              className="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-teal-600 px-4 py-3.5 text-base font-black text-white shadow-lg shadow-teal-600/20 transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
            >
              {submitting && <Loader2 className="h-5 w-5 animate-spin" />}
              {effectiveAmount !== null && effectiveAmount > 0
                ? `${t(locale, 'cagnotte.form.submit')} ${money(effectiveAmount, fundraiser.currency, locale)}`
                : t(locale, 'cagnotte.form.submit')}
            </button>
          </div>
        )}

        {step.kind === 'ready' && !fundraiser.accepts_contributions && (
          <div className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 text-center text-sm font-bold text-slate-500">
            {t(locale, 'cagnotte.ended', 'Cette cagnotte n\'accepte plus de contributions pour le moment.')}
          </div>
        )}

        {/* Mur des soutiens */}
        {fundraiser.show_supporters && supporters !== null && supporters.length > 0 && (
          <div className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-black text-slate-950">{t(locale, 'cagnotte.supporters.title')}</h2>
            <ul className="mt-4 space-y-3">
              {supporters.map((supporter, index) => (
                <li key={index} className="rounded-2xl bg-slate-50 px-4 py-3">
                  <p className="text-sm font-bold text-slate-950">
                    {supporter.name}
                    {supporter.amount !== null && supporter.currency !== null && (
                      <span className="ms-2 text-teal-700">{money(supporter.amount, supporter.currency, locale)}</span>
                    )}
                  </p>
                  {supporter.message && <p className="mt-1 text-sm text-slate-600">{supporter.message}</p>}
                </li>
              ))}
            </ul>
          </div>
        )}

        <p className="mt-8 text-center text-xs font-bold text-slate-300">{t(locale, 'cagnotte.poweredBy')}</p>
      </div>
    </main>
  );
}
