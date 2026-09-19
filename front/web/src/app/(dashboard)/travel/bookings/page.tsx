'use client';

/**
 * TravelManager (BC-24, #7636) — réservations & billetterie de l'espace
 * gérant : liste filtrée (`GET /travel/bookings?status|trip_id|reference`),
 * détail avec passagers et billets (`GET /travel/bookings/{b}` —
 * `TravelBookingResource` charge `passengers` + `tickets`), cycle de vie
 * complet (`POST /travel/bookings/{b}/{confirm|cancel|refund|issue-ticket|
 * refund-passenger}`), e-billet PDF via URL signée
 * (`GET /travel/tickets/{t}/pdf`), révocation (`POST /travel/tickets/{t}/revoke`)
 * et check-in embarquement (`POST /travel/tickets/{t}/check-in`).
 *
 * Gardes backend reflétées dans l'UI (le serveur reste source de vérité,
 * ses 422 sont affichés tels quels) :
 * - confirm : réservation `pending` uniquement ;
 * - cancel : `pending` ou `confirmed`, motif obligatoire 3..500 ;
 * - refund / refund-passenger / issue-ticket : `confirmed` uniquement ;
 * - revoke : impossible sur un billet `checked_in` ;
 * - pdf : 410 si le billet est `void`.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { TicketCheck, X } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { readApiError } from '@/components/travel/TravelCrudTable';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/** `TravelPassengerResource` — RGPD : jamais le n° de pièce, juste `has_document`. */
type TravelPassenger = {
  id: number;
  booking_id: number;
  full_name: string;
  birth_date: string | null;
  document_type: string | null;
  has_document: boolean;
  age_category: string;
  class_id: number | null;
  seat_number: number | null;
  unit_price_minor: number;
};

/** `TravelTicketResource` — `validation_code` présent UNIQUEMENT à l'émission. */
type TravelTicket = {
  id: number;
  ticket_number: string;
  booking_id: number;
  passenger_id: number;
  status: string;
  issued_at: string | null;
  valid_from: string | null;
  valid_until: string | null;
  checked_in_at: string | null;
  created_at: string | null;
  validation_code?: string;
};

/** `TravelBookingResource` — passagers présents en liste, billets au détail. */
type TravelBooking = {
  id: number;
  reference: string;
  trip_id: number;
  status: string;
  passenger_count: number;
  total_amount_minor: number;
  currency: string;
  booking_source: string;
  payment_status: string;
  expires_at: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  created_at: string | null;
  passengers?: TravelPassenger[];
  tickets?: TravelTicket[];
};

type TravelTripRef = { id: number; code: string; departure_date: string; departure_time: string };
type TravelClassRef = { id: number; code: string; label: string };

const BOOKING_STATUSES = ['pending', 'confirmed', 'cancelled', 'refunded', 'completed'] as const;

function bookingStatusLabel(locale: AppLocale, status: string): string {
  return t(locale, `travel.bookingStatus.${status}`, status);
}

function paymentStatusLabel(locale: AppLocale, status: string): string {
  return t(locale, `travel.paymentStatus.${status}`, status);
}

function sourceLabel(locale: AppLocale, source: string): string {
  return t(locale, `travel.bookingSource.${source}`, source);
}

/** Statuts billets backend `issued|checked_in|void` → clés legacy du catalogue. */
function ticketStatusLabel(locale: AppLocale, status: string): string {
  const key = status === 'checked_in' ? 'checkedIn' : status === 'void' ? 'revoked' : status;
  return t(locale, `travel.ticketStatus.${key}`, status);
}

function ageCategoryLabel(locale: AppLocale, category: string): string {
  return t(locale, `travel.ageCategory.${category}`, category);
}

function bookingBadgeClass(status: string): string {
  switch (status) {
    case 'confirmed':
      return 'bg-emerald-100 text-emerald-800';
    case 'completed':
      return 'bg-cyan-100 text-cyan-800';
    case 'cancelled':
      return 'bg-red-100 text-red-700';
    case 'refunded':
      return 'bg-amber-100 text-amber-800';
    default:
      return 'bg-slate-100 text-slate-700';
  }
}

function ticketBadgeClass(status: string): string {
  switch (status) {
    case 'checked_in':
      return 'bg-emerald-100 text-emerald-800';
    case 'void':
      return 'bg-red-100 text-red-700';
    default:
      return 'bg-cyan-100 text-cyan-800';
  }
}

function formatMinor(locale: AppLocale, amountMinor: number, currency: string): string {
  return `${amountMinor.toLocaleString(locale)} ${currency}`;
}

function formatDateTime(locale: AppLocale, iso: string | null): string {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(locale);
}

function ModalShell({
  title,
  subtitle,
  onClose,
  children,
  wide,
}: {
  title: string;
  subtitle?: string;
  onClose: () => void;
  children: React.ReactNode;
  wide?: boolean;
}) {
  const locale = getPreferredLocale();
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
      <div className={`max-h-[85vh] w-full ${wide ? 'max-w-4xl' : 'max-w-lg'} overflow-y-auto rounded-2xl bg-white p-6 shadow-xl`}>
        <div className="mb-4 flex items-start justify-between gap-4">
          <div>
            <h3 className="text-lg font-bold text-slate-900">{title}</h3>
            {subtitle ? <p className="text-sm text-slate-500">{subtitle}</p> : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={t(locale, 'travel.action.cancel', 'Annuler')}
            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

export default function TravelBookingsPage() {
  const locale = getPreferredLocale();

  // Référentiels des sélecteurs (trajets pour le filtre, classes pour le détail).
  const [trips, setTrips] = useState<TravelTripRef[]>([]);
  const [classes, setClasses] = useState<TravelClassRef[]>([]);

  const [bookings, setBookings] = useState<TravelBooking[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState({ status: '', trip_id: '', reference: '' });

  const [toast, setToast] = useState('');
  const [detailId, setDetailId] = useState<number | null>(null);

  const notify = useCallback((message: string) => {
    setToast(message);
    window.setTimeout(() => setToast(''), 4000);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const loadReferentials = async () => {
      try {
        const [tripsRes, classesRes] = await Promise.all([
          apiFetch('/travel/trips?per_page=200'),
          apiFetch('/travel/classes?per_page=1000'),
        ]);
        if (cancelled) return;
        if (tripsRes.ok) setTrips(((await tripsRes.json()) as { data?: TravelTripRef[] }).data ?? []);
        if (classesRes.ok) setClasses(((await classesRes.json()) as { data?: TravelClassRef[] }).data ?? []);
      } catch {
        // Sélecteurs vides ; la liste des réservations signale déjà les erreurs.
      }
    };
    void loadReferentials();
    return () => {
      cancelled = true;
    };
  }, []);

  const tripLabel = useCallback(
    (tripId: number): string => {
      const trip = trips.find((item) => item.id === tripId);
      if (!trip) return `#${tripId}`;
      return `${trip.code} — ${trip.departure_date}`;
    },
    [trips],
  );

  const classLabel = useCallback(
    (classId: number | null): string => {
      if (classId === null) return '—';
      return classes.find((c) => c.id === classId)?.label ?? `#${classId}`;
    },
    [classes],
  );

  const hasFilters = Object.values(filters).some((v) => v !== '');

  const loadBookings = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      // Filtres du contrôleur : `status`, `trip_id`, `reference` (exact).
      const params = new URLSearchParams({ per_page: '200' });
      for (const [key, value] of Object.entries(filters)) {
        if (value.trim() !== '') params.set(key, value.trim());
      }
      const res = await apiFetch(`/travel/bookings?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: TravelBooking[] };
      setBookings(Array.isArray(payload.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [filters, locale]);

  useEffect(() => {
    void loadBookings();
  }, [loadBookings]);

  const detailBooking = useMemo(
    () => (detailId === null ? null : (bookings.find((b) => b.id === detailId) ?? null)),
    [detailId, bookings],
  );

  return (
    <ModulePageShell
      icon={TicketCheck}
      title={t(locale, 'travel.bookings.title', 'Réservations')}
      description={t(
        locale,
        'travel.bookings.subtitle',
        'Réservations guichet : confirmation, annulation, remboursement et billets.',
      )}
    >
      {toast ? (
        <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700" role="status">
          {toast}
        </p>
      ) : null}
      {error ? (
        <div className="flex items-center justify-between gap-4 rounded-lg bg-red-50 px-4 py-3">
          <p className="text-sm font-semibold text-red-700">{error}</p>
          <button
            type="button"
            onClick={() => void loadBookings()}
            className="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100"
          >
            {t(locale, 'travel.gate.retry', 'Réessayer')}
          </button>
        </div>
      ) : null}

      {/* Filtres du contrôleur bookings (statut, trajet, référence exacte). */}
      <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'travel.bookings.filterStatus', 'Statut')}
          </span>
          <select
            value={filters.status}
            onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">{t(locale, 'travel.bookings.allStatuses', 'Tous')}</option>
            {BOOKING_STATUSES.map((status) => (
              <option key={status} value={status}>
                {bookingStatusLabel(locale, status)}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'travel.bookings.filterTrip', 'Trajet')}
          </span>
          <select
            value={filters.trip_id}
            onChange={(e) => setFilters((prev) => ({ ...prev, trip_id: e.target.value }))}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          >
            <option value="">{t(locale, 'travel.bookings.allTrips', 'Tous')}</option>
            {trips.map((trip) => (
              <option key={trip.id} value={trip.id}>
                {trip.code} — {trip.departure_date}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-slate-700">
            {t(locale, 'travel.field.reference', 'Référence')}
          </span>
          <input
            type="text"
            value={filters.reference}
            onChange={(e) => setFilters((prev) => ({ ...prev, reference: e.target.value }))}
            placeholder={t(locale, 'travel.search.booking', 'Référence exacte…')}
            className="rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
          />
        </label>
        {hasFilters ? (
          <button
            type="button"
            onClick={() => setFilters({ status: '', trip_id: '', reference: '' })}
            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            {t(locale, 'travel.bookings.reset', 'Réinitialiser')}
          </button>
        ) : null}
      </div>

      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.reference', 'Référence')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.trip', 'Trajet')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.status', 'Statut')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.paymentStatus', 'Paiement')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.passengerCount', 'Passagers')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.totalAmount', 'Montant')}
              </th>
              <th className="px-4 py-3 text-start font-semibold text-slate-700">
                {t(locale, 'travel.field.createdAt', 'Créé le')}
              </th>
              <th className="px-4 py-3 text-end font-semibold text-slate-700">
                {t(locale, 'travel.table.actions', 'Actions')}
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'travel.loading', 'Chargement…')}
                </td>
              </tr>
            ) : bookings.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                  {t(locale, 'travel.table.emptyNested', 'Aucun élément.')}
                </td>
              </tr>
            ) : (
              bookings.map((booking) => (
                <tr key={booking.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-medium text-slate-900">{booking.reference}</td>
                  <td className="px-4 py-3 text-slate-700">{tripLabel(booking.trip_id)}</td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${bookingBadgeClass(booking.status)}`}
                    >
                      {bookingStatusLabel(locale, booking.status)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{paymentStatusLabel(locale, booking.payment_status)}</td>
                  <td className="px-4 py-3 text-slate-700">{booking.passenger_count}</td>
                  <td className="px-4 py-3 text-slate-700">
                    {formatMinor(locale, booking.total_amount_minor, booking.currency)}
                  </td>
                  <td className="px-4 py-3 text-slate-700">{formatDateTime(locale, booking.created_at)}</td>
                  <td className="px-4 py-3 text-end">
                    <button
                      type="button"
                      className="font-medium text-cyan-700 hover:text-cyan-800"
                      onClick={() => setDetailId(booking.id)}
                    >
                      {t(locale, 'travel.action.view', 'Voir')}
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {detailId !== null ? (
        <BookingDetailPanel
          bookingId={detailId}
          referenceHint={detailBooking?.reference ?? null}
          tripLabel={tripLabel}
          classLabel={classLabel}
          notify={notify}
          onClose={() => setDetailId(null)}
          onChanged={loadBookings}
        />
      ) : null}
    </ModulePageShell>
  );
}

type ReasonMode =
  | { kind: 'cancel' }
  | { kind: 'refund' }
  | { kind: 'refund-passenger'; passenger: TravelPassenger };

/**
 * Détail d'une réservation (`GET /travel/bookings/{b}` — passagers + billets
 * chargés) avec le cycle de vie complet et la billetterie.
 */
function BookingDetailPanel({
  bookingId,
  referenceHint,
  tripLabel,
  classLabel,
  notify,
  onClose,
  onChanged,
}: {
  bookingId: number;
  referenceHint: string | null;
  tripLabel: (tripId: number) => string;
  classLabel: (classId: number | null) => string;
  notify: (message: string) => void;
  onClose: () => void;
  onChanged: () => Promise<void>;
}) {
  const locale = getPreferredLocale();
  const [booking, setBooking] = useState<TravelBooking | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [busy, setBusy] = useState(false);
  const [reasonMode, setReasonMode] = useState<ReasonMode | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch(`/travel/bookings/${bookingId}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const payload = (await res.json()) as { data?: TravelBooking };
      setBooking(payload.data ?? null);
    } catch {
      setError(t(locale, 'travel.error.loadFailed', 'Impossible de charger les données.'));
    } finally {
      setLoading(false);
    }
  }, [bookingId, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  /** POST sans payload sur la réservation (confirm / issue-ticket). */
  const postBookingAction = async (path: string, successMessage: string) => {
    setBusy(true);
    setActionError('');
    try {
      const res = await apiFetch(`/travel/bookings/${bookingId}/${path}`, { method: 'POST' });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.error.actionFailed', "L'action a échoué."));
      }
      notify(successMessage);
      await load();
      await onChanged();
    } catch (e) {
      setActionError(
        e instanceof Error && e.message ? e.message : t(locale, 'travel.error.actionFailed', "L'action a échoué."),
      );
    } finally {
      setBusy(false);
    }
  };

  const confirmBooking = async () => {
    if (!window.confirm(t(locale, 'travel.confirm.confirmBooking', 'Confirmer cette réservation ? Le paiement sera marqué comme encaissé.'))) {
      return;
    }
    await postBookingAction('confirm', t(locale, 'travel.toast.confirmed', 'Réservation confirmée.'));
  };

  const issueTickets = async () => {
    if (!window.confirm(t(locale, 'travel.confirm.issueTickets', 'Émettre les billets de cette réservation ?'))) {
      return;
    }
    await postBookingAction('issue-ticket', t(locale, 'travel.toast.ticketsIssued', 'Billets émis.'));
  };

  /** Motif validé (3..500) — cancel / refund / refund-passenger. */
  const submitReason = async (mode: ReasonMode, reason: string) => {
    // `refund_key` : clé d'idempotence client (le backend déduplique dessus).
    const body: Record<string, unknown> =
      mode.kind === 'refund-passenger'
        ? { passenger_id: mode.passenger.id, reason, refund_key: crypto.randomUUID() }
        : { reason };
    const path = mode.kind === 'refund-passenger' ? 'refund-passenger' : mode.kind;
    const res = await apiFetch(`/travel/bookings/${bookingId}/${path}`, {
      method: 'POST',
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const msg = await readApiError(res);
      throw new Error(msg ?? t(locale, 'travel.error.actionFailed', "L'action a échoué."));
    }
    setReasonMode(null);
    notify(
      mode.kind === 'cancel'
        ? t(locale, 'travel.toast.cancelled', 'Réservation annulée.')
        : t(locale, 'travel.toast.refunded', 'Réservation remboursée.'),
    );
    await load();
    await onChanged();
  };

  /** `GET /travel/tickets/{t}/pdf` → `{ data: { pdf_url } }` (URL signée 30 min). */
  const downloadPdf = async (ticket: TravelTicket) => {
    setActionError('');
    try {
      const res = await apiFetch(`/travel/tickets/${ticket.id}/pdf`);
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.tickets.pdfError', 'Téléchargement impossible.'));
      }
      const payload = (await res.json()) as { data?: { pdf_url?: string } };
      const url = payload.data?.pdf_url;
      if (!url) throw new Error(t(locale, 'travel.tickets.pdfError', 'Téléchargement impossible.'));
      window.open(url, '_blank', 'noopener,noreferrer');
    } catch (e) {
      setActionError(
        e instanceof Error && e.message ? e.message : t(locale, 'travel.tickets.pdfError', 'Téléchargement impossible.'),
      );
    }
  };

  /** POST sans payload sur un billet (check-in / revoke). */
  const postTicketAction = async (ticket: TravelTicket, path: string, successMessage: string) => {
    setBusy(true);
    setActionError('');
    try {
      const res = await apiFetch(`/travel/tickets/${ticket.id}/${path}`, { method: 'POST' });
      if (!res.ok) {
        const msg = await readApiError(res);
        throw new Error(msg ?? t(locale, 'travel.error.actionFailed', "L'action a échoué."));
      }
      notify(successMessage);
      await load();
    } catch (e) {
      setActionError(
        e instanceof Error && e.message ? e.message : t(locale, 'travel.error.actionFailed', "L'action a échoué."),
      );
    } finally {
      setBusy(false);
    }
  };

  const revokeTicket = async (ticket: TravelTicket) => {
    if (!window.confirm(t(locale, 'travel.confirm.revokeTicket', 'Révoquer ce billet ? Le PDF sera invalidé.'))) {
      return;
    }
    await postTicketAction(ticket, 'revoke', t(locale, 'travel.toast.ticketRevoked', 'Billet révoqué.'));
  };

  const checkInTicket = async (ticket: TravelTicket) => {
    await postTicketAction(ticket, 'check-in', t(locale, 'travel.toast.checkedIn', 'Passager embarqué.'));
  };

  const passengers = booking?.passengers ?? [];
  const tickets = booking?.tickets ?? [];
  const passengerName = (passengerId: number): string =>
    passengers.find((p) => p.id === passengerId)?.full_name ?? `#${passengerId}`;

  const title = `${t(locale, 'travel.bookings.detailTitle', 'Réservation')} — ${booking?.reference ?? referenceHint ?? `#${bookingId}`}`;

  return (
    <ModalShell title={title} subtitle={booking ? tripLabel(booking.trip_id) : undefined} onClose={onClose} wide>
      {error ? (
        <div className="flex items-center justify-between gap-4 rounded-lg bg-red-50 px-3 py-2">
          <p className="text-sm text-red-700">{error}</p>
          <button
            type="button"
            onClick={() => void load()}
            className="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-1 text-sm font-semibold text-red-700 hover:bg-red-100"
          >
            {t(locale, 'travel.gate.retry', 'Réessayer')}
          </button>
        </div>
      ) : null}
      {actionError ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{actionError}</p> : null}

      {loading ? (
        <p className="px-1 py-6 text-center text-sm text-slate-500">{t(locale, 'travel.loading', 'Chargement…')}</p>
      ) : booking ? (
        <div className="space-y-5">
          {/* En-tête : statut, paiement, canal, montant, contact. */}
          <div className="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-3">
            <div>
              <p className="text-xs font-medium text-slate-500">{t(locale, 'travel.field.status', 'Statut')}</p>
              <span
                className={`mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${bookingBadgeClass(booking.status)}`}
              >
                {bookingStatusLabel(locale, booking.status)}
              </span>
            </div>
            <div>
              <p className="text-xs font-medium text-slate-500">{t(locale, 'travel.field.paymentStatus', 'Paiement')}</p>
              <p className="mt-1 font-medium text-slate-800">{paymentStatusLabel(locale, booking.payment_status)}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-slate-500">{t(locale, 'travel.field.totalAmount', 'Montant')}</p>
              <p className="mt-1 font-medium text-slate-800">
                {formatMinor(locale, booking.total_amount_minor, booking.currency)}
              </p>
            </div>
            <div>
              <p className="text-xs font-medium text-slate-500">{t(locale, 'travel.bookings.source', 'Canal')}</p>
              <p className="mt-1 font-medium text-slate-800">{sourceLabel(locale, booking.booking_source)}</p>
            </div>
            <div className="col-span-2">
              <p className="text-xs font-medium text-slate-500">{t(locale, 'travel.bookings.contact', 'Contact')}</p>
              <p className="mt-1 font-medium text-slate-800">
                {[booking.contact_email, booking.contact_phone].filter(Boolean).join(' · ') || '—'}
              </p>
            </div>
          </div>

          {/* Actions du cycle de vie (gardes backend reflétées). */}
          <div className="flex flex-wrap gap-2">
            {booking.status === 'pending' ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => void confirmBooking()}
                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {t(locale, 'travel.action.confirm', 'Confirmer')}
              </button>
            ) : null}
            {booking.status === 'confirmed' ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => void issueTickets()}
                className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
              >
                {t(locale, 'travel.action.issueTicket', 'Billet')}
              </button>
            ) : null}
            {booking.status === 'pending' || booking.status === 'confirmed' ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => setReasonMode({ kind: 'cancel' })}
                className="rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 disabled:opacity-50"
              >
                {t(locale, 'travel.action.cancel', 'Annuler')}
              </button>
            ) : null}
            {booking.status === 'confirmed' ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => setReasonMode({ kind: 'refund' })}
                className="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50"
              >
                {t(locale, 'travel.action.refund', 'Rembourser')}
              </button>
            ) : null}
          </div>

          {/* Passagers (RGPD : pas de n° de pièce, seulement son existence). */}
          <div>
            <h4 className="mb-2 text-sm font-bold text-slate-800">
              {t(locale, 'travel.bookings.passengers', 'Passagers')}
            </h4>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.fullName', 'Nom complet')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.ageCategory', 'Catégorie')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.class', 'Classe')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.seat', 'Siège')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.unitPrice', 'Prix unitaire')}
                    </th>
                    <th className="px-4 py-2 text-end font-semibold text-slate-700">
                      {t(locale, 'travel.table.actions', 'Actions')}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {passengers.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-6 text-center text-slate-500">
                        {t(locale, 'travel.table.emptyNested', 'Aucun élément.')}
                      </td>
                    </tr>
                  ) : (
                    passengers.map((passenger) => (
                      <tr key={passenger.id} className="hover:bg-slate-50">
                        <td className="px-4 py-2 font-medium text-slate-900">{passenger.full_name}</td>
                        <td className="px-4 py-2 text-slate-700">{ageCategoryLabel(locale, passenger.age_category)}</td>
                        <td className="px-4 py-2 text-slate-700">{classLabel(passenger.class_id)}</td>
                        <td className="px-4 py-2 text-slate-700">{passenger.seat_number ?? '—'}</td>
                        <td className="px-4 py-2 text-slate-700">
                          {formatMinor(locale, passenger.unit_price_minor, booking.currency)}
                        </td>
                        <td className="px-4 py-2 text-end">
                          {booking.status === 'confirmed' ? (
                            <button
                              type="button"
                              disabled={busy}
                              className="font-medium text-red-500 hover:text-red-700 disabled:opacity-50"
                              onClick={() => setReasonMode({ kind: 'refund-passenger', passenger })}
                            >
                              {t(locale, 'travel.bookings.refundPassenger', 'Rembourser le passager')}
                            </button>
                          ) : null}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Billets : PDF (URL signée), check-in, révocation. */}
          <div>
            <h4 className="mb-2 text-sm font-bold text-slate-800">
              {t(locale, 'travel.bookings.tickets', 'Billets')}
            </h4>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="min-w-full divide-y divide-slate-200 text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.ticketNumber', 'N° billet')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.passenger', 'Passager')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.status', 'Statut')}
                    </th>
                    <th className="px-4 py-2 text-start font-semibold text-slate-700">
                      {t(locale, 'travel.field.issuedAt', 'Émis le')}
                    </th>
                    <th className="px-4 py-2 text-end font-semibold text-slate-700">
                      {t(locale, 'travel.table.actions', 'Actions')}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {tickets.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                        {t(
                          locale,
                          'travel.tickets.empty',
                          "Aucun billet émis pour cette réservation. Utilisez l'action « Billet » depuis l'onglet Réservations.",
                        )}
                      </td>
                    </tr>
                  ) : (
                    tickets.map((ticket) => (
                      <tr key={ticket.id} className="hover:bg-slate-50">
                        <td className="px-4 py-2 font-medium text-slate-900">{ticket.ticket_number}</td>
                        <td className="px-4 py-2 text-slate-700">{passengerName(ticket.passenger_id)}</td>
                        <td className="px-4 py-2">
                          <span
                            className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${ticketBadgeClass(ticket.status)}`}
                          >
                            {ticketStatusLabel(locale, ticket.status)}
                          </span>
                        </td>
                        <td className="px-4 py-2 text-slate-700">{formatDateTime(locale, ticket.issued_at)}</td>
                        <td className="px-4 py-2 text-end">
                          <div className="flex flex-wrap justify-end gap-3">
                            {ticket.status !== 'void' ? (
                              <button
                                type="button"
                                className="font-medium text-cyan-700 hover:text-cyan-800"
                                onClick={() => void downloadPdf(ticket)}
                              >
                                {t(locale, 'travel.tickets.downloadPdf', 'PDF')}
                              </button>
                            ) : null}
                            {ticket.status === 'issued' ? (
                              <button
                                type="button"
                                disabled={busy}
                                className="font-medium text-emerald-700 hover:text-emerald-800 disabled:opacity-50"
                                onClick={() => void checkInTicket(ticket)}
                              >
                                {t(locale, 'travel.checkin.checkIn', 'Embarquer')}
                              </button>
                            ) : null}
                            {ticket.status === 'checked_in' ? (
                              <span className="font-medium text-emerald-700">
                                {t(locale, 'travel.checkin.checkedIn', 'Embarqué ✓')}
                              </span>
                            ) : null}
                            {ticket.status === 'issued' ? (
                              <button
                                type="button"
                                disabled={busy}
                                className="font-medium text-red-500 hover:text-red-700 disabled:opacity-50"
                                onClick={() => void revokeTicket(ticket)}
                              >
                                {t(locale, 'travel.tickets.revoke', 'Révoquer')}
                              </button>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : null}

      {reasonMode !== null ? (
        <ReasonPanel mode={reasonMode} onClose={() => setReasonMode(null)} onSubmit={submitReason} />
      ) : null}
    </ModalShell>
  );
}

/**
 * Panneau modal de motif (3..500 caractères) — annulation, remboursement
 * intégral (`CancelTravelBookingRequest`) et remboursement partiel d'un
 * passager (`RefundTravelPassengerRequest`, `refund_key` idempotente générée
 * côté client).
 */
function ReasonPanel({
  mode,
  onClose,
  onSubmit,
}: {
  mode: ReasonMode;
  onClose: () => void;
  onSubmit: (mode: ReasonMode, reason: string) => Promise<void>;
}) {
  const locale = getPreferredLocale();
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const title =
    mode.kind === 'cancel'
      ? t(locale, 'travel.bookings.cancelReasonTitle', "Motif d'annulation")
      : t(locale, 'travel.bookings.refundReasonTitle', 'Motif de remboursement');
  const subtitle = mode.kind === 'refund-passenger' ? mode.passenger.full_name : undefined;

  const submit = async () => {
    if (reason.trim().length < 3) {
      setError(t(locale, 'travel.bookings.reasonRequired', 'Le motif est obligatoire.'));
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      await onSubmit(mode, reason.trim());
    } catch (e) {
      setError(
        e instanceof Error && e.message ? e.message : t(locale, 'travel.error.actionFailed', "L'action a échoué."),
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ModalShell title={title} subtitle={subtitle} onClose={onClose}>
      {error ? <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
      <label className="block text-sm">
        <span className="mb-1 block font-medium text-slate-700">
          {t(locale, 'travel.bookings.reason', 'Motif')} <span className="text-red-500">*</span>
        </span>
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          maxLength={500}
          className="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none"
        />
      </label>
      <div className="mt-5 flex justify-end gap-2">
        <button
          type="button"
          onClick={onClose}
          className="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
        >
          {t(locale, 'travel.action.cancel', 'Annuler')}
        </button>
        <button
          type="button"
          onClick={() => void submit()}
          disabled={submitting}
          className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
        >
          {submitting
            ? t(locale, 'travel.form.saving', 'Enregistrement…')
            : mode.kind === 'cancel'
              ? t(locale, 'travel.action.cancel', 'Annuler')
              : t(locale, 'travel.action.refund', 'Rembourser')}
        </button>
      </div>
    </ModalShell>
  );
}
