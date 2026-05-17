'use client';

import { useEffect, useState, useMemo, useCallback } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
import {
  FileText,
  Search,
  Calendar,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Download,
} from 'lucide-react';

interface Contract {
  id: number;
  employee_id: number;
  employee_name: string;
  type: string;
  start_date: string;
  end_date: string | null;
  status: string;
  salary: number;
  created_at: string;
}

export default function ContractsPage() {
  const [contracts, setContracts] = useState<Contract[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');

  const loadContracts = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/contracts');
      const data = await res.json();
      setContracts(data.data || []);
    } catch {
      // silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadContracts(); }, [loadContracts]);

  const filtered = useMemo(() =>
    contracts.filter(c => {
      const matchesSearch = c.employee_name?.toLowerCase().includes(search.toLowerCase()) || c.type?.toLowerCase().includes(search.toLowerCase());
      const matchesStatus = statusFilter === 'all' || c.status === statusFilter;
      return matchesSearch && matchesStatus;
    }), [contracts, search, statusFilter]);

  const stats = useMemo(() => ({
    active: contracts.filter(c => c.status === 'active').length,
    expiring: contracts.filter(c => {
      if (!c.end_date) return false;
      const diff = new Date(c.end_date).getTime() - Date.now();
      return diff > 0 && diff < 30 * 24 * 3600 * 1000;
    }).length,
    suspended: contracts.filter(c => c.status === 'suspended').length,
    total: contracts.length,
  }), [contracts]);

  const statusBadge = (status: string) => {
    const styles: Record<string, string> = {
      active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
      suspended: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
      terminated: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
      draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    const labels: Record<string, string> = { active: 'Actif', suspended: 'Suspendu', terminated: 'Termine', draft: 'Brouillon' };
    return <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${styles[status] || styles.draft}`}>{labels[status] || status}</span>;
  };

  const downloadPdf = async (id: number) => {
    try {
      const res = await apiFetch(`/contracts/${id}/pdf`);
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `contract-${id}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // handle error
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Contrats</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400">Gestion des contrats employes</p>
      </div>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {[
          { label: 'Actifs', value: stats.active, icon: CheckCircle2, color: 'text-emerald-600' },
          { label: 'Expirant bientot', value: stats.expiring, icon: AlertTriangle, color: 'text-amber-600' },
          { label: 'Suspendus', value: stats.suspended, icon: Clock, color: 'text-red-500' },
          { label: 'Total', value: stats.total, icon: FileText, color: 'text-blue-600' },
        ].map((stat, i) => (
          <motion.div key={i} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }} className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
            <stat.icon className={`h-5 w-5 ${stat.color} mb-2`} />
            <p className="text-2xl font-bold text-slate-900 dark:text-white">{stat.value}</p>
            <p className="text-xs text-slate-500">{stat.label}</p>
          </motion.div>
        ))}
      </div>

      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input type="text" placeholder="Rechercher..." value={search} onChange={e => setSearch(e.target.value)} className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 pl-10 pr-4 py-2.5 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500" />
        </div>
        <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} className="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-slate-900 dark:text-white">
          <option value="all">Tous les statuts</option>
          <option value="active">Actif</option>
          <option value="suspended">Suspendu</option>
          <option value="terminated">Termine</option>
        </select>
      </div>

      <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
                <th className="px-4 py-3 text-left font-medium text-slate-500">Employe</th>
                <th className="px-4 py-3 text-left font-medium text-slate-500">Type</th>
                <th className="px-4 py-3 text-left font-medium text-slate-500">Debut</th>
                <th className="px-4 py-3 text-left font-medium text-slate-500">Fin</th>
                <th className="px-4 py-3 text-center font-medium text-slate-500">Statut</th>
                <th className="px-4 py-3 text-center font-medium text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">Chargement...</td></tr>
              ) : filtered.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-400">Aucun contrat trouve</td></tr>
              ) : filtered.map(c => (
                <tr key={c.id} className="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{c.employee_name}</td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-400 uppercase text-xs font-semibold">{c.type}</td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-400 flex items-center gap-1"><Calendar className="h-3 w-3" />{c.start_date}</td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-400">{c.end_date || 'Indefini'}</td>
                  <td className="px-4 py-3 text-center">{statusBadge(c.status)}</td>
                  <td className="px-4 py-3 text-center">
                    <button onClick={() => downloadPdf(c.id)} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-emerald-600" title="Telecharger PDF"><Download className="h-4 w-4" /></button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
