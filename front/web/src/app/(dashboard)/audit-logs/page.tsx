'use client';

/**
 * Issue #9333 — Page de consultation de la piste d'audit tenant (RGPD).
 * 
 * Réservé au principal. Permet de répondre à « qui a consulté la fiche de X ? ».
 * Pagination serveur, filtres combinables, export CSV limité et audité.
 */

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { Download, Filter, RefreshCw, Search, Shield } from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type AuditLogEntry = {
  id: number;
  company_id: string;
  user_id: number | null;
  action: string;
  module: string | null;
  auditable_type: string | null;
  auditable_id: number | null;
  old_values: Record<string, unknown>;
  new_values: Record<string, unknown>;
  ip_address: string | null;
  user_agent: string | null;
  metadata: Record<string, unknown>;
  created_at: string;
  user?: {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
  };
};

type AuditLogsPayload = {
  data?: AuditLogEntry[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    retention_months: number;
  };
};

export default function AuditLogsPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  
  const [logs, setLogs] = useState<AuditLogEntry[]>([]);
  const [meta, setMeta] = useState({ page: 1, lastPage: 1, total: 0, retentionMonths: 12 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Filtres
  const [searchAction, setSearchAction] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [showFilters, setShowFilters] = useState(false);

  // Export
  const [exporting, setExporting] = useState(false);

  const load = useCallback(async (page: number) => {
    setLoading(true);
    setError(null);

    try {
      const params = new URLSearchParams({ page: String(page), per_page: '50' });
      
      if (searchAction.trim()) {
        params.set('action', searchAction.trim());
      }
      if (dateFrom) {
        params.set('date_from', dateFrom);
      }
      if (dateTo) {
        params.set('date_to', dateTo);
      }

      const response = await apiFetch(`/audit-logs?${params.toString()}`, { _cacheBust: true });
      
      if (!response.ok) {
        if (response.status === 403) {
          setError(t(locale, 'auditLogs.forbidden', 'Accès réservé au principal'));
          return;
        }
        setError(t(locale, 'auditLogs.loadError', 'Erreur de chargement'));
        return;
      }

      const payload = (await response.json()) as AuditLogsPayload;
      setLogs(Array.isArray(payload.data) ? payload.data : []);
      setMeta({
        page: payload.meta?.current_page ?? page,
        lastPage: payload.meta?.last_page ?? 1,
        total: payload.meta?.total ?? 0,
        retentionMonths: payload.meta?.retention_months ?? 12,
      });
    } catch (err) {
      setError(t(locale, 'auditLogs.loadError', 'Erreur de chargement'));
    } finally {
      setLoading(false);
    }
  }, [locale, searchAction, dateFrom, dateTo]);

  useEffect(() => {
    void load(1);
  }, [load]);

  const handleSearch = (event: React.FormEvent) => {
    event.preventDefault();
    void load(1);
  };

  const goToPage = (page: number) => {
    if (page < 1 || page > meta.lastPage || page === meta.page) return;
    void load(page);
  };

  const handleExport = async () => {
    if (!dateFrom || !dateTo) {
      setError(t(locale, 'auditLogs.exportDatesRequired', 'Dates de début et fin requises pour l\'export'));
      return;
    }

    setExporting(true);
    setError(null);
    setNotice(null);

    try {
      const params = new URLSearchParams({
        date_from: dateFrom,
        date_to: dateTo,
      });
      
      if (searchAction.trim()) {
        params.set('action', searchAction.trim());
      }

      const response = await apiFetch(`/audit-logs/export?${params.toString()}`);
      
      if (!response.ok) {
        setError(t(locale, 'auditLogs.exportError', 'Erreur d\'export'));
        return;
      }

      const payload = await response.json() as { data?: { csv: string; count: number } };
      const csv = payload.data?.csv ?? '';
      const count = payload.data?.count ?? 0;

      // Télécharger le CSV
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `audit-logs-${dateFrom}-${dateTo}.csv`;
      link.click();

      setNotice(
        t(locale, 'auditLogs.exportSuccess', `Export réussi : ${count} entrées`)
      );
    } catch (err) {
      setError(t(locale, 'auditLogs.exportError', 'Erreur d\'export'));
    } finally {
      setExporting(false);
    }
  };

  const formatDate = (dateString: string) => {
    try {
      return new Date(dateString).toLocaleString(locale === 'fr' ? 'fr-FR' : locale);
    } catch {
      return dateString;
    }
  };

  const getUserName = (entry: AuditLogEntry) => {
    if (!entry.user) return t(locale, 'auditLogs.system', 'Système');
    const name = `${entry.user.first_name ?? ''} ${entry.user.last_name ?? ''}`.trim();
    return name || entry.user.email || `#${entry.user.id}`;
  };

  const getResourceLabel = (entry: AuditLogEntry) => {
    if (!entry.auditable_type) return '—';
    const type = entry.auditable_type.split('\\').pop() ?? entry.auditable_type;
    return entry.auditable_id ? `${type} #${entry.auditable_id}` : type;
  };

  return (
    <ModulePageShell
      title={t(locale, 'auditLogs.title', 'Piste d\'audit')}
      subtitle={t(locale, 'auditLogs.subtitle', 'Journal des actions administratives (RGPD)')}
      icon={Shield}
      accentClassName="bg-gradient-to-br from-blue-100 via-white to-white"
    >
      {error ? (
        <div
          role="alert"
          className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
          {error}
        </div>
      ) : null}

      {notice ? (
        <div
          role="status"
          className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
          {notice}
        </div>
      ) : null}

      {/* Statistiques */}
      <section className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-500">
            {t(locale, 'auditLogs.totalEntries', 'Entrées totales')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">{loading ? '...' : meta.total}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-500">
            {t(locale, 'auditLogs.retention', 'Rétention')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">
            {meta.retentionMonths} <span className="text-xl">{t(locale, 'auditLogs.months', 'mois')}</span>
          </p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-500">
            {t(locale, 'auditLogs.currentPage', 'Page')}
          </p>
          <p className="mt-3 text-4xl font-black text-slate-950">
            {meta.page} <span className="text-xl">/ {meta.lastPage}</span>
          </p>
        </div>
      </section>

      {/* Filtres et actions */}
      <section className="rounded-3xl border border-app-border bg-white p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            onClick={() => setShowFilters(!showFilters)}
            className="inline-flex items-center gap-2 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
          >
            <Filter className="h-4 w-4" />
            {t(locale, 'auditLogs.filters', 'Filtres')}
          </button>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => void load(meta.page)}
              disabled={loading}
              className="inline-flex items-center gap-2 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-60"
            >
              <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
              {t(locale, 'auditLogs.refresh', 'Actualiser')}
            </button>

            <button
              type="button"
              onClick={() => void handleExport()}
              disabled={exporting || !dateFrom || !dateTo}
              className="inline-flex items-center gap-2 rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
            >
              <Download className="h-4 w-4" />
              {t(locale, 'auditLogs.export', 'Exporter CSV')}
            </button>
          </div>
        </div>

        {showFilters ? (
          <form onSubmit={handleSearch} className="mt-4 grid gap-4 border-t border-app-border pt-4 md:grid-cols-3">
            <label className="text-sm font-medium text-slate-700">
              {t(locale, 'auditLogs.action', 'Action')}
              <input
                type="text"
                value={searchAction}
                onChange={(e) => setSearchAction(e.target.value)}
                placeholder={t(locale, 'auditLogs.actionPlaceholder', 'Ex: employee.created')}
                className="mt-1 w-full rounded-xl border border-app-border px-3 py-2 text-sm"
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {t(locale, 'auditLogs.dateFrom', 'Date de début')}
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="mt-1 w-full rounded-xl border border-app-border px-3 py-2 text-sm"
              />
            </label>

            <label className="text-sm font-medium text-slate-700">
              {t(locale, 'auditLogs.dateTo', 'Date de fin')}
              <input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="mt-1 w-full rounded-xl border border-app-border px-3 py-2 text-sm"
              />
            </label>

            <div className="md:col-span-3">
              <button
                type="submit"
                disabled={loading}
                className="rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
              >
                <Search className="inline h-4 w-4 mr-2" />
                {t(locale, 'auditLogs.search', 'Rechercher')}
              </button>
            </div>
          </form>
        ) : null}
      </section>

      {/* Liste des logs */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
            {t(locale, 'auditLogs.entries', 'Entrées d\'audit')}
          </h2>
        </div>

        {loading ? (
          <p className="px-6 py-8 text-sm text-slate-500">
            {t(locale, 'auditLogs.loading', 'Chargement...')}
          </p>
        ) : logs.length === 0 ? (
          <p className="px-6 py-8 text-sm text-slate-500">
            {t(locale, 'auditLogs.empty', 'Aucune entrée')}
          </p>
        ) : (
          <div className="divide-y divide-app-border">
            {logs.map((log) => (
              <div key={log.id} className="px-6 py-4">
                <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-bold text-slate-900">{log.action}</p>
                    <p className="text-xs text-slate-500">
                      {getUserName(log)} • {formatDate(log.created_at)}
                    </p>
                    {log.module ? (
                      <span className="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                        {log.module}
                      </span>
                    ) : null}
                  </div>

                  <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className="rounded-full bg-blue-100 px-3 py-1 font-semibold text-blue-800">
                      {getResourceLabel(log)}
                    </span>
                    {log.ip_address ? (
                      <span className="rounded-full bg-slate-100 px-3 py-1 font-mono text-slate-600">
                        {log.ip_address}
                      </span>
                    ) : null}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination */}
        {meta.lastPage > 1 ? (
          <div className="flex items-center justify-between border-t border-app-border px-6 py-4">
            <button
              type="button"
              onClick={() => goToPage(meta.page - 1)}
              disabled={meta.page === 1 || loading}
              className="rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-60"
            >
              {t(locale, 'auditLogs.previous', 'Précédent')}
            </button>

            <span className="text-sm font-medium text-slate-600">
              {t(locale, 'auditLogs.pageOf', `Page ${meta.page} sur ${meta.lastPage}`)}
            </span>

            <button
              type="button"
              onClick={() => goToPage(meta.page + 1)}
              disabled={meta.page === meta.lastPage || loading}
              className="rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:opacity-60"
            >
              {t(locale, 'auditLogs.next', 'Suivant')}
            </button>
          </div>
        ) : null}
      </section>
    </ModulePageShell>
  );
}
