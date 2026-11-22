'use client';

/**
 * Suivi de réservation — verticale Billets (#9628).
 *
 * Repris de `front/travel-web/src/components/track-content.tsx`. Le suivi se
 * fait par référence + code, sans compte : c'est le seul moyen d'accès du
 * voyageur invité.
 *
 * L'API répond un 404 uniforme pour empêcher l'énumération des références : on
 * ne distingue donc pas « inexistante » de « code faux », et le message reste
 * neutre.
 */

import { useState, type FormEvent } from 'react';

import { SurfaceBadge, SurfaceCard, SurfaceNotice } from '../../kernel/components/SurfacePrimitives';
import type { SurfaceIdentity } from '../../kernel/domain/surface-identity';
import { formatPrice } from '../../kernel/domain/money';
import { trackBooking, fetchTicketPdf, type TrackedBooking } from '../infrastructure/booking-gateway';
import { TRAVEL_BOOKING_COPY as C } from '../../copy/retail-copy';

const INPUT_STYLE = {
  height: '2.5rem',
  width: '100%',
  borderRadius: '0.6rem',
  border: '1px solid #D6D3D1',
  padding: '0 0.75rem',
  fontSize: '0.88rem',
} as const;

export function BookingTrackingSurface({ identity }: { identity: SurfaceIdentity }) {
  const [reference, setReference] = useState('');
  const [code, setCode] = useState('');
  const [pending, setPending] = useState(false);
  const [booking, setBooking] = useState<TrackedBooking | null>(null);
  const [notFound, setNotFound] = useState(false);
  // Téléchargement de l'e-billet : l'URL signée est demandée à la volée (elle
  // expire), jamais stockée. `downloadingId` borne un seul appel à la fois.
  const [downloadingId, setDownloadingId] = useState<number | null>(null);
  const [downloadError, setDownloadError] = useState<string | null>(null);

  const downloadTicket = async (ticketId: number) => {
    if (downloadingId !== null) {
      return;
    }

    setDownloadingId(ticketId);
    setDownloadError(null);

    try {
      const link = await fetchTicketPdf(ticketId, code.trim());

      if (link.pdfUrl === '') {
        setDownloadError(C.ticketDownloadFailed);

        return;
      }

      // `noopener` : l'URL signée est une ressource tierce, elle ne doit pas
      // obtenir de référence sur cette fenêtre.
      window.open(link.pdfUrl, '_blank', 'noopener,noreferrer');
    } catch {
      setDownloadError(C.ticketDownloadFailed);
    } finally {
      setDownloadingId(null);
    }
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (pending) {
      return;
    }

    setPending(true);
    setNotFound(false);
    setBooking(null);

    const result = await trackBooking(reference.trim(), code.trim());

    if (result === null) {
      setNotFound(true);
    } else {
      setBooking(result);
    }

    setPending(false);
  };

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <form onSubmit={submit} style={{ display: 'grid', gap: '0.6rem', maxWidth: '26rem' }}>
        <label style={{ fontSize: '0.8rem', fontWeight: 600, color: '#57534E' }}>
          Référence de réservation
          <input
            type="text"
            required
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            placeholder="LP-XXXXXX"
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
          Aucune réservation ne correspond à ces informations. Vérifiez la référence et le code
          reçus à la confirmation.
        </SurfaceNotice>
      ) : null}

      {booking !== null ? (
        <SurfaceCard identity={identity}>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem' }}>
            <div>
              <p style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800 }}>
                {booking.reference}
              </p>
              {booking.tripCode !== null ? (
                <p style={{ margin: '0.15rem 0 0', fontSize: '0.8rem', color: '#78716C' }}>
                  Trajet {booking.tripCode}
                  {booking.departureDate !== null ? ` · ${booking.departureDate}` : ''}
                  {booking.departureTime !== null ? ` ${booking.departureTime}` : ''}
                </p>
              ) : null}
            </div>
            <SurfaceBadge identity={identity} tone="accent">
              {booking.status}
            </SurfaceBadge>
          </div>

          <p style={{ margin: '0.5rem 0 0', fontSize: '0.85rem', color: '#57534E' }}>
            {booking.passengerCount} passager{booking.passengerCount > 1 ? 's' : ''} ·{' '}
            {formatPrice(booking.totalMinor, booking.currency)} · paiement {booking.paymentStatus}
          </p>

          {booking.tickets.length > 0 ? (
            <ul style={{ margin: '0.5rem 0 0', padding: 0, listStyle: 'none' }}>
              {booking.tickets.map((ticket) => (
                <li
                  key={ticket.id}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: '0.5rem',
                    fontSize: '0.82rem',
                    color: '#57534E',
                  }}
                >
                  <span>
                    Billet {ticket.number}
                    {ticket.status !== '' ? ` — ${ticket.status}` : ''}
                  </span>

                  <button
                    type="button"
                    onClick={() => void downloadTicket(ticket.id)}
                    disabled={downloadingId !== null}
                    style={{
                      padding: '0.3rem 0.7rem',
                      borderRadius: '0.5rem',
                      border: `1px solid ${identity.palette.primary}`,
                      background: 'transparent',
                      color: identity.palette.primary,
                      fontSize: '0.78rem',
                      fontWeight: 600,
                      cursor: downloadingId !== null ? 'not-allowed' : 'pointer',
                      opacity: downloadingId !== null ? 0.6 : 1,
                    }}
                  >
                    {downloadingId === ticket.id ? C.submitting : C.downloadTicket}
                  </button>
                </li>
              ))}
            </ul>
          ) : null}

          {downloadError !== null ? (
            <SurfaceNotice identity={identity} alert>
              {downloadError}
            </SurfaceNotice>
          ) : null}

          {booking.expiresAt !== null ? (
            <p style={{ margin: '0.5rem 0 0', fontSize: '0.78rem', color: '#A8A29E' }}>
              Réservation valable jusqu&apos;au {booking.expiresAt}.
            </p>
          ) : null}
        </SurfaceCard>
      ) : null}
    </div>
  );
}
