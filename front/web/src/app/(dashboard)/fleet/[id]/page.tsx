'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { AlertTriangle, ArrowLeft, MapPin, Route } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';

/**
 * #7400 — Fiche véhicule de service : caractéristiques, position live et
 * itinéraires (lecture seule).
 *
 * Endpoints consommés (tous existants, sous `api.manager`) :
 *   GET /vehicles/{id}            → fiche
 *   GET /vehicles/{id}/position   → dernier relevé du traceur (404 si aucun
 *                                   traceur n'est appairé : état vide explicite,
 *                                   jamais une erreur brute)
 *   GET /vehicles/{id}/trips      → itinéraires paginés
 *
 * La position est celle du **dernier relevé synchronisé** : la synchronisation
 * planifiée Traccar est un prérequis distinct (#7400, hors périmètre) — l'écran
 * affiche donc l'horodatage du relevé plutôt que de laisser croire à du temps
 * réel.
 */

type Vehicle = {
  id: number;
  plate_number?: string | null;
  brand?: string | null;
  model?: string | null;
  year?: number | null;
  type?: string | null;
  vin?: string | null;
  fuel_type?: string | null;
  status?: string | null;
  mileage?: number | null;
  insurance_expiry?: string | null;
  technical_control_expiry?: string | null;
  assigned_driver_id?: number | null;
};

type Position = {
  latitude?: number | string | null;
  longitude?: number | string | null;
  speed?: number | string | null;
  fixTime?: string | null;
  deviceTime?: string | null;
};

type Trip = {
  id: number;
  start_time?: string | null;
  end_time?: string | null;
  // #7526 — noms RÉELS du contrat `VehicleTripResource` : l'API émet
  // `start_address` / `end_address`. Les anciens `start_location` /
  // `end_location` n'existent pas dans la réponse : les deux colonnes de
  // l'itinéraire affichaient donc « — » pour TOUS les trajets.
  start_address?: string | null;
  end_address?: string | null;
  distance_km?: number | string | null;
};

// Constante technique (attributs de lien externe) — hors catalogue i18n.
const EXTERNAL_LINK_REL = ['noopener', 'noreferrer'].join(' ');

/**
 * #7526 — `GET /vehicles/{id}/position` relaie la charge utile Traccar BRUTE
 * (`VehicleController::position()` ne convertit rien) : `speed` y est exprimé
 * en **nœuds**. L'afficher tel quel sous l'étiquette « km/h » annonçait une
 * vitesse 1,852 fois trop faible (22,68 nœuds valent ~42 km/h).
 * Même facteur que `FleetTrackingSyncService::KNOTS_TO_KMH` (source serveur).
 */
const KNOTS_TO_KMH = 1.852;

function knotsToKmh(speed: number | string): number {
  return Math.round(Number(speed) * KNOTS_TO_KMH * 100) / 100;
}

const STATUS_LABEL_KEY: Record<string, string> = {
  active: 'fleet.statusActive',
  maintenance: 'fleet.statusMaintenance',
  decommissioned: 'fleet.statusDecommissioned',
};

/** Affiche une date ISO en locale courante, sans planter sur une valeur vide. */
function formatDateTime(value?: string | null, locale = 'fr'): string {
  if (!value) {
    return '—';
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }
  return parsed.toLocaleString(locale);
}

export default function FleetVehiclePage() {
  const params = useParams<{ id: string }>();
  const vehicleId = Number(params?.id);
  const locale = getPreferredLocale();

  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  const [position, setPosition] = useState<Position | null>(null);
  const [trips, setTrips] = useState<Trip[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!Number.isFinite(vehicleId) || vehicleId <= 0) {
      setError(t(locale, 'fleet.notFound'));
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const vehicleRes = await apiFetch(`/vehicles/${vehicleId}`);
      const vehiclePayload = await vehicleRes.json();
      setVehicle(vehiclePayload?.data ?? null);
    } catch {
      setVehicle(null);
      setError(t(locale, 'fleet.notFound'));
      setLoading(false);
      return;
    }

    // Position et itinéraires sont INDÉPENDANTS de la fiche : un traceur non
    // appairé (404) ne doit pas masquer les caractéristiques du véhicule.
    const [positionResult, tripsResult] = await Promise.allSettled([
      apiFetch(`/vehicles/${vehicleId}/position`).then((r) => r.json()),
      apiFetch(`/vehicles/${vehicleId}/trips?per_page=50`).then((r) => r.json()),
    ]);

    if (positionResult.status === 'fulfilled') {
      setPosition((positionResult.value?.data ?? null) as Position | null);
    } else {
      setPosition(null);
    }

    if (tripsResult.status === 'fulfilled') {
      const payload = tripsResult.value;
      setTrips(Array.isArray(payload?.data) ? payload.data : []);
    } else {
      setTrips([]);
    }

    setLoading(false);
  }, [locale, vehicleId]);

  useEffect(() => {
    void load();
  }, [load]);

  const statusLabel = vehicle?.status
    ? t(locale, STATUS_LABEL_KEY[vehicle.status] ?? 'fleet.statusUnknown')
    : t(locale, 'fleet.statusUnknown');

  const latitude = position?.latitude != null ? Number(position.latitude) : null;
  const longitude = position?.longitude != null ? Number(position.longitude) : null;
  const hasPosition = latitude !== null && longitude !== null
    && Number.isFinite(latitude) && Number.isFinite(longitude);

  return (
    <ModulePageShell
      title={vehicle?.plate_number ?? t(locale, 'fleet.title')}
      subtitle={t(locale, 'fleet.detailSubtitle')}
      icon={Route}
    >
      <Link
        href="/fleet"
        className="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 underline-offset-4 hover:underline"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden />
        {t(locale, 'fleet.back')}
      </Link>

      {loading ? <p className="text-sm text-slate-500">{t(locale, 'fleet.loading')}</p> : null}

      {error ? (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden />
          <p role="alert" className="text-sm font-semibold">
            {error}
          </p>
        </div>
      ) : null}

      {vehicle ? (
        <section className="rounded-2xl border border-white/30 bg-white/80 p-6 shadow-premium backdrop-blur-xl">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-2xl font-black tracking-tight text-slate-950">
                {[vehicle.brand, vehicle.model].filter(Boolean).join(' ') || '—'}
              </p>
              <p className="text-sm text-slate-600">{vehicle.plate_number ?? '—'}</p>
            </div>
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
              {statusLabel}
            </span>
          </div>

          <dl className="mt-5 grid gap-x-6 gap-y-2 text-sm text-slate-600 sm:grid-cols-2">
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.year')}</dt>
              <dd className="font-semibold text-slate-800">{vehicle.year ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.type')}</dt>
              <dd className="font-semibold text-slate-800">{vehicle.type ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.fuelType')}</dt>
              <dd className="font-semibold text-slate-800">{vehicle.fuel_type ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.mileage')}</dt>
              <dd className="font-semibold text-slate-800">
                {vehicle.mileage != null ? `${vehicle.mileage} km` : '—'}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.insuranceExpiry')}</dt>
              <dd className="font-semibold text-slate-800">{vehicle.insurance_expiry ?? '—'}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.technicalControlExpiry')}</dt>
              <dd className="font-semibold text-slate-800">
                {vehicle.technical_control_expiry ?? '—'}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>{t(locale, 'fleet.assignedDriver')}</dt>
              <dd className="font-semibold text-slate-800">
                {vehicle.assigned_driver_id != null
                  ? `#${vehicle.assigned_driver_id}`
                  : t(locale, 'fleet.unassigned')}
              </dd>
            </div>
          </dl>
        </section>
      ) : null}

      {vehicle ? (
        <section className="rounded-2xl border border-white/30 bg-white/80 p-6 shadow-premium backdrop-blur-xl">
          <h2 className="flex items-center gap-2 text-lg font-black text-slate-950">
            <MapPin className="h-5 w-5 text-emerald-700" aria-hidden />
            {t(locale, 'fleet.position')}
          </h2>

          {hasPosition ? (
            <>
              <dl className="mt-4 grid gap-x-6 gap-y-2 text-sm text-slate-600 sm:grid-cols-3">
                <div className="flex justify-between gap-3">
                  <dt>{t(locale, 'fleet.coordinates')}</dt>
                  <dd className="font-semibold text-slate-800">
                    {latitude.toFixed(5)}, {longitude.toFixed(5)}
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt>{t(locale, 'fleet.speed')}</dt>
                  <dd className="font-semibold text-slate-800">
                    {position?.speed != null ? `${knotsToKmh(position.speed)} km/h` : '—'}
                  </dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt>{t(locale, 'fleet.updatedAt')}</dt>
                  <dd className="font-semibold text-slate-800">
                    {formatDateTime(position?.fixTime ?? position?.deviceTime ?? null, locale)}
                  </dd>
                </div>
              </dl>
              <a
                href={`https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=16/${latitude}/${longitude}`}
                target="_blank"
                rel={EXTERNAL_LINK_REL}
                className="mt-3 inline-flex text-sm font-bold text-emerald-700 underline-offset-4 hover:underline"
              >
                {t(locale, 'fleet.viewOnMap')}
              </a>
              <p className="mt-3 text-xs text-slate-500">{t(locale, 'fleet.positionHint')}</p>
            </>
          ) : (
            <p className="mt-3 text-sm text-slate-500">{t(locale, 'fleet.positionUnavailable')}</p>
          )}
        </section>
      ) : null}

      {vehicle ? (
        <section className="rounded-2xl border border-white/30 bg-white/80 p-6 shadow-premium backdrop-blur-xl">
          <h2 className="text-lg font-black text-slate-950">{t(locale, 'fleet.trips')}</h2>
          <p className="mt-1 text-xs text-slate-500">{t(locale, 'fleet.tripsHint')}</p>

          {trips.length === 0 ? (
            <p className="mt-4 text-sm text-slate-500">{t(locale, 'fleet.tripsEmpty')}</p>
          ) : (
            <div className="mt-4 overflow-x-auto">
              <table className="w-full min-w-[40rem] border-collapse text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th className="border-b border-slate-200 px-3 py-2">{t(locale, 'fleet.period')}</th>
                    <th className="border-b border-slate-200 px-3 py-2">{t(locale, 'fleet.startLocation')}</th>
                    <th className="border-b border-slate-200 px-3 py-2">{t(locale, 'fleet.endLocation')}</th>
                    <th className="border-b border-slate-200 px-3 py-2">{t(locale, 'fleet.distance')}</th>
                  </tr>
                </thead>
                <tbody>
                  {trips.map((trip) => (
                    <tr key={trip.id} className="text-slate-700">
                      <td className="border-b border-slate-100 px-3 py-2">
                        {formatDateTime(trip.start_time, locale)}
                        <span className="block text-xs text-slate-500">
                          {formatDateTime(trip.end_time, locale)}
                        </span>
                      </td>
                      <td className="border-b border-slate-100 px-3 py-2">
                        {trip.start_address ?? '—'}
                      </td>
                      <td className="border-b border-slate-100 px-3 py-2">
                        {trip.end_address ?? '—'}
                      </td>
                      <td className="border-b border-slate-100 px-3 py-2">
                        {trip.distance_km != null ? `${Number(trip.distance_km)} km` : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
    </ModulePageShell>
  );
}
