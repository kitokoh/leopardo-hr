'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { ArrowLeft, ExternalLink, MapPin } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * #7400 — Fiche véhicule (surface client).
 *
 * Le cœur du besoin exprimé : « suivre l'itinéraire de mes véhicules de
 * service ». Les itinéraires viennent de `GET /vehicles/{id}/trips`, alimenté
 * par la synchronisation Traccar — et lisibles depuis #7399 (la ressource
 * exposait `start_location`/`end_location`/`purpose`, champs inexistants, au
 * lieu de `start_address`/`end_address`/distance/durée/vitesses).
 */

type Vehicle = {
  id: number;
  plate_number: string;
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
};

type Trip = {
  id: number;
  start_time?: string | null;
  end_time?: string | null;
  start_address?: string | null;
  end_address?: string | null;
  distance_km?: string | number | null;
  duration_minutes?: number | null;
  max_speed_kmh?: string | number | null;
  avg_speed_kmh?: string | number | null;
};

type Alert = {
  id: number;
  type?: string | null;
  severity?: string | null;
  message?: string | null;
  acknowledged?: boolean | null;
  created_at?: string | null;
};

type Maintenance = {
  id: number;
  type?: string | null;
  description?: string | null;
  service_date?: string | null;
  cost?: string | number | null;
  currency?: string | null;
  provider?: string | null;
  next_service_date?: string | null;
};

const SEVERITY_STYLES: Record<string, string> = {
  critical: 'border-red-200 bg-red-50 text-red-700',
  high: 'border-orange-200 bg-orange-50 text-orange-700',
  medium: 'border-amber-200 bg-amber-50 text-amber-700',
  low: 'border-slate-200 bg-slate-100 text-slate-600',
};

function osmLink(lat: number | string, lng: number | string): string {
  return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=13/${lat}/${lng}`;
}

function minutesToLabel(minutes?: number | null): string {
  if (minutes == null) {
    return '—';
  }
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return h > 0 ? `${h} h ${String(m).padStart(2, '0')}` : `${m} min`;
}

export default function FleetVehiclePage() {
  const params = useParams<{ id: string }>();
  const vehicleId = params?.id;
  const locale = getPreferredLocale();
  const c = useCallback(
    (key: string, fallback: string) => t(locale, `fleet.${key}`, fallback),
    [locale],
  );

  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  const [position, setPosition] = useState<Position | null>(null);
  const [trips, setTrips] = useState<Trip[]>([]);
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [maintenance, setMaintenance] = useState<Maintenance[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!vehicleId) {
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [vehicleRes, tripsRes, alertsRes, maintenanceRes] = await Promise.all([
        apiFetch(`/vehicles/${vehicleId}`),
        apiFetch(`/vehicles/${vehicleId}/trips?per_page=50`),
        apiFetch(`/vehicles/${vehicleId}/alerts?per_page=50`),
        apiFetch(`/vehicles/${vehicleId}/maintenance?per_page=50`),
      ]);

      const vehicleBody = (await vehicleRes.json()) as { data?: Vehicle };
      const tripsBody = (await tripsRes.json()) as { data?: Trip[] };
      const alertsBody = (await alertsRes.json()) as { data?: Alert[] };
      const maintenanceBody = (await maintenanceRes.json()) as { data?: Maintenance[] };

      setVehicle(vehicleBody.data ?? null);
      setTrips(tripsBody.data ?? []);
      setAlerts(alertsBody.data ?? []);
      setMaintenance(maintenanceBody.data ?? []);
    } catch {
      setError(c('error', 'Impossible de charger les données de la flotte.'));
    } finally {
      setLoading(false);
    }

    // La position est best-effort : sans traceur appairé, l'API répond par un
    // message (pas de `data`). Son échec ne doit pas masquer la fiche.
    try {
      const positionRes = await apiFetch(`/vehicles/${vehicleId}/position`);
      const body = (await positionRes.json()) as { data?: Position };
      setPosition(body.data ?? null);
    } catch {
      setPosition(null);
    }
  }, [c, vehicleId]);

  useEffect(() => {
    void load();
  }, [load]);

  const hasCoords = position?.latitude != null && position?.longitude != null;

  return (
    <ModulePageShell title={vehicle?.plate_number ?? c('vehicle', 'Véhicule')} subtitle={c('title', 'Flotte')}>
      <div className="space-y-6">
        <Link className="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900" href="/fleet">
          <ArrowLeft className="h-4 w-4" />
          {c('back', 'Retour à la flotte')}
        </Link>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
        )}

        {!loading && !vehicle && !error && (
          <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            {c('notFound', 'Véhicule introuvable.')}
          </div>
        )}

        {vehicle && (
          <>
            <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                  [c('vehicle', 'Véhicule'), [vehicle.brand, vehicle.model].filter(Boolean).join(' ') || '—'],
                  [c('status', 'Statut'), vehicle.status ?? '—'],
                  [c('mileage', 'Kilométrage'), vehicle.mileage != null ? `${vehicle.mileage.toLocaleString()} km` : '—'],
                  [c('fuelType', 'Carburant'), vehicle.fuel_type ?? '—'],
                  [c('driver', 'Chauffeur'), vehicle.assigned_driver_id != null ? `#${vehicle.assigned_driver_id}` : c('notAssigned', 'Non affecté')],
                  [c('insuranceExpiry', 'Assurance'), vehicle.insurance_expiry ?? '—'],
                  [c('techControlExpiry', 'Contrôle technique'), vehicle.technical_control_expiry ?? '—'],
                ].map(([label, value]) => (
                  <div key={String(label)}>
                    <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{label}</p>
                    <p className="mt-1 text-sm font-semibold text-slate-900">{value}</p>
                  </div>
                ))}
              </div>
            </section>

            <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
              <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                <MapPin className="h-5 w-5 text-emerald-600" />
                {c('liveTitle', 'Positions en direct')}
              </h2>
              {hasCoords ? (
                <div className="mt-3 space-y-1 text-sm text-slate-700">
                  <p>
                    {position?.latitude}, {position?.longitude}
                  </p>
                  <p className="text-slate-500">
                    {c('speed', 'Vitesse')} : {position?.speed != null ? `${Number(position.speed).toFixed(1)} kn` : '—'}
                    {' · '}
                    {c('lastFix', 'Dernier relevé')} : {position?.fixTime ? new Date(position.fixTime).toLocaleString() : '—'}
                  </p>
                  <a
                    className="inline-flex items-center gap-1 text-emerald-700 hover:underline"
                    href={osmLink(position!.latitude as number, position!.longitude as number)}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <ExternalLink className="h-4 w-4" />
                    {c('openMap', 'Voir sur la carte')}
                  </a>
                </div>
              ) : (
                <p className="mt-3 text-sm text-slate-500">{c('noTracker', 'Aucun traceur appairé')}</p>
              )}
            </section>

            {/* Itinéraires -------------------------------------------------- */}
            <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
              <h2 className="text-lg font-bold text-slate-900">{c('itineraries', 'Itinéraires')}</h2>
              <p className="mt-1 text-sm text-slate-500">{c('itinerariesHint', 'Trajets enregistrés par le traceur.')}</p>

              {trips.length === 0 ? (
                <p className="mt-4 text-sm text-slate-500">{c('noTrips', 'Aucun trajet enregistré.')}</p>
              ) : (
                <div className="mt-4 overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs uppercase tracking-wider text-slate-500">
                        <th className="py-2 pr-4">{c('from', 'Départ')}</th>
                        <th className="py-2 pr-4">{c('to', 'Arrivée')}</th>
                        <th className="py-2 pr-4">{c('distance', 'Distance')}</th>
                        <th className="py-2 pr-4">{c('duration', 'Durée')}</th>
                        <th className="py-2 pr-4">{c('speedMax', 'Vitesse max')}</th>
                        <th className="py-2">{c('speedAvg', 'Vitesse moyenne')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {trips.map((trip) => (
                        <tr key={trip.id} className="border-t border-app-border align-top">
                          <td className="py-3 pr-4 text-slate-700">
                            {trip.start_address ?? '—'}
                            <span className="block text-xs text-slate-400">
                              {trip.start_time ? new Date(trip.start_time).toLocaleString() : ''}
                            </span>
                          </td>
                          <td className="py-3 pr-4 text-slate-700">
                            {trip.end_address ?? '—'}
                            <span className="block text-xs text-slate-400">
                              {trip.end_time ? new Date(trip.end_time).toLocaleString() : ''}
                            </span>
                          </td>
                          <td className="py-3 pr-4 font-semibold text-slate-900">
                            {trip.distance_km != null ? `${Number(trip.distance_km).toFixed(1)} km` : '—'}
                          </td>
                          <td className="py-3 pr-4 text-slate-700">{minutesToLabel(trip.duration_minutes)}</td>
                          <td className="py-3 pr-4 text-slate-700">
                            {trip.max_speed_kmh != null ? `${Number(trip.max_speed_kmh).toFixed(1)} km/h` : '—'}
                          </td>
                          <td className="py-3 text-slate-700">
                            {trip.avg_speed_kmh != null ? `${Number(trip.avg_speed_kmh).toFixed(1)} km/h` : '—'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            {/* Alertes ------------------------------------------------------ */}
            <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
              <h2 className="text-lg font-bold text-slate-900">{c('alerts', 'Alertes')}</h2>
              {alerts.length === 0 ? (
                <p className="mt-4 text-sm text-slate-500">{c('noAlerts', 'Aucune alerte.')}</p>
              ) : (
                <ul className="mt-4 space-y-2">
                  {alerts.map((alert) => (
                    <li key={alert.id} className="flex flex-wrap items-center gap-3 rounded-xl border border-app-border px-3 py-2">
                      <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${SEVERITY_STYLES[alert.severity ?? ''] ?? 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                        {alert.severity ?? alert.type ?? '—'}
                      </span>
                      <span className="text-sm text-slate-700">{alert.message ?? alert.type ?? '—'}</span>
                      <span className="text-xs text-slate-400">
                        {alert.created_at ? new Date(alert.created_at).toLocaleString() : ''}
                      </span>
                      <span className="ml-auto text-xs font-semibold text-slate-500">
                        {alert.acknowledged ? c('acknowledged', 'Acquittée') : c('pending', 'À traiter')}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {/* Maintenance -------------------------------------------------- */}
            <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
              <h2 className="text-lg font-bold text-slate-900">{c('maintenance', 'Maintenance')}</h2>
              {maintenance.length === 0 ? (
                <p className="mt-4 text-sm text-slate-500">{c('noMaintenance', 'Aucune intervention enregistrée.')}</p>
              ) : (
                <div className="mt-4 overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs uppercase tracking-wider text-slate-500">
                        <th className="py-2 pr-4">{c('serviceDate', 'Date')}</th>
                        <th className="py-2 pr-4">{c('maintenance', 'Maintenance')}</th>
                        <th className="py-2 pr-4">{c('cost', 'Coût')}</th>
                        <th className="py-2 pr-4">{c('provider', 'Prestataire')}</th>
                        <th className="py-2">{c('nextService', 'Prochain entretien')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {maintenance.map((row) => (
                        <tr key={row.id} className="border-t border-app-border">
                          <td className="py-3 pr-4 text-slate-700">{row.service_date ?? '—'}</td>
                          <td className="py-3 pr-4 font-medium text-slate-900">{row.type ?? '—'}</td>
                          <td className="py-3 pr-4 text-slate-700">
                            {row.cost != null ? `${Number(row.cost).toLocaleString()} ${row.currency ?? ''}` : '—'}
                          </td>
                          <td className="py-3 pr-4 text-slate-700">{row.provider ?? '—'}</td>
                          <td className="py-3 text-slate-700">{row.next_service_date ?? '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </>
        )}

        {loading && <p className="text-sm text-slate-500">{c('loading', 'Chargement…')}</p>}
      </div>
    </ModulePageShell>
  );
}
