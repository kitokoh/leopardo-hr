'use client';

import { useCallback, useEffect, useState } from 'react';
import { Magnet, AlertTriangle, Inbox, ChevronLeft, ChevronRight } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface CrmLead {
  id: number;
  first_name: string;
  last_name: string;
  email: string | null;
  phone: string | null;
  company_name: string | null;
  source: string | null;
  status: string;
  created_at: string;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
  };
}

/**
 * #5715 — CRM Client : prospects (leads, tenant-scoped).
 *
 * Consomme exclusivement `GET /api/v1/crm/leads` (contrat #5712). Aucun
 * appel au CRM commercial plateforme. États loading / error / empty +
 * pagination ; `source` et `status` sont des whitelists (jamais de valeur
 * libre rendue sans libellé).
 */
export default function CrmLeadsPage() {
  const locale = getPreferredLocale();
  const [leads, setLeads] = useState<CrmLead[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const loadLeads = useCallback(async (targetPage: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/crm/leads?per_page=25&page=${targetPage}`);
      if (res.status === 403 || res.status === 404) {
        setError('forbidden');
        setLeads([]);

        return;
      }
      const body = (await res.json()) as PaginatedResponse<CrmLead>;
      setLeads(body.data ?? []);
      setPage(body.meta?.current_page ?? targetPage);
      setLastPage(body.meta?.last_page ?? 1);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadLeads(page);
  }, [loadLeads, page]);

  const statusBadge = (status: string) => {
    const styles: Record<string, string> = {
      new: 'bg-blue-50 text-blue-700',
      contacted: 'bg-amber-50 text-amber-700',
      qualified: 'bg-violet-50 text-violet-700',
      converted: 'bg-emerald-50 text-emerald-700',
      lost: 'bg-slate-100 text-slate-500',
    };

    return (
      <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${styles[status] ?? styles.new}`}>
        {status}
      </span>
    );
  };

  return (
    <ModulePageShell
      title={t(locale, 'crm.leads.title')}
      subtitle={t(locale, 'crm.leads.subtitle')}
      accentClassName="from-amber-500/10 via-white/40 to-orange-500/10"
    >
      <div className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
        <div className="mb-5 flex items-center gap-2 text-sm text-slate-500">
          <Magnet className="h-4 w-4" />
          <span>{leads.length}</span>
        </div>

        {loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
            <div className="h-5 w-5 animate-spin rounded-full border-2 border-amber-500 border-t-transparent" />
            <span className="text-sm">{t(locale, 'crm.loading')}</span>
          </div>
        )}

        {!loading && error === 'forbidden' && (
          <div className="flex items-center justify-center gap-3 py-16 text-amber-600">
            <AlertTriangle className="h-6 w-6" />
            <p className="text-sm font-medium">{t(locale, 'crm.featureLocked')}</p>
          </div>
        )}

        {!loading && error === 'network' && (
          <div className="flex items-center justify-center gap-3 py-16 text-red-500">
            <AlertTriangle className="h-6 w-6" />
            <p className="text-sm font-medium">{t(locale, 'crm.errorLoading')}</p>
          </div>
        )}

        {!loading && !error && leads.length === 0 && (
          <div className="flex flex-col items-center gap-3 py-16 text-slate-400">
            <Inbox className="h-10 w-10" />
            <p className="text-sm">{t(locale, 'crm.leads.empty')}</p>
          </div>
        )}

        {!loading && !error && leads.length > 0 && (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.leads.colName')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.leads.colCompany')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.leads.colStatus')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.leads.colSource')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.leads.colEmail')}</th>
                    <th className="pb-3 font-bold">{t(locale, 'crm.leads.colCreated')}</th>
                  </tr>
                </thead>
                <tbody>
                  {leads.map((lead) => (
                    <tr key={lead.id} className="border-b border-slate-50 last:border-0">
                      <td className="py-3 pr-4 font-semibold text-slate-800">
                        {lead.first_name} {lead.last_name}
                      </td>
                      <td className="py-3 pr-4 text-slate-600">{lead.company_name ?? '—'}</td>
                      <td className="py-3 pr-4">{statusBadge(lead.status)}</td>
                      <td className="py-3 pr-4 text-slate-600">{lead.source ?? '—'}</td>
                      <td className="py-3 pr-4 text-slate-600">{lead.email ?? '—'}</td>
                      <td className="py-3 text-slate-500">
                        {lead.created_at ? new Date(lead.created_at).toLocaleDateString() : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {lastPage > 1 && (
              <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  <ChevronLeft className="h-4 w-4" />
                  {t(locale, 'crm.paginationPrev')}
                </button>
                <span className="text-sm text-slate-500">
                  {t(locale, 'crm.paginationPage')} {page} / {lastPage}
                </span>
                <button
                  type="button"
                  disabled={page >= lastPage}
                  onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  {t(locale, 'crm.paginationNext')}
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </ModulePageShell>
  );
}
