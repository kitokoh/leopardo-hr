'use client';

import { useEffect, useState, useMemo, useCallback, useSyncExternalStore } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getCopy, getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';
import {
  DollarSign,
  Download,
  FileText,
  Calendar,
  Search,
  ChevronLeft,
  ChevronRight,
  Eye,
} from 'lucide-react';

const emptySubscribe = () => () => {};

interface PaySlip {
  id: number;
  employee_id: number;
  employee_name: string;
  period: string;
  gross_salary: number;
  net_salary: number;
  status: string;
  created_at: string;
}

interface PayrollRun {
  id: number;
  period: string;
  status: string;
  total_gross: number;
  total_net: number;
  employee_count: number;
  created_at: string;
}

export default function PayrollPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).payrollPage;
  const [payslips, setPayslips] = useState<PaySlip[]>([]);
  const [runs, setRuns] = useState<PayrollRun[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [tab, setTab] = useState<'slips' | 'runs'>('slips');
  const itemsPerPage = 10;

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [slipsRes, runsRes] = await Promise.all([
        apiFetch('/pay-slips').then(r => r.json()).catch(() => ({ data: [] })),
        apiFetch('/payroll-runs').then(r => r.json()).catch(() => ({ data: [] })),
      ]);
      setPayslips(slipsRes.data || []);
      setRuns(runsRes.data || []);
    } catch {
      // silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const filtered = useMemo(() =>
    payslips.filter(p =>
      p.employee_name?.toLowerCase().includes(search.toLowerCase()) ||
      p.period?.includes(search)
    ), [payslips, search]);

  const totalPages = Math.ceil(filtered.length / itemsPerPage);
  const paginated = filtered.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  const formatCurrency = (val: number) =>
    new Intl.NumberFormat(toIntlLocale(locale), { style: 'currency', currency: 'EUR' }).format(val || 0);

  const downloadPdf = async (id: number) => {
    try {
      const res = await apiFetch(`/pay-slips/${id}/pdf`);
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `payslip-${id}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // handle error
    }
  };

  const statCards = [
    { label: labels.statTotalGross, value: formatCurrency(runs.reduce((s, r) => s + (r.total_gross || 0), 0)), icon: DollarSign, accent: 'text-emerald-600 bg-emerald-50' },
    { label: labels.statTotalNet, value: formatCurrency(runs.reduce((s, r) => s + (r.total_net || 0), 0)), icon: FileText, accent: 'text-finance-dark bg-finance-light' },
    { label: labels.statPayslips, value: String(payslips.length), icon: Calendar, accent: 'text-ia-dark bg-ia-light' },
  ];

  return (
    <ModulePageShell
      title={labels.title}
      subtitle={labels.subtitle}
      accentClassName="bg-gradient-to-br from-finance-light via-white to-white"
    >
      <section className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {statCards.map((stat, i) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="rounded-2xl border border-app-border bg-white p-5 shadow-sm"
          >
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{stat.label}</p>
                <p className="mt-2 text-2xl font-black text-slate-950">{stat.value}</p>
              </div>
              <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${stat.accent}`}>
                <stat.icon className="h-5 w-5" />
              </div>
            </div>
          </motion.div>
        ))}
      </section>

      <div className="flex gap-2 border-b border-app-border">
        <button
          onClick={() => setTab('slips')}
          className={`px-4 py-2.5 text-sm font-bold uppercase tracking-wide border-b-2 transition-colors ${tab === 'slips' ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
        >
          {labels.tabSlips}
        </button>
        <button
          onClick={() => setTab('runs')}
          className={`px-4 py-2.5 text-sm font-bold uppercase tracking-wide border-b-2 transition-colors ${tab === 'runs' ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
        >
          {labels.tabRuns}
        </button>
      </div>

      {tab === 'slips' && (
        <>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              type="text"
              placeholder={labels.searchPlaceholder}
              value={search}
              onChange={e => { setSearch(e.target.value); setCurrentPage(1); }}
              className="w-full rounded-xl border border-app-border bg-white pl-10 pr-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-app-border bg-transparent/50">
                    <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnEmployee}</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnPeriod}</th>
                    <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnGross}</th>
                    <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnNet}</th>
                    <th className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnStatus}</th>
                    <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnActions}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-app-border">
                  {loading ? (
                    <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-500">{labels.loading}</td></tr>
                  ) : paginated.length === 0 ? (
                    <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-500">{labels.noPayslips}</td></tr>
                  ) : paginated.map(slip => (
                    <tr key={slip.id} className="transition-colors hover:bg-transparent/60">
                      <td className="px-6 py-4 font-bold text-slate-950">{slip.employee_name}</td>
                      <td className="px-4 py-4 text-slate-600">{slip.period}</td>
                      <td className="px-4 py-4 text-right tabular-nums text-slate-900">{formatCurrency(slip.gross_salary)}</td>
                      <td className="px-4 py-4 text-right tabular-nums font-bold text-emerald-600">{formatCurrency(slip.net_salary)}</td>
                      <td className="px-4 py-4 text-center">
                        <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${slip.status === 'validated' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                          {slip.status === 'validated' ? labels.statusValidated : labels.statusDraft}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <button onClick={() => downloadPdf(slip.id)} className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-emerald-600" title={labels.downloadPdf}><Download className="h-4 w-4" /></button>
                          <button className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-emerald-600" title={labels.viewDetail}><Eye className="h-4 w-4" /></button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {totalPages > 1 && (
              <div className="flex items-center justify-between border-t border-app-border px-6 py-3">
                <p className="text-xs font-medium text-slate-500">{filtered.length} {labels.resultsCount}</p>
                <div className="flex items-center gap-1">
                  <button onClick={() => setCurrentPage(p => Math.max(1, p - 1))} disabled={currentPage === 1} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 disabled:opacity-30"><ChevronLeft className="h-4 w-4" /></button>
                  <span className="px-2 text-sm font-medium text-slate-600">{currentPage}/{totalPages}</span>
                  <button onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 disabled:opacity-30"><ChevronRight className="h-4 w-4" /></button>
                </div>
              </div>
            )}
          </section>
        </>
      )}

      {tab === 'runs' && (
        <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-app-border bg-transparent/50">
                  <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnPeriod}</th>
                  <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnEmployees}</th>
                  <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnTotalGross}</th>
                  <th className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnTotalNet}</th>
                  <th className="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">{labels.columnStatus}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-app-border">
                {loading ? (
                  <tr><td colSpan={5} className="px-6 py-10 text-center text-sm text-slate-500">{labels.loading}</td></tr>
                ) : runs.length === 0 ? (
                  <tr><td colSpan={5} className="px-6 py-10 text-center text-sm text-slate-500">{labels.noRuns}</td></tr>
                ) : runs.map(run => (
                  <tr key={run.id} className="transition-colors hover:bg-transparent/60">
                    <td className="px-6 py-4 font-bold text-slate-950">{run.period}</td>
                    <td className="px-4 py-4 text-right text-slate-600">{run.employee_count}</td>
                    <td className="px-4 py-4 text-right tabular-nums text-slate-900">{formatCurrency(run.total_gross)}</td>
                    <td className="px-4 py-4 text-right tabular-nums font-bold text-emerald-600">{formatCurrency(run.total_net)}</td>
                    <td className="px-6 py-4 text-center">
                      <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${run.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                        {run.status === 'completed' ? labels.statusCompleted : run.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </ModulePageShell>
  );
}

