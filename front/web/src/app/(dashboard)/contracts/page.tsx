'use client';

import { useEffect, useState, useMemo, useCallback } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
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
      active: 'bg-emerald-50 text-emerald-700',
      suspended: 'bg-amber-50 text-amber-700',
      terminated: 'bg-red-50 text-red-700',
      draft: 'bg-slate-100 text-slate-600',
    };
    const labels: Record<string, string> = { active: 'Actif', suspended: 'Suspendu', terminated: 'Termine', draft: 'Brouillon' };
    return <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${styles[status] || styles.draft}`}>{labels[status] || status}</span>;
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

  const statCards = [
    { label: 'Actifs', value: stats.active, icon: CheckCircle2, accent: 'text-emerald-600 bg-emerald-50' },
    { label: 'Expirant bientot', value: stats.expiring, icon: AlertTriangle, accent: 'text-amber-600 bg-amber-50' },
    { label: 'Suspendus', value: stats.suspended, icon: Clock, accent: 'text-red-500 bg-red-50' },
    { label: 'Total', value: stats.total, icon: FileText, accent: 'text-emerald-600 bg-emerald-50' },
  ];

  return (
    <ModulePageShell
      title="Contrats"
      subtitle="Gestion des contrats employes : suivi des statuts, echeances et export PDF, branche directement sur l'API RH."
      accentClassName="bg-gradient-to-br from-rh-light via-white to-white"
    >
      <section className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {statCards.map((stat, i) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="rounded-2xl border border-app-border bg-white p-5 shadow-sm"
          >
            <div className={`mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl ${stat.accent}`}>
              <stat.icon className="h-5 w-5" />
            </div>
            <p className="text-2xl font-black text-slate-950">{stat.value}</p>
            <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{stat.label}</p>
          </motion.div>
        ))}
      </section>

      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input
            type="text"
            placeholder="Rechercher un employe ou un type de contrat..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full rounded-xl border border-app-border bg-white pl-10 pr-4 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </div>
        <select
          value={statusFilter}
          onChange={e => setStatusFilter(e.target.value)}
          className="rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-medium text-slate-700"
        >
          <option value="all">Tous les statuts</option>
          <option value="active">Actif</option>
          <option value="suspended">Suspendu</option>
          <option value="terminated">Termine</option>
        </select>
      </div>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Contrats</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-app-border bg-transparent/50">
                <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Employe</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Type</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Debut</th>
                <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Fin</th>
                <th className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">Statut</th>
                <th className="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-app-border">
              {loading ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-500">Chargement des contrats...</td></tr>
              ) : filtered.length === 0 ? (
                <tr><td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-500">Aucun contrat trouve.</td></tr>
              ) : filtered.map(c => (
                <tr key={c.id} className="transition-colors hover:bg-transparent/60">
                  <td className="px-6 py-4 font-bold text-slate-950">{c.employee_name}</td>
                  <td className="px-4 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">{c.type}</td>
                  <td className="px-4 py-4 text-slate-600">
                    <span className="inline-flex items-center gap-1"><Calendar className="h-3 w-3 text-slate-400" />{c.start_date}</span>
                  </td>
                  <td className="px-4 py-4 text-slate-600">{c.end_date || 'Indefini'}</td>
                  <td className="px-4 py-4 text-center">{statusBadge(c.status)}</td>
                  <td className="px-6 py-4 text-right">
                    <button onClick={() => downloadPdf(c.id)} className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-emerald-600" title="Telecharger PDF">
                      <Download className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </ModulePageShell>
  );
}

