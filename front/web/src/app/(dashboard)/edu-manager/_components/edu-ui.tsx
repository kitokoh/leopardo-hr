'use client';

import type { ReactNode } from 'react';
import { AlertTriangle, Inbox, Loader2, RefreshCw } from 'lucide-react';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Petit kit UI partagé des écrans EduManager (EDU-011/012/013).
 *
 * Réutilise le design system du portail (tokens glass-*, slate, emerald/cyan)
 * pour rester cohérent avec les autres modules (restaurant, crm, accounting).
 * Tous les composants sont stateless : l'état vit dans les pages.
 */

export function Spinner({ label }: { label?: string }) {
  const locale = getPreferredLocale();
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-slate-500" role="status">
      <Loader2 className="h-6 w-6 animate-spin text-emerald-600" aria-hidden="true" />
      {label ? <span className="text-sm font-medium">{label}</span> : <span className="text-sm font-medium">{t(locale, 'edu.common.loading')}</span>}
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
          {t(locale, 'edu.common.retry')}
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
    return <Spinner label={t(getPreferredLocale(), 'edu.common.loading')} />;
  }

  if (error) {
    return <ErrorState message={error} onRetry={onRetry} />;
  }

  if (rows.length === 0) {
    return <EmptyState label={emptyLabel} />;
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white/80 shadow-sm backdrop-blur-xl">
      <table className="w-full min-w-[640px] text-left text-sm">
        <thead>
          <tr className="border-b border-slate-200/80 bg-slate-50/80">
            {columns.map((column) => (
              <th key={column.key} className={`px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500 ${column.className ?? ''}`}>
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

type ModalProps = {
  open: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
};

export function Modal({ open, title, onClose, children }: ModalProps) {
  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label={title}>
      <button type="button" aria-label="Fermer" className="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" onClick={onClose} />
      <div className="relative max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-white/20 bg-white p-6 shadow-premium">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-black tracking-tight text-slate-950">{title}</h3>
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl px-2 py-1 text-sm font-bold text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          >
            ✕
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

export function Field({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5">
      <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">{label}</span>
      {children}
      {hint ? <span className="block text-xs text-slate-400">{hint}</span> : null}
    </label>
  );
}

const inputClasses =
  'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100';

export function TextInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`${inputClasses} ${props.className ?? ''}`} />;
}

export function SelectInput(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={`${inputClasses} ${props.className ?? ''}`} />;
}

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'ghost' | 'danger';
};

export function Button({ variant = 'primary', className = '', ...props }: ButtonProps) {
  const base =
    'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-50';
  const variants: Record<string, string> = {
    primary: 'bg-gradient-to-r from-emerald-500 to-cyan-600 text-white shadow-md shadow-emerald-500/20 hover:from-emerald-600 hover:to-cyan-700',
    ghost: 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
    danger: 'border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100',
  };

  return <button {...props} className={`${base} ${variants[variant]} ${className}`} />;
}

export function StatusBadge({ status }: { status: string }) {
  const tone =
    status === 'active' || status === 'published' || status === 'validated'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
      : status === 'draft' || status === 'pending'
        ? 'border-amber-200 bg-amber-50 text-amber-700'
        : status === 'inactive' || status === 'archived' || status === 'rejected'
          ? 'border-slate-200 bg-slate-100 text-slate-500'
          : 'border-cyan-200 bg-cyan-50 text-cyan-700';

  return (
    <span className={`inline-block rounded-lg border px-2 py-0.5 text-[10px] font-black uppercase tracking-widest ${tone}`}>
      {status}
    </span>
  );
}
