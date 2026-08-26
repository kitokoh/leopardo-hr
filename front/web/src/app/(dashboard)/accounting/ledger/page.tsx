'use client';

import { useCallback, useEffect, useState } from 'react';
import { Download, RefreshCw, ListOrdered } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface LedgerEntry {
  id: number;
  entry_date: string;
  account_code: string;
  account_label: string;
  debit: number;
  credit: number;
  piece: string;
  description: string;
  running_balance: number;
}

const currentPeriod = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const fmt = (n: number) =>
  new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

/**
 * #5534 — Grand livre : écritures par période avec running balance,
 * solde d'ouverture, filtre par compte, export FEC du module.
 */
export default function LedgerPage() {
  const locale = getPreferredLocale();
  const [period, setPeriod] = useState(currentPeriod());
  const [accountFilter, setAccountFilter] = useState('');
  const [entries, setEntries] = useState<LedgerEntry[]>([]);
  const [opening, setOpening] = useState(0);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const qs = new URLSearchParams({ period });
      if (accountFilter) qs.set('account_code', accountFilter);
      const res = await apiFetch(`/accounting/ledger?${qs.toString()}&per_page=100`);
      const body = await res.json();
      setEntries(body.data || []);
      setOpening(Number(body.meta?.opening_balance) || 0);
      setTotal(Number(body.meta?.total) || 0);
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setLoading(false);
    }
  }, [locale, period, accountFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  const exportFec = async () => {
    try {
      const res = await apiFetch(`/accounting/journal/export-fec?period=${period}`);
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `FEC-${period}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError(t(locale, 'accountingModule.fecError'));
    }
  };

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.ledgerTitle')}
      subtitle={t(locale, 'accountingModule.ledgerSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label className="flex items-center gap-2 text-sm font-bold text-slate-600">
          {t(locale, 'accountingModule.periodLabel')}
          <input
            type="month"
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
          />
        </label>
        <input
          value={accountFilter}
          onChange={(e) => setAccountFilter(e.target.value)}
          placeholder={t(locale, 'accountingModule.ledgerAccountFilter')}
          className="w-full max-w-xs rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
        />
        <button
          onClick={() => void exportFec()}
          className="inline-flex items-center gap-2 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-600"
        >
          <Download className="h-4 w-4" />
          {t(locale, 'accountingModule.ledgerExportFec')}
        </button>
        {error && (
          <button onClick={() => void load()} className="inline-flex items-center gap-1 text-xs font-bold text-red-600">
            <RefreshCw className="h-3 w-3" /> {t(locale, 'accountingModule.retry')}
          </button>
        )}
      </div>

      <section className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{t(locale, 'accountingModule.ledgerOpening')}</p>
          <p className={`mt-1 text-xl font-black ${opening < 0 ? 'text-red-600' : 'text-slate-900'}`}>{fmt(opening)}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{t(locale, 'accountingModule.ledgerBalance')}</p>
          <p className="mt-1 text-xl font-black text-slate-900">{fmt(entries.at(-1)?.running_balance ?? opening)}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{t(locale, 'accountingModule.ledgerPiece')}</p>
          <p className="mt-1 text-xl font-black text-slate-900">{total}</p>
        </div>
      </section>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-transparent/50">
                <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDate')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerPiece')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerAccount')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDesc')}</th>
                <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDebit')}</th>
                <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerCredit')}</th>
                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerBalance')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr><td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.loading')}</td></tr>
              ) : entries.length === 0 ? (
                <tr><td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.ledgerEmpty')}</td></tr>
              ) : (
                entries.map((entry) => (
                  <tr key={entry.id} className="transition-colors hover:bg-transparent/60">
                    <td className="whitespace-nowrap px-6 py-3 font-mono text-xs text-slate-500">{entry.entry_date}</td>
                    <td className="px-4 py-3 font-mono text-xs font-bold text-slate-700">{entry.piece}</td>
                    <td className="px-4 py-3">
                      <span className="font-mono text-xs font-bold text-slate-700">{entry.account_code}</span>
                      <span className="ml-2 text-xs text-slate-500">{entry.account_label}</span>
                    </td>
                    <td className="max-w-[220px] truncate px-4 py-3 text-slate-600" title={entry.description}>{entry.description}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-slate-700">{entry.debit ? fmt(entry.debit) : ''}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-slate-700">{entry.credit ? fmt(entry.credit) : ''}</td>
                    <td className="px-6 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(entry.running_balance)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </ModulePageShell>
  );
}
