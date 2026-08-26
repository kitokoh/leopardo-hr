'use client';

import { useCallback, useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Plus, RefreshCw, Lock, Loader2, CalendarRange, AlertTriangle } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t, interpolate } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface FiscalYear {
  year: number;
  status: 'open' | 'closed';
  closed_at: string | null;
  closed_by: string | null;
}

/**
 * #5534 — Exercices comptables : liste, ouverture, clôture avec avertissement
 * (report à nouveau, irréversible).
 */
export default function FiscalYearsPage() {
  const locale = getPreferredLocale();
  const [years, setYears] = useState<FiscalYear[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showOpen, setShowOpen] = useState(false);
  const [newYear, setNewYear] = useState(new Date().getFullYear());
  const [busy, setBusy] = useState(false);
  const [closing, setClosing] = useState<FiscalYear | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/accounting/fiscal-years');
      const body = await res.json();
      setYears(body.data || []);
    } catch {
      setError(t(locale, 'accountingModule.fyError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const openYear = async () => {
    setBusy(true);
    setError(null);
    try {
      await apiFetch('/accounting/fiscal-years', {
        method: 'POST',
        body: JSON.stringify({ year: newYear }),
      });
      setShowOpen(false);
      await load();
    } catch {
      setError(t(locale, 'accountingModule.fyOpenError'));
    } finally {
      setBusy(false);
    }
  };

  const closeYear = async () => {
    if (!closing) return;
    setBusy(true);
    setError(null);
    try {
      await apiFetch(`/accounting/fiscal-years/${closing.year}/close`, { method: 'POST' });
      setClosing(null);
      await load();
    } catch {
      setError(t(locale, 'accountingModule.fyCloseError'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.fyTitle')}
      subtitle={t(locale, 'accountingModule.fySubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      {error && (
        <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          <RefreshCw className="h-4 w-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="flex justify-end">
        <button
          onClick={() => setShowOpen((v) => !v)}
          className="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-amber-600"
        >
          <Plus className="h-4 w-4" />
          {t(locale, 'accountingModule.fyOpen')}
        </button>
      </div>

      {showOpen && (
        <motion.section initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="rounded-2xl border border-amber-200 bg-amber-50/50 p-5">
          <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
            <CalendarRange className="h-4 w-4 text-amber-600" />
            {t(locale, 'accountingModule.fyOpenTitle')}
          </h3>
          <div className="flex flex-wrap items-center gap-3">
            <input
              type="number"
              value={newYear}
              onChange={(e) => setNewYear(Number(e.target.value))}
              className="w-32 rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            />
            <button
              onClick={() => void openYear()}
              disabled={busy || newYear < 2000 || newYear > 2100}
              className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-600 disabled:opacity-50"
            >
              {busy && <Loader2 className="h-4 w-4 animate-spin" />}
              {t(locale, 'accountingModule.fyOpen')}
            </button>
            <button onClick={() => setShowOpen(false)} className="rounded-xl border border-app-border bg-white px-4 py-2.5 text-sm font-bold text-slate-600">
              {t(locale, 'accountingModule.chartCancel')}
            </button>
          </div>
        </motion.section>
      )}

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-app-border bg-transparent/50">
              <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.fyYear')}</th>
              <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.fyStatus')}</th>
              <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.fyClosedAt')}</th>
              <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500"> </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-app-border">
            {loading ? (
              <tr><td colSpan={4} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.loading')}</td></tr>
            ) : years.length === 0 ? (
              <tr><td colSpan={4} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.fyEmpty')}</td></tr>
            ) : (
              years.map((year) => (
                <tr key={year.year} className="transition-colors hover:bg-transparent/60">
                  <td className="px-6 py-4 font-mono text-sm font-black text-slate-800">{year.year}</td>
                  <td className="px-4 py-4">
                    <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${year.status === 'open' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                      {year.status === 'open' ? t(locale, 'accountingModule.fyStatusOpen') : t(locale, 'accountingModule.fyStatusClosed')}
                    </span>
                  </td>
                  <td className="px-4 py-4 text-xs text-slate-500">{year.closed_at ? new Date(year.closed_at).toLocaleDateString() : '—'}</td>
                  <td className="px-6 py-4 text-right">
                    {year.status === 'open' && (
                      <button
                        onClick={() => setClosing(year)}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100"
                      >
                        <Lock className="h-3 w-3" />
                        {t(locale, 'accountingModule.fyClose')}
                      </button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </section>

      {closing && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" role="dialog" aria-modal="true">
          <div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl">
            <div className="mb-3 flex items-center gap-2 text-rose-600">
              <AlertTriangle className="h-5 w-5" />
              <h3 className="text-lg font-black text-slate-900">{interpolate(t(locale, 'accountingModule.fyCloseConfirmTitle'), { year: closing.year })}</h3>
            </div>
            <p className="text-sm text-slate-600">{t(locale, 'accountingModule.fyCloseConfirmBody')}</p>
            <div className="mt-6 flex justify-end gap-2">
              <button onClick={() => setClosing(null)} disabled={busy} className="rounded-xl border border-app-border px-4 py-2.5 text-sm font-bold text-slate-600">
                {t(locale, 'accountingModule.chartCancel')}
              </button>
              <button
                onClick={() => void closeYear()}
                disabled={busy}
                className="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-rose-700 disabled:opacity-50"
              >
                {busy && <Loader2 className="h-4 w-4 animate-spin" />}
                {t(locale, 'accountingModule.fyConfirm')}
              </button>
            </div>
          </div>
        </div>
      )}
    </ModulePageShell>
  );
}
