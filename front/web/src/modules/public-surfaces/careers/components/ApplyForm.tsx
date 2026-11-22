'use client';

/**
 * Formulaire de candidature — verticale Emploi (#9629).
 *
 * Repris de `front/web/src/app/[companySlug]/careers/jobs/[jobId]/ApplyForm.tsx`.
 * Îlot client : c'est le seul endroit de la verticale qui a besoin d'état.
 *
 * ## Ce que le formulaire ne fait pas
 *
 * Il n'invente aucun succès. Tant que l'API n'a pas répondu 201, l'écran
 * affiche « envoi en cours » ; un 409 dit « vous avez déjà postulé », un 422
 * remonte le message du serveur, une panne réseau dit qu'elle a échoué. Aucun
 * « candidature envoyée » optimiste.
 *
 * ## Anti-spam
 *
 * Le champ `website` est un piège : il est masqué en CSS et `tabIndex={-1}`,
 * donc un humain ne le remplit jamais. Le backend le déclare `prohibited` —
 * toute valeur non vide fait rejeter la candidature.
 */

import { useState } from 'react';

import type { SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import {
  isAcceptableResume,
  validateApplicationDraft,
  type ApplicationDraft,
} from '../domain/application';
import { submitApplication } from '../infrastructure/careers-gateway';

type Phase = 'idle' | 'sending' | 'sent' | 'already' | 'rejected' | 'failed';

const EMPTY_DRAFT: ApplicationDraft = {
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  coverLetter: '',
  consent: false,
};

export function ApplyForm({
  identity,
  companySlug,
  jobId,
  locale,
}: {
  identity: SurfaceIdentity;
  companySlug: string;
  jobId: number;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  const [draft, setDraft] = useState<ApplicationDraft>(EMPTY_DRAFT);
  const [resume, setResume] = useState<File | null>(null);
  const [phase, setPhase] = useState<Phase>('idle');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [serverMessage, setServerMessage] = useState<string | null>(null);
  const [reference, setReference] = useState<string | null>(null);

  const field = (name: keyof ApplicationDraft) => ({
    value: String(draft[name]),
    onChange: (
      event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
    ): void => {
      const value =
        event.target instanceof HTMLInputElement && event.target.type === 'checkbox'
          ? event.target.checked
          : event.target.value;

      setDraft((current) => ({ ...current, [name]: value }));
    },
  });

  async function onSubmit(event: React.FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    const validation = validateApplicationDraft(draft);

    if (resume !== null && !isAcceptableResume(resume)) {
      validation.resume = 'invalid';
    }

    setErrors(validation);

    if (Object.keys(validation).length > 0) {
      return;
    }

    setPhase('sending');
    setServerMessage(null);

    const outcome = await submitApplication(companySlug, jobId, { ...draft, resume });

    switch (outcome.kind) {
      case 'created':
        setReference(outcome.reference);
        setPhase('sent');
        break;
      case 'already-applied':
        setPhase('already');
        break;
      case 'rejected':
        setServerMessage(outcome.message);
        setPhase('rejected');
        break;
      default:
        setPhase('failed');
    }
  }

  const inputStyle: React.CSSProperties = {
    width: '100%',
    padding: '0.6rem 0.7rem',
    borderRadius: '0.5rem',
    border: `1px solid ${identity.palette.muted}55`,
    fontSize: '0.9rem',
    fontFamily: 'inherit',
    background: '#FFFFFF',
    color: identity.palette.onSurface,
  };

  const labelStyle: React.CSSProperties = {
    display: 'block',
    fontSize: '0.78rem',
    fontWeight: 700,
    marginBottom: '0.25rem',
    color: identity.palette.muted,
  };

  if (phase === 'sent') {
    return (
      <div
        role="status"
        style={{
          padding: '1.1rem 1.25rem',
          borderRadius: '0.8rem',
          border: `1px solid ${identity.palette.primary}44`,
          background: `${identity.palette.accent}14`,
        }}
      >
        <strong style={{ display: 'block', fontSize: '0.95rem' }}>{shared.applicationSent}</strong>
        {reference ? (
          <p style={{ margin: '0.4rem 0 0', fontSize: '0.85rem', fontFamily: 'monospace' }}>
            {reference}
          </p>
        ) : null}
        <p style={{ margin: '0.4rem 0 0', fontSize: '0.82rem', color: identity.palette.muted }}>
          {shared.applicationSentHint}
        </p>
      </div>
    );
  }

  return (
    <form
      onSubmit={onSubmit}
      noValidate
      style={{
        display: 'grid',
        gap: '0.85rem',
        padding: '1.25rem',
        borderRadius: '0.9rem',
        border: `1px solid ${identity.palette.muted}33`,
        background: '#FFFFFF',
      }}
    >
      <div style={{ display: 'grid', gap: '0.75rem', gridTemplateColumns: '1fr 1fr' }}>
        <div>
          <label style={labelStyle} htmlFor="apply-first-name">
            {shared.firstName}
          </label>
          <input
            id="apply-first-name"
            style={inputStyle}
            autoComplete="given-name"
            aria-invalid={errors.firstName ? true : undefined}
            {...field('firstName')}
          />
        </div>

        <div>
          <label style={labelStyle} htmlFor="apply-last-name">
            {shared.lastName}
          </label>
          <input
            id="apply-last-name"
            style={inputStyle}
            autoComplete="family-name"
            aria-invalid={errors.lastName ? true : undefined}
            {...field('lastName')}
          />
        </div>
      </div>

      <div>
        <label style={labelStyle} htmlFor="apply-email">
          {shared.emailLabel}
        </label>
        <input
          id="apply-email"
          type="email"
          style={inputStyle}
          autoComplete="email"
          aria-invalid={errors.email ? true : undefined}
          {...field('email')}
        />
      </div>

      <div>
        <label style={labelStyle} htmlFor="apply-phone">
          {shared.phoneLabel}
        </label>
        <input
          id="apply-phone"
          type="tel"
          style={inputStyle}
          autoComplete="tel"
          {...field('phone')}
        />
      </div>

      <div>
        <label style={labelStyle} htmlFor="apply-cover">
          {shared.coverLetterLabel}
        </label>
        <textarea
          id="apply-cover"
          rows={5}
          style={{ ...inputStyle, resize: 'vertical' }}
          {...field('coverLetter')}
        />
      </div>

      <div>
        <label style={labelStyle} htmlFor="apply-resume">
          {shared.resumeLabel}
        </label>
        <input
          id="apply-resume"
          type="file"
          accept=".pdf,.doc,.docx"
          onChange={(event) => setResume(event.target.files?.[0] ?? null)}
          style={{ fontSize: '0.85rem' }}
          aria-invalid={errors.resume ? true : undefined}
        />
      </div>

      {/* Champ piège anti-spam : masqué, non focusable, jamais rempli par un humain. */}
      <div aria-hidden style={{ position: 'absolute', left: '-9999px', height: 0, overflow: 'hidden' }}>
        <label htmlFor="apply-website">Website</label>
        <input id="apply-website" name="website" tabIndex={-1} autoComplete="off" defaultValue="" />
      </div>

      <label
        style={{
          display: 'flex',
          alignItems: 'flex-start',
          gap: '0.5rem',
          fontSize: '0.8rem',
          lineHeight: 1.5,
          color: identity.palette.muted,
        }}
      >
        <input
          type="checkbox"
          checked={draft.consent}
          onChange={(event) =>
            setDraft((current) => ({ ...current, consent: event.target.checked }))
          }
          aria-invalid={errors.consent ? true : undefined}
          style={{ marginTop: '0.2rem' }}
        />
        <span>{shared.consentLabel}</span>
      </label>

      {phase === 'already' ? (
        <p role="alert" style={{ margin: 0, fontSize: '0.85rem', color: identity.palette.primary }}>
          {shared.alreadyApplied}
        </p>
      ) : null}

      {phase === 'rejected' ? (
        <p role="alert" style={{ margin: 0, fontSize: '0.85rem', color: '#B42318' }}>
          {serverMessage ?? shared.unavailable}
        </p>
      ) : null}

      {phase === 'failed' ? (
        <p role="alert" style={{ margin: 0, fontSize: '0.85rem', color: '#B42318' }}>
          {shared.unavailable}
        </p>
      ) : null}

      <button
        type="submit"
        disabled={phase === 'sending'}
        style={{
          justifySelf: 'start',
          padding: '0.7rem 1.2rem',
          borderRadius: '0.6rem',
          border: 'none',
          background: identity.palette.primary,
          color: identity.palette.onPrimary,
          fontWeight: 700,
          fontSize: '0.9rem',
          cursor: phase === 'sending' ? 'progress' : 'pointer',
          opacity: phase === 'sending' ? 0.7 : 1,
        }}
      >
        {phase === 'sending' ? '…' : shared.applySubmit}
      </button>
    </form>
  );
}
