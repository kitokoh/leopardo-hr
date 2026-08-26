'use client';

import { useCallback, useEffect, useState } from 'react';
import { RefreshCw, Scale, Download } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface BalanceLine {
  account_code: string;
  account_label: string;
  total_debit: number;
  total_credit: number;
  balance: number;
}

const currentPeriod = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const fmt = (n: number) =>
  new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

/**
 * #5534 — Balance de vérification : totaux par compte + totaux généraux,
 * indicateur d'équilibre, export FEC.
 */
export default function BalancePage() {
  const locale = getPreferredLocale();
  const [period, setPeriod] = useState(currentPeriod());
  const [lines, setLines] = useState<BalanceLine[]>([]);
  const [totals, setTotals] = useState({ total_debit: 0, total_credit: 0, difference: 0 });
  const [balanced, setBalanced] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/accounting/balance?period=${period}`);
      const body = await res.json();
      setLines(body.data || []);
      setTotals(body.meta?.totals || { total_debit: 0, total_credit: 0, difference: 0 });
      setBalanced(Boolean(body.meta?.balanced));
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setLoading(false);
    }
  }, [locale, period]);

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
      title={t(locale, 'accountingModule.balanceTitle')}
      subtitle={t(locale, 'accountingModule.balanceSubtitle')}
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

      <div className={`flex items-center gap-2 rounded-2xl border px-5 py-4 text-sm font-bold ${balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
        <Scale className="h-4 w-4" />
        {balanced ? t(locale, 'accountingModule.balanceBalanced') : t(locale, 'accountingModule.balanceUnbalanced')}
        <span className="ml-auto font-mono text-xs font-medium">
          D {fmt(totals.total_debit)} · C {fmt(totals.total_credit)} · Δ {fmt(totals.difference)}
        </span>
      </div>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-transparent/50">
                <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartCode')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.chartLabel')}</th>
                <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDebit')}</th>
                <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerCredit')}</th>
                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerBalance')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr><td colSpan={5} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.loading')}</td></tr>
              ) : lines.length === 0 ? (
                <tr><td colSpan={5} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.balanceEmpty')}</td></tr>
              ) : (
                lines.map((line) => (
                  <tr key={line.account_code} className="transition-colors hover:bg-transparent/60">
                    <td className="px-6 py-3 font-mono text-xs font-bold text-slate-800">{line.account_code}</td>
                    <td className="px-4 py-3 text-slate-600">{line.account_label}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-slate-700">{fmt(line.total_debit)}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-slate-700">{fmt(line.total_credit)}</td>
                    <td className="px-6 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(line.balance)}</td>
                  </tr>
                ))
              )}
            </tbody>
            <tfoot>
              <tr className="border-t border-app-border bg-slate-50">
                <td colSpan={2} className="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.balanceTotals')}</td>
                <td className="px-4 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(totals.total_debit)}</td>
                <td className="px-4 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(totals.total_credit)}</td>
                <td className="px-6 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(totals.difference)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </ModulePageShell>
  );
}
