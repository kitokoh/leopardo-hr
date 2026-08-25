'use client';

import { useCallback, useEffect, useState } from 'react';
import { RefreshCw, FileBarChart2, CheckCircle2, AlertTriangle } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface Section {
  class: number;
  label: string;
  total: number;
}

interface BalanceSheet {
  actif: Section[];
  passif: Section[];
  capitaux_propres: Section[];
  total_actif: number;
  total_passif: number;
  total_capitaux: number;
  total_passif_et_capitaux: number;
  resultat_net: number;
  balanced: boolean;
}

const currentYear = () => new Date().getFullYear();
const currentPeriod = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};
const fmt = (n: number) =>
  new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

/**
 * #5534 — États financiers : bilan (par exercice) + compte de résultat (par
 * période), sections par classe PCG, invariant actif = passif + capitaux.
 */
export default function StatementsPage() {
  const locale = getPreferredLocale();
  const [tab, setTab] = useState<'balanceSheet' | 'income'>('balanceSheet');
  const [year, setYear] = useState(currentYear());
  const [period, setPeriod] = useState(currentPeriod());
  const [bs, setBs] = useState<BalanceSheet | null>(null);
  const [income, setIncome] = useState<{ sections: Section[]; total_charges: number; total_produits: number; resultat_net: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      if (tab === 'balanceSheet') {
        const res = await apiFetch(`/accounting/statements/balance-sheet?year=${year}`);
        const body = await res.json();
        setBs(body.data as BalanceSheet);
      } else {
        const res = await apiFetch(`/accounting/statements/income-statement?period=${period}`);
        const body = await res.json();
        setIncome(body.data);
      }
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setLoading(false);
    }
  }, [locale, tab, year, period]);

  useEffect(() => {
    void load();
  }, [load]);

  const renderSection = (sections: Section[] | undefined, label: string) => (
    <div className="mb-4">
      <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">{label}</p>
      {(!sections || sections.length === 0) ? (
        <p className="text-sm text-slate-400">{t(locale, 'accountingModule.statementEmpty')}</p>
      ) : (
        <ul className="divide-y divide-app-border rounded-xl border border-app-border">
          {sections.map((section) => (
            <li key={section.class} className="flex items-center justify-between px-4 py-2.5 text-sm">
              <span className="text-slate-600">
                <span className="mr-2 font-mono text-xs font-bold text-slate-400">{section.class}</span>
                {section.label}
              </span>
              <span className="font-mono text-xs font-bold text-slate-800">{fmt(section.total)}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.statementsTitle')}
      subtitle={t(locale, 'accountingModule.statementsSubtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="inline-flex rounded-xl border border-app-border bg-white p-1">
          <button
            onClick={() => setTab('balanceSheet')}
            className={`rounded-lg px-4 py-2 text-sm font-bold transition ${tab === 'balanceSheet' ? 'bg-violet-500 text-white' : 'text-slate-500 hover:bg-slate-50'}`}
          >
            {t(locale, 'accountingModule.tabBalanceSheet')}
          </button>
          <button
            onClick={() => setTab('income')}
            className={`rounded-lg px-4 py-2 text-sm font-bold transition ${tab === 'income' ? 'bg-violet-500 text-white' : 'text-slate-500 hover:bg-slate-50'}`}
          >
            {t(locale, 'accountingModule.tabIncomeStatement')}
          </button>
        </div>
        {tab === 'balanceSheet' ? (
          <label className="flex items-center gap-2 text-sm font-bold text-slate-600">
            {t(locale, 'accountingModule.yearLabel')}
            <input
              type="number"
              value={year}
              onChange={(e) => setYear(Number(e.target.value))}
              className="w-28 rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            />
          </label>
        ) : (
          <label className="flex items-center gap-2 text-sm font-bold text-slate-600">
            {t(locale, 'accountingModule.periodLabel')}
            <input
              type="month"
              value={period}
              onChange={(e) => setPeriod(e.target.value)}
              className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
            />
          </label>
        )}
        {error && (
          <button onClick={() => void load()} className="inline-flex items-center gap-1 text-xs font-bold text-red-600">
            <RefreshCw className="h-3 w-3" /> {t(locale, 'accountingModule.retry')}
          </button>
        )}
      </div>

      {loading ? (
        <div className="rounded-3xl border border-app-border bg-white p-12 text-center text-sm text-slate-400">{t(locale, 'accountingModule.loading')}</div>
      ) : tab === 'balanceSheet' && bs ? (
        <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          <div className={`mb-5 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-bold ${bs.balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
            {bs.balanced ? <CheckCircle2 className="h-4 w-4" /> : <AlertTriangle className="h-4 w-4" />}
            {bs.balanced ? t(locale, 'accountingModule.statementBalanced') : t(locale, 'accountingModule.statementUnbalanced')}
          </div>
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>{renderSection(bs.actif, t(locale, 'accountingModule.statementActif'))}</div>
            <div>
              {renderSection(bs.passif, t(locale, 'accountingModule.statementPassif'))}
              {renderSection(bs.capitaux_propres, t(locale, 'accountingModule.statementCapitaux'))}
            </div>
          </div>
          <div className="mt-4 grid grid-cols-2 gap-4 border-t border-app-border pt-4 sm:grid-cols-4">
            {[
              [t(locale, 'accountingModule.statementTotalActif'), bs.total_actif],
              [t(locale, 'accountingModule.statementTotalPassif'), bs.total_passif],
              [t(locale, 'accountingModule.statementTotalCapitaux'), bs.total_capitaux],
              [t(locale, 'accountingModule.statementResultat'), bs.resultat_net],
            ].map(([label, value]) => (
              <div key={String(label)} className="rounded-xl bg-slate-50 px-4 py-3">
                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{label}</p>
                <p className="mt-1 font-mono text-sm font-black text-slate-900">{fmt(Number(value))}</p>
              </div>
            ))}
          </div>
        </section>
      ) : income ? (
        <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
          {renderSection(income.sections, t(locale, 'accountingModule.statementResultat'))}
          <div className="mt-4 grid grid-cols-3 gap-4 border-t border-app-border pt-4">
            <div className="rounded-xl bg-slate-50 px-4 py-3">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{t(locale, 'accountingModule.ledgerCredit')}</p>
              <p className="mt-1 font-mono text-sm font-black text-emerald-700">{fmt(income.total_produits)}</p>
            </div>
            <div className="rounded-xl bg-slate-50 px-4 py-3">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{t(locale, 'accountingModule.ledgerDebit')}</p>
              <p className="mt-1 font-mono text-sm font-black text-red-700">{fmt(income.total_charges)}</p>
            </div>
            <div className="rounded-xl bg-slate-50 px-4 py-3">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{t(locale, 'accountingModule.statementResultat')}</p>
              <p className="mt-1 font-mono text-sm font-black text-slate-900">{fmt(income.resultat_net)}</p>
            </div>
          </div>
        </section>
      ) : null}
    </ModulePageShell>
  );
}
