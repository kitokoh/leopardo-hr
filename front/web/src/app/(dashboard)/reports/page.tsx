'use client';

import { useState, useCallback } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import {
  BarChart3,
  Download,
  FileText,
  Calendar,
  Users,
  Clock,
  TrendingUp,
  DollarSign,
} from 'lucide-react';

interface ReportConfig {
  id: string;
  title: string;
  description: string;
  icon: typeof BarChart3;
  color: string;
  endpoint: string;
  params: { key: string; label: string; type: string }[];
}

const reports: ReportConfig[] = [
  {
    id: 'attendance-summary',
    title: 'Resume Presences',
    description: 'Rapport mensuel des presences, retards et absences par employe.',
    icon: Clock,
    color: 'text-blue-600 bg-blue-50',
    endpoint: '/reports/attendance-summary',
    params: [
      { key: 'month', label: 'Mois', type: 'month' },
    ],
  },
  {
    id: 'payroll-summary',
    title: 'Resume Paie',
    description: 'Total brut/net, cotisations et charges par periode de paie.',
    icon: DollarSign,
    color: 'text-emerald-600 bg-emerald-50',
    endpoint: '/reports/payroll-summary',
    params: [
      { key: 'period', label: 'Periode', type: 'month' },
    ],
  },
  {
    id: 'leave-balances',
    title: 'Soldes Conges',
    description: 'Etat des soldes de conges pour tous les employes.',
    icon: Calendar,
    color: 'text-violet-600 bg-violet-50',
    endpoint: '/reports/leave-balances',
    params: [
      { key: 'year', label: 'Annee', type: 'number' },
    ],
  },
  {
    id: 'headcount',
    title: 'Effectifs',
    description: 'Evolution des effectifs, entrees et sorties par periode.',
    icon: Users,
    color: 'text-amber-600 bg-amber-50',
    endpoint: '/reports/headcount',
    params: [
      { key: 'from', label: 'Du', type: 'date' },
      { key: 'to', label: 'Au', type: 'date' },
    ],
  },
  {
    id: 'training-progress',
    title: 'Suivi Formations',
    description: 'Taux de participation et completion des formations.',
    icon: TrendingUp,
    color: 'text-cyan-600 bg-cyan-50',
    endpoint: '/reports/training-progress',
    params: [
      { key: 'year', label: 'Annee', type: 'number' },
    ],
  },
  {
    id: 'contract-expiry',
    title: 'Echeances Contrats',
    description: 'Contrats arrivant a echeance dans les 30, 60, 90 prochains jours.',
    icon: FileText,
    color: 'text-red-500 bg-red-50',
    endpoint: '/reports/contract-expiry',
    params: [
      { key: 'days', label: 'Jours', type: 'number' },
    ],
  },
];

export default function ReportsPage() {
  const [generating, setGenerating] = useState<string | null>(null);
  const [params, setParams] = useState<Record<string, Record<string, string>>>({});
  const [results, setResults] = useState<Record<string, string>>({});

  const updateParam = (reportId: string, key: string, value: string) => {
    setParams(prev => ({
      ...prev,
      [reportId]: { ...(prev[reportId] || {}), [key]: value },
    }));
  };

  const generateReport = useCallback(async (report: ReportConfig) => {
    setGenerating(report.id);
    setResults(prev => ({ ...prev, [report.id]: '' }));
    try {
      const queryParams = params[report.id] || {};
      const qs = Object.entries(queryParams).filter(([, v]) => v).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
      const url = `${report.endpoint}${qs ? `?${qs}` : ''}`;
      const res = await apiFetch(url);
      const blob = await res.blob();
      const downloadUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = downloadUrl;
      a.download = `${report.id}-${new Date().toISOString().slice(0, 10)}.pdf`;
      a.click();
      URL.revokeObjectURL(downloadUrl);
      setResults(prev => ({ ...prev, [report.id]: 'Rapport telecharge avec succes.' }));
    } catch {
      setResults(prev => ({ ...prev, [report.id]: 'Erreur lors de la generation du rapport.' }));
    } finally {
      setGenerating(null);
    }
  }, [params]);

  return (
    <ModulePageShell
      title="Rapports"
      subtitle="Generez et telechargez vos rapports RH : presences, paie, conges, effectifs, formations et contrats."
      accentClassName="bg-gradient-to-br from-violet-500/10 via-white to-white"
    >
      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {reports.map((report, i) => (
          <motion.div
            key={report.id}
            initial={{ opacity: 0, y: 15 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="flex flex-col rounded-2xl border border-app-border bg-white p-5 shadow-sm"
          >
            <div className="mb-3 flex items-start gap-3">
              <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${report.color}`}>
                <report.icon className="h-5 w-5" />
              </div>
              <div className="flex-1">
                <h3 className="text-sm font-bold text-slate-950">{report.title}</h3>
                <p className="mt-0.5 text-xs text-slate-500">{report.description}</p>
              </div>
            </div>

            <div className="mb-4 flex-1 space-y-2">
              {report.params.map(p => (
                <div key={p.key}>
                  <label className="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-400">{p.label}</label>
                  <input
                    type={p.type}
                    value={params[report.id]?.[p.key] || ''}
                    onChange={e => updateParam(report.id, p.key, e.target.value)}
                    className="w-full rounded-xl border border-app-border bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                </div>
              ))}
            </div>

            <button
              onClick={() => generateReport(report)}
              disabled={generating === report.id}
              className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
            >
              {generating === report.id ? (
                <>
                  <BarChart3 className="h-4 w-4 animate-spin" />
                  Generation...
                </>
              ) : (
                <>
                  <Download className="h-4 w-4" />
                  Generer
                </>
              )}
            </button>

            {results[report.id] && (
              <p className={`mt-2 text-xs font-medium ${results[report.id].includes('Erreur') ? 'text-red-500' : 'text-emerald-600'}`}>
                {results[report.id]}
              </p>
            )}
          </motion.div>
        ))}
      </div>
    </ModulePageShell>
  );
}
