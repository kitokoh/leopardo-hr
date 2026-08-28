'use client';

import { useCallback, useEffect, useState } from 'react';
import { AlertTriangle, Inbox, RefreshCw } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface CrmOpportunity {
  id: number;
  name: string;
  stage: string;
  amount: number | null;
  expected_close_at: string | null;
  pipeline_id: number | null;
  status: string;
}

/**
 * #5715 — CRM Client : pipeline d'opportunités (base, tenant-scoped).
 *
 * Vue kanban minimale regroupant les opportunités par stage
 * (`GET /api/v1/crm/opportunities`, contrat #5712) — aucune donnée du
 * pipeline commercial plateforme (PlatformCrmPipelineController n'est
 * jamais appelé, ADR-CRM-DUAL-CONTEXTS).
 */
export default function CrmPipelinePage() {
  const locale = getPreferredLocale();
  const [opportunities, setOpportunities] = useState<CrmOpportunity[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadOpportunities = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/crm/opportunities?per_page=100');
      if (res.status === 403 || res.status === 404) {
        setError('forbidden');
        setOpportunities([]);

        return;
      }
      const body = (await res.json()) as { data?: CrmOpportunity[] };
      setOpportunities(body.data ?? []);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadOpportunities();
  }, [loadOpportunities]);

  const stages = [
    { key: 'prospecting', color: 'bg-blue-100 text-blue-700' },
    { key: 'qualification', color: 'bg-violet-100 text-violet-700' },
    { key: 'proposal', color: 'bg-amber-100 text-amber-700' },
    { key: 'negotiation', color: 'bg-orange-100 text-orange-700' },
    { key: 'won', color: 'bg-emerald-100 text-emerald-700' },
    { key: 'lost', color: 'bg-slate-100 text-slate-500' },
  ];

  const byStage = (stageKey: string) => opportunities.filter((o) => (o.stage ?? 'prospecting') === stageKey);

  const formatAmount = (amount: number | null) =>
    amount == null ? '—' : new Intl.NumberFormat(locale === 'fr' ? 'fr-FR' : locale, { style: 'currency', currency: 'EUR' }).format(amount);

  return (
    <ModulePageShell
      title={t(locale, 'crm.pipeline.title')}
      subtitle={t(locale, 'crm.pipeline.subtitle')}
      accentClassName="from-violet-500/10 via-white/40 to-purple-500/10"
    >
      {loading && (
        <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-violet-500 border-t-transparent" />
          <span className="text-sm">{t(locale, 'crm.loading')}</span>
        </div>
      )}

      {!loading && error === 'forbidden' && (
        <div className="flex items-center justify-center gap-3 rounded-3xl border border-white/20 bg-white/70 p-10 text-amber-600 shadow-premium backdrop-blur-xl">
          <AlertTriangle className="h-6 w-6" />
          <p className="text-sm font-medium">{t(locale, 'crm.featureLocked')}</p>
        </div>
      )}

      {!loading && error === 'network' && (
        <div className="flex items-center justify-center gap-3 rounded-3xl border border-white/20 bg-white/70 p-10 text-red-500 shadow-premium backdrop-blur-xl">
          <AlertTriangle className="h-6 w-6" />
          <p className="text-sm font-medium">{t(locale, 'crm.errorLoading')}</p>
        </div>
      )}

      {!loading && !error && opportunities.length === 0 && (
        <div className="flex flex-col items-center gap-3 rounded-3xl border border-white/20 bg-white/70 p-14 text-slate-400 shadow-premium backdrop-blur-xl">
          <Inbox className="h-10 w-10" />
          <p className="text-sm">{t(locale, 'crm.pipeline.empty')}</p>
        </div>
      )}

      {!loading && !error && opportunities.length > 0 && (
        <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
          {stages.map((stage) => (
            <div key={stage.key} className="rounded-2xl border border-slate-100 bg-white/60 p-3 backdrop-blur">
              <div className="mb-3 flex items-center justify-between">
                <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider ${stage.color}`}>
                  {t(locale, `crm.pipeline.stage.${stage.key}`, stage.key)}
                </span>
                <span className="text-xs font-bold text-slate-400">{byStage(stage.key).length}</span>
              </div>
              <div className="space-y-2">
                {byStage(stage.key).map((opportunity) => (
                  <div key={opportunity.id} className="rounded-xl border border-slate-100 bg-white p-3 shadow-sm">
                    <p className="text-sm font-semibold text-slate-800">{opportunity.name}</p>
                    <p className="mt-1 text-xs font-bold text-slate-600">{formatAmount(opportunity.amount)}</p>
                    {opportunity.expected_close_at && (
                      <p className="mt-1 text-[11px] text-slate-400">
                        {new Date(opportunity.expected_close_at).toLocaleDateString()}
                      </p>
                    )}
                  </div>
                ))}
                {byStage(stage.key).length === 0 && (
                  <p className="py-4 text-center text-xs text-slate-300">—</p>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {!loading && !error && opportunities.length > 0 && (
        <div className="flex justify-end">
          <button
            type="button"
            onClick={() => void loadOpportunities()}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50"
          >
            <RefreshCw className="h-4 w-4" />
            {t(locale, 'crm.refresh')}
          </button>
        </div>
      )}
    </ModulePageShell>
  );
}
