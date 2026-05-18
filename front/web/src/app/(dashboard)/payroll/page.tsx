'use client';

import { useEffect, useState, useMemo, useCallback } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
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
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val || 0);

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

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Paie</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400">Gestion des bulletins de paie et cycles de paie</p>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[
          { label: 'Total Brut', value: formatCurrency(runs.reduce((s, r) => s + (r.total_gross || 0), 0)), icon: DollarSign, color: 'text-emerald-600' },
          { label: 'Total Net', value: formatCurrency(runs.reduce((s, r) => s + (r.total_net || 0), 0)), icon: FileText, color: 'text-blue-600' },
          { label: 'Bulletins', value: String(payslips.length), icon: Calendar, color: 'text-purple-600' },
        ].map((stat, i) => (
          <motion.div
            key={i}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5"
          >
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">{stat.label}</p>
                <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{stat.value}</p>
              </div>
              <stat.icon className={`h-8 w-8 ${stat.color} opacity-60`} />
            </div>
          </motion.div>
        ))}
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-200 dark:border-slate-700">
        <button onClick={() => setTab('slips')} className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${tab === 'slips' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>Bulletins de paie</button>
        <button onClick={() => setTab('runs')} className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${tab === 'runs' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>Cycles de paie</button>
      </div>

      {tab === 'slips' && (
        <>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              type="text"
              placeholder="Rechercher par nom ou periode..."
              value={search}
              onChange={e => { setSearch(e.target.value); setCurrentPage(1); }}
              className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 pl-10 pr-4 py-2.5 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                    <th className="px-4 py-3 text-left font-medium text-slate-500">Employe</th>
                    <th className="px-4 py-3 text-left font-medium text-slate-500">Periode</th>
                    <th className="px-4 py-3 text-right font-medium text-slate-500">Brut</th>
                    <th className="px-4 py-3 text-right font-medium text-slate-500">Net</th>
                    <th className="px-4 py-3 text-center font-medium text-slate-500">Statut</th>
                    <th className="px-4 py-3 text-center font-medium text-slate-500">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">Chargement...</td></tr>
                  ) : paginated.length === 0 ? (
                    <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">Aucun bulletin trouve</td></tr>
                  ) : paginated.map(slip => (
                    <tr key={slip.id} className="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                      <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{slip.employee_name}</td>
                      <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{slip.period}</td>
                      <td className="px-4 py-3 text-right text-slate-900 dark:text-white tabular-nums">{formatCurrency(slip.gross_salary)}</td>
                      <td className="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{formatCurrency(slip.net_salary)}</td>
                      <td className="px-4 py-3 text-center">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${slip.status === 'validated' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'}`}>
                          {slip.status === 'validated' ? 'Valide' : 'Brouillon'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex items-center justify-center gap-1">
                          <button onClick={() => downloadPdf(slip.id)} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-emerald-600" title="Telecharger PDF"><Download className="h-4 w-4" /></button>
                          <button className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-blue-600" title="Voir detail"><Eye className="h-4 w-4" /></button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {totalPages > 1 && (
              <div className="flex items-center justify-between border-t border-slate-200 dark:border-slate-700 px-4 py-3">
                <p className="text-xs text-slate-500">{filtered.length} resultats</p>
                <div className="flex items-center gap-1">
                  <button onClick={() => setCurrentPage(p => Math.max(1, p - 1))} disabled={currentPage === 1} className="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30"><ChevronLeft className="h-4 w-4" /></button>
                  <span className="px-2 text-sm text-slate-600 dark:text-slate-400">{currentPage}/{totalPages}</span>
                  <button onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages} className="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30"><ChevronRight className="h-4 w-4" /></button>
                </div>
              </div>
            )}
          </div>
        </>
      )}

      {tab === 'runs' && (
        <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                  <th className="px-4 py-3 text-left font-medium text-slate-500">Periode</th>
                  <th className="px-4 py-3 text-right font-medium text-slate-500">Employes</th>
                  <th className="px-4 py-3 text-right font-medium text-slate-500">Total Brut</th>
                  <th className="px-4 py-3 text-right font-medium text-slate-500">Total Net</th>
                  <th className="px-4 py-3 text-center font-medium text-slate-500">Statut</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={5} className="px-4 py-12 text-center text-slate-400">Chargement...</td></tr>
                ) : runs.length === 0 ? (
                  <tr><td colSpan={5} className="px-4 py-12 text-center text-slate-400">Aucun cycle de paie</td></tr>
                ) : runs.map(run => (
                  <tr key={run.id} className="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{run.period}</td>
                    <td className="px-4 py-3 text-right text-slate-600 dark:text-slate-400">{run.employee_count}</td>
                    <td className="px-4 py-3 text-right text-slate-900 dark:text-white tabular-nums">{formatCurrency(run.total_gross)}</td>
                    <td className="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{formatCurrency(run.total_net)}</td>
                    <td className="px-4 py-3 text-center">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${run.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                        {run.status === 'completed' ? 'Termine' : run.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
