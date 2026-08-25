'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { Eye, Download, FileText, Globe, RefreshCw, ChevronLeft, ChevronRight, Fingerprint } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { t, interpolate } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

interface AccountingDocument {
  id: number;
  number: string;
  type: string;
  status: string;
}

interface ShareAccess {
  id: number;
  action: 'accounting.share.info' | 'accounting.share.download';
  module: string | null;
  request_id: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string | null;
}

interface AccessPage {
  data: ShareAccess[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  } | null;
}

/**
 * #5522 — Réponse à incident RGPD : « qui a consulté / téléchargé quel
 * document partagé, quand, depuis quelle IP ». Page dédiée du dashboard web
 * branchée sur GET /accounting/documents/shared/{document}/accesses.
 *
 * Le module Comptabilité complet (#5534) intégrera cette page dans sa
 * section document ; en attendant, la page est autonome (sélecteur de
 * document + historique paginé).
 */
export default function ShareAccessesPage() {
  const locale = getPreferredLocale();
  const [documents, setDocuments] = useState<AccountingDocument[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [accesses, setAccesses] = useState<ShareAccess[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loadingDocs, setLoadingDocs] = useState(true);
  const [loadingAccesses, setLoadingAccesses] = useState(false);
  const [docsError, setDocsError] = useState<string | null>(null);
  const [accessError, setAccessError] = useState<string | null>(null);

  const loadDocuments = useCallback(async () => {
    setLoadingDocs(true);
    setDocsError(null);
    try {
      const res = await apiFetch('/accounting/documents?per_page=100');
      const body = await res.json();
      setDocuments(body.data || []);
    } catch {
      setDocsError(t(locale, 'shareAccesses.loadDocumentsError'));
    } finally {
      setLoadingDocs(false);
    }
  }, [locale]);

  useEffect(() => {
    void loadDocuments();
  }, [loadDocuments]);

  const loadAccesses = useCallback(
    async (documentId: number, targetPage: number) => {
      setLoadingAccesses(true);
      setAccessError(null);
      try {
        const res = await apiFetch(
          `/accounting/documents/shared/${documentId}/accesses?per_page=25&page=${targetPage}`,
        );
        if (res.status === 403) {
          setAccessError(t(locale, 'shareAccesses.loadError'));
          setAccesses([]);
          return;
        }
        const body = (await res.json()) as AccessPage;
        setAccesses(body.data || []);
        setLastPage(body.meta?.last_page ?? 1);
        setTotal(body.meta?.total ?? 0);
      } catch {
        setAccessError(t(locale, 'shareAccesses.loadError'));
        setAccesses([]);
      } finally {
        setLoadingAccesses(false);
      }
    },
    [locale],
  );

  useEffect(() => {
    if (selectedId === null) {
      setAccesses([]);
      setTotal(0);
      setLastPage(1);
      return;
    }
    setPage(1);
    void loadAccesses(selectedId, 1);
  }, [selectedId, loadAccesses]);

  const selectedDocument = useMemo(
    () => documents.find((doc) => doc.id === selectedId) ?? null,
    [documents, selectedId],
  );

  const actionLabel = (action: string) =>
    action === 'accounting.share.download'
      ? t(locale, 'shareAccesses.actionDownload')
      : t(locale, 'shareAccesses.actionInfo');

  const actionIcon = (action: string) =>
    action === 'accounting.share.download' ? (
      <Download className="h-3.5 w-3.5" />
    ) : (
      <Eye className="h-3.5 w-3.5" />
    );

  const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-DZ' : locale === 'tr' ? 'tr-TR' : locale === 'en' ? 'en-GB' : 'fr-FR', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso));
  };

  return (
    <ModulePageShell
      title={t(locale, 'shareAccesses.title')}
      subtitle={t(locale, 'shareAccesses.subtitle')}
      accentClassName="bg-gradient-to-br from-amber-100 via-white to-white"
    >
      <motion.section
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="rounded-2xl border border-app-border bg-white p-5 shadow-sm"
      >
        <label htmlFor="share-access-document" className="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
          <FileText className="h-3.5 w-3.5" />
          {t(locale, 'shareAccesses.documentLabel')}
        </label>
        <select
          id="share-access-document"
          value={selectedId ?? ''}
          onChange={(e) => setSelectedId(e.target.value ? Number(e.target.value) : null)}
          disabled={loadingDocs}
          className="w-full max-w-xl rounded-xl border border-app-border bg-white px-3 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="">{loadingDocs ? t(locale, 'shareAccesses.loading') : t(locale, 'shareAccesses.selectDocument')}</option>
          {documents.map((doc) => (
            <option key={doc.id} value={doc.id}>
              {doc.number} · {doc.type}
            </option>
          ))}
        </select>
        {docsError && (
          <div className="mt-3 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <RefreshCw className="h-4 w-4 shrink-0" />
            <span>{docsError}</span>
            <button
              onClick={() => void loadDocuments()}
              className="ml-auto inline-flex items-center gap-1 rounded-lg bg-red-100 px-3 py-1 text-xs font-bold text-red-700 transition hover:bg-red-200"
            >
              <RefreshCw className="h-3 w-3" />
              {t(locale, 'shareAccesses.retry')}
            </button>
          </div>
        )}
      </motion.section>

      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex flex-wrap items-center gap-3 border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {t(locale, 'shareAccesses.title')}
          </h2>
          {selectedDocument && (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
              <Fingerprint className="h-3 w-3" />
              {selectedDocument.number}
            </span>
          )}
          {total > 0 && (
            <span className="ml-auto text-xs font-medium text-slate-400">
              {interpolate(t(locale, 'shareAccesses.total'), { count: total })}
            </span>
          )}
        </div>

        {selectedId === null ? (
          <div className="px-6 py-14 text-center">
            <Globe className="mx-auto mb-3 h-8 w-8 text-slate-300" />
            <p className="text-sm font-medium text-slate-500">{t(locale, 'shareAccesses.noDocumentSelected')}</p>
          </div>
        ) : accessError ? (
          <div className="px-6 py-14 text-center" role="alert">
            <RefreshCw className="mx-auto mb-3 h-8 w-8 text-red-300" />
            <p className="text-sm font-medium text-red-600">{accessError}</p>
          </div>
        ) : loadingAccesses ? (
          <div className="px-6 py-14 text-center">
            <p className="text-sm font-medium text-slate-400">{t(locale, 'shareAccesses.loading')}</p>
          </div>
        ) : accesses.length === 0 ? (
          <div className="px-6 py-14 text-center">
            <Eye className="mx-auto mb-3 h-8 w-8 text-slate-300" />
            <p className="text-sm font-bold text-slate-700">{t(locale, 'shareAccesses.emptyTitle')}</p>
            <p className="mt-1 text-sm text-slate-500">{t(locale, 'shareAccesses.emptyBody')}</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-app-border bg-transparent/50">
                    <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'shareAccesses.dateHeader')}</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'shareAccesses.actionHeader')}</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'shareAccesses.ipHeader')}</th>
                    <th className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'shareAccesses.userAgentHeader')}</th>
                    <th className="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t(locale, 'shareAccesses.requestIdHeader')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-app-border">
                  {accesses.map((access) => (
                    <tr key={access.id} className="transition-colors hover:bg-transparent/60">
                      <td className="whitespace-nowrap px-6 py-4 font-medium text-slate-700">{formatDate(access.created_at)}</td>
                      <td className="px-4 py-4">
                        <span
                          className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
                            access.action === 'accounting.share.download'
                              ? 'bg-emerald-50 text-emerald-700'
                              : 'bg-sky-50 text-sky-700'
                          }`}
                        >
                          {actionIcon(access.action)}
                          {actionLabel(access.action)}
                        </span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 font-mono text-xs text-slate-600">{access.ip_address || '—'}</td>
                      <td className="max-w-[280px] truncate px-4 py-4 text-slate-500" title={access.user_agent ?? undefined}>
                        {access.user_agent || '—'}
                      </td>
                      <td className="whitespace-nowrap px-6 py-4 font-mono text-xs text-slate-400">{access.request_id || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {lastPage > 1 && (
              <div className="flex items-center justify-between border-t border-app-border px-6 py-3">
                <button
                  onClick={() => selectedId !== null && void loadAccesses(selectedId, page - 1).then(() => setPage(page - 1))}
                  disabled={page <= 1 || loadingAccesses}
                  className="inline-flex items-center gap-1 rounded-lg border border-app-border px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                >
                  <ChevronLeft className="h-3.5 w-3.5" />
                  {t(locale, 'shareAccesses.previousPage')}
                </button>
                <span className="text-xs font-medium text-slate-500">
                  {interpolate(t(locale, 'shareAccesses.pageOf'), { current: page, total: lastPage })}
                </span>
                <button
                  onClick={() => selectedId !== null && void loadAccesses(selectedId, page + 1).then(() => setPage(page + 1))}
                  disabled={page >= lastPage || loadingAccesses}
                  className="inline-flex items-center gap-1 rounded-lg border border-app-border px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                >
                  {t(locale, 'shareAccesses.nextPage')}
                  <ChevronRight className="h-3.5 w-3.5" />
                </button>
              </div>
            )}
          </>
        )}
      </section>
    </ModulePageShell>
  );
}
