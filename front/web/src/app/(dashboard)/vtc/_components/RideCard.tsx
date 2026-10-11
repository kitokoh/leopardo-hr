'use client';

import { MapPin } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import {
  formatDateTime,
  formatMinorPrice,
  rideStatusLabel,
  rideStatusTone,
  type VtcDispatchRide,
} from './vtc-shared';

const TONE_CLASS: Record<string, string> = {
  hot: 'bg-amber-100 text-amber-800',
  engaged: 'bg-emerald-100 text-emerald-800',
  neutral: 'bg-slate-100 text-slate-700',
};

/**
 * Carte d'une course active de la console dispatch (VTC-07/#8363).
 * Lecture seule : le pilotage (affectation manuelle) reste hors scope v1.
 */
export function RideCard({ ride, locale }: { ride: VtcDispatchRide; locale: AppLocale }) {
  const tone = rideStatusTone(ride.status);

  return (
    <li className="rounded-2xl border border-white/30 bg-white/80 p-5 shadow-premium backdrop-blur-xl">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-lg font-black tracking-tight text-slate-950">
            {ride.reference ?? `#${ride.id}`}
          </p>
          <p className="text-sm text-slate-600">
            {t(locale, 'vtc.requestedAt')} · {formatDateTime(locale, ride.requested_at)}
          </p>
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-bold ${TONE_CLASS[tone]}`}>
          {rideStatusLabel(locale, ride.status)}
        </span>
      </div>

      <dl className="mt-4 space-y-2 text-sm text-slate-600">
        <div className="flex items-start gap-2">
          <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden />
          <div className="flex-1">
            <dt className="font-semibold text-slate-700">{t(locale, 'vtc.pickup')}</dt>
            <dd>{ride.pickup?.address ?? '—'}</dd>
          </div>
        </div>
        <div className="flex items-start gap-2">
          <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-cyan-600" aria-hidden />
          <div className="flex-1">
            <dt className="font-semibold text-slate-700">{t(locale, 'vtc.dropoff')}</dt>
            <dd>{ride.dropoff?.address ?? '—'}</dd>
          </div>
        </div>
      </dl>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-sm">
        <span className="font-semibold text-slate-800">
          {t(locale, 'vtc.estimatedPrice')} ·{' '}
          {formatMinorPrice(locale, ride.final_price_minor ?? ride.estimated_price_minor, ride.currency)}
        </span>
        <span className="text-slate-500">
          {ride.driver_id != null
            ? `${t(locale, 'vtc.driver')} #${ride.driver_id}`
            : ride.pending_offer_driver_id != null
              ? `${t(locale, 'vtc.pendingOffer')} → #${ride.pending_offer_driver_id}`
              : t(locale, 'vtc.unassigned')}
        </span>
      </div>
    </li>
  );
}
