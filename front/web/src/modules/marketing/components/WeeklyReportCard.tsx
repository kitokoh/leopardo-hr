'use client';

import { useCallback, useEffect, useState } from 'react';
import { Sparkles, TrendingUp, Mail, CalendarClock, AlertTriangle } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';

export type WeeklyReport = {
  period: { from: string; to: string };
  stats: {
    posts_published: number;
    posts_failed: number;
    posts_scheduled_upcoming: number;
    publications_by_platform: Record<string, number>;
    campaigns_started?: number;
    campaign_emails_sent?: number;
  };
  summary: string;
  summary_source: string;
};

type WeeklyReportPayload = {
  data?: WeeklyReport;
};

/**
 * Module Marketing — Issue #7755.
 *
 * Carte « Bilan hebdo » : agrégats marketing des 7 derniers jours
 * (GET /marketing/reports/weekly, issue #7753) + synthèse rédigée par
 * l'IA (badge « Synthèse IA ») ou déterministe en repli — jamais bloquant.
 */
export function WeeklyReportCard() {
  const [report, setReport] = useState<WeeklyReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/marketing/reports/weekly');
      const payload = (await res.json()) as WeeklyReportPayload;
      setReport(payload.data ?? null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger le bilan hebdomadaire.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <section data-testid="weekly-report-card" className="rounded-3xl border border-app-border bg-white shadow-sm">
      <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
        <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Bilan des 7 derniers jours</h2>
        {report ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-violet-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-violet-700">
            <Sparkles className="h-3 w-3" />
            {report.summary_source === 'ai' ? 'Synthese IA' : 'Synthese automatique'}
          </span>
        ) : null}
      </div>
      <div className="p-6">
        {loading ? (
          <p className="text-sm text-slate-500">Chargement du bilan...</p>
        ) : error ? (
          <p className="text-sm text-red-700">{error}</p>
        ) : report ? (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <div className="rounded-2xl bg-slate-50 p-4">
                <TrendingUp className="mb-2 h-4 w-4 text-emerald-600" />
                <p className="text-xl font-black text-slate-950">{report.stats.posts_published}</p>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Posts publies</p>
              </div>
              <div className="rounded-2xl bg-slate-50 p-4">
                <AlertTriangle className="mb-2 h-4 w-4 text-red-500" />
                <p className="text-xl font-black text-slate-950">{report.stats.posts_failed}</p>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Echecs</p>
              </div>
              <div className="rounded-2xl bg-slate-50 p-4">
                <CalendarClock className="mb-2 h-4 w-4 text-info" />
                <p className="text-xl font-black text-slate-950">{report.stats.posts_scheduled_upcoming}</p>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">A venir</p>
              </div>
              <div className="rounded-2xl bg-slate-50 p-4">
                <Mail className="mb-2 h-4 w-4 text-emerald-600" />
                <p className="text-xl font-black text-slate-950">{report.stats.campaign_emails_sent ?? 0}</p>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Emails campagne</p>
              </div>
            </div>
            {Object.keys(report.stats.publications_by_platform).length > 0 ? (
              <div className="flex flex-wrap gap-1.5">
                {Object.entries(report.stats.publications_by_platform).map(([platform, total]) => (
                  <span key={platform} className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                    {platform} · {total}
                  </span>
                ))}
              </div>
            ) : null}
            <p data-testid="weekly-report-summary" className="rounded-2xl border border-violet-100 bg-violet-50/50 px-4 py-3 text-sm leading-relaxed text-slate-700">
              {report.summary}
            </p>
          </div>
        ) : (
          <p className="text-sm text-slate-500">Aucun bilan disponible.</p>
        )}
      </div>
    </section>
  );
}
