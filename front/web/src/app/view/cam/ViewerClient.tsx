'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertCircle, Camera as CameraIcon, Film, Loader2, ShieldAlert } from 'lucide-react';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import {
  fetchPublicCameraView,
  readCameraTokenFromUrl,
  type PublicCameraView,
} from '@/lib/cameras';

/**
 * #7425 (tranche 2) — viewer tiers, **sans session**.
 *
 * Ce que cette page fait, et ce qu'elle refuse de faire :
 *  - elle lit le jeton dans le **fragment** de l'URL (`#t=…`) et le transporte
 *    en en-tête `X-Token` (l'API n'accepte pas de jeton en chaîne de requête,
 *    #4931/#6560) ;
 *  - si le jeton est dans la **chaîne de requête** (anciens liens), elle ne
 *    l'utilise pas et l'explique : il est déjà journalisé par les proxys, donc
 *    le « réparer » ne le rendrait pas sûr ;
 *  - elle n'affiche **pas** de lecteur vidéo : le direct exige un nœud Edge
 *    chez le client (ADR-0021). Elle montre ce à quoi le lien donne droit et
 *    l'adresse du flux, jamais une image morte.
 */

type CopyKey =
  | 'title'
  | 'subtitle'
  | 'loading'
  | 'invalidTitle'
  | 'invalidBody'
  | 'missingTitle'
  | 'missingBody'
  | 'legacyTitle'
  | 'legacyBody'
  | 'cameraOff'
  | 'edgeTitle'
  | 'edgeBody'
  | 'streamTitle'
  | 'expires'
  | 'label'
  | 'retry'
  | 'guide';

const COPY_KEYS: CopyKey[] = [
  'title', 'subtitle', 'loading', 'invalidTitle', 'invalidBody', 'missingTitle', 'missingBody',
  'legacyTitle', 'legacyBody', 'cameraOff', 'edgeTitle', 'edgeBody', 'streamTitle', 'expires',
  'label', 'retry', 'guide',
];

function buildCopy(locale: AppLocale): Record<CopyKey, string> {
  const copy = {} as Record<CopyKey, string>;
  for (const key of COPY_KEYS) {
    copy[key] = t(locale, `camViewer.${key}`);
  }
  return copy;
}

export default function ViewerClient() {
  const locale = getPreferredLocale();
  const c = useMemo(() => buildCopy(locale), [locale]);

  const [state, setState] = useState<'loading' | 'ready' | 'invalid' | 'missing' | 'legacy' | 'camera_off' | 'network'>('loading');
  const [view, setView] = useState<PublicCameraView | null>(null);
  const [attempt, setAttempt] = useState(0);

  const load = useCallback(async () => {
    // `window` n'existe qu'au montage : le fragment n'est pas envoyé au serveur.
    const { token, legacy } = readCameraTokenFromUrl(window.location.href);
    if (legacy) {
      setState('legacy');
      return;
    }
    if (!token) {
      setState('missing');
      return;
    }

    setState('loading');
    const result = await fetchPublicCameraView(token);
    if (result.ok) {
      setView(result.view);
      setState('ready');
      return;
    }
    setState(
      result.reason === 'camera_unavailable'
        ? 'camera_off'
        : result.reason === 'invalid_token'
          ? 'invalid'
          : 'network',
    );
  }, []);

  useEffect(() => {
    void load();
  }, [load, attempt]);

  const panel = (icon: React.ReactNode, title: string, body: string, withRetry: boolean) => (
    <div role="alert" className="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
      <p className="flex items-center gap-2 text-base font-bold">
        {icon}
        {title}
      </p>
      <p className="mt-2 text-sm leading-relaxed">{body}</p>
      {withRetry ? (
        <button
          type="button"
          onClick={() => setAttempt((n) => n + 1)}
          className="mt-4 rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-bold"
          data-testid="viewer-retry"
        >
          {c.retry}
        </button>
      ) : null}
      <p className="mt-4 text-xs text-amber-800/80">{c.guide}</p>
    </div>
  );

  return (
    <main className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center gap-6 p-6">
      <header className="flex items-center gap-3">
        <CameraIcon className="h-6 w-6 text-emerald-600" aria-hidden="true" />
        <div>
          <h1 className="text-lg font-black text-slate-800">{c.title}</h1>
          <p className="text-xs text-slate-500">{c.subtitle}</p>
        </div>
      </header>

      {state === 'loading' ? (
        <p className="flex items-center gap-2 text-sm text-slate-500" data-testid="viewer-loading">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
          {c.loading}
        </p>
      ) : null}

      {state === 'legacy' ? panel(<ShieldAlert className="h-5 w-5" aria-hidden="true" />, c.legacyTitle, c.legacyBody, false) : null}
      {state === 'missing' ? panel(<AlertCircle className="h-5 w-5" aria-hidden="true" />, c.missingTitle, c.missingBody, false) : null}
      {state === 'invalid' ? panel(<AlertCircle className="h-5 w-5" aria-hidden="true" />, c.invalidTitle, c.invalidBody, true) : null}
      {state === 'camera_off' ? panel(<AlertCircle className="h-5 w-5" aria-hidden="true" />, c.invalidTitle, c.cameraOff, true) : null}
      {state === 'network' ? panel(<AlertCircle className="h-5 w-5" aria-hidden="true" />, c.invalidTitle, c.invalidBody, true) : null}

      {state === 'ready' && view ? (
        <section className="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" data-testid="viewer-ready">
          <div>
            <h2 className="text-base font-bold text-slate-800" data-testid="viewer-camera-name">
              {view.camera.name}
            </h2>
            {view.camera.location ? <p className="text-sm text-slate-500">{view.camera.location}</p> : null}
            {view.label ? (
              <p className="mt-1 text-xs text-slate-500">
                {c.label} : {view.label}
              </p>
            ) : null}
            {view.expires_at ? (
              <p className="text-xs text-slate-500">
                {c.expires} {view.expires_at}
              </p>
            ) : null}
          </div>

          {/* La chaîne vidéo exige un nœud Edge : on le dit au lieu d'afficher un lecteur mort. */}
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900" data-testid="viewer-edge-notice">
            <p className="flex items-center gap-2 text-sm font-bold">
              <Film className="h-4 w-4" aria-hidden="true" />
              {c.edgeTitle}
            </p>
            <p className="mt-1 text-[13px] leading-relaxed">{c.edgeBody}</p>
          </div>

          <div>
            <p className="text-xs font-bold text-slate-600">{c.streamTitle}</p>
            <p className="mt-1 break-all rounded-xl bg-slate-50 p-3 text-[11px] text-slate-600" data-testid="viewer-stream-url">
              {view.stream_url}
            </p>
          </div>
        </section>
      ) : null}
    </main>
  );
}
