'use client';

import { useEffect, useMemo, useState } from 'react';
import { Download, Loader2, Plane, Search, Ticket, XCircle } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, normalizeLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

/**
 * Portail client voyageur — TRAVEL-702 (#6089).
 *
 * Suivi d'une réservation par référence + code de validation (e-billet),
 * téléchargement des e-billets PDF et annulation en ligne (motif,
 * preuve par code, départ futur). Consomme les endpoints shop
 * (`/travel/shop/bookings/{reference}`, `/travel/tickets/{id}/pdf`,
 * `/travel/shop/bookings/{reference}/cancel`).
 */

type BookingData = {
  reference?: string;
  status?: string;
  booking_source?: string;
  total_amount_minor?: number;
  currency?: string;
  passenger_count?: number;
  trip?: { id?: number; code?: string; departure_date?: string; departure_time?: string } | null;
  ticket_numbers?: string[];
  ticket_ids?: number[];
  passengers?: Array<{ id?: number; full_name?: string; seat_number?: number | null }>;
  cancel_reason?: string | null;
};

type PortalCopy = ReturnType<typeof getCopy>['travelPortal'];

const STATUS_KEY: Record<string, keyof PortalCopy> = {
  pending: 'statusPending',
  confirmed: 'statusConfirmed',
  cancelled: 'cancelled',
  refunded: 'statusRefunded',
  completed: 'statusCompleted',
};

export default function TravelPortalPage() {
  const { locale } = useVitrineLocale();
  const appLocale = normalizeLocale(locale);
  const copy = useMemo(() => getCopy(appLocale).travelPortal, [appLocale]);

  const [reference, setReference] = useState('');
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [booking, setBooking] = useState<BookingData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reason, setReason] = useState('');
  const [cancelling, setCancelling] = useState(false);

  // i18n minimal pour les statuts (clés existantes si possible).
  const fallbackCopy = copy as Record<string, string>;

  const trackBooking = async (e?: React.FormEvent) => {
    e?.preventDefault();
    if (!reference.trim() || !code.trim()) {
      return;
    }
    setLoading(true);
    setError(null);
    setBooking(null);
    try {
      const response = await apiFetch(
        `/travel/shop/bookings/${encodeURIComponent(reference.trim())}?code=${encodeURIComponent(code.trim())}`,
      );
      const payload = (await response.json()) as { data?: BookingData };
      setBooking(payload.data ?? null);
      if (!payload.data) {
        setError(fallbackCopy.notFound);
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) {
        setError(fallbackCopy.notFound);
      } else {
        setError(fallbackCopy.error);
      }
    } finally {
      setLoading(false);
    }
  };

  const downloadTicket = async (ticketId: number) => {
    try {
      const response = await apiFetch(`/travel/tickets/${ticketId}/pdf`);
      const payload = (await response.json()) as { data?: { pdf_url?: string } };
      const url = payload.data?.pdf_url;
      if (url) {
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    } catch {
      setError(fallbackCopy.error);
    }
  };

  const cancelBooking = async () => {
    if (!booking?.reference || !reason.trim() || cancelling) {
      return;
    }
    setCancelling(true);
    setError(null);
    try {
      const response = await apiFetch(`/travel/shop/bookings/${encodeURIComponent(booking.reference)}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ code: code.trim(), reason: reason.trim() }),
      });
      const payload = (await response.json()) as { data?: BookingData };
      setBooking(payload.data ?? null);
      setReason('');
    } catch (err) {
      if (err instanceof ApiError) {
        const body = (err.body ?? {}) as { error?: string };
        if (body.error === 'TRAVEL_BOOKING_CODE_INVALID') {
          setError(fallbackCopy.invalidCode);
        } else if (body.error === 'TRAVEL_BOOKING_DEPARTURE_PAST') {
          setError(fallbackCopy.departurePast);
        } else {
          setError(fallbackCopy.error);
        }
      } else {
        setError(fallbackCopy.error);
      }
    } finally {
      setCancelling(false);
    }
  };

  const statusKey = booking?.status ? STATUS_KEY[booking.status] : undefined;
  const statusLabel = statusKey ? fallbackCopy[statusKey] : booking?.status ?? '';
  const canCancel = booking?.status === 'pending' || booking?.status === 'confirmed';
  const isCancelled = booking?.status === 'cancelled';

  return (
    <ModulePageShell title={copy.title} subtitle={copy.subtitle} icon={<Plane className="h-5 w-5" />}>
      <div className="mx-auto max-w-2xl space-y-6">
        <form onSubmit={trackBooking} className="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-6">
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-white/80">{copy.referenceLabel}</span>
              <input
                type="text"
                value={reference}
                onChange={(e) => setReference(e.target.value)}
                placeholder={copy.referencePlaceholder}
                className="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-primary"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-white/80">{copy.codeLabel}</span>
              <input
                type="text"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder={copy.codePlaceholder}
                className="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-primary"
              />
            </label>
          </div>
          <Button type="submit" disabled={loading || !reference.trim() || !code.trim()}>
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
            {loading ? copy.tracking : copy.track}
          </Button>
        </form>

        {error && (
          <div className="rounded-xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200">
            {error}
          </div>
        )}

        {booking && !error && (
          <div className="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-lg font-semibold text-white">{booking.reference}</p>
                <p className="text-sm text-white/60">
                  {copy.status} : <span className="text-white/90">{statusLabel}</span>
                </p>
              </div>
              {isCancelled && (
                <span className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs text-amber-200">
                  {copy.cancelled}
                </span>
              )}
            </div>

            {booking.trip && (
              <div className="grid gap-2 text-sm sm:grid-cols-2">
                <p className="text-white/70">
                  {copy.trip} : <span className="text-white/90">{booking.trip.code ?? '—'}</span>
                </p>
                <p className="text-white/70">
                  {booking.trip.departure_date}
                  {booking.trip.departure_time ? ` ${booking.trip.departure_time}` : ''}
                </p>
                <p className="text-white/70">
                  {copy.passengers} : <span className="text-white/90">{booking.passenger_count ?? 0}</span>
                </p>
                <p className="text-white/70">
                  {copy.total} :{' '}
                  <span className="text-white/90">
                    {booking.currency ?? ''} {((booking.total_amount_minor ?? 0) / 100).toFixed(2)}
                  </span>
                </p>
              </div>
            )}

            {(booking.ticket_ids?.length ?? 0) > 0 && (
              <div className="space-y-2">
                <p className="text-sm font-medium text-white/80">{copy.tickets}</p>
                {booking.ticket_ids?.map((ticketId, index) => (
                  <div key={ticketId} className="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                    <span className="flex items-center gap-2 text-sm text-white/80">
                      <Ticket className="h-4 w-4" />
                      {booking.ticket_numbers?.[index] ?? `#${ticketId}`}
                    </span>
                    <Button variant="ghost" size="sm" onClick={() => downloadTicket(ticketId)}>
                      <Download className="h-4 w-4" />
                      {copy.downloadTicket}
                    </Button>
                  </div>
                ))}
              </div>
            )}

            {canCancel && (
              <div className="space-y-3 rounded-xl border border-white/10 bg-white/5 p-4">
                <p className="text-sm font-medium text-white/80">{copy.cancel}</p>
                <textarea
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  placeholder={copy.cancelReasonPlaceholder}
                  rows={2}
                  className="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white outline-none focus:border-primary"
                />
                <Button variant="danger" disabled={cancelling || reason.trim().length < 5} onClick={cancelBooking}>
                  {cancelling ? <Loader2 className="h-4 w-4 animate-spin" /> : <XCircle className="h-4 w-4" />}
                  {cancelling ? copy.cancelling : copy.cancelConfirm}
                </Button>
              </div>
            )}
          </div>
        )}
      </div>
    </ModulePageShell>
  );
}
