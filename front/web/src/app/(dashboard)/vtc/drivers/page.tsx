'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, CarTaxiFront, RefreshCw } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { DriverCard } from '../_components/DriverCard';
import type { VtcDriver } from '../_components/vtc-shared';

/**
 * BC-34 VTC (épic #8349, VTC-07/#8363) — vue chauffeurs de la console
 * dispatch : statuts et dernières positions connues, polling 15 s.
 *
 * Source : `GET /v1/vtc/dispatch/drivers` (VTC-06, rôle vtc.dispatcher).
 * La carte interactive (clustering) est explicitement hors scope v1 — les
 * positions sont affichées en coordonnées textuelles.
 */

const POLL_INTERVAL_MS = 15_000;

export default function VtcDriversPage() {
  const locale = getPreferredLocale();

  const [drivers, setDrivers] = useState<VtcDriver[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await apiFetch('/vtc/dispatch/drivers');
      const payload = await res.json();
      setDrivers(Array.isArray(payload?.data) ? payload.data : []);
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
      title={t(locale, 'vtc.drivers')}
      subtitle={t(locale, 'vtc.subtitle')}
      icon={CarTaxiFront}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm font-semibold text-slate-600">
          {drivers.length} {t(locale, 'vtc.driverCount')} · {t(locale, 'vtc.autoRefresh')}
        </p>
        <div className="flex items-center gap-2">
          <Link
            href="/vtc"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-white"
          >
            {t(locale, 'vtc.openRides')}
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

      {!loading && !error && drivers.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-8 text-center">
          <p className="text-base font-bold text-slate-700">{t(locale, 'vtc.emptyDrivers')}</p>
          <p className="mt-1 text-sm text-slate-500">{t(locale, 'vtc.emptyDriversHint')}</p>
        </div>
      ) : null}

      <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {drivers.map((driver) => (
          <DriverCard key={driver.id} driver={driver} locale={locale} />
        ))}
      </ul>
    </ModulePageShell>
  );
}
