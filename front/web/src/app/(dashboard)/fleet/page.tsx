'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, ExternalLink, Gauge, MapPin, Truck, Wrench } from 'lucide-react';
import { ModulePageShell } from '@/components/module-page-shell';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * #7400 — Module FLOTTE : surface CLIENT.
 *
 * L'API existait (`/api/v1/vehicles`, `/fleet/overview`, `/fleet/live-map`,
 * `/fleet/reports/*`, BC-19 DEVICE) et l'admin plateforme avait sa vue, mais
 * AUCUNE page ne l'exposait au client : le propriétaire ne pouvait pas voir ses
 * véhicules dans son espace.
 *
 * Pas de carte interactive embarquée : le web client n'embarque aucune
 * bibliothèque cartographique (l'admin plateforme utilise Leaflet de son côté).
 * Ajouter Leaflet ici suppose de trancher une dépendance, la CSP (`connect-src`
 * vers un fournisseur de tuiles) et le fournisseur — décision hors périmètre.
 * La vue affiche donc les coordonnées et propose un lien vers OpenStreetMap,
 * sans dépendance ni appel tiers.
 */

type Overview = {
  total_vehicles?: number;
  active?: number;
  in_maintenance?: number;
  decommissioned?: number;
  unacknowledged_alerts?: number;
};

type Vehicle = {
  id: number;
  plate_number: string;
  brand?: string | null;
  model?: string | null;
  type?: string | null;
  status?: string | null;
  mileage?: number | null;
  fuel_type?: string | null;
  assigned_driver_id?: number | null;
};

type LivePosition = {
  vehicle_id: number;
  plate_number: string;
  brand?: string | null;
  model?: string | null;
  type?: string | null;
  position?: {
    latitude?: number | string | null;
    longitude?: number | string | null;
    speed?: number | string | null;
    fixTime?: string | null;
    deviceTime?: string | null;
  } | null;
};

type MileageRow = {
  vehicle_id: number;
  total_km?: string | number | null;
  trip_count?: number | null;
  avg_speed?: string | number | null;
};

const STATUS_STYLES: Record<string, string> = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
  decommissioned: 'border-slate-200 bg-slate-100 text-slate-600',
};

function osmLink(lat: number | string, lng: number | string): string {
  return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=13/${lat}/${lng}`;
}

export default function FleetPage() {
  const locale = getPreferredLocale();
  const c = useCallback(
    (key: string, fallback: string) => t(locale, `fleet.${key}`, fallback),
    [locale],
  );

  const [overview, setOverview] = useState<Overview | null>(null);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [positions, setPositions] = useState<LivePosition[]>([]);
  const [mileage, setMileage] = useState<MileageRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [overviewRes, vehiclesRes, liveRes, mileageRes] = await Promise.all([
        apiFetch('/fleet/overview'),
        apiFetch('/vehicles?per_page=100'),
        apiFetch('/fleet/live-map'),
        apiFetch('/fleet/reports/mileage'),
      ]);

      const overviewBody = (await overviewRes.json()) as { data?: Overview };
      const vehiclesBody = (await vehiclesRes.json()) as { data?: Vehicle[] };
      const liveBody = (await liveRes.json()) as { data?: LivePosition[] };
      const mileageBody = (await mileageRes.json()) as { data?: MileageRow[] };

      setOverview(overviewBody.data ?? null);
      setVehicles(vehiclesBody.data ?? []);
      setPositions(liveBody.data ?? []);
      setMileage(mileageBody.data ?? []);
    } catch {
      setError(c('error', 'Impossible de charger les données de la flotte.'));
    } finally {
      setLoading(false);
    }
  }, [c]);

  useEffect(() => {
    void load();
  }, [load]);

  const mileageByVehicle = new Map(mileage.map((row) => [row.vehicle_id, row]));

  const stats = [
    { key: 'statTotal', label: c('statTotal', 'Véhicules'), value: overview?.total_vehicles ?? vehicles.length, icon: Truck, tone: 'text-slate-700' },
    { key: 'statActive', label: c('statActive', 'En service'), value: overview?.active ?? 0, icon: Gauge, tone: 'text-emerald-600' },
    { key: 'statMaintenance', label: c('statMaintenance', 'En maintenance'), value: overview?.in_maintenance ?? 0, icon: Wrench, tone: 'text-amber-600' },
    { key: 'statAlerts', label: c('statAlerts', 'Alertes'), value: overview?.unacknowledged_alerts ?? 0, icon: AlertTriangle, tone: 'text-red-600' },
  ];

  return (
    <ModulePageShell title={c('title', 'Flotte')} subtitle={c('subtitle', 'Vos véhicules, leur position et leurs itinéraires.')}>
      <div className="space-y-6">
        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
        )}

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {stats.map(({ key, label, value, icon: Icon, tone }) => (
            <div key={key} className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <p className="text-xs font-bold uppercase tracking-wider text-slate-500">{label}</p>
                <Icon className={`h-5 w-5 ${tone}`} />
              </div>
              <p className="mt-2 text-3xl font-black text-slate-900">{loading ? '—' : value}</p>
            </div>
          ))}
        </div>

        {/* Positions live ------------------------------------------------- */}
        <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-slate-900">{c('liveTitle', 'Positions en direct')}</h2>
          <p className="mt-1 text-sm text-slate-500">{c('liveHint', 'Dernière position connue de chaque véhicule suivi.')}</p>

          {positions.length === 0 ? (
            <p className="mt-4 text-sm text-slate-500">{c('noTracker', 'Aucun traceur appairé')}</p>
          ) : (
            <div className="mt-4 overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wider text-slate-500">
                    <th className="py-2 pr-4">{c('vehicle', 'Véhicule')}</th>
                    <th className="py-2 pr-4">{c('plate', 'Immatriculation')}</th>
                    <th className="py-2 pr-4"><MapPin className="h-4 w-4" /></th>
                    <th className="py-2 pr-4">{c('speed', 'Vitesse')}</th>
                    <th className="py-2 pr-4">{c('lastFix', 'Dernier relevé')}</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {positions.map((row) => {
                    const p = row.position;
                    const hasCoords = p?.latitude != null && p?.longitude != null;
                    return (
                      <tr key={row.vehicle_id} className="border-t border-app-border">
                        <td className="py-3 pr-4 font-medium text-slate-900">
                          <Link className="hover:underline" href={`/fleet/${row.vehicle_id}`}>
                            {[row.brand, row.model].filter(Boolean).join(' ') || `#${row.vehicle_id}`}
                          </Link>
                        </td>
                        <td className="py-3 pr-4 text-slate-700">{row.plate_number}</td>
                        <td className="py-3 pr-4 text-slate-700">
                          {hasCoords ? `${p?.latitude}, ${p?.longitude}` : c('noTracker', 'Aucun traceur appairé')}
                        </td>
                        <td className="py-3 pr-4 text-slate-700">
                          {p?.speed != null ? `${Number(p.speed).toFixed(1)} kn` : '—'}
                        </td>
                        <td className="py-3 pr-4 text-slate-500">
                          {p?.fixTime ? new Date(p.fixTime).toLocaleString() : '—'}
                        </td>
                        <td className="py-3">
                          {hasCoords && (
                            <a
                              className="inline-flex items-center gap-1 text-emerald-700 hover:underline"
                              href={osmLink(p!.latitude as number, p!.longitude as number)}
                              target="_blank"
                              rel="noopener noreferrer"
                            >
                              <ExternalLink className="h-4 w-4" />
                              {c('openMap', 'Voir sur la carte')}
                            </a>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {/* Liste des véhicules --------------------------------------------- */}
        <section className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-slate-900">{c('vehicles', 'Véhicules')}</h2>

          {vehicles.length === 0 && !loading ? (
            <p className="mt-4 text-sm text-slate-500">{c('empty', 'Aucun véhicule enregistré pour le moment.')}</p>
          ) : (
            <div className="mt-4 overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs uppercase tracking-wider text-slate-500">
                    <th className="py-2 pr-4">{c('plate', 'Immatriculation')}</th>
                    <th className="py-2 pr-4">{c('vehicle', 'Véhicule')}</th>
                    <th className="py-2 pr-4">{c('status', 'Statut')}</th>
                    <th className="py-2 pr-4">{c('mileage', 'Kilométrage')}</th>
                    <th className="py-2 pr-4">{c('fuelType', 'Carburant')}</th>
                    <th className="py-2 pr-4">{c('driver', 'Chauffeur')}</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {vehicles.map((v) => (
                    <tr key={v.id} className="border-t border-app-border">
                      <td className="py-3 pr-4 font-medium text-slate-900">{v.plate_number}</td>
                      <td className="py-3 pr-4 text-slate-700">
                        {[v.brand, v.model].filter(Boolean).join(' ') || '—'}
                        {v.type ? <span className="ml-2 text-slate-400">({v.type})</span> : null}
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${STATUS_STYLES[v.status ?? ''] ?? 'border-slate-200 bg-slate-100 text-slate-600'}`}>
                          {v.status ?? '—'}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-slate-700">
                        {v.mileage != null ? `${v.mileage.toLocaleString()} km` : '—'}
                        {mileageByVehicle.get(v.id)?.total_km != null && (
                          <span className="ml-2 text-xs text-slate-400">
                            (+{Number(mileageByVehicle.get(v.id)?.total_km).toLocaleString()} km,{' '}
                            {mileageByVehicle.get(v.id)?.trip_count ?? 0} {c('trips', 'trajets')})
                          </span>
                        )}
                      </td>
                      <td className="py-3 pr-4 text-slate-700">{v.fuel_type ?? '—'}</td>
                      <td className="py-3 pr-4 text-slate-700">
                        {v.assigned_driver_id != null ? `#${v.assigned_driver_id}` : c('notAssigned', 'Non affecté')}
                      </td>
                      <td className="py-3">
                        <Link className="font-medium text-emerald-700 hover:underline" href={`/fleet/${v.id}`}>
                          {c('viewDetail', 'Voir la fiche')}
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>
    </ModulePageShell>
  );
}
