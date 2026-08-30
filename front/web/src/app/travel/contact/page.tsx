'use client';

import { useCallback, useMemo, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { AlertTriangle, CheckCircle2, Send } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';

/**
 * TRAVEL-913 (#6425) — Formulaire de contact voyageurs PUBLIC.
 *
 * Page publique (hors dashboard, aucune session employé) : le tenant est
 * résolu par le lien signé expirable généré par l'agence
 * (`POST /travel/public-contact-link`, params `company_id` + `signature` +
 * `expires` dans l'URL). Le formulaire POSTe sur `/api/v1/travel/public/contact`
 * en propageant ces paramètres signés — impossible de forger un lien pour un
 * autre tenant. Consentement de contact explicite obligatoire (RGPD).
 */

type ContactPayload = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  message: string;
  consent_email: boolean;
};

export default function TravelPublicContactPage() {
  const searchParams = useSearchParams();
  const locale = getPreferredLocale();
  const dir = catalogDirection(locale);

  const signed = useMemo(() => {
    const companyId = searchParams.get('company_id');
    const signature = searchParams.get('signature');
    const expires = searchParams.get('expires');
    return { companyId, signature, expires, valid: Boolean(companyId && signature && expires) };
  }, [searchParams]);

  const [form, setForm] = useState<ContactPayload>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    message: '',
    consent_email: false,
  });
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const set = useCallback((key: keyof ContactPayload, value: string | boolean) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  }, []);

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    setFieldErrors({});

    if (!signed.valid) {
      setError(t(locale, 'travel.publicContact.invalidLink', "Lien de contact invalide ou expiré. Demandez un nouveau lien à l'agence."));
      return;
    }

    setSending(true);
    try {
      const query = new URLSearchParams({ company_id: signed.companyId as string, signature: signed.signature as string, expires: signed.expires as string });
      const response = await apiFetch(`/api/v1/travel/public/contact?${query.toString()}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        if (payload.errors) {
          setFieldErrors(Object.fromEntries(Object.entries(payload.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)])));
          return;
        }
        setError(payload.message || t(locale, 'travel.publicContact.sendFailed', "L'envoi a échoué. Réessayez."));
        return;
      }
      setSent(true);
    } catch {
      setError(t(locale, 'travel.publicContact.sendFailed', "L'envoi a échoué. Réessayez."));
    } finally {
      setSending(false);
    }
  }

  if (sent) {
    return (
      <main dir={dir} className="mx-auto flex min-h-[60vh] max-w-xl items-center px-4 py-16">
        <div className="w-full rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center dark:border-emerald-800 dark:bg-emerald-900/20">
          <CheckCircle2 className="mx-auto h-12 w-12 text-emerald-500" />
          <h1 className="mt-4 text-xl font-bold text-slate-900 dark:text-white">
            {t(locale, 'travel.publicContact.ackTitle', 'Demande bien reçue')}
          </h1>
          <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
            {t(locale, 'travel.publicContact.ackBody', "Merci ! L'agence vous répondra dans les meilleurs délais.")}
          </p>
        </div>
      </main>
    );
  }

  return (
    <main dir={dir} className="mx-auto max-w-xl px-4 py-16">
      <div className="mb-6 text-center">
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
          {t(locale, 'travel.publicContact.title', 'Contactez l’agence de voyage')}
        </h1>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {t(locale, 'travel.publicContact.subtitle', 'Une question sur vos trajets, réservations ou billets ? Écrivez-nous.')}
        </p>
      </div>

      {!signed.valid && (
        <div className="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200" role="alert">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
          <p>
            {t(locale, 'travel.publicContact.noLink', "Ce formulaire nécessite le lien de contact fourni par l'agence (lien signé).")}
          </p>
        </div>
      )}

      <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800" onSubmit={submit} noValidate>
        {error && (
          <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300" role="alert">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="first-name" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              {t(locale, 'travel.publicContact.firstName', 'Prénom')}
            </label>
            <input
              id="first-name"
              type="text"
              maxLength={120}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
              value={form.first_name}
              onChange={(e) => set('first_name', e.target.value)}
            />
          </div>
          <div>
            <label htmlFor="last-name" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              {t(locale, 'travel.publicContact.lastName', 'Nom')}
            </label>
            <input
              id="last-name"
              type="text"
              maxLength={120}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
              value={form.last_name}
              onChange={(e) => set('last_name', e.target.value)}
            />
          </div>
        </div>

        <div>
          <label htmlFor="email" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
            {t(locale, 'travel.publicContact.email', 'Email')} *
          </label>
          <input
            id="email"
            type="email"
            required
            maxLength={190}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
            value={form.email}
            onChange={(e) => set('email', e.target.value)}
          />
          {fieldErrors.email && <p className="mt-1 text-xs text-red-600">{fieldErrors.email}</p>}
        </div>

        <div>
          <label htmlFor="phone" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
            {t(locale, 'travel.publicContact.phone', 'Téléphone')}
          </label>
          <input
            id="phone"
            type="tel"
            maxLength={40}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
            value={form.phone}
            onChange={(e) => set('phone', e.target.value)}
          />
        </div>

        <div>
          <label htmlFor="message" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
            {t(locale, 'travel.publicContact.message', 'Message')} *
          </label>
          <textarea
            id="message"
            required
            rows={4}
            maxLength={2000}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
            value={form.message}
            onChange={(e) => set('message', e.target.value)}
          />
          {fieldErrors.message && <p className="mt-1 text-xs text-red-600">{fieldErrors.message}</p>}
        </div>

        <label className="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
          <input
            type="checkbox"
            required
            className="mt-0.5 h-4 w-4 rounded border-slate-300"
            checked={form.consent_email}
            onChange={(e) => set('consent_email', e.target.checked)}
          />
          <span>
            {t(locale, 'travel.publicContact.consent', "J'accepte d'être contacté(e) par email au sujet de ma demande.")} *
          </span>
        </label>
        {fieldErrors.consent_email && <p className="text-xs text-red-600">{fieldErrors.consent_email}</p>}

        <button
          type="submit"
          disabled={sending}
          className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
        >
          <Send className="h-4 w-4" />
          {sending
            ? t(locale, 'travel.publicContact.sending', 'Envoi…')
            : t(locale, 'travel.publicContact.send', 'Envoyer la demande')}
        </button>
      </form>
    </main>
  );
}
