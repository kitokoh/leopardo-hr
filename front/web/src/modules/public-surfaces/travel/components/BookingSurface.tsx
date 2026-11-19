'use client';

/**
 * Réservation de billets — verticale Billets (#9628).
 *
 * Repris de `front/travel-web/src/components/booking-flow.tsx`. La logique
 * (tarifs, validation, idempotence) est dans `domain/booking` ; ce composant
 * collecte et rend.
 *
 * ## Le plan de sièges n'est pas la source de vérité
 *
 * L'inventaire affiché est rafraîchi périodiquement, et un siège devenu
 * indisponible est retiré de la sélection avec un message explicite. Mais la
 * disponibilité définitive est tranchée **par le serveur à la confirmation** :
 * le plan ne fait qu'éviter au client de découvrir le conflit trop tard.
 */

import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import {
  MAX_PASSENGERS,
  bookingTotal,
  buildBookingPayload,
  newIdempotencyKey,
  validateBooking,
  type AgeCategory,
  type CreatedBooking,
  type PassengerDraft,
  type TripPrice,
} from '../domain/booking';
import { formatPrice } from '../../kernel/domain/money';
import {
  buildSeatMapLayout,
  isSeatSelectable,
  seatAriaLabel,
  seatStatusSymbol,
  selectableSeatNumbers,
  type SeatInput,
  type SeatStatusValue,
} from '../domain/seat-layout';
import { reconcileSeatSelection } from '../domain/seat-selection';
import { createBooking } from '../infrastructure/booking-gateway';
import { TRAVEL_BOOKING_COPY as C } from '../../copy/retail-copy';

/** Intervalle de rafraîchissement de l'inventaire (ms). */
export const SEAT_REFRESH_INTERVAL_MS = 30_000;

const INPUT_STYLE = {
  height: '2.5rem',
  width: '100%',
  borderRadius: '0.6rem',
  border: '1px solid #D6D3D1',
  padding: '0 0.75rem',
  fontSize: '0.88rem',
} as const;

const ERROR_MESSAGES: Record<string, string> = {
  'missing-name': C.passengerNamesRequired,
  'missing-contact': C.contactRequired,
};

export function BookingSurface({
  identity,
  tripId,
  totalSeats,
  prices,
  initialSeats,
  seatLayout,
  onRefreshSeats,
}: {
  identity: SurfaceIdentity;
  tripId: number;
  totalSeats: number;
  prices: readonly TripPrice[];
  initialSeats: readonly SeatInput[];
  seatLayout: { seatsPerRow: number; aisleAfter: number | null } | null;
  /** Recharge l'inventaire ; `null` si l'API ne répond pas. */
  onRefreshSeats: () => Promise<readonly SeatInput[] | null>;
}) {
  const defaultClassId = prices[0]?.classId ?? 0;

  const [liveSeats, setLiveSeats] = useState<readonly SeatInput[]>(initialSeats);
  const [passengers, setPassengers] = useState<PassengerDraft[]>([
    { fullName: '', ageCategory: 'adult', classId: defaultClassId, seatNumber: null },
  ]);
  const [contactEmail, setContactEmail] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [notifyConsent, setNotifyConsent] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [seatNotice, setSeatNotice] = useState<string | null>(null);
  const [created, setCreated] = useState<CreatedBooking | null>(null);

  // Une clé par session de checkout : un rejeu réseau ne crée jamais deux
  // réservations. Elle est conservée jusqu'au succès.
  const idempotencyKey = useRef<string>(newIdempotencyKey());

  const freeSeats = useMemo(() => selectableSeatNumbers(liveSeats), [liveSeats]);
  const selectedSeats = useMemo(
    () =>
      passengers
        .map((passenger) => passenger.seatNumber)
        .filter((seat): seat is number => seat !== null),
    [passengers],
  );
  const totalMinor = useMemo(() => bookingTotal(passengers, prices), [passengers, prices]);
  const currency = prices[0]?.currency ?? 'EUR';

  useEffect(() => {
    let cancelled = false;

    const refresh = async () => {
      const fresh = await onRefreshSeats();

      if (cancelled || fresh === null) {
        return;
      }

      setLiveSeats(fresh);

      setPassengers((current) => {
        // La réconciliation travaille sur la forme `seat_number` du domaine ;
        // on projette, on réconcilie, puis on reprojette sur le brouillon.
        const projected = current.map((passenger) => ({
          seat_number: passenger.seatNumber,
        }));

        const { passengers: reconciled, changed } = reconcileSeatSelection(projected, fresh);

        if (!changed) {
          return current;
        }

        setSeatNotice(
          C.seatTaken,
        );

        return current.map((passenger, index) => ({
          ...passenger,
          seatNumber: reconciled[index]?.seat_number ?? null,
        }));
      });
    };

    const timer = setInterval(() => void refresh(), SEAT_REFRESH_INTERVAL_MS);

    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [onRefreshSeats]);

  const updatePassenger = (index: number, patch: Partial<PassengerDraft>) => {
    setPassengers((current) =>
      current.map((passenger, position) =>
        position === index ? { ...passenger, ...patch } : passenger,
      ),
    );
  };

  const toggleSeat = (seat: number) => {
    setSeatNotice(null);

    setPassengers((current) => {
      const holder = current.findIndex((passenger) => passenger.seatNumber === seat);

      if (holder >= 0) {
        return current.map((passenger, position) =>
          position === holder ? { ...passenger, seatNumber: null } : passenger,
        );
      }

      const empty = current.findIndex((passenger) => passenger.seatNumber === null);

      if (empty >= 0) {
        return current.map((passenger, position) =>
          position === empty ? { ...passenger, seatNumber: seat } : passenger,
        );
      }

      if (current.length >= MAX_PASSENGERS) {
        return current;
      }

      return [
        ...current,
        { fullName: '', ageCategory: 'adult', classId: defaultClassId, seatNumber: seat },
      ];
    });
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    const problem = validateBooking({ passengers, contactEmail, contactPhone });

    if (problem !== null) {
      setError(ERROR_MESSAGES[problem]);

      return;
    }

    setSubmitting(true);

    try {
      const booking = await createBooking(
        buildBookingPayload({
          tripId,
          idempotencyKey: idempotencyKey.current,
          passengers,
          contactEmail,
          contactPhone,
          notifyConsent,
        }),
      );

      setCreated(booking);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : C.bookingFailed);
      setSubmitting(false);
    }
  };

  if (created !== null) {
    return (
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 800 }}>Réservation confirmée</h2>
        <p style={{ margin: '0.3rem 0 0', fontSize: '0.9rem' }}>
          Référence <strong>{created.reference}</strong>
          {created.agencyName !== null ? ` — ${created.agencyName}` : ''}
        </p>
        <p style={{ margin: '0.2rem 0 0', fontSize: '0.85rem', color: '#78716C' }}>
          {created.passengerCount} passager{created.passengerCount > 1 ? 's' : ''} ·{' '}
          {formatPrice(created.totalMinor, created.currency)}
        </p>
        <p style={{ margin: '0.5rem 0 0', fontSize: '0.8rem', color: '#78716C' }}>
          Conservez cette référence : elle suffit à suivre votre réservation.
        </p>
      </SurfaceCard>
    );
  }

  const layout = buildSeatMapLayout(liveSeats, {
    seatsPerRow: seatLayout?.seatsPerRow ?? 4,
    aisleAfter: seatLayout?.aisleAfter ?? null,
  });

  return (
    <form onSubmit={submit} style={{ display: 'grid', gap: '1.25rem' }}>
      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Choisir un siège</h2>
        <p style={{ margin: '0.2rem 0 0.6rem', fontSize: '0.8rem', color: '#78716C' }}>
          {selectedSeats.length} siège{selectedSeats.length > 1 ? 's' : ''} sélectionné
          {selectedSeats.length > 1 ? 's' : ''}
          {selectedSeats.length === 0 ? C.autoAssignHint : ''}
        </p>

        <div style={{ display: 'grid', gap: '0.35rem' }}>
          {layout.rows.map((row) => (
            <div
              key={row.rowNumber}
              style={{ display: 'flex', gap: '0.3rem', alignItems: 'center' }}
            >
              <span style={{ width: '1.6rem', fontSize: '0.7rem', color: '#A8A29E' }}>
                {row.rowNumber}
              </span>

              {row.cells.map((cell, position) => {
                // Couloir et siège absent ne sont pas des boutons : l'un est un
                // espace, l'autre une case inerte qui garde sa place réelle.
                if (cell.seatNumber === null) {
                  return <span key={`aisle-${position}`} style={{ width: '0.9rem' }} />;
                }

                const isSelected = selectedSeats.includes(cell.seatNumber);
                // `aisle` et `absent` ne sont pas des états de siège : on les
                // écarte avant d'interroger la règle de sélection.
                const isFree =
                  cell.status !== 'aisle' &&
                  cell.status !== 'absent' &&
                  isSeatSelectable(cell.status) &&
                  freeSeats.includes(cell.seatNumber);

                return (
                  <button
                    key={cell.seatNumber}
                    type="button"
                    disabled={!isFree}
                    onClick={() => toggleSeat(cell.seatNumber as number)}
                    aria-label={seatAriaLabel(cell.seatNumber, cell.status as SeatStatusValue)}
                    aria-pressed={isSelected}
                    title={cell.label}
                    style={{
                      width: '2rem',
                      height: '2rem',
                      borderRadius: '0.4rem',
                      border: `1px solid ${isSelected ? identity.palette.primary : '#D6D3D1'}`,
                      background: isSelected
                        ? identity.palette.primary
                        : isFree
                          ? '#FFFFFF'
                          : '#F5F5F4',
                      color: isSelected
                        ? identity.palette.onPrimary
                        : isFree
                          ? '#1C1917'
                          : '#A8A29E',
                      fontSize: '0.7rem',
                      fontWeight: 600,
                      cursor: isFree ? 'pointer' : 'not-allowed',
                    }}
                  >
                    {cell.seatNumber}
                    {cell.symbol !== '' ? (
                      <span aria-hidden="true" style={{ fontSize: '0.55rem' }}>
                        {seatStatusSymbol(cell.status)}
                      </span>
                    ) : null}
                  </button>
                );
              })}
            </div>
          ))}
        </div>

        {seatNotice !== null ? (
          <p
            role="status"
            style={{
              margin: '0.6rem 0 0',
              padding: '0.5rem 0.7rem',
              borderRadius: '0.5rem',
              background: '#FFFBEB',
              color: '#92400E',
              fontSize: '0.8rem',
            }}
          >
            {seatNotice}
          </p>
        ) : null}

        <p style={{ margin: '0.6rem 0 0', fontSize: '0.75rem', color: '#A8A29E' }}>
          {totalSeats} places au total — la disponibilité est revérifiée par le serveur à la
          confirmation.
        </p>
      </SurfaceCard>

      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Passagers</h2>

        {passengers.map((passenger, index) => (
          <div
            key={index}
            style={{
              display: 'grid',
              gap: '0.5rem',
              gridTemplateColumns: '2fr 1fr 1fr',
              alignItems: 'end',
              borderTop: index === 0 ? 'none' : '1px solid #F5F5F4',
              paddingTop: index === 0 ? 0 : '0.6rem',
            }}
          >
            <label style={{ fontSize: '0.75rem', fontWeight: 600, color: '#57534E' }}>
              Nom complet
              <input
                type="text"
                required
                value={passenger.fullName}
                onChange={(event) => updatePassenger(index, { fullName: event.target.value })}
                style={INPUT_STYLE}
              />
            </label>

            <label style={{ fontSize: '0.75rem', fontWeight: 600, color: '#57534E' }}>
              Catégorie
              <select
                value={passenger.ageCategory}
                onChange={(event) =>
                  updatePassenger(index, { ageCategory: event.target.value as AgeCategory })
                }
                style={INPUT_STYLE}
              >
                <option value="adult">Adulte</option>
                <option value="child">Enfant</option>
                <option value="infant">Nourrisson</option>
              </select>
            </label>

            <div style={{ fontSize: '0.75rem', color: '#57534E' }}>
              Siège
              <div style={{ ...INPUT_STYLE, display: 'flex', alignItems: 'center' }}>
                {passenger.seatNumber ?? 'auto'}
              </div>
            </div>
          </div>
        ))}

        <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
          <button
            type="button"
            onClick={() =>
              setPassengers((current) =>
                current.length >= MAX_PASSENGERS
                  ? current
                  : [
                      ...current,
                      {
                        fullName: '',
                        ageCategory: 'adult',
                        classId: defaultClassId,
                        seatNumber: null,
                      },
                    ],
              )
            }
            style={{
              padding: '0.4rem 0.8rem',
              borderRadius: '0.5rem',
              border: '1px solid #D6D3D1',
              background: '#FFFFFF',
              fontSize: '0.8rem',
              cursor: 'pointer',
            }}
          >
            + Ajouter un passager
          </button>

          {passengers.length > 1 ? (
            <button
              type="button"
              onClick={() => setPassengers((current) => current.slice(0, -1))}
              style={{
                padding: '0.4rem 0.8rem',
                borderRadius: '0.5rem',
                border: '1px solid #D6D3D1',
                background: '#FFFFFF',
                fontSize: '0.8rem',
                cursor: 'pointer',
              }}
            >
              − Retirer
            </button>
          ) : null}
        </div>
      </SurfaceCard>

      <SurfaceCard identity={identity}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>Contact</h2>

        <div style={{ display: 'grid', gap: '0.5rem', gridTemplateColumns: '1fr 1fr' }}>
          <label style={{ fontSize: '0.75rem', fontWeight: 600, color: '#57534E' }}>
            E-mail
            <input
              type="email"
              value={contactEmail}
              onChange={(event) => setContactEmail(event.target.value)}
              style={INPUT_STYLE}
            />
          </label>

          <label style={{ fontSize: '0.75rem', fontWeight: 600, color: '#57534E' }}>
            Téléphone
            <input
              type="tel"
              value={contactPhone}
              onChange={(event) => setContactPhone(event.target.value)}
              style={INPUT_STYLE}
            />
          </label>
        </div>

        <label
          style={{
            display: 'flex',
            gap: '0.5rem',
            alignItems: 'flex-start',
            fontSize: '0.8rem',
            color: '#57534E',
          }}
        >
          <input
            type="checkbox"
            checked={notifyConsent}
            onChange={(event) => setNotifyConsent(event.target.checked)}
            style={{ marginTop: '0.15rem' }}
          />
          J&apos;accepte d&apos;être prévenu par e-mail ou SMS en cas de changement d&apos;horaire.
        </label>
      </SurfaceCard>

      {error !== null ? (
        <SurfaceNotice identity={identity} alert>
          {error}
        </SurfaceNotice>
      ) : null}

      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
        <button
          type="submit"
          disabled={submitting}
          style={{
            padding: '0.8rem 1.4rem',
            borderRadius: '0.6rem',
            border: 'none',
            background: identity.palette.primary,
            color: identity.palette.onPrimary,
            fontWeight: 700,
            cursor: submitting ? 'not-allowed' : 'pointer',
            opacity: submitting ? 0.6 : 1,
          }}
        >
          {submitting ? C.booking : C.book}
        </button>
        <SurfaceBadge identity={identity} tone="accent">
          Total {formatPrice(totalMinor, currency)}
        </SurfaceBadge>
      </div>
    </form>
  );
}
