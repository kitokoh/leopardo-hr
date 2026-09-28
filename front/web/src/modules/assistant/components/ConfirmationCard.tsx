'use client';

import { useState } from 'react';
import { AlertTriangle, CheckCircle2, ShieldCheck, Wrench, XCircle } from 'lucide-react';

import { Button } from '@/components/ui/Button';
import type { AppLocale } from '@/lib/i18n';
import { ta } from '../lib/i18n';
import type { AssistantPendingItem } from '../lib/storage';

type ConfirmationCardProps = {
  item: AssistantPendingItem;
  locale: AppLocale;
  onConfirm: (item: AssistantPendingItem) => Promise<void>;
  onReject: (item: AssistantPendingItem) => Promise<void>;
};

/** Rend une valeur d'argument/résultat lisible (objets → JSON compact). */
function formatValue(value: unknown): string {
  if (value === null || value === undefined) {
    return '—';
  }
  if (typeof value === 'string') {
    return value;
  }
  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
}

/**
 * BOS-035 (#8224) — carte de confirmation d'une action d'écriture proposée
 * par l'assistant. Une carte = UNE action, one-shot : après confirm/reject,
 * la carte affiche l'état final (exécutée + résultat, rejetée, expirée —
 * TTL serveur de 15 min — ou échouée) et ne propose plus d'action.
 */
export function ConfirmationCard({ item, locale, onConfirm, onReject }: ConfirmationCardProps) {
  const [busy, setBusy] = useState<'confirm' | 'reject' | null>(null);

  const handle = async (kind: 'confirm' | 'reject') => {
    if (busy || item.state !== 'pending') {
      return;
    }
    setBusy(kind);
    try {
      if (kind === 'confirm') {
        await onConfirm(item);
      } else {
        await onReject(item);
      }
    } finally {
      setBusy(null);
    }
  };

  const argumentEntries = Object.entries(item.arguments ?? {});

  return (
    <div
      data-testid="assistant-confirmation-card"
      className="mt-3 rounded-2xl border border-ia/30 bg-ia-light/40 p-4 shadow-sm"
    >
      <div className="flex flex-wrap items-center gap-2">
        <ShieldCheck className="h-4 w-4 shrink-0 text-ia" aria-hidden="true" />
        <p className="text-sm font-bold text-slate-900">{ta(locale, 'confirm_card_title')}</p>
        <span className="inline-flex items-center gap-1 rounded-full bg-ia px-2.5 py-0.5 text-xs font-bold text-white">
          <Wrench className="h-3 w-3" aria-hidden="true" />
          {item.tool}
        </span>
      </div>

      <p className="mt-2 text-sm leading-relaxed text-slate-700">{item.summary}</p>

      {argumentEntries.length > 0 ? (
        <div className="mt-3 rounded-xl border border-slate-200 bg-white p-3">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
            {ta(locale, 'confirm_args_title')}
          </p>
          <dl className="mt-2 space-y-1.5">
            {argumentEntries.map(([name, value]) => (
              <div key={name} className="flex flex-wrap items-baseline gap-x-2">
                <dt className="text-xs font-bold text-slate-500">{name}</dt>
                <dd className="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm text-slate-800">
                  {formatValue(value)}
                </dd>
              </div>
            ))}
          </dl>
        </div>
      ) : null}

      {item.state === 'pending' ? (
        <div className="mt-3 flex flex-wrap gap-2">
          <Button
            variant="primary"
            size="sm"
            loading={busy === 'confirm'}
            disabled={busy !== null}
            onClick={() => void handle('confirm')}
          >
            {busy === 'confirm' ? ta(locale, 'confirming') : ta(locale, 'confirm_button')}
          </Button>
          <Button
            variant="danger"
            size="sm"
            loading={busy === 'reject'}
            disabled={busy !== null}
            onClick={() => void handle('reject')}
          >
            {busy === 'reject' ? ta(locale, 'rejecting') : ta(locale, 'reject_button')}
          </Button>
        </div>
      ) : null}

      {item.state === 'executed' ? (
        <div
          data-testid="assistant-card-executed"
          className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3"
        >
          <p className="flex items-center gap-2 text-sm font-bold text-emerald-700">
            <CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden="true" />
            {ta(locale, 'executed_badge')}
          </p>
          {item.result !== undefined && item.result !== null ? (
            <div className="mt-2">
              <p className="text-xs font-bold uppercase tracking-wider text-emerald-800">
                {ta(locale, 'result_title')}
              </p>
              <pre className="mt-1 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-white/70 p-2 text-xs text-slate-700">
                {formatValue(item.result)}
              </pre>
            </div>
          ) : null}
        </div>
      ) : null}

      {item.state === 'rejected' ? (
        <p
          data-testid="assistant-card-rejected"
          className="mt-3 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-600"
        >
          <XCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
          {ta(locale, 'rejected_badge')}
        </p>
      ) : null}

      {item.state === 'expired' ? (
        <p
          data-testid="assistant-card-expired"
          className="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
        >
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {ta(locale, 'expired_notice')}
        </p>
      ) : null}

      {item.state === 'failed' ? (
        <p
          data-testid="assistant-card-failed"
          className="mt-3 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"
        >
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {item.errorMessage ?? ta(locale, 'execution_failed')}
        </p>
      ) : null}
    </div>
  );
}
