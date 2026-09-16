'use client';

import type { ReactNode } from 'react';
import { useCallback, useEffect, useState } from 'react';
import { AlertTriangle, Check, Copy, ShieldAlert } from 'lucide-react';
import { getPreferredLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { ModulePageShell } from '@/components/module-page-shell';
import { Button } from '@/components/ui/Button';

/**
 * BC-19 (#7425) — page de visionnage TIERS, sans session.
 *
 * L'endpoint backend `GET /api/v1/view/cam` n'accepte plus que l'en-tête
 * `X-Token` (le `?t=` a été retiré : fuite dans les logs proxy/CDN, #6560).
 * Cette page est donc autonome et se contente de relayer le jeton :
 *   1. elle lit le jeton dans le fragment (`#<jeton>` — jamais envoyé au
 *      serveur) ou, pour compatibilité avec le lien `share_url` de l'API,
 *      dans le paramètre `t` de la query ;
 *   2. elle appelle le relais serveur same-origin `/api/view/cam`, qui relaie
 *      le jeton en en-tête `X-Token` vers l'API ;
 *   3. elle rend l'état réel : jeton invalide/expiré (INVALID_TOKEN), caméra
 *      indisponible (CAMERA_NOT_FOUND) ou flux fourni par l'API.
 *
 * La révocation est immédiate côté API (`DELETE /cameras/{id}/access-tokens/{token}`)
 * : le lien cesse alors de fonctionner — c'est la garantie annoncée ici.
 */

type ViewerPayload = {
  camera?: { id?: number; name?: string | null; location?: string | null };
  stream_url?: string | null;
  stream_token?: string | null;
  expires_at?: string | null;
  label?: string | null;
  granted_to_name?: string | null;
};

type ViewerState =
  | { kind: 'loading' }
  | { kind: 'missing' }
  | { kind: 'invalid' }
  | { kind: 'not_found' }
  | { kind: 'error' }
  | { kind: 'ready'; payload: ViewerPayload };

/** Panneau et bandeau locaux : la page publique est autonome (elle ne dépend
 *  d'aucune primitive de la zone dashboard). */
function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className={`rounded-2xl border border-slate-200/60 bg-white/80 p-6 shadow-sm backdrop-blur-xl ${className}`}>
      {children}
    </div>
  );
}

function Warning({ children }: { children: ReactNode }) {
  return (
    <div role="status" className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
      {children}
    </div>
  );
}

export default function PublicCameraViewerPage() {
  const locale = getPreferredLocale();
  const [token, setToken] = useState('');
  const [state, setState] = useState<ViewerState>({ kind: 'loading' });
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    const url = new URL(window.location.href);
    const fromQuery = url.searchParams.get('t') ?? '';
    const fromHash = window.location.hash.replace(/^#/, '');
    setToken((fromQuery || fromHash).trim());
  }, []);

  const load = useCallback(async () => {
    if (token === '') {
      setState({ kind: 'missing' });
      return;
    }

    setState({ kind: 'loading' });
    try {
      const response = await fetch('/api/view/cam', {
        headers: { 'X-Token': token, Accept: 'application/json' },
        cache: 'no-store',
      });

      if (response.status === 404) {
        const body = (await response.json().catch(() => null)) as { error?: string } | null;
        setState(body?.error === 'CAMERA_NOT_FOUND' ? { kind: 'not_found' } : { kind: 'invalid' });
        return;
      }

      if (!response.ok) {
        setState({ kind: 'error' });
        return;
      }

      const body = (await response.json()) as { data?: ViewerPayload };
      setState(body.data ? { kind: 'ready', payload: body.data } : { kind: 'error' });
    } catch {
      setState({ kind: 'error' });
    }
  }, [token]);

  useEffect(() => {
    void load();
  }, [load]);

  const formatDate = (value?: string | null): string =>
    value ? new Date(value).toLocaleString(locale) : '';

  const payload = state.kind === 'ready' ? state.payload : null;

  return (
    <div className="mx-auto w-full max-w-3xl p-4 md:p-8">
      <ModulePageShell
        title={t(locale, 'cameras.viewer.title')}
        subtitle={t(locale, 'cameras.viewer.subtitle')}
        accentClassName="border-cyan-500/10 bg-cyan-500/5"
      >
        <div className="space-y-4">
          {state.kind === 'loading' ? (
            <Panel className="py-10 text-center text-sm font-medium text-slate-500">{t(locale, 'cameras.viewer.loading')}</Panel>
          ) : state.kind === 'missing' ? (
            <Panel className="space-y-2 text-center">
              <ShieldAlert className="mx-auto h-8 w-8 text-amber-500" aria-hidden="true" />
              <p className="text-sm font-black text-slate-900">{t(locale, 'cameras.viewer.missingToken')}</p>
              <p className="text-sm text-slate-600">{t(locale, 'cameras.viewer.missingTokenBody')}</p>
            </Panel>
          ) : state.kind === 'invalid' ? (
            <Panel className="space-y-2 text-center">
              <ShieldAlert className="mx-auto h-8 w-8 text-rose-500" aria-hidden="true" />
              <p className="text-sm font-black text-slate-900">{t(locale, 'cameras.viewer.invalidToken')}</p>
              <p className="text-sm text-slate-600">{t(locale, 'cameras.viewer.invalidTokenBody')}</p>
              <p className="text-xs text-slate-400">{t(locale, 'cameras.viewer.revocationHint')}</p>
            </Panel>
          ) : state.kind === 'not_found' ? (
            <Panel className="space-y-2 text-center">
              <AlertTriangle className="mx-auto h-8 w-8 text-amber-500" aria-hidden="true" />
              <p className="text-sm font-black text-slate-900">{t(locale, 'cameras.viewer.cameraNotFound')}</p>
              <p className="text-sm text-slate-600">{t(locale, 'cameras.viewer.cameraNotFoundBody')}</p>
            </Panel>
          ) : state.kind === 'error' ? (
            <Panel className="space-y-3 text-center">
              <p className="text-sm font-bold text-rose-700">{t(locale, 'cameras.viewer.loadError')}</p>
              <Button variant="ghost" onClick={() => void load()}>
                {t(locale, 'cameras.viewer.retry')}
              </Button>
            </Panel>
          ) : (
            <Panel>
              <div>
                <p className="text-lg font-black tracking-tight text-slate-950">{payload?.camera?.name ?? ''}</p>
                {payload?.camera?.location ? (
                  <p className="text-sm text-slate-500">{payload.camera.location}</p>
                ) : null}
              </div>

              <Warning>
                <span className="block font-black">{t(locale, 'cameras.detail.streamUnavailable')}</span>
                <span className="block font-medium">{t(locale, 'cameras.detail.streamUnavailableHint')}</span>
              </Warning>

              <dl className="space-y-3 text-sm">
                <div>
                  <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.viewer.streamUrl')}</dt>
                  <dd className="font-mono text-xs break-all text-slate-800">{payload?.stream_url ?? ''}</dd>
                </div>
                <div>
                  <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.viewer.streamToken')}</dt>
                  <dd className="font-mono text-xs break-all text-slate-800">{payload?.stream_token ?? ''}</dd>
                </div>
                <div>
                  <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.viewer.expiresAt')}</dt>
                  <dd className="text-slate-800">{formatDate(payload?.expires_at)}</dd>
                </div>
                {payload?.granted_to_name ? (
                  <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest text-slate-500">{t(locale, 'cameras.viewer.grantedTo')}</dt>
                    <dd className="text-slate-800">{payload.granted_to_name}</dd>
                  </div>
                ) : null}
              </dl>

              <div className="flex flex-wrap items-center gap-2">
                {payload?.stream_url ? (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      navigator.clipboard
                        .writeText(String(payload.stream_url))
                        .then(() => setCopied(true))
                        .catch(() => setCopied(false));
                    }}
                  >
                    {copied ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : <Copy className="h-3.5 w-3.5" aria-hidden="true" />}
                    {copied ? t(locale, 'cameras.detail.tokenCopied') : t(locale, 'cameras.detail.tokenCopy')}
                  </Button>
                ) : null}
              </div>

              <p className="text-xs text-slate-400">{t(locale, 'cameras.detail.webrtcHint')}</p>
              <p className="text-xs text-slate-400">{t(locale, 'cameras.viewer.revocationHint')}</p>
            </Panel>
          )}
        </div>
      </ModulePageShell>
    </div>
  );
}
