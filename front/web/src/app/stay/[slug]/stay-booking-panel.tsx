'use client';

import { useMemo, useState } from 'react';
import { CalendarDays, CheckCircle2, Search, XCircle } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import type {
  PublicStayAvailabilityRoomType,
  PublicStayReservationResult,
  PublicStayReservationTrack,
  PublicStayRoomType,
} from '@/lib/hospitality-public-api';
import { formatStayMoney } from './format';

/**
 * HOSP-008 (#7950) — parcours transactionnel de la page publique
 * `/stay/{slug}` (modèle : `restaurant-order-panel.tsx`, RESTO-903).
 *
 * Dates + voyageurs → disponibilités par type de chambre (HOSP-006) →
 * réservation idempotente (`idempotency_key` uuid, statut `pending`,
 * `expires_at=+30min`) → confirmation avec **référence + code de suivi**
 * (le code n'est montré qu'UNE fois : le serveur ne stocke que son hash) →
 * suivi / annulation sans compte par référence + code. Tous les appels
 * passent par le proxy same-origin `/api/v1` (apiFetch) — AUCUN jeton.
 */

function uuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `stay-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

interface StayBookingPanelProps {
  slug: string;
  locale: AppLocale;
  roomTypes: PublicStayRoomType[];
}

export default function StayBookingPanel({ slug, locale, roomTypes }: StayBookingPanelProps) {
  const base = `/public/hospitality/properties/${encodeURIComponent(slug)}`;

  // Recherche de disponibilités.
  const [checkIn, setCheckIn] = useState('');
  const [checkOut, setCheckOut] = useState('');
  const [adults, setAdults] = useState(1);
  const [availability, setAvailability] = useState<PublicStayAvailabilityRoomType[] | null>(null);
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);

  // Formulaire de réservation.
  const [selectedRoomTypeId, setSelectedRoomTypeId] = useState<number | null>(null);
  const [guestName, setGuestName] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [booking, setBooking] = useState(false);
  const [bookingError, setBookingError] = useState<string | null>(null);
  const [reservation, setReservation] = useState<PublicStayReservationResult | null>(null);
  // Clé d'idempotence stable pour la tentative courante (rejeu réseau → même réservation).
  const [idempotencyKey, setIdempotencyKey] = useState(uuid);

  // Suivi / annulation sans compte.
  const [trackReference, setTrackReference] = useState('');
  const [trackCode, setTrackCode] = useState('');
  const [tracking, setTracking] = useState<PublicStayReservationTrack | null>(null);
  const [trackError, setTrackError] = useState<string | null>(null);
  const [trackBusy, setTrackBusy] = useState(false);

  const datesValid = checkIn !== '' && checkOut !== '' && checkOut > checkIn;

  const selectedRoomType = useMemo(
    () =>
      availability?.find((roomType) => roomType.room_type_id === selectedRoomTypeId) ?? null,
    [availability, selectedRoomTypeId],
  );

  const search = async () => {
    if (!datesValid) {
      setSearchError(t(locale, 'stay.public.datesError'));
      return;
    }
    setSearching(true);
    setSearchError(null);
    setAvailability(null);
    setSelectedRoomTypeId(null);
    try {
      const query = `from=${encodeURIComponent(checkIn)}&to=${encodeURIComponent(checkOut)}&adults=${adults}`;
      const res = await apiFetch(`${base}/availability?${query}`, { _cacheBust: true });
      const json = (await res.json()) as {
        data?: { room_types?: PublicStayAvailabilityRoomType[] };
      };
      setAvailability(json.data?.room_types ?? []);
    } catch {
      setSearchError(t(locale, 'stay.public.searchError'));
    } finally {
      setSearching(false);
    }
  };

  const book = async () => {
    if (!selectedRoomType || !datesValid) {
      return;
    }
    if (guestName.trim() === '') {
      setBookingError(t(locale, 'stay.public.guestNameError'));
      return;
    }
    setBooking(true);
    setBookingError(null);
    try {
      const res = await apiFetch(`${base}/reservations`, {
        method: 'POST',
        body: JSON.stringify({
          room_type_id: selectedRoomType.room_type_id,
          guest_name: guestName.trim(),
          contact_email: contactEmail.trim() || undefined,
          contact_phone: contactPhone.trim() || undefined,
          check_in: checkIn,
          check_out: checkOut,
          adults,
          idempotency_key: idempotencyKey,
        }),
      });
      const json = (await res.json()) as { data?: PublicStayReservationResult };
      if (!json.data) {
        throw Object.assign(new Error('empty'), { status: 422 });
      }
      setReservation(json.data);
      setTrackReference(json.data.reference);
      setTrackCode(json.data.tracking_code);
      // Prochaine tentative éventuelle = nouvelle action logique.
      setIdempotencyKey(uuid());
    } catch (err) {
      const status = (err as { status?: number })?.status;
      setBookingError(
        t(
          locale,
          status === 409 || status === 422
            ? 'stay.public.noAvailabilityError'
            : 'stay.public.bookingError',
        ),
      );
    } finally {
      setBooking(false);
    }
  };

  const track = async () => {
    if (trackReference.trim() === '' || trackCode.trim() === '') {
      setTrackError(t(locale, 'stay.public.trackFormError'));
      return;
    }
    setTrackBusy(true);
    setTrackError(null);
    try {
      const res = await apiFetch(
        `/public/hospitality/reservations/${encodeURIComponent(trackReference.trim())}?code=${encodeURIComponent(trackCode.trim())}`,
        { _cacheBust: true },
      );
      const json = (await res.json()) as { data?: PublicStayReservationTrack };
      setTracking(json.data ?? null);
      if (!json.data) {
        setTrackError(t(locale, 'stay.public.trackNotFound'));
      }
    } catch {
      setTracking(null);
      setTrackError(t(locale, 'stay.public.trackNotFound'));
    } finally {
      setTrackBusy(false);
    }
  };

  const cancel = async () => {
    if (!tracking) {
      return;
    }
    setTrackBusy(true);
    setTrackError(null);
    try {
      await apiFetch(
        `/public/hospitality/reservations/${encodeURIComponent(tracking.reference)}/cancel`,
        {
          method: 'POST',
          body: JSON.stringify({ code: trackCode.trim() }),
        },
      );
      setTracking({ ...tracking, status: 'cancelled' });
    } catch {
      setTrackError(t(locale, 'stay.public.cancelError'));
    } finally {
      setTrackBusy(false);
    }
  };

  const statusLabel = (status: string): string =>
    t(locale, `stay.public.status.${status}`, status);

  const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none';

  return (
    <section className="mt-10" aria-labelledby="stay-booking-title">
      <h2 id="stay-booking-title" className="text-xl font-semibold text-slate-900">
        {t(locale, 'stay.public.bookingTitle')}
      </h2>

      {/* 1. Dates + voyageurs → disponibilités. */}
      <div className="mt-4 grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-4">
        <label className="text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'stay.public.checkInLabel')}
          </span>
          <input
            type="date"
            value={checkIn}
            onChange={(event) => setCheckIn(event.target.value)}
            className={inputClass}
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'stay.public.checkOutLabel')}
          </span>
          <input
            type="date"
            value={checkOut}
            min={checkIn || undefined}
            onChange={(event) => setCheckOut(event.target.value)}
            className={inputClass}
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'stay.public.adultsLabel')}
          </span>
          <input
            type="number"
            min={1}
            max={30}
            value={adults}
            onChange={(event) => setAdults(Math.max(1, Number(event.target.value) || 1))}
            className={inputClass}
          />
        </label>
        <div className="flex items-end">
          <button
            type="button"
            onClick={() => void search()}
            disabled={searching}
            className="inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
          >
            <Search className="h-4 w-4" aria-hidden />
            {t(locale, searching ? 'stay.public.searching' : 'stay.public.searchAvailability')}
          </button>
        </div>
      </div>
      {searchError ? (
        <p role="alert" className="mt-2 text-sm text-red-700">
          {searchError}
        </p>
      ) : null}

      {/* 2. Choix du type de chambre disponible. */}
      {availability !== null ? (
        availability.length === 0 || availability.every((roomType) => roomType.available === 0) ? (
          <p className="mt-4 text-sm text-slate-600">{t(locale, 'stay.public.noAvailability')}</p>
        ) : (
          <ul className="mt-4 grid gap-3 sm:grid-cols-2">
            {availability.map((roomType) => {
              const selectable = roomType.available > 0;
              const selected = roomType.room_type_id === selectedRoomTypeId;
              return (
                <li key={roomType.room_type_id}>
                  <button
                    type="button"
                    disabled={!selectable}
                    onClick={() => setSelectedRoomTypeId(roomType.room_type_id)}
                    aria-pressed={selected}
                    className={`w-full rounded-lg border p-4 text-start text-sm transition ${
                      selected
                        ? 'border-emerald-700 ring-1 ring-emerald-700'
                        : 'border-slate-200 hover:border-slate-400'
                    } ${selectable ? '' : 'opacity-50'}`}
                  >
                    <span className="block font-semibold text-slate-900">{roomType.name}</span>
                    <span className="mt-1 block text-emerald-700">
                      {t(locale, 'stay.public.pricePerNight').replace(
                        '{price}',
                        formatStayMoney(roomType.base_price_minor, roomType.currency, locale),
                      )}
                    </span>
                    <span className="mt-1 block text-slate-600">
                      {selectable
                        ? t(locale, 'stay.public.unitsAvailable').replace(
                            '{count}',
                            String(roomType.available),
                          )
                        : t(locale, 'stay.public.soldOut')}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        )
      ) : null}

      {/* 3. Formulaire voyageur → réservation. */}
      {selectedRoomType && !reservation ? (
        <div className="mt-6 grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-3">
          <label className="text-sm sm:col-span-3">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'stay.public.guestNameLabel')}
            </span>
            <input
              type="text"
              value={guestName}
              maxLength={150}
              onChange={(event) => setGuestName(event.target.value)}
              className={inputClass}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'stay.public.emailLabel')}
            </span>
            <input
              type="email"
              value={contactEmail}
              maxLength={190}
              onChange={(event) => setContactEmail(event.target.value)}
              className={inputClass}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'stay.public.phoneLabel')}
            </span>
            <input
              type="tel"
              value={contactPhone}
              maxLength={40}
              onChange={(event) => setContactPhone(event.target.value)}
              className={inputClass}
            />
          </label>
          <div className="flex items-end">
            <button
              type="button"
              onClick={() => void book()}
              disabled={booking}
              className="inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-60"
            >
              <CalendarDays className="h-4 w-4" aria-hidden />
              {t(locale, booking ? 'stay.public.booking' : 'stay.public.book')}
            </button>
          </div>
          {bookingError ? (
            <p role="alert" className="text-sm text-red-700 sm:col-span-3">
              {bookingError}
            </p>
          ) : null}
        </div>
      ) : null}

      {/* 4. Confirmation : référence + code de suivi (affiché UNE seule fois). */}
      {reservation ? (
        <div className="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4">
          <p className="inline-flex items-center gap-2 font-semibold text-emerald-900">
            <CheckCircle2 className="h-5 w-5" aria-hidden />
            {t(locale, 'stay.public.confirmedTitle')}
          </p>
          <dl className="mt-3 grid gap-2 text-sm text-emerald-900 sm:grid-cols-2">
            <div>
              <dt className="font-medium">{t(locale, 'stay.public.referenceLabel')}</dt>
              <dd className="font-mono text-base" data-testid="stay-reservation-reference">
                {reservation.reference}
              </dd>
            </div>
            <div>
              <dt className="font-medium">{t(locale, 'stay.public.trackingCodeLabel')}</dt>
              <dd className="font-mono text-base" data-testid="stay-reservation-tracking-code">
                {reservation.tracking_code}
              </dd>
            </div>
          </dl>
          <p className="mt-3 text-sm text-emerald-900">
            {t(locale, 'stay.public.trackingCodeNotice')}
          </p>
          {reservation.expires_at ? (
            <p className="mt-1 text-sm text-emerald-900">
              {t(locale, 'stay.public.pendingExpiryNotice')}
            </p>
          ) : null}
        </div>
      ) : null}

      {/* 5. Suivi / annulation sans compte. */}
      <div className="mt-10 rounded-lg border border-slate-200 p-4">
        <h3 className="text-lg font-semibold text-slate-900">
          {t(locale, 'stay.public.trackTitle')}
        </h3>
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          <label className="text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'stay.public.referenceLabel')}
            </span>
            <input
              type="text"
              value={trackReference}
              onChange={(event) => setTrackReference(event.target.value)}
              className={inputClass}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium text-slate-700">
              {t(locale, 'stay.public.trackingCodeLabel')}
            </span>
            <input
              type="text"
              value={trackCode}
              onChange={(event) => setTrackCode(event.target.value)}
              className={inputClass}
            />
          </label>
          <div className="flex items-end">
            <button
              type="button"
              onClick={() => void track()}
              disabled={trackBusy}
              className="inline-flex w-full items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            >
              {t(locale, 'stay.public.trackAction')}
            </button>
          </div>
        </div>
        {trackError ? (
          <p role="alert" className="mt-2 text-sm text-red-700">
            {trackError}
          </p>
        ) : null}
        {tracking ? (
          <div className="mt-4 text-sm text-slate-700" data-testid="stay-tracking-result">
            <p>
              <span className="font-medium">{t(locale, 'stay.public.statusLabel')} :</span>{' '}
              {statusLabel(tracking.status)}
            </p>
            <p className="mt-1">
              {tracking.check_in} → {tracking.check_out}
              {tracking.room_type ? ` · ${tracking.room_type.name}` : ''}
            </p>
            {tracking.total_amount_minor !== null && tracking.currency ? (
              <p className="mt-1">
                {formatStayMoney(tracking.total_amount_minor, tracking.currency, locale)}
              </p>
            ) : null}
            {tracking.status === 'pending' || tracking.status === 'confirmed' ? (
              <button
                type="button"
                onClick={() => void cancel()}
                disabled={trackBusy}
                className="mt-3 inline-flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
              >
                <XCircle className="h-4 w-4" aria-hidden />
                {t(locale, 'stay.public.cancelAction')}
              </button>
            ) : null}
          </div>
        ) : null}
      </div>
    </section>
  );
}
