'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { RefreshCw, Download, Landmark, Loader2, CheckCircle2, Link2 } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface StatementLine {
  id: number;
  line_number: number;
  line_date: string;
  label: string;
  amount: number;
  external_reference: string | null;
  status: 'pending' | 'matched';
  matched_payment_id: number | null;
  confidence: number | null;
  proposed_payment_id: number | null;
}

interface BankStatement {
  id: number;
  statement_period: string;
  import_reference: string | null;
  opening_balance: number | null;
  closing_balance: number | null;
  status: string;
  lines: StatementLine[];
}

interface Payment {
  id: number;
  document_id: number | null;
  amount: number;
  method: string;
  reference: string | null;
  status: string;
}

const fmt = (n: number | null | undefined) =>
  new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

/**
 * #5523 — Rapprochement bancaire : UI de matching manuel (manquements #5435).
 * Liste des relevés → lignes pending avec proposition du matching auto
 * (confidence) → bouton matcher (POST /bank-statement-lines/{line}/match) +
 * export CSV de l'état (GET /bank-statements/{statement}/export).
 */
export default function ReconciliationPage() {
  const locale = getPreferredLocale();
  const [statements, setStatements] = useState<BankStatement[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [statement, setStatement] = useState<BankStatement | null>(null);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [matchByLine, setMatchByLine] = useState<Record<number, string>>({});
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const loadStatements = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/accounting/bank-statements');
      const body = await res.json();
      setStatements(body.data || []);
    } catch {
      setError(t(locale, 'bankRecon.loadError'));
    } finally {
      setLoading(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadStatements();
  }, [loadStatements]);

  const loadDetail = useCallback(
    async (id: number) => {
      setDetailLoading(true);
      setError(null);
      try {
        const [detailRes, paymentsRes] = await Promise.all([
          apiFetch(`/accounting/bank-statements/${id}`),
          apiFetch('/accounting/payments?status=recorded'),
        ]);
        const detailBody = await detailRes.json();
        const paymentsBody = await paymentsRes.json();
        setStatement(detailBody.data as BankStatement);
        setPayments(paymentsBody.data || []);
      } catch {
        setError(t(locale, 'bankRecon.loadError'));
      } finally {
        setDetailLoading(false);
      }
    },
    [locale],
  );

  useEffect(() => {
    if (selectedId === null) {
      setStatement(null);
      return;
    }
    void loadDetail(selectedId);
  }, [selectedId, loadDetail]);

  const stats = useMemo(() => {
    if (!statement) return null;
    const lines = statement.lines;
    const pending = lines.filter((l) => l.status === 'pending');
    const matched = lines.filter((l) => l.status === 'matched');
    const sum = (arr: StatementLine[]) => arr.reduce((acc, l) => acc + Number(l.amount || 0), 0);
    const expectedClosing =
      statement.opening_balance !== null
        ? Number(statement.opening_balance) + sum(lines)
        : null;
    return {
      total: lines.length,
      pending: pending.length,
      matched: matched.length,
      pendingAmount: sum(pending),
      matchedAmount: sum(matched),
      expectedClosing,
      gap:
        expectedClosing !== null && statement.closing_balance !== null
          ? expectedClosing - Number(statement.closing_balance)
          : null,
    };
  }, [statement]);

  const matchLine = async (line: StatementLine) => {
    const paymentId = Number(matchByLine[line.id]);
    if (!paymentId) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const res = await apiFetch(`/accounting/bank-statement-lines/${line.id}/match`, {
        method: 'POST',
        body: JSON.stringify({ payment_id: paymentId }),
      });
      if (!res.ok) {
        setError(t(locale, 'bankRecon.matchError'));
        return;
      }
      setNotice(t(locale, 'bankRecon.matchDone'));
      if (selectedId !== null) await loadDetail(selectedId);
    } catch {
      setError(t(locale, 'bankRecon.matchError'));
    } finally {
      setBusy(false);
    }
  };

  const exportCsv = async () => {
    if (selectedId === null) return;
    try {
      const res = await apiFetch(`/accounting/bank-statements/${selectedId}/export`);
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `rapprochement-${statement?.statement_period ?? selectedId}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError(t(locale, 'bankRecon.loadError'));
    }
  };

  const selectedPeriod = statement?.statement_period ?? '';

  return (
    <ModulePageShell
      title={t(locale, 'bankRecon.title')}
      subtitle={t(locale, 'bankRecon.subtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label className="flex items-center gap-2 text-sm font-bold text-slate-600">
          <Landmark className="h-4 w-4 text-slate-400" />
          {t(locale, 'bankRecon.statementsTitle')}
          <select
            value={selectedId ?? ''}
            onChange={(e) => setSelectedId(e.target.value ? Number(e.target.value) : null)}
            disabled={loading}
            className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm"
          >
            <option value="">{loading ? t(locale, 'bankRecon.loading') : t(locale, 'bankRecon.selectStatement')}</option>
            {statements.map((s) => (
              <option key={s.id} value={s.id}>
                {s.statement_period} · {s.import_reference ?? s.id}
              </option>
            ))}
          </select>
        </label>
        {statement && (
          <button
            onClick={() => void exportCsv()}
            className="inline-flex items-center gap-2 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-600"
          >
            <Download className="h-4 w-4" />
            {t(locale, 'bankRecon.exportCsv')}
          </button>
        )}
        {error && (
          <button onClick={() => (selectedId === null ? void loadStatements() : void loadDetail(selectedId))} className="inline-flex items-center gap-1 text-xs font-bold text-red-600">
            <RefreshCw className="h-3 w-3" /> {t(locale, 'bankRecon.retry')}
          </button>
        )}
      </div>

      {(error || notice) && (
        <div className={`flex items-center gap-2 rounded-lg border px-4 py-3 text-sm ${notice ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`} role={notice ? 'status' : 'alert'}>
          {notice ? <CheckCircle2 className="h-4 w-4 shrink-0" /> : <RefreshCw className="h-4 w-4 shrink-0" />}
          {notice ?? error}
        </div>
      )}

      {stats && (
        <section className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {[
            [t(locale, 'bankRecon.opening'), fmt(statement?.opening_balance)],
            [t(locale, 'bankRecon.closingExpected'), fmt(stats.expectedClosing)],
            [t(locale, 'bankRecon.closingReported'), fmt(statement?.closing_balance)],
            [t(locale, 'bankRecon.gap'), fmt(stats.gap)],
          ].map(([label, value]) => (
            <div key={String(label)} className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{label}</p>
              <p className="mt-1 font-mono text-lg font-black text-slate-900">{value}</p>
            </div>
          ))}
          <div className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{t(locale, 'bankRecon.matchedLines')} / {t(locale, 'bankRecon.pendingLines')}</p>
            <p className="mt-1 text-lg font-black text-slate-900">
              {stats.matched} / {stats.pending}
            </p>
          </div>
          <div className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{t(locale, 'bankRecon.matchedAmount')}</p>
            <p className="mt-1 font-mono text-lg font-black text-emerald-700">{fmt(stats.matchedAmount)}</p>
          </div>
          <div className="rounded-2xl border border-app-border bg-white p-4 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{t(locale, 'bankRecon.pendingAmount')}</p>
            <p className="mt-1 font-mono text-lg font-black text-amber-600">{fmt(stats.pendingAmount)}</p>
          </div>
        </section>
      )}

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-transparent/50">
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'bankRecon.lineDate')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'bankRecon.lineLabel')}</th>
                <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'bankRecon.lineAmount')}</th>
                <th className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'bankRecon.lineStatus')}</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'bankRecon.confidence')}</th>
                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'bankRecon.match')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {detailLoading ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'bankRecon.loading')}</td></tr>
              ) : !statement ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'bankRecon.selectStatement')}</td></tr>
              ) : statement.lines.length === 0 ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-400">{t(locale, 'bankRecon.noStatements')}</td></tr>
              ) : (
                statement.lines.map((line) => (
                  <tr key={line.id} className={`transition-colors hover:bg-transparent/60 ${line.status === 'pending' ? 'bg-amber-50/40' : ''}`}>
                    <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{line.line_date}</td>
                    <td className="max-w-[260px] truncate px-4 py-3 font-medium text-slate-700" title={line.label}>
                      {line.label}
                      {line.proposed_payment_id && line.status === 'pending' && (
                        <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sky-600">
                          <Link2 className="h-2.5 w-2.5" />
                          {t(locale, 'bankRecon.proposed')} #{line.proposed_payment_id}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right font-mono text-xs font-bold text-slate-800">{fmt(line.amount)}</td>
                    <td className="px-4 py-3 text-center">
                      <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${line.status === 'matched' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                        {line.status === 'matched' ? t(locale, 'bankRecon.statusMatched') : t(locale, 'bankRecon.statusPending')}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-500">
                      {line.confidence !== null ? `${Math.round(Number(line.confidence) * 100)} %` : '—'}
                    </td>
                    <td className="px-6 py-3 text-right">
                      {line.status === 'pending' ? (
                        <div className="flex items-center justify-end gap-2">
                          <select
                            value={matchByLine[line.id] ?? ''}
                            onChange={(e) => setMatchByLine((prev) => ({ ...prev, [line.id]: e.target.value }))}
                            className="max-w-[180px] rounded-lg border border-app-border bg-white px-2 py-1.5 text-xs"
                          >
                            <option value="">{t(locale, 'bankRecon.matchPayment')}…</option>
                            {payments.length === 0 && <option disabled>{t(locale, 'bankRecon.noPayments')}</option>}
                            {payments.map((payment) => (
                              <option key={payment.id} value={payment.id}>
                                #{payment.id} · {fmt(payment.amount)} · {payment.reference ?? payment.method}
                              </option>
                            ))}
                          </select>
                          <button
                            onClick={() => void matchLine(line)}
                            disabled={busy || !matchByLine[line.id]}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-cyan-700 disabled:opacity-40"
                          >
                            {busy ? <Loader2 className="h-3 w-3 animate-spin" /> : <Link2 className="h-3 w-3" />}
                            {t(locale, 'bankRecon.match')}
                          </button>
                        </div>
                      ) : (
                        <span className="text-xs text-slate-400">#{line.matched_payment_id}</span>
                      )}
                    </td>
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
