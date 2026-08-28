'use client';

import { useCallback, useEffect, useState } from 'react';
import { Users, AlertTriangle, Inbox, ChevronLeft, ChevronRight } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface CrmContact {
  id: number;
  first_name: string;
  last_name: string;
  email: string | null;
  phone: string | null;
  title: string | null;
  is_primary: boolean;
  account_id: number | null;
  account?: { id: number; name: string } | null;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
  };
}

/**
 * #5715 — CRM Client : contacts (tenant-scoped).
 *
 * Consomme exclusivement `GET /api/v1/crm/contacts` (contrat #5712) avec
 * eager loading du compte (`account`). Aucun appel au CRM commercial
 * plateforme. États loading / error / empty + pagination.
 */
export default function CrmContactsPage() {
  const locale = getPreferredLocale();
  const [contacts, setContacts] = useState<CrmContact[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const loadContacts = useCallback(async (targetPage: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/crm/contacts?per_page=25&page=${targetPage}`);
      if (res.status === 403 || res.status === 404) {
        setError('forbidden');
        setContacts([]);

        return;
      }
      const body = (await res.json()) as PaginatedResponse<CrmContact>;
      setContacts(body.data ?? []);
      setPage(body.meta?.current_page ?? targetPage);
      setLastPage(body.meta?.last_page ?? 1);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadContacts(page);
  }, [loadContacts, page]);

  return (
    <ModulePageShell
      title={t(locale, 'crm.contacts.title')}
      subtitle={t(locale, 'crm.contacts.subtitle')}
      accentClassName="from-cyan-500/10 via-white/40 to-blue-500/10"
    >
      <div className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
        <div className="mb-5 flex items-center gap-2 text-sm text-slate-500">
          <Users className="h-4 w-4" />
          <span>{contacts.length}</span>
        </div>

        {loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
            <div className="h-5 w-5 animate-spin rounded-full border-2 border-cyan-500 border-t-transparent" />
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

        {!loading && !error && contacts.length === 0 && (
          <div className="flex flex-col items-center gap-3 py-16 text-slate-400">
            <Inbox className="h-10 w-10" />
            <p className="text-sm">{t(locale, 'crm.contacts.empty')}</p>
          </div>
        )}

        {!loading && !error && contacts.length > 0 && (
          <>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {contacts.map((contact) => (
                <div
                  key={contact.id}
                  className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="font-bold text-slate-800">
                        {contact.first_name} {contact.last_name}
                      </p>
                      {contact.is_primary && (
                        <span className="mt-1 inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-cyan-700">
                          {t(locale, 'crm.contacts.primary')}
                        </span>
                      )}
                    </div>
                    {contact.account && (
                      <span className="text-xs font-medium text-slate-400">{contact.account.name}</span>
                    )}
                  </div>
                  <div className="mt-3 space-y-1 text-sm text-slate-500">
                    <p>{contact.title ?? '—'}</p>
                    <p className="truncate">{contact.email ?? '—'}</p>
                    <p>{contact.phone ?? '—'}</p>
                  </div>
                </div>
              ))}
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
