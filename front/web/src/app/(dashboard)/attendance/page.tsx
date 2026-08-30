'use client';

/**
 * Page Pointage du jour + export CSV mensuel (#5696).
 *
 * - Vue manager (mode collection) : affiche toute l'équipe + bouton export CSV.
 * - Vue employé (mode single) : affiche son propre pointage.
 *
 * L'export utilise GET /api/v1/export/attendance?format=csv&from=...&to=...
 * et déclenche un téléchargement côté navigateur.
 */

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { Download, Loader2, Calendar } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

type AttendanceItem = {
  employee_id?: number;
  name?: string;
  matricule?: string;
  checked_in?: boolean;
  check_in_time?: string | null;
  check_out_time?: string | null;
  status?: string;
  hours_worked?: number | string | null;
};

type AttendancePayload = {
  data?: {
    mode?: string;
    items?: AttendanceItem[];
    item?: AttendanceItem;
    checked_in?: boolean;
    check_in_time?: string | null;
    check_out_time?: string | null;
    status?: string;
    hours_worked?: number | string | null;
  };
};

type ExportPayload = {
  data?: {
    format?: string;
    content?: string;
    filename?: string;
    count?: number;
  };
};

const emptySubscribe = () => () => {};

/** Formate YYYY-MM-DD pour le premier et dernier jour du mois donné. */
function monthRange(yearMonth: string): { from: string; to: string } {
  const [year, month] = yearMonth.split('-').map(Number);
  const from = new Date(year, month - 1, 1);
  const to   = new Date(year, month, 0); // dernier jour du mois
  const pad  = (n: number) => String(n).padStart(2, '0');
  return {
    from: `${from.getFullYear()}-${pad(from.getMonth() + 1)}-01`,
    to:   `${to.getFullYear()}-${pad(to.getMonth() + 1)}-${pad(to.getDate())}`,
  };
}

/** Déclenche un téléchargement de fichier CSV dans le navigateur. */
function downloadCsv(csvContent: string, filename: string): void {
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/** Renvoie le mois courant au format YYYY-MM. */
function currentYearMonth(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function AttendancePage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const copy = getCopy(locale);
  const [mode, setMode]   = useState<'collection' | 'single'>('single');
  const [items, setItems] = useState<AttendanceItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  // Export CSV (#5696)
  const [exportMonth, setExportMonth]     = useState<string>(currentYearMonth);
  const [exporting, setExporting]         = useState(false);
  const [exportError, setExportError]     = useState<string | null>(null);
  const [exportSuccess, setExportSuccess] = useState<string | null>(null);

  useEffect(() => {
    let active = true;

    async function load() {
      try {
        const response = await apiFetch('/attendance/today');
        const payload  = (await response.json()) as AttendancePayload;
        const data     = payload.data;

        if (!active) return;

        if (data?.mode === 'collection' && Array.isArray(data.items)) {
          setMode('collection');
          setItems(data.items);
          return;
        }

        const singleItem = data?.item ?? data;
        setMode('single');
        setItems(singleItem ? [singleItem as AttendanceItem] : []);
      } catch (err) {
        if (!active) return;
        setError(err instanceof ApiError ? err.message : copy.attendancePage.loadError);
      } finally {
        if (active) setLoading(false);
      }
    }

    void load();
    return () => { active = false; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /** Export CSV mensuel — #5696. */
  const handleExportCsv = useCallback(async () => {
    setExporting(true);
    setExportError(null);
    setExportSuccess(null);

    const { from, to } = monthRange(exportMonth);
    const params = new URLSearchParams({ format: 'csv', from, to });

    try {
      const response = await apiFetch(`/export/attendance?${params.toString()}`);
      const payload  = (await response.json()) as ExportPayload;

      const csvContent = payload.data?.content ?? '';
      const filename   = payload.data?.filename ?? `attendance_${from}_${to}.csv`;
      const count      = payload.data?.count ?? 0;

      if (!csvContent) {
        setExportError(i18nT(locale, 'csvExport.empty', 'Aucune donnée à exporter pour cette période.'));
        return;
      }

      downloadCsv(csvContent, filename);
      setExportSuccess(
        count > 0
          ? i18nT(locale, 'csvExport.success', `${count} ligne(s) exportée(s).`).replace('{count}', String(count))
          : i18nT(locale, 'csvExport.done', 'Export téléchargé.')
      );
    } catch (err) {
      setExportError(err instanceof ApiError ? err.message : i18nT(locale, 'csvExport.error', 'Impossible de générer l\'export.'));
    } finally {
      setExporting(false);
    }
  }, [exportMonth, locale]);

  return (
    <ModulePageShell
      title={i18nT(locale, 'attendance.title', 'Pointage du jour')}
      subtitle={i18nT(locale, 'attendance.subtitle', 'État temps réel depuis l\'API RH.')}
      accentClassName="bg-gradient-to-br from-warning/10 via-white to-white"
    >
      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {/* ── Export CSV mensuel (#5696) — visible uniquement pour les managers ── */}
      {!loading && mode === 'collection' && (
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <Calendar className="h-5 w-5 text-emerald-600 shrink-0" aria-hidden="true" />
          <div className="flex-1">
            <p className="text-sm font-bold text-slate-950">
              {i18nT(locale, 'csvExport.title', 'Export CSV présences')}
            </p>
            <p className="text-xs text-slate-500">
              {i18nT(locale, 'csvExport.subtitle', 'Téléchargez le rapport mensuel de pointage de votre équipe.')}
            </p>
          </div>
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <input
              type="month"
              value={exportMonth}
              max={currentYearMonth()}
              className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              onChange={(e) => { setExportMonth(e.target.value); setExportError(null); setExportSuccess(null); }}
              aria-label={i18nT(locale, 'csvExport.monthLabel', 'Mois à exporter')}
            />
            <Button
              type="button"
              loading={exporting}
              disabled={exporting || !exportMonth}
              icon={exporting ? undefined : <Download className="h-4 w-4" />}
              className="h-9 rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-widest text-white hover:bg-emerald-500 disabled:opacity-50 shrink-0"
              onClick={() => void handleExportCsv()}
            >
              {exporting
                ? i18nT(locale, 'csvExport.exporting', 'Export...')
                : i18nT(locale, 'csvExport.button', 'Exporter CSV')}
            </Button>
          </div>

          {/* Feedback */}
          {exportSuccess && (
            <p className="w-full text-xs font-medium text-emerald-700 mt-1">{exportSuccess}</p>
          )}
          {exportError && (
            <p role="alert" className="w-full text-xs font-medium text-red-600 mt-1">{exportError}</p>
          )}
        </div>
      )}

      {/* ── Statistiques rapides ──────────────────────────────────────────── */}
      <section className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Mode</p>
          <p className="mt-3 text-3xl font-black text-slate-950">{loading ? '...' : mode === 'collection' ? 'Manager' : 'Employé'}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Lignes</p>
          <p className="mt-3 text-3xl font-black text-slate-950">{loading ? '...' : items.length}</p>
        </div>
        <div className="rounded-2xl border border-app-border bg-white p-5 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-widest text-slate-400">Source</p>
          <p className="mt-3 text-lg font-bold text-slate-950">GET /attendance/today</p>
        </div>
      </section>

      {/* ── Liste pointage ────────────────────────────────────────────────── */}
      <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="border-b border-app-border px-6 py-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">État courant</h2>
        </div>
        <div className="divide-y divide-app-border">
          {loading ? (
            <div className="flex items-center gap-2 px-6 py-8 text-sm text-slate-500">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span>Chargement du pointage...</span>
            </div>
          ) : items.length === 0 ? (
            <div className="px-6 py-8 text-sm text-slate-500">Aucune donnée visible pour aujourd'hui.</div>
          ) : (
            items.map((item, index) => (
              <div key={`${item.employee_id ?? 'self'}-${index}`} className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                  <p className="text-sm font-bold text-slate-950">{item.name ?? 'Mon pointage'}</p>
                  <p className="text-xs text-slate-500">{item.matricule ?? 'Session personnelle'}</p>
                </div>
                <div className="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider">
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{item.check_in_time ?? 'Pas d\'entrée'}</span>
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{item.check_out_time ?? 'Pas de sortie'}</span>
                  <span className="rounded-full bg-warning/15 px-3 py-1 text-warning">{item.status ?? 'inconnu'}</span>
                </div>
              </div>
            ))
          )}
        </div>
      </section>
    </ModulePageShell>
  );
}
