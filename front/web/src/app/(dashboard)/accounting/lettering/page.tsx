'use client';

import { useCallback, useEffect, useState } from 'react';
import { RefreshCw, Link2, Unlink, Loader2, CheckCircle2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface JournalEntry {
  id: number;
  date: string;
  piece: string;
  description: string;
  account_code: string;
  account_label: string;
  debit: number;
  credit: number;
}

const currentPeriod = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};
const fmt = (n: number) =>
  new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

/**
 * #5534 — Lettrage : sélection multi-écritures du journal + lettre
 * (POST/DELETE /accounting/journal/lettering). Période clôturée = lecture seule.
 */
export default function LetteringPage() {
  const locale = getPreferredLocale();
  const [period, setPeriod] = useState(currentPeriod());
  const [entries, setEntries] = useState<JournalEntry[]>([]);
  const [closed, setClosed] = useState(false);
  const [balanced, setBalanced] = useState(true);
  const [totals, setTotals] = useState({ total_debit: 0, total_credit: 0 });
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [letter, setLetter] = useState('');
  const [unletterLetter, setUnletterLetter] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    setNotice(null);
    try {
      const res = await apiFetch(`/accounting/journal?period=${period}`);
      const body = await res.json();
      setEntries(body.entries || []);
      setClosed(Boolean(body.closed));
      setBalanced(Boolean(body.balanced));
      setTotals(body.totals || { total_debit: 0, total_credit: 0 });
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setLoading(false);
    }
  }, [locale, period]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggle = (id: number) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const applyLettering = async () => {
    setNotice(null);
    setError(null);
    if (selected.size < 2) {
      setError(t(locale, 'accountingModule.lgNeedSelection'));
      return;
    }
    if (!letter.trim()) {
      setError(t(locale, 'accountingModule.lgNeedLetter'));
      return;
    }
    setBusy(true);
    try {
      const res = await apiFetch('/accounting/journal/lettering', {
        method: 'POST',
        body: JSON.stringify({ letter: letter.trim(), entry_ids: Array.from(selected) }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => null);
        setError(
          t(locale, 'accountingModule.lgError').replace(
            '{message}',
            body?.message ?? body?.data?.message ?? res.statusText,
          ),
        );
        return;
      }
      setNotice(t(locale, 'accountingModule.lgDone'));
      setSelected(new Set());
      setLetter('');
      await load();
      setNotice(t(locale, 'accountingModule.lgDone'));
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setBusy(false);
    }
  };

  const unletter = async () => {
    setNotice(null);
    setError(null);
    if (!unletterLetter.trim()) {
      setError(t(locale, 'accountingModule.lgNeedLetter'));
      return;
    }
    setBusy(true);
    try {
      await apiFetch(`/accounting/journal/lettering/${unletterLetter.trim()}`, { method: 'DELETE' });
      setUnletterLetter('');
      await load();
      setNotice(t(locale, 'accountingModule.lgUnlettered'));
    } catch {
      setError(t(locale, 'accountingModule.errorGeneric'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModulePageShell
      title={t(locale, 'accountingModule.lgTitle')}
      subtitle={t(locale, 'accountingModule.lgSubtitle')}
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
        <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${closed ? 'bg-slate-100 text-slate-500' : balanced ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
          {closed ? t(locale, 'accountingModule.lgClosed') : balanced ? t(locale, 'accountingModule.lgBalanced') : t(locale, 'accountingModule.lgUnbalanced')}
        </span>
        {error && (
          <button onClick={() => void load()} className="inline-flex items-center gap-1 text-xs font-bold text-red-600">
            <RefreshCw className="h-3 w-3" /> {t(locale, 'accountingModule.retry')}
          </button>
        )}
      </div>

      {(error || notice) && (
        <div className={`flex items-center gap-2 rounded-lg border px-4 py-3 text-sm ${notice ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`} role={notice ? 'status' : 'alert'}>
          {notice ? <CheckCircle2 className="h-4 w-4 shrink-0" /> : <RefreshCw className="h-4 w-4 shrink-0" />}
          {notice ?? error}
        </div>
      )}

      <div className="flex flex-col gap-3 rounded-2xl border border-app-border bg-white p-4 shadow-sm sm:flex-row sm:items-center">
        <input
          value={letter}
          onChange={(e) => setLetter(e.target.value)}
          placeholder={t(locale, 'accountingModule.lgLetter')}
          disabled={closed}
          className="w-40 rounded-xl border border-app-border px-3 py-2.5 text-sm"
        />
        <button
          onClick={() => void applyLettering()}
          disabled={busy || closed || selected.size === 0}
          className="inline-flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-700 disabled:opacity-50"
        >
          {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Link2 className="h-4 w-4" />}
          {t(locale, 'accountingModule.lgApply')} ({selected.size})
        </button>
        <div className="ml-auto flex items-center gap-2">
          <input
            value={unletterLetter}
            onChange={(e) => setUnletterLetter(e.target.value)}
            placeholder={t(locale, 'accountingModule.lgUnletterPlaceholder')}
            disabled={closed}
            className="w-44 rounded-xl border border-app-border px-3 py-2.5 text-sm"
          />
          <button
            onClick={() => void unletter()}
            disabled={busy || closed}
            className="inline-flex items-center gap-2 rounded-xl border border-app-border px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
          >
            <Unlink className="h-4 w-4" />
            {t(locale, 'accountingModule.lgUnletter')}
          </button>
        </div>
      </div>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-transparent/50">
                <th className="w-12 px-4 py-3">{t(locale, 'accountingModule.lgSelect')}</th>
                <th className="px-2 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDate')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerPiece')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerAccount')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDesc')}</th>
                <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerDebit')}</th>
                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.ledgerCredit')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr><td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.loading')}</td></tr>
              ) : entries.length === 0 ? (
                <tr><td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'accountingModule.lgEmpty')}</td></tr>
              ) : (
                entries.map((entry) => (
                  <tr
                    key={entry.id}
                    className={`cursor-pointer transition-colors hover:bg-transparent/60 ${selected.has(entry.id) ? 'bg-cyan-50/60' : ''}`}
                    onClick={() => toggle(entry.id)}
                  >
                    <td className="px-4 py-3 text-center">
                      <input
                        type="checkbox"
                        checked={selected.has(entry.id)}
                        onClick={(e) => e.stopPropagation()}
                        onChange={() => toggle(entry.id)}
                        disabled={closed}
                        className="h-4 w-4 rounded border-app-border accent-cyan-600"
                      />
                    </td>
                    <td className="whitespace-nowrap px-2 py-3 font-mono text-xs text-slate-500">{entry.date}</td>
                    <td className="px-4 py-3 font-mono text-xs font-bold text-slate-700">{entry.piece}</td>
                    <td className="px-4 py-3">
                      <span className="font-mono text-xs font-bold text-slate-700">{entry.account_code}</span>
                      <span className="ml-2 text-xs text-slate-500">{entry.account_label}</span>
                    </td>
                    <td className="max-w-[200px] truncate px-4 py-3 text-slate-600" title={entry.description}>{entry.description}</td>
                    <td className="px-4 py-3 text-right font-mono text-xs text-slate-700">{entry.debit ? fmt(entry.debit) : ''}</td>
                    <td className="px-6 py-3 text-right font-mono text-xs text-slate-700">{entry.credit ? fmt(entry.credit) : ''}</td>
                  </tr>
                ))
              )}
            </tbody>
            <tfoot>
              <tr className="border-t border-app-border bg-slate-50">
                <td colSpan={5} className="px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'accountingModule.balanceTotals')}</td>
                <td className="px-4 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(totals.total_debit)}</td>
                <td className="px-6 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(totals.total_credit)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </ModulePageShell>
  );
}
