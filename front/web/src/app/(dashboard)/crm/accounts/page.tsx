'use client';

import { useCallback, useEffect, useState } from 'react';
import { Building2, Search, AlertTriangle, Inbox, ChevronLeft, ChevronRight } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface CrmAccount {
  id: number;
  name: string;
  status: string;
  email: string | null;
  phone: string | null;
  owner_id: number | null;
  archived_at: string | null;
  created_at: string;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

/**
 * #5715 — CRM Client : comptes (tenant-scoped).
 *
 * Consomme exclusivement `GET /api/v1/crm/accounts` (contrat #5712) —
 * aucun appel à PlatformCrmPipelineController. États loading / error /
 * empty et pagination (enveloppe Laravel `data`/`meta`).
 */
export default function CrmAccountsPage() {
  const locale = getPreferredLocale();
  const [accounts, setAccounts] = useState<CrmAccount[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const loadAccounts = useCallback(async (targetPage: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/crm/accounts?per_page=25&page=${targetPage}`);
      if (res.status === 403 || res.status === 404) {
        setError('forbidden');
        setAccounts([]);

        return;
      }
      const body = (await res.json()) as PaginatedResponse<CrmAccount>;
      setAccounts(body.data ?? []);
      setPage(body.meta?.current_page ?? targetPage);
      setLastPage(body.meta?.last_page ?? 1);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadAccounts(page);
  }, [loadAccounts, page]);

  const filtered = accounts.filter((account) => {
    const q = search.toLowerCase();

    return (
      account.name.toLowerCase().includes(q)
      || (account.email ?? '').toLowerCase().includes(q)
    );
  });

  const statusBadge = (status: string) => {
    const styles: Record<string, string> = {
      active: 'bg-emerald-50 text-emerald-700',
      archived: 'bg-slate-100 text-slate-500',
    };

    return (
      <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${styles[status] ?? styles.active}`}>
        {status}
      </span>
    );
  };

  return (
    <ModulePageShell
      title={t(locale, 'crm.accounts.title')}
      subtitle={t(locale, 'crm.accounts.subtitle')}
      accentClassName="from-emerald-500/10 via-white/40 to-teal-500/10"
    >
      <div className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t(locale, 'crm.searchPlaceholder')}
              className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-4 text-sm text-slate-800 outline-none transition-colors focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
            />
          </div>
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Building2 className="h-4 w-4" />
            <span>{accounts.length}</span>
          </div>
        </div>

        {loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
            <div className="h-5 w-5 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent" />
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

        {!loading && !error && filtered.length === 0 && (
          <div className="flex flex-col items-center gap-3 py-16 text-slate-400">
            <Inbox className="h-10 w-10" />
            <p className="text-sm">{t(locale, 'crm.accounts.empty')}</p>
          </div>
        )}

        {!loading && !error && filtered.length > 0 && (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-400">
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.accounts.colName')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.accounts.colStatus')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.accounts.colEmail')}</th>
                    <th className="pb-3 pr-4 font-bold">{t(locale, 'crm.accounts.colPhone')}</th>
                    <th className="pb-3 font-bold">{t(locale, 'crm.accounts.colCreated')}</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((account) => (
                    <tr key={account.id} className="border-b border-slate-50 last:border-0">
                      <td className="py-3 pr-4 font-semibold text-slate-800">{account.name}</td>
                      <td className="py-3 pr-4">{statusBadge(account.status)}</td>
                      <td className="py-3 pr-4 text-slate-600">{account.email ?? '—'}</td>
                      <td className="py-3 pr-4 text-slate-600">{account.phone ?? '—'}</td>
                      <td className="py-3 text-slate-500">
                        {account.created_at ? new Date(account.created_at).toLocaleDateString() : '—'}
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
