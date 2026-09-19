'use client';

import type { ReactNode } from 'react';
import { AlertTriangle, Inbox, Loader2, RefreshCw } from 'lucide-react';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Petit kit UI partagé des écrans HealthManager (HC-008, #7792).
 *
 * Calque du kit EduManager (`edu-ui.tsx`, EDU-011) : réutilise le design
 * system du portail (tokens glass-*, slate, emerald/cyan — aucune couleur
 * codée en dur, décision P05) pour rester cohérent avec les autres
 * modules. Tous les composants sont stateless : l'état vit dans les pages.
 */

export function Spinner({ label }: { label?: string }) {
  const locale = getPreferredLocale();
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-slate-500" role="status">
      <Loader2 className="h-6 w-6 animate-spin text-emerald-700" aria-hidden="true" />
      <span className="text-sm font-medium">{label ?? t(locale, 'health.common.loading')}</span>
    </div>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  const locale = getPreferredLocale();
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/70 p-8 text-center">
      <AlertTriangle className="h-8 w-8 text-rose-500" aria-hidden="true" />
      <p className="max-w-md text-sm font-medium text-rose-700">{message}</p>
      {onRetry ? (
        <button
          type="button"
          onClick={onRetry}
          className="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50"
        >
          <RefreshCw className="h-4 w-4" aria-hidden="true" />
          {t(locale, 'health.common.retry')}
        </button>
      ) : null}
    </div>
  );
}

export function EmptyState({ label }: { label: string }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white/50 p-10 text-center">
      <Inbox className="h-8 w-8 text-slate-400" aria-hidden="true" />
      <p className="text-sm font-medium text-slate-500">{label}</p>
    </div>
  );
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className={`rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-sm backdrop-blur-xl ${className}`}>
      {children}
    </div>
  );
}

export function SectionTitle({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="mb-4">
      <h2 className="text-lg font-black tracking-tight text-slate-950">{title}</h2>
      {subtitle ? <p className="mt-1 text-sm text-slate-500">{subtitle}</p> : null}
    </div>
  );
}

export function StatusBadge({ status }: { status: string }) {
  const tone =
    status === 'active' || status === 'free' || status === 'paid' || status === 'confirmed' || status === 'completed'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
      : status === 'draft' || status === 'scheduled' || status === 'partially_paid' || status === 'maintenance'
        ? 'border-amber-200 bg-amber-50 text-amber-700'
        : status === 'cancelled' || status === 'no_show' || status === 'archived' || status === 'deceased' || status === 'inactive'
          ? 'border-slate-200 bg-slate-100 text-slate-500'
          : 'border-cyan-200 bg-cyan-50 text-cyan-700';

  return (
    <span className={`inline-block rounded-lg border px-2 py-0.5 text-[10px] font-black uppercase tracking-widest ${tone}`}>
      {status}
    </span>
  );
}

export type Column<T> = {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  className?: string;
};

type DataTableProps<T> = {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  emptyLabel: string;
  loading?: boolean;
  error?: string | null;
  onRetry?: () => void;
};

export function DataTable<T>({ columns, rows, rowKey, emptyLabel, loading, error, onRetry }: DataTableProps<T>) {
  if (loading) {
    return <Spinner />;
  }

  if (error) {
    return <ErrorState message={error} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyState label={emptyLabel} />;
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white/80 shadow-sm backdrop-blur-xl">
      <table className="w-full min-w-[640px] text-start text-sm">
        <thead>
          <tr className="border-b border-slate-200/80 bg-slate-50/80">
            {columns.map((column) => (
              <th key={column.key} className={`px-4 py-3 text-start text-[10px] font-black uppercase tracking-widest text-slate-500 ${column.className ?? ''}`}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((row) => (
            <tr key={rowKey(row)} className="transition-colors hover:bg-emerald-50/40">
              {columns.map((column) => (
                <td key={column.key} className={`px-4 py-3 align-middle text-slate-700 ${column.className ?? ''}`}>
                  {column.render(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** Carte compteur du tableau de bord (RDV du jour, occupation, CA…). */
export function StatCard({
  icon: Icon,
  label,
  value,
  hint,
}: {
  icon: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>;
  label: string;
  value: string;
  hint?: string;
}) {
  return (
    <Card>
      <div className="flex items-center gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/15 to-cyan-500/15 text-emerald-700">
          <Icon className="h-5 w-5" aria-hidden="true" />
        </div>
        <div>
          <p className="text-2xl font-black tracking-tight text-slate-950">{value}</p>
          <p className="text-xs font-bold text-slate-500">{label}</p>
          {hint ? <p className="text-[10px] text-slate-400">{hint}</p> : null}
        </div>
      </div>
    </Card>
  );
}
