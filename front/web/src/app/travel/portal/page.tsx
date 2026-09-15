'use client';

import { useEffect, useMemo, useState } from 'react';
import { Download, Loader2, Search, Ticket } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, normalizeLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

// Constante technique (fenêtre popup) — hors catalogue i18n.
const POPUP_FEATURES = ['noopener', 'noreferrer'].join(',');

/**
 * Portail client voyageur — TRAVEL-702 (#6089), recâblé en #7395.
 *
 * Page PUBLIQUE (« Espace voyageur ») : le passager n'a ni compte Leopardo ni
 * jeton boutique. Elle est donc sortie du groupe `(dashboard)` et consomme la
 * surface passagère `/public/travel/passenger/*`, authentifiée par le couple
 * référence de réservation + code de validation imprimé sur l'e-billet (#7394).
 *
 * Avant #7395, la page appelait les endpoints STAFF
 * (`/travel/shop/bookings/{reference}`, `/travel/tickets/{id}/pdf`) : un vrai
 * passager recevait 401 UNAUTHENTICATED, et le champ « code de validation »
 * n'était même pas vérifié côté serveur.
 *
 * L'annulation en ligne n'est PAS exposée ici : elle exige un acteur employé
 * (`CancelBookingAction` renseigne `cancelled_by`), ce qui suppose de décider
 * qui porte une annulation faite par le passager. Hors périmètre, voir #7395.
 */

type BookingData = {
  reference?: string;
  status?: string;
  booking_source?: string;
  total_amount_minor?: number;
  currency?: string;
  passenger_count?: number;
  trip?: {
    code?: string;
    departure_date?: string;
    departure_time?: string;
    arrival_date?: string;
    arrival_time?: string;
    origin?: string | null;
    destination?: string | null;
  } | null;
  /** Numéros de billet (imprimés sur l'e-billet) — sert au téléchargement PDF. */
  ticket_numbers?: string[];
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
        `/public/travel/passenger/bookings/${encodeURIComponent(reference.trim())}?code=${encodeURIComponent(code.trim())}`,
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

  const downloadTicket = async (ticketNumber: string) => {
    if (!booking?.reference) {
      return;
    }
    try {
      // Le numéro de billet contient un `#` : il passe en paramètre de requête
      // (un `#` dans un segment de chemin est lu comme un fragment par le proxy
      // same-origin et tronque l'URL — constaté en #7395).
      const response = await apiFetch(
        `/public/travel/passenger/bookings/${encodeURIComponent(booking.reference)}/ticket`
          + `?number=${encodeURIComponent(ticketNumber)}&code=${encodeURIComponent(code.trim())}`,
      );
      const payload = (await response.json()) as { data?: { pdf_url?: string } };
      const url = payload.data?.pdf_url;
      if (url) {
        window.open(url, '_blank', POPUP_FEATURES);
      }
    } catch {
      setError(fallbackCopy.error);
    }
  };

  const statusKey = booking?.status ? STATUS_KEY[booking.status] : undefined;
  const statusLabel = statusKey ? fallbackCopy[statusKey] : booking?.status ?? '';
  const isCancelled = booking?.status === 'cancelled';

  return (
    <ModulePageShell
      title={copy.title}
      subtitle={copy.subtitle}
      accentClassName="from-emerald-500 to-teal-600"
    >
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

            {(booking.ticket_numbers?.length ?? 0) > 0 && (
              <div className="space-y-2">
                <p className="text-sm font-medium text-white/80">{copy.tickets}</p>
                {booking.ticket_numbers?.map((ticketNumber) => (
                  <div key={ticketNumber} className="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                    <span className="flex items-center gap-2 text-sm text-white/80">
                      <Ticket className="h-4 w-4" />
                      {ticketNumber}
                    </span>
                    <Button variant="ghost" size="sm" onClick={() => downloadTicket(ticketNumber)}>
                      <Download className="h-4 w-4" />
                      {copy.downloadTicket}
                    </Button>
                  </div>
                ))}
              </div>
            )}

          </div>
        )}
      </div>
    </ModulePageShell>
  );
}
