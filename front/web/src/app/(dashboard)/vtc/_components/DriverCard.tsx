'use client';

import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';
import {
  driverStatusLabel,
  driverStatusTone,
  formatCoordinates,
  formatDateTime,
  type VtcDriver,
} from './vtc-shared';

const TONE_CLASS: Record<string, string> = {
  ok: 'bg-emerald-100 text-emerald-800',
  busy: 'bg-amber-100 text-amber-800',
  neutral: 'bg-slate-100 text-slate-700',
};

/**
 * Carte d'un chauffeur de la console dispatch (VTC-07/#8363) : statut,
 * véhicule et dernière position connue (donnée personnelle — consultation
 * auditée côté API, rétention bornée par la purge RGPD vtc).
 */
export function DriverCard({ driver, locale }: { driver: VtcDriver; locale: AppLocale }) {
  const tone = driverStatusTone(driver.status);
  const coordinates = formatCoordinates({
    latitude: driver.current_latitude,
    longitude: driver.current_longitude,
  });

  return (
    <li className="rounded-2xl border border-white/30 bg-white/80 p-5 shadow-premium backdrop-blur-xl">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-lg font-black tracking-tight text-slate-950">
            {driver.name ?? `#${driver.id}`}
          </p>
          <p className="text-sm text-slate-600">{driver.phone ?? '—'}</p>
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-bold ${TONE_CLASS[tone]}`}>
          {driverStatusLabel(locale, driver.status)}
        </span>
      </div>

      <dl className="mt-4 space-y-1 text-sm text-slate-600">
        <div className="flex justify-between gap-3">
          <dt>{t(locale, 'vtc.vehicle')}</dt>
          <dd className="font-semibold text-slate-800">
            {driver.vehicle_id != null ? `#${driver.vehicle_id}` : t(locale, 'vtc.unassigned')}
          </dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt>{t(locale, 'vtc.lastPosition')}</dt>
          <dd className="font-semibold text-slate-800">
            {coordinates ?? t(locale, 'vtc.positionUnavailable')}
          </dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt>{t(locale, 'vtc.updatedAt')}</dt>
          <dd className="font-semibold text-slate-800">
            {formatDateTime(locale, driver.location_updated_at)}
          </dd>
        </div>
      </dl>
    </li>
  );
}
