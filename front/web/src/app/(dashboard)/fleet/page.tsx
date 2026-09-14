'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, RefreshCw, Truck } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';

/**
 * #7400 — Flotte de l'agence : liste des véhicules de service.
 *
 * Le suivi des véhicules n'existait que côté admin plateforme
 * (`front/admin-dashboard/src/views/fleet`). Ici, lecture seule appuyée sur
 * l'API flotte existante (`GET /vehicles`, réservée aux managers —
 * `api.manager`, sécurité #2217) : immatriculation, marque/modèle, statut et
 * kilométrage, avec accès à la fiche véhicule (position live + itinéraires).
 *
 * Aucune surface d'écriture ici : la création/affectation de véhicule reste
 * hors périmètre de ce socle (#7400).
 */

type Vehicle = {
  id: number;
  plate_number?: string | null;
  brand?: string | null;
  model?: string | null;
  year?: number | null;
  type?: string | null;
  fuel_type?: string | null;
  status?: string | null;
  mileage?: number | null;
  assigned_driver_id?: number | null;
};

const STATUS_LABEL_KEY: Record<string, string> = {
  active: 'fleet.statusActive',
  maintenance: 'fleet.statusMaintenance',
  decommissioned: 'fleet.statusDecommissioned',
};

export default function FleetPage() {
  const locale = getPreferredLocale();

  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/vehicles?per_page=100');
      const payload = await res.json();
      setVehicles(Array.isArray(payload?.data) ? payload.data : []);
    } catch {
      setError(t(locale, 'fleet.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const statusLabel = (status?: string | null): string =>
    t(locale, STATUS_LABEL_KEY[status ?? ''] ?? 'fleet.statusUnknown');

  return (
    <ModulePageShell
      title={t(locale, 'fleet.title')}
      subtitle={t(locale, 'fleet.subtitle')}
      icon={Truck}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm font-semibold text-slate-600">
          {vehicles.length} {t(locale, 'fleet.count')}
        </p>
        <Button variant="secondary" onClick={() => void load()} disabled={loading}>
          <RefreshCw className="mr-2 h-4 w-4" aria-hidden />
          {t(locale, 'fleet.retry')}
        </Button>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">{t(locale, 'fleet.loading')}</p>
      ) : null}

      {error ? (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden />
          <p role="alert" className="text-sm font-semibold">
            {error}
          </p>
        </div>
      ) : null}

      {!loading && !error && vehicles.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-8 text-center">
          <p className="text-base font-bold text-slate-700">{t(locale, 'fleet.empty')}</p>
          <p className="mt-1 text-sm text-slate-500">{t(locale, 'fleet.emptyHint')}</p>
        </div>
      ) : null}

      <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {vehicles.map((vehicle) => (
          <li
            key={vehicle.id}
            className="rounded-2xl border border-white/30 bg-white/80 p-5 shadow-premium backdrop-blur-xl"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-lg font-black tracking-tight text-slate-950">
                  {vehicle.plate_number ?? '—'}
                </p>
                <p className="text-sm text-slate-600">
                  {[vehicle.brand, vehicle.model].filter(Boolean).join(' ') || '—'}
                </p>
              </div>
              <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                {statusLabel(vehicle.status)}
              </span>
            </div>

            <dl className="mt-4 space-y-1 text-sm text-slate-600">
              <div className="flex justify-between gap-3">
                <dt>{t(locale, 'fleet.mileage')}</dt>
                <dd className="font-semibold text-slate-800">
                  {vehicle.mileage != null ? `${vehicle.mileage} km` : '—'}
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

            <Link
              href={`/fleet/${vehicle.id}`}
              className="mt-4 inline-flex text-sm font-bold text-emerald-700 underline-offset-4 hover:underline"
            >
              {t(locale, 'fleet.open')}
            </Link>
          </li>
        ))}
      </ul>
    </ModulePageShell>
  );
}
