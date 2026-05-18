'use client';

import { useState, useCallback } from 'react';
import { motion } from 'framer-motion';
import { apiFetch } from '@/lib/api-client';
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
    color: 'text-blue-600',
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
    color: 'text-emerald-600',
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
    color: 'text-purple-600',
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
    color: 'text-amber-600',
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
    color: 'text-cyan-600',
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
    color: 'text-red-500',
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
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Rapports</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400">Generez et telechargez vos rapports RH</p>
      </div>

      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {reports.map((report, i) => (
          <motion.div
            key={report.id}
            initial={{ opacity: 0, y: 15 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 flex flex-col"
          >
            <div className="flex items-start gap-3 mb-3">
              <div className={`rounded-lg p-2 bg-slate-50 dark:bg-slate-700`}>
                <report.icon className={`h-5 w-5 ${report.color}`} />
              </div>
              <div className="flex-1">
                <h3 className="font-bold text-slate-900 dark:text-white text-sm">{report.title}</h3>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{report.description}</p>
              </div>
            </div>

            <div className="space-y-2 mb-4 flex-1">
              {report.params.map(p => (
                <div key={p.key}>
                  <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{p.label}</label>
                  <input
                    type={p.type}
                    value={params[report.id]?.[p.key] || ''}
                    onChange={e => updateParam(report.id, p.key, e.target.value)}
                    className="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
              ))}
            </div>

            <button
              onClick={() => generateReport(report)}
              disabled={generating === report.id}
              className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors disabled:opacity-50"
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
              <p className={`text-xs mt-2 ${results[report.id].includes('Erreur') ? 'text-red-500' : 'text-emerald-600'}`}>
                {results[report.id]}
              </p>
            )}
          </motion.div>
        ))}
      </div>
    </div>
  );
}
