'use client';

/**
 * Demande de course VTC — verticale Mobilité (#9628).
 *
 * Repris de `front/travel-web/src/components/vtc-booking-flow.tsx`. Surface
 * 100 % invitée : aucun compte, la course se demande puis se suit par
 * référence + code.
 *
 * ## Le code de suivi n'est montré qu'une fois
 *
 * L'API ne le renvoie qu'à la création (201). Au rejeu idempotent (200), il est
 * absent : c'est voulu, et le composant le dit à l'utilisateur plutôt que
 * d'afficher un champ vide.
 */

import { useRef, useState, type FormEvent } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { formatPrice } from '../../kernel/domain/money';
import {
  isPriced,
  validateRideRequest,
  type CreatedGuestRide,
  type GeoPoint,
  type RideEstimate,
} from '../domain/ride';
import { createRide, estimateRide } from '../infrastructure/ride-gateway';

const INPUT_STYLE = {
  height: '2.5rem',
  width: '100%',
  borderRadius: '0.6rem',
  border: '1px solid #D6D3D1',
  padding: '0 0.75rem',
  fontSize: '0.88rem',
} as const;

const ERROR_MESSAGES: Record<string, string> = {
  'invalid-pickup': 'Le point de départ est invalide.',
  'invalid-dropoff': 'La destination est invalide.',
  'missing-name': 'Indiquez le nom du passager.',
  'missing-phone': 'Indiquez un téléphone joignable.',
};

/** Parse une saisie « lat,lng » ; `null` si le format est inattendu. */
function parsePoint(value: string): GeoPoint | null {
  const [latText, lngText] = value.split(',');

  if (latText === undefined || lngText === undefined) {
    return null;
  }

  const lat = Number(latText.trim());
  const lng = Number(lngText.trim());

  return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
}

export function RideRequestSurface({
  identity,
  operatorSlug,
  operatorName,
}: {
  identity: SurfaceIdentity;
  operatorSlug: string;
  operatorName: string;
}) {
  const [pickupText, setPickupText] = useState('');
  const [dropoffText, setDropoffText] = useState('');
  const [passengerName, setPassengerName] = useState('');
  const [passengerPhone, setPassengerPhone] = useState('');
  const [estimate, setEstimate] = useState<RideEstimate | null>(null);
  const [ride, setRide] = useState<CreatedGuestRide | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Une clé par demande : un double tap renvoie la même course, jamais un doublon.
  const idempotencyKey = useRef<string>(crypto.randomUUID());

  const points = () => {
    const pickup = parsePoint(pickupText);
    const dropoff = parsePoint(dropoffText);

    return { pickup, dropoff };
  };

  const requestEstimate = async () => {
    const { pickup, dropoff } = points();

    if (pickup === null || dropoff === null) {
      setError('Saisissez les coordonnées au format « latitude, longitude ».');

      return;
    }

    setPending(true);
    setError(null);

    try {
      setEstimate(await estimateRide({ operator: operatorSlug, pickup, dropoff }));
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Le devis a échoué.');
    } finally {
      setPending(false);
    }
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const { pickup, dropoff } = points();

    if (pickup === null || dropoff === null) {
      setError('Saisissez les coordonnées au format « latitude, longitude ».');

      return;
    }

    const problem = validateRideRequest({ pickup, dropoff, passengerName, passengerPhone });

    if (problem !== null) {
      setError(ERROR_MESSAGES[problem]);

      return;
    }

    setPending(true);
    setError(null);

    try {
      setRide(
        await createRide({
          operator: operatorSlug,
          idempotencyKey: idempotencyKey.current,
          pickup,
          dropoff,
          passengerName,
          passengerPhone,
        }),
      );
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'La demande de course a échoué.');
    } finally {
      setPending(false);
    }
  };

  if (ride !== null) {
    return (
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '1.05rem', fontWeight: 800 }}>Course demandée</h2>
        <p style={{ margin: '0.3rem 0 0', fontSize: '0.9rem' }}>
          Référence <strong>{ride.reference}</strong> — {operatorName}
        </p>

        {ride.trackingCode !== null ? (
          <p
            style={{
              margin: '0.5rem 0 0',
              padding: '0.6rem 0.8rem',
              borderRadius: '0.6rem',
              background: `${identity.palette.accent}22`,
              fontSize: '0.85rem',
            }}
          >
            Code de suivi : <strong>{ride.trackingCode}</strong> — notez-le maintenant, il ne sera
            plus affiché.
          </p>
        ) : (
          <p style={{ margin: '0.5rem 0 0', fontSize: '0.8rem', color: '#78716C' }}>
            Cette demande existait déjà : le code de suivi vous a été présenté à sa création.
          </p>
        )}

        {ride.estimatedPriceMinor !== null ? (
          <p style={{ margin: '0.4rem 0 0', fontSize: '0.85rem', color: '#57534E' }}>
            Prix estimé {formatPrice(ride.estimatedPriceMinor, ride.currency ?? 'EUR')}
          </p>
        ) : null}
      </SurfaceCard>
    );
  }

  return (
    <form onSubmit={submit} style={{ display: 'grid', gap: '0.75rem', maxWidth: '30rem' }}>
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>
          Demander une course — {operatorName}
        </h2>

        <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
          Départ (latitude, longitude)
          <input
            type="text"
            required
            value={pickupText}
            onChange={(event) => setPickupText(event.target.value)}
            placeholder="36.7538, 3.0588"
            style={INPUT_STYLE}
          />
        </label>

        <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
          Destination (latitude, longitude)
          <input
            type="text"
            required
            value={dropoffText}
            onChange={(event) => setDropoffText(event.target.value)}
            placeholder="36.7000, 3.2000"
            style={INPUT_STYLE}
          />
        </label>

        <div style={{ display: 'grid', gap: '0.5rem', gridTemplateColumns: '1fr 1fr' }}>
          <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
            Nom du passager
            <input
              type="text"
              required
              value={passengerName}
              onChange={(event) => setPassengerName(event.target.value)}
              style={INPUT_STYLE}
            />
          </label>

          <label style={{ fontSize: '0.78rem', fontWeight: 600, color: '#57534E' }}>
            Téléphone
            <input
              type="tel"
              required
              value={passengerPhone}
              onChange={(event) => setPassengerPhone(event.target.value)}
              style={INPUT_STYLE}
            />
          </label>
        </div>

        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginTop: '0.4rem' }}>
          <button
            type="button"
            onClick={() => void requestEstimate()}
            disabled={pending}
            style={{
              padding: '0.5rem 0.9rem',
              borderRadius: '0.5rem',
              border: '1px solid #D6D3D1',
              background: '#FFFFFF',
              fontSize: '0.82rem',
              cursor: pending ? 'not-allowed' : 'pointer',
            }}
          >
            Estimer le prix
          </button>

          {estimate !== null ? (
            <SurfaceBadge identity={identity} tone={isPriced(estimate) ? 'accent' : 'neutral'}>
              {isPriced(estimate)
                ? formatPrice(estimate.priceMinor as number, estimate.currency ?? 'EUR')
                : 'Prix à confirmer'}
            </SurfaceBadge>
          ) : null}
        </div>

        {estimate !== null && estimate.roadDistanceM !== null ? (
          <p style={{ margin: '0.3rem 0 0', fontSize: '0.78rem', color: '#78716C' }}>
            {(estimate.roadDistanceM / 1000).toFixed(1)} km par la route
            {estimate.durationS !== null ? ` · ~${Math.round(estimate.durationS / 60)} min` : ''}
          </p>
        ) : null}
      </SurfaceCard>

      {error !== null ? (
        <SurfaceNotice identity={identity} alert>
          {error}
        </SurfaceNotice>
      ) : null}

      <button
        type="submit"
        disabled={pending}
        style={{
          justifySelf: 'start',
          padding: '0.75rem 1.3rem',
          borderRadius: '0.6rem',
          border: 'none',
          background: identity.palette.primary,
          color: identity.palette.onPrimary,
          fontWeight: 700,
          cursor: pending ? 'not-allowed' : 'pointer',
          opacity: pending ? 0.6 : 1,
        }}
      >
        {pending ? 'Envoi…' : 'Demander la course'}
      </button>
    </form>
  );
}
