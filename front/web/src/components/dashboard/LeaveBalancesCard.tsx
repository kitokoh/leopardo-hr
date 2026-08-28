'use client';

import { useCallback, useEffect, useState } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { AppLocale, getCopy } from '@/lib/i18n';

interface LeaveBalanceRow {
  id: number;
  absence_type_id: number;
  balance: number;
  used: number;
  pending: number;
  year: number;
  absence_type: { id: number; name: string; code: string } | null;
}

interface LeaveBalancesPayload {
  data: LeaveBalanceRow[];
}

/**
 * Carte « Soldes de congés » (issue #5694).
 *
 * Affiche les soldes de l'utilisateur courant pour l'année en cours
 * (GET /me/leave-balances — self-service, employé comme manager).
 * Disponible = balance − used − pending (même règle que le backend,
 * AbsenceService::currentAvailableBalance).
 */
export default function LeaveBalancesCard({ locale }: { locale: AppLocale }) {
  const labels = getCopy(locale).leaveBalances;
  const [rows, setRows] = useState<LeaveBalanceRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiFetch('/me/leave-balances');
      const payload = (await response.json()) as LeaveBalancesPayload;
      setRows(payload.data ?? []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setLoading(false);
    }
  }, [labels.loadError]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" aria-busy="true">
        <h2 className="text-lg font-semibold text-slate-900">{labels.title}</h2>
        <p className="mt-3 text-sm text-slate-500">{labels.loading}</p>
      </section>
    );
  }

  if (error !== null) {
    return (
      <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">{labels.title}</h2>
        <p className="mt-3 text-sm text-red-600" role="alert">{error}</p>
      </section>
    );
  }

  return (
    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-lg font-semibold text-slate-900">{labels.title}</h2>
      <p className="mt-0.5 text-sm text-slate-500">{labels.subtitle}</p>

      {rows.length === 0 ? (
        <p className="mt-4 text-sm text-slate-500">{labels.noData}</p>
      ) : (
        <ul className="mt-4 divide-y divide-slate-100">
          {rows.map((row) => {
            const available = row.balance - row.used - row.pending;
            return (
              <li key={row.id} className="flex items-center justify-between gap-3 py-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-slate-900">
                    {row.absence_type?.name ?? row.absence_type_id}
                  </p>
                  <p className="text-xs text-slate-500">
                    {labels.used} {row.used} {labels.daysShort} · {labels.pending} {row.pending}{' '}
                    {labels.daysShort}
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-xl font-bold text-emerald-600">
                    {available} <span className="text-xs font-medium text-slate-400">{labels.daysShort}</span>
                  </p>
                  <p className="text-xs text-slate-500">{labels.available}</p>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
