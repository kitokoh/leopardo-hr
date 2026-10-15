'use client';

/**
 * #8493 / #8494 (I-06, I-07) — indicateur d'état de connexion partagé POS/KDS.
 *
 * Rend VISIBLE l'état dérivé par `deriveConnectionState` (Partie 7 : « état de
 * connexion clair ») et le nombre d'opérations en attente, avec un bouton
 * « Réessayer » qui force un rejeu. Aucun texte en dur : tout vient du
 * catalogue i18n (`offline.*`, ×4 locales) — garde PA2-I18N-014.
 */

import { CloudOff, RefreshCw, Wifi } from 'lucide-react';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';
import type { ConnectionState } from '@/lib/offline/connection-state';

export interface ConnectionIndicatorProps {
  state: ConnectionState;
  pendingCount: number;
  onRetry?: () => void;
}

export function ConnectionIndicator({ state, pendingCount, onRetry }: ConnectionIndicatorProps) {
  const locale = getPreferredLocale();

  const tone =
    state === 'online'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
      : state === 'degraded'
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : 'border-red-200 bg-red-50 text-red-700';

  const label =
    state === 'online'
      ? t(locale, 'offline.online')
      : state === 'degraded'
        ? t(locale, 'offline.degraded')
        : t(locale, 'offline.offline');

  return (
    <div
      className={`inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold ${tone}`}
      role="status"
      aria-live="polite"
    >
      {state === 'online' ? (
        <Wifi className="h-3.5 w-3.5" aria-hidden />
      ) : (
        <CloudOff className="h-3.5 w-3.5" aria-hidden />
      )}
      <span>{label}</span>
      {pendingCount > 0 ? (
        <span className="rounded-full bg-white/70 px-2 py-0.5 font-bold">
          {t(locale, 'offline.pending').replace('{count}', String(pendingCount))}
        </span>
      ) : null}
      {state !== 'online' && onRetry ? (
        <button
          type="button"
          onClick={onRetry}
          className="inline-flex items-center gap-1 rounded-md border border-current/30 px-2 py-0.5 font-bold transition hover:bg-white/50"
        >
          <RefreshCw className="h-3 w-3" aria-hidden />
          {t(locale, 'offline.retry')}
        </button>
      ) : null}
    </div>
  );
}
