'use client';

import { useState } from 'react';
import { AlertTriangle, CheckCircle2, Loader2, Send, X } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { catalogDirection, t } from '@/lib/i18n/locale-catalog';
import {
  CONTACT_MESSAGE_MAX,
  CONTACT_MESSAGE_MIN,
  CONTACT_NAME_MAX,
  CONTACT_NAME_MIN,
  createIdempotencyKey,
  submitPublicContact,
  type PublicContactResult,
} from '@/lib/public-contact-api';

/**
 * SUPERAPP-P1 (#8760) — formulaire « Contacter ce professionnel ».
 *
 * Ouvert depuis un résultat de `/decouvrir`. Aucune authentification : le
 * message part par le contrat public `POST /api/v1/public/contact-requests`,
 * qui route vers le tenant du professionnel et crée un lead CRM + une
 * notification côté Business OS.
 *
 * Choix assumés :
 *  - l'identifiant du destinataire est EXCLUSIVEMENT le couple
 *    (`target_type`, `target_slug`) déjà visible dans `/decouvrir` — aucun
 *    `company_id` ne transite par le navigateur ;
 *  - les états sont explicites : envoi, succès avec référence, profil
 *    indisponible (404 uniforme), erreur réseau avec retry — jamais de faux
 *    succès optimiste ;
 *  - `idempotency_key` est créée À L'OUVERTURE et conservée pendant les
 *    retries : un double-clic ne crée pas deux demandes.
 */

type FormState = 'idle' | 'sending' | 'sent' | 'unavailable' | 'error';

interface ContactDialogProps {
  locale: AppLocale;
  targetType: string;
  targetSlug: string;
  targetName: string;
  onClose: () => void;
}

export default function ContactDialog({
  locale,
  targetType,
  targetSlug,
  targetName,
  onClose,
}: ContactDialogProps) {
  const dir = catalogDirection(locale);

  const [state, setState] = useState<FormState>('idle');
  const [reference, setReference] = useState<string>('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState('');
  const [consent, setConsent] = useState(false);

  // Stable pour toute la durée de la modale : les retries réutilisent la même
  // clé, donc la même demande (idempotence côté serveur).
  const [idempotencyKey] = useState(() => createIdempotencyKey());

  const handleResult = (result: PublicContactResult) => {
    if (result.status === 'sent') {
      setReference(result.reference);
      setState('sent');
      return;
    }
    if (result.status === 'unavailable') {
      setState('unavailable');
      return;
    }
    if (result.status === 'invalid') {
      setFieldErrors(result.fields ?? {});
      setState('idle');
      return;
    }
    setState('error');
  };

  const onSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (state === 'sending') return;

    setFieldErrors({});
    setState('sending');

    const result = await submitPublicContact(
      {
        target_type: targetType,
        target_slug: targetSlug,
        sender_name: name.trim(),
        sender_email: email.trim(),
        sender_phone: phone.trim() ? phone.trim() : null,
        message: message.trim(),
        consent,
        // Honeypot toujours vide : rempli uniquement par un bot.
        company_website: '',
      },
      idempotencyKey,
    );

    handleResult(result);
  };

  return (
    <div
      dir={dir}
      role="dialog"
      aria-modal="true"
      aria-labelledby="contact-dialog-title"
      className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 p-4 sm:items-center"
    >
      <div className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 id="contact-dialog-title" className="text-lg font-bold text-slate-950">
              {t(locale, 'discovery.contactTitle')}
            </h2>
            <p className="mt-0.5 text-sm text-slate-500">{targetName}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={t(locale, 'discovery.contactClose')}
            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
          >
            <X className="h-5 w-5" aria-hidden />
          </button>
        </div>

        {state === 'sent' ? (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
            <CheckCircle2 className="mx-auto mb-2 h-6 w-6 text-emerald-600" aria-hidden />
            <p className="text-sm font-semibold text-emerald-900">
              {t(locale, 'discovery.contactSent')}
            </p>
            <p className="mt-1 break-all text-xs text-emerald-800">
              {t(locale, 'discovery.contactReference')} : {reference}
            </p>
            <button
              type="button"
              onClick={onClose}
              className="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
            >
              {t(locale, 'discovery.contactClose')}
            </button>
          </div>
        ) : state === 'unavailable' ? (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-center">
            <AlertTriangle className="mx-auto mb-2 h-6 w-6 text-amber-600" aria-hidden />
            <p className="text-sm font-semibold text-amber-900">
              {t(locale, 'discovery.contactNotAvailable')}
            </p>
            <button
              type="button"
              onClick={onClose}
              className="mt-4 rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-900"
            >
              {t(locale, 'discovery.contactClose')}
            </button>
          </div>
        ) : (
          <form onSubmit={onSubmit} className="grid gap-3">
            <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
              {t(locale, 'discovery.contactName')}
              <input
                type="text"
                required
                minLength={CONTACT_NAME_MIN}
                maxLength={CONTACT_NAME_MAX}
                value={name}
                onChange={(event) => setName(event.target.value)}
                autoComplete="name"
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900"
              />
            </label>

            <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
              {t(locale, 'discovery.contactEmail')}
              <input
                type="email"
                required
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900"
              />
            </label>

            <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
              {t(locale, 'discovery.contactPhone')}
              <input
                type="tel"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                autoComplete="tel"
                maxLength={40}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900"
              />
            </label>

            <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
              {t(locale, 'discovery.contactMessage')}
              <textarea
                required
                rows={4}
                minLength={CONTACT_MESSAGE_MIN}
                maxLength={CONTACT_MESSAGE_MAX}
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900"
              />
            </label>

            {/* Honeypot : invisible et hors du flux d'accessibilité. */}
            <input
              type="text"
              name="company_website"
              tabIndex={-1}
              autoComplete="off"
              aria-hidden
              className="hidden"
            />

            <label className="flex items-start gap-2 text-xs text-slate-600">
              <input
                type="checkbox"
                required
                checked={consent}
                onChange={(event) => setConsent(event.target.checked)}
                className="mt-0.5"
              />
              <span>{t(locale, 'discovery.contactConsent')}</span>
            </label>

            {Object.keys(fieldErrors).length > 0 && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800">
                {t(locale, 'discovery.contactInvalid')}
              </p>
            )}

            {state === 'error' && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800">
                {t(locale, 'discovery.contactError')}
              </p>
            )}

            <div className="mt-1 flex justify-end gap-2">
              <button
                type="button"
                onClick={onClose}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
              >
                {t(locale, 'discovery.contactCancel')}
              </button>
              <button
                type="submit"
                disabled={state === 'sending'}
                className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              >
                {state === 'sending' ? (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                ) : (
                  <Send className="h-4 w-4" aria-hidden />
                )}
                {state === 'sending'
                  ? t(locale, 'discovery.contactSending')
                  : t(locale, 'discovery.contactSend')}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
