'use client';

/**
 * Suivi de candidature — verticale Emploi (#9629).
 *
 * Repris de `front/web/src/app/[companySlug]/careers/applications/TrackApplicationsForm.tsx`.
 *
 * ## Pas de compte candidat
 *
 * Le candidat n'a pas de compte : c'est le contrat de l'API, pas un raccourci.
 * Il retrouve ses dossiers avec l'adresse email utilisée pour postuler. Le
 * formulaire le dit explicitement, pour qu'il ne cherche pas un « mot de passe
 * oublié » qui n'existe pas.
 *
 * ## Un email inconnu n'est pas une erreur
 *
 * Le backend rend une liste vide plutôt qu'un 404 : un 404 confirmerait
 * « cette adresse n'a jamais postulé », ce qui est une fuite d'information.
 * L'écran affiche donc « aucun dossier » et non « adresse inconnue ».
 */

import { useState } from 'react';

import type { SurfaceIdentity } from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import { isValidEmail } from '../domain/application';
import { loadApplicationTrackingView, type TrackingView } from '../application/load-tracking';

export function TrackApplicationsForm({
  identity,
  companySlug,
  locale,
}: {
  identity: SurfaceIdentity;
  companySlug: string;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  const [email, setEmail] = useState('');
  const [phase, setPhase] = useState<'idle' | 'loading' | 'done' | 'invalid'>('idle');
  const [result, setResult] = useState<TrackingView | null>(null);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (!isValidEmail(email)) {
      setPhase('invalid');
      return;
    }

    setPhase('loading');

    const view = await loadApplicationTrackingView(companySlug, email.trim(), locale);

    setResult(view);
    setPhase('done');
  }

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <form
        onSubmit={onSubmit}
        noValidate
        style={{
          display: 'flex',
          flexWrap: 'wrap',
          gap: '0.6rem',
          alignItems: 'flex-end',
          padding: '1.1rem',
          borderRadius: '0.9rem',
          border: `1px solid ${identity.palette.muted}33`,
          background: '#FFFFFF',
        }}
      >
        <div style={{ flex: '1 1 16rem' }}>
          <label
            htmlFor="track-email"
            style={{
              display: 'block',
              fontSize: '0.78rem',
              fontWeight: 700,
              marginBottom: '0.25rem',
              color: identity.palette.muted,
            }}
          >
            {shared.company}
          </label>
          <input
            id="track-email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            aria-invalid={phase === 'invalid' ? true : undefined}
            style={{
              width: '100%',
              padding: '0.6rem 0.7rem',
              borderRadius: '0.5rem',
              border: `1px solid ${identity.palette.muted}55`,
              fontSize: '0.9rem',
              fontFamily: 'inherit',
            }}
          />
        </div>

        <button
          type="submit"
          disabled={phase === 'loading'}
          style={{
            padding: '0.65rem 1.1rem',
            borderRadius: '0.6rem',
            border: 'none',
            background: identity.palette.primary,
            color: identity.palette.onPrimary,
            fontWeight: 700,
            fontSize: '0.88rem',
            cursor: phase === 'loading' ? 'progress' : 'pointer',
            opacity: phase === 'loading' ? 0.7 : 1,
          }}
        >
          {phase === 'loading' ? '…' : shared.bookable}
        </button>
      </form>

      {phase === 'invalid' ? (
        <p role="alert" style={{ margin: 0, fontSize: '0.85rem', color: '#B42318' }}>
          {shared.unavailable}
        </p>
      ) : null}

      {phase === 'done' && result !== null ? (
        result.failed ? (
          <p role="alert" style={{ margin: 0, fontSize: '0.85rem', color: '#B42318' }}>
            {shared.unavailable}
          </p>
        ) : result.applications.length === 0 ? (
          <p style={{ margin: 0, fontSize: '0.88rem', color: identity.palette.muted }}>
            {shared.empty}
          </p>
        ) : (
          <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '0.6rem' }}>
            {result.applications.map((application) => (
              <li
                key={application.reference}
                style={{
                  padding: '0.85rem 1rem',
                  borderRadius: '0.7rem',
                  border: `1px solid ${identity.palette.muted}33`,
                  background: '#FFFFFF',
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: '0.5rem',
                  alignItems: 'baseline',
                  justifyContent: 'space-between',
                }}
              >
                <div>
                  <strong style={{ display: 'block', fontSize: '0.92rem' }}>
                    {application.jobTitle}
                  </strong>
                  <span style={{ fontSize: '0.78rem', color: identity.palette.muted }}>
                    {[application.jobLocation, application.appliedAt?.slice(0, 10)]
                      .filter(Boolean)
                      .join(' · ')}
                  </span>
                </div>

                <span
                  style={{
                    fontSize: '0.78rem',
                    fontWeight: 700,
                    padding: '0.2rem 0.55rem',
                    borderRadius: '999px',
                    background: application.active
                      ? `${identity.palette.accent}22`
                      : `${identity.palette.muted}18`,
                    color: application.active
                      ? identity.palette.primary
                      : identity.palette.muted,
                  }}
                >
                  {application.statusLabel}
                </span>
              </li>
            ))}
          </ul>
        )
      ) : null}

      {phase === 'done' && result?.retentionDays ? (
        <p style={{ margin: 0, fontSize: '0.75rem', color: identity.palette.muted }}>
          {result.retentionDays} j
        </p>
      ) : null}
    </div>
  );
}
