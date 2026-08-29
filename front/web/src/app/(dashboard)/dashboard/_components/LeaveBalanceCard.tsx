'use client';

import { useEffect, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { ArrowRight, CalendarDays, Loader2 } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const emptySubscribe = () => () => {};

/** Solde de congés (GET /me/leave-balances) — issue #5694. */
type LeaveBalance = {
  id: number;
  absence_type_id?: number;
  balance?: number | string;
  used?: number | string;
  pending?: number | string;
  year?: number;
  absence_type?: {
    id?: number;
    name?: string;
    code?: string;
  } | null;
};

type BalancesPayload = {
  data?: LeaveBalance[];
};

function toNumber(value: number | string | undefined): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

/**
 * Carte « Mon solde de congés » — dashboard employé (issue #5694).
 *
 * Affiche les jours restants par type d'absence pour l'employé connecté,
 * depuis `GET /me/leave-balances` (même source que l'app mobile et que le
 * formulaire web de demande de congé). Restant = balance − used − pending.
 */
export function LeaveBalanceCard() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const [balances, setBalances] = useState<LeaveBalance[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        const response = await apiFetch('/me/leave-balances');
        const payload = await response.json() as BalancesPayload;

        if (!active) {
          return;
        }

        setBalances(Array.isArray(payload.data) ? payload.data : []);
      } catch (err) {
        if (!active) {
          return;
        }

        setError(err instanceof ApiError ? err.message : i18nT(locale, 'dashboard.leave_balance_error'));
      }
    }

    void load();

    return () => {
      active = false;
    };
  }, [locale]);

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rh to-rh-dark">
            <CalendarDays className="h-5 w-5 text-white" aria-hidden="true" />
          </div>
          <div>
            <h2 className="font-bold text-slate-950">{i18nT(locale, 'dashboard.leave_balance_title')}</h2>
            <p className="text-xs text-slate-500">{i18nT(locale, 'dashboard.leave_balance_subtitle')}</p>
          </div>
        </div>
        <Link
          href="/absences"
          className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-rh transition hover:bg-rh/10"
        >
          {i18nT(locale, 'dashboard.see_absences')}
          <ArrowRight className="h-3 w-3" aria-hidden="true" />
        </Link>
      </div>

      <div className="mt-4">
        {error ? (
          <p className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
        ) : balances === null ? (
          <div className="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            {i18nT(locale, 'dashboard.leave_balance_loading')}
          </div>
        ) : balances.length === 0 ? (
          <p className="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-500">
            {i18nT(locale, 'dashboard.leave_balance_empty')}
          </p>
        ) : (
          <ul className="space-y-2">
            {balances.map((balance) => {
              const name = balance.absence_type?.name ?? i18nT(locale, 'dashboard.leave_balance_unknown');
              const remaining = toNumber(balance.balance) - toNumber(balance.used) - toNumber(balance.pending);
              const used = toNumber(balance.used);

              return (
                <li
                  key={balance.id}
                  className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2"
                >
                  <span className="text-sm font-semibold text-slate-700">{name}</span>
                  <span className="text-xs text-slate-500">
                    {formatMessage(i18nT(locale, 'dashboard.leave_balance_available'), { days: Math.max(0, remaining) })}
                    {used > 0
                      ? ` · ${formatMessage(i18nT(locale, 'dashboard.leave_balance_used'), { days: used })}`
                      : ''}
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </section>
  );
}

// Interpolation légère des clés i18n à trous ({days}) — catalogue JSON brut.
function formatMessage(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (match, name: string) =>
    name in values ? String(values[name]) : match
  );
}
