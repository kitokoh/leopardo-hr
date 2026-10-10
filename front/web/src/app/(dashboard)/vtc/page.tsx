'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, CarTaxiFront, RefreshCw, Users } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { RideCard } from './_components/RideCard';
import type { VtcDispatchRide } from './_components/vtc-shared';

/**
 * BC-34 VTC (épic #8349, VTC-07/#8363) — console dispatch : courses actives
 * du tenant, rafraîchies par polling (15 s — v1 sans websocket, spec §5.5).
 *
 * Source : `GET /v1/vtc/dispatch/rides` (VTC-06, rôle vtc.dispatcher).
 * Sécurité : la page est réservée aux tenants dont le flag `vtc` est actif —
 * un 403 `MODULE_DISABLED` (gate serveur fail-closed) affiche l'état dédié
 * au lieu d'une erreur générique.
 */

const POLL_INTERVAL_MS = 15_000;

export default function VtcDispatchPage() {
  const locale = getPreferredLocale();

  const [rides, setRides] = useState<VtcDispatchRide[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await apiFetch('/vtc/dispatch/rides');
      const payload = await res.json();
      setRides(Array.isArray(payload?.data) ? payload.data : []);
    } catch (err) {
      if (err instanceof ApiError && err.status === 403) {
        setError(t(locale, 'vtc.moduleDisabled'));
      } else {
        setError(t(locale, 'vtc.loadError'));
      }
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
    const timer = setInterval(() => void load(), POLL_INTERVAL_MS);
    return () => clearInterval(timer);
  }, [load]);

  return (
    <ModulePageShell
      title={t(locale, 'vtc.title')}
      subtitle={t(locale, 'vtc.subtitle')}
      icon={CarTaxiFront}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm font-semibold text-slate-600">
          {rides.length} {t(locale, 'vtc.rideCount')} · {t(locale, 'vtc.autoRefresh')}
        </p>
        <div className="flex items-center gap-2">
          <Link
            href="/vtc/drivers"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-white"
          >
            <Users className="h-4 w-4" aria-hidden />
            {t(locale, 'vtc.openDrivers')}
          </Link>
          <Button variant="secondary" onClick={() => void load()} disabled={loading}>
            <RefreshCw className="mr-2 h-4 w-4" aria-hidden />
            {t(locale, 'vtc.retry')}
          </Button>
        </div>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">{t(locale, 'vtc.loading')}</p>
      ) : null}

      {error ? (
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden />
          <p role="alert" className="text-sm font-semibold">
            {error}
          </p>
        </div>
      ) : null}

      {!loading && !error && rides.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-8 text-center">
          <p className="text-base font-bold text-slate-700">{t(locale, 'vtc.emptyRides')}</p>
          <p className="mt-1 text-sm text-slate-500">{t(locale, 'vtc.emptyRidesHint')}</p>
        </div>
      ) : null}

      <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {rides.map((ride) => (
          <RideCard key={ride.id} ride={ride} locale={locale} />
        ))}
      </ul>
    </ModulePageShell>
  );
}
