import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * BC-34 VTC (épic #8349, VTC-07/#8363) — types et helpers partagés de la
 * console dispatch web. Shapes alignées sur les endpoints VTC-06
 * (`VtcDispatchController::rides/drivers`, contrat openapi.yaml).
 */

export type VtcRidePoint = {
  latitude?: number | null;
  longitude?: number | null;
  address?: string | null;
};

export type VtcDispatchRide = {
  id: number;
  reference?: string | null;
  status?: string | null;
  driver_id?: number | null;
  pending_offer_driver_id?: number | null;
  pickup?: VtcRidePoint | null;
  dropoff?: VtcRidePoint | null;
  estimated_price_minor?: number | null;
  final_price_minor?: number | null;
  currency?: string | null;
  requested_at?: string | null;
};

export type VtcDriver = {
  id: number;
  user_id?: number | null;
  name?: string | null;
  phone?: string | null;
  status?: string | null;
  vehicle_id?: number | null;
  current_latitude?: number | null;
  current_longitude?: number | null;
  location_updated_at?: string | null;
  created_at?: string | null;
};

const RIDE_STATUS_LABEL_KEY: Record<string, string> = {
  requested: 'vtc.statusRequested',
  dispatching: 'vtc.statusDispatching',
  accepted: 'vtc.statusAccepted',
  arrived: 'vtc.statusArrived',
  in_progress: 'vtc.statusInProgress',
  completed: 'vtc.statusCompleted',
  expired: 'vtc.statusExpired',
  cancelled: 'vtc.statusCancelled',
};

const DRIVER_STATUS_LABEL_KEY: Record<string, string> = {
  offline: 'vtc.driverOffline',
  available: 'vtc.driverAvailable',
  busy: 'vtc.driverBusy',
  suspended: 'vtc.driverSuspended',
};

export function rideStatusLabel(locale: AppLocale, status?: string | null): string {
  return t(locale, RIDE_STATUS_LABEL_KEY[status ?? ''] ?? 'vtc.statusUnknown');
}

export function driverStatusLabel(locale: AppLocale, status?: string | null): string {
  return t(locale, DRIVER_STATUS_LABEL_KEY[status ?? ''] ?? 'vtc.statusUnknown');
}

/** Badge de statut : course « chaude » (dispatch/attente) vs course engagée. */
export function rideStatusTone(status?: string | null): 'hot' | 'engaged' | 'neutral' {
  if (status === 'requested' || status === 'dispatching') return 'hot';
  if (status === 'accepted' || status === 'arrived' || status === 'in_progress') return 'engaged';
  return 'neutral';
}

export function driverStatusTone(status?: string | null): 'ok' | 'busy' | 'neutral' {
  if (status === 'available') return 'ok';
  if (status === 'busy') return 'busy';
  return 'neutral';
}

/** Prix en unités mineures → libellé localisé (ex. 2 500 XAF). */
export function formatMinorPrice(
  locale: AppLocale,
  minor?: number | null,
  currency?: string | null,
): string {
  if (minor == null) return '—';
  const code = currency ?? '';
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: code || 'XAF',
      maximumFractionDigits: 0,
    }).format(minor / 100);
  } catch {
    return `${Math.round(minor / 100)} ${code}`.trim();
  }
}

export function formatDateTime(locale: AppLocale, iso?: string | null): string {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '—';
  return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' }).format(date);
}

export function formatCoordinates(point?: VtcRidePoint | null): string | null {
  const lat = point?.latitude;
  const lng = point?.longitude;
  if (lat == null || lng == null) return null;
  return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
}
