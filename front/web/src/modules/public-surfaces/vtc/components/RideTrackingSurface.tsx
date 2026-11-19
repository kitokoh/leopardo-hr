'use client';

/**
 * Suivi de course VTC — verticale Mobilité (#9628).
 *
 * Repris de `front/travel-web/src/app/vtc/suivi/page.tsx`. Suivi par référence
 * + code, sans compte. L'API répond un 404 uniforme : le message reste neutre.
 */

import { useState, type FormEvent } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { formatPrice } from '../../kernel/domain/money';
import { reachedRideSteps, type GuestRide } from '../domain/ride';
import { rideStatusLabel, trackRide } from '../infrastructure/ride-gateway';
import { MOBILITY_TRACKING_COPY as C } from '../../copy/retail-copy';

const INPUT_STYLE = {
  height: '2.5rem',
  width: '100%',
  borderRadius: '0.6rem',
  border: '1px solid #D6D3D1',
  padding: '0 0.75rem',
  fontSize: '0.88rem',
} as const;

export function RideTrackingSurface({ identity }: { identity: SurfaceIdentity }) {
  const [reference, setReference] = useState('');
  const [code, setCode] = useState('');
  const [pending, setPending] = useState(false);
  const [ride, setRide] = useState<GuestRide | null>(null);
  const [notFound, setNotFound] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (pending) {
      return;
    }

    setPending(true);
    setNotFound(false);
    setRide(null);

    const result = await trackRide(reference.trim(), code.trim());

    if (result === null) {
      setNotFound(true);
    } else {
      setRide(result);
    }

    setPending(false);
  };

  const steps = ride === null ? [] : reachedRideSteps(ride.status);

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <form onSubmit={submit} style={{ display: 'grid', gap: '0.6rem', maxWidth: '26rem' }}>
        <label style={{ fontSize: '0.8rem', fontWeight: 600, color: '#57534E' }}>
          Référence de course
          <input
            type="text"
            required
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            style={INPUT_STYLE}
          />
        </label>

        <label style={{ fontSize: '0.8rem', fontWeight: 600, color: '#57534E' }}>
          Code de suivi
          <input
            type="text"
            required
            value={code}
            onChange={(event) => setCode(event.target.value)}
            style={INPUT_STYLE}
          />
        </label>

        <button
          type="submit"
          disabled={pending}
          style={{
            justifySelf: 'start',
            padding: '0.7rem 1.2rem',
            borderRadius: '0.6rem',
            border: 'none',
            background: identity.palette.primary,
            color: identity.palette.onPrimary,
            fontWeight: 700,
            cursor: pending ? 'not-allowed' : 'pointer',
            opacity: pending ? 0.6 : 1,
          }}
        >
          {pending ? C.searching : C.track}
        </button>
      </form>

      {notFound ? (
        <SurfaceNotice identity={identity} alert>
          Aucune course ne correspond à ces informations.
        </SurfaceNotice>
      ) : null}

      {ride !== null ? (
        <SurfaceCard identity={identity}>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem' }}>
            <p style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>{ride.reference}</p>
            <SurfaceBadge identity={identity} tone="accent">
              {rideStatusLabel(ride.status)}
            </SurfaceBadge>
          </div>

          {ride.pickupAddress !== null || ride.dropoffAddress !== null ? (
            <p style={{ margin: '0.4rem 0 0', fontSize: '0.82rem', color: '#57534E' }}>
              {ride.pickupAddress ?? C.pickup} → {ride.dropoffAddress ?? 'Destination'}
            </p>
          ) : null}

          {ride.estimatedPriceMinor !== null ? (
            <p style={{ margin: '0.3rem 0 0', fontSize: '0.85rem', fontWeight: 700 }}>
              {formatPrice(ride.estimatedPriceMinor, ride.currency ?? 'EUR')}
            </p>
          ) : null}

          {steps.length > 0 ? (
            <ol
              style={{
                margin: '0.6rem 0 0',
                padding: 0,
                listStyle: 'none',
                display: 'grid',
                gap: '0.25rem',
              }}
            >
              {steps.map((step) => (
                <li key={step} style={{ fontSize: '0.8rem', color: '#57534E' }}>
                  ✓ {rideStatusLabel(step)}
                </li>
              ))}
            </ol>
          ) : null}
        </SurfaceCard>
      ) : null}
    </div>
  );
}
