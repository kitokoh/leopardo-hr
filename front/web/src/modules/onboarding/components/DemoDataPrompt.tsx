'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { Check, Loader2, Sparkles } from 'lucide-react';
import { apiFetch } from '@/lib/api-client';
import { LeoMascot } from '@/components/ui/LeoMascot';
import type { AppLocale, StoredAuthUser } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #7866 — pop-up « Voulez-vous découvrir votre espace avec des données de
 * démonstration ? » à la première entrée dans le dashboard.
 *
 * Un tenant fraîchement activé sur une verticale démarre sur un espace VIDE :
 * cette invite propose d'installer le kit de démonstration servi par l'API
 * #7865 (`GET /demo-data`, `POST /demo-data/{code}/import|dismiss`). Trois
 * issues, jamais bloquantes :
 *
 *  - « Importer » : import séquentiel de chaque kit `available` +
 *    `not_imported` (réalistement UN par tenant), état de succès bref puis
 *    `onClose('imported')` — le layout recharge `/auth/me` (#7245/#7322) ;
 *  - « Plus tard » : fermeture pour LA session uniquement — aucun appel
 *    serveur, aucun localStorage : l'invite reviendra à la prochaine entrée ;
 *  - « Non merci » : `POST dismiss` (persisté dans
 *    `company.metadata.demo_data`, idempotent) puis `onClose('dismissed')`.
 *
 * Le serveur est la SOURCE DE VÉRITÉ : `shouldShowDemoDataPrompt` n'est
 * qu'une pré-garde bon marché (miroir du RBAC + metadata `/auth/me`, même
 * politique fail-safe que `shouldShowSetupInterview`) ; le composant fetch
 * `GET /demo-data` au montage et ne rend RIEN tant qu'aucun kit n'est
 * `available: true` + `status: 'not_imported'`.
 */

export type DemoDataPromptCloseReason = 'imported' | 'later' | 'dismissed';

type DemoDataKitState = {
  code: string;
  available?: boolean;
  status?: string;
  imported_at?: string | null;
  dismissed_at?: string | null;
};

/**
 * Codes de verticales CONNUS du portail (miroir de l'allowlist serveur
 * `SolutionCatalogue`) : seuls ces flags, actifs dans les features du tenant,
 * déclenchent la pré-garde. Un code inconnu n'affiche jamais l'invite —
 * le serveur tranche de toute façon (`GET /demo-data`).
 */
const KNOWN_VERTICAL_CODES = ['restaurant', 'travelagency', 'fuel_station', 'edumanager', 'pharmacy'] as const;

/** Un flag de feature est ACTIF quand il vaut `true`, `available` ou `trial` (sémantique de `client-features`). */
function isFeatureActive(value: unknown): boolean {
  return value === true || value === 'available' || value === 'trial';
}

/**
 * Faut-il proposer l'import des données de démonstration ? Pré-garde bon
 * marché, miroir de la garde serveur (`DemoDataController` :
 * `principal`/`rh`) — même logique fail-safe que `shouldShowSetupInterview` :
 * on ne montre pas une invite dont les écritures répondraient 403.
 *
 * Vrai seulement si le tenant a AU MOINS une verticale connue active (flags
 * `/auth/me` : `user.features` au niveau racine et/ou `company.features`)
 * dont l'entrée `company.metadata.demo_data[code]` n'est ni `imported` ni
 * `dismissed`. Le composant re-vérifie ensuite auprès du serveur.
 */
export function shouldShowDemoDataPrompt(user?: StoredAuthUser | null): boolean {
  if (!user || user.role !== 'manager') {
    return false;
  }

  const managerRole = (user.manager_role ?? '').toLowerCase();
  if (managerRole !== 'principal' && managerRole !== 'rh') {
    return false;
  }

  const company = user.company;
  if (!company) {
    return false;
  }

  const demoData = (company.metadata as { demo_data?: Record<string, { status?: string }> } | undefined)
    ?.demo_data;

  return KNOWN_VERTICAL_CODES.some((code) => {
    const active =
      isFeatureActive((user.features as Record<string, unknown> | null | undefined)?.[code]) ||
      isFeatureActive((company.features as Record<string, unknown> | null | undefined)?.[code]);
    if (!active) {
      return false;
    }
    const status = demoData?.[code]?.status;
    return status !== 'imported' && status !== 'dismissed';
  });
}

export function DemoDataPrompt({
  locale,
  onClose,
}: {
  locale: AppLocale;
  onClose: (reason: DemoDataPromptCloseReason) => void;
}) {
  const t = useCallback(
    (key: string, fallback = '') => i18nT(locale, `demoDataPrompt.${key}`, fallback),
    [locale],
  );

  // `null` tant que le serveur n'a pas répondu : on ne rend RIEN avant.
  const [kits, setKits] = useState<DemoDataKitState[] | null>(null);
  const [importing, setImporting] = useState(false);
  const [imported, setImported] = useState(false);
  const [dismissing, setDismissing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Garde SYNCHRONE anti-double fermeture (même leçon que WelcomeScreen :
  // le state n'est pas encore à jour au second clic d'un double-clic rapide).
  const closedRef = useRef(false);

  // Le serveur est la source de vérité : seuls les kits `available` ET
  // `not_imported` sont proposables (la pré-garde metadata peut être en
  // avance ou en retard sur l'état réel).
  const actionable = (kits ?? []).filter(
    (kit) => kit.available === true && kit.status === 'not_imported',
  );

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const response = await apiFetch('/demo-data');
        if (!response.ok) {
          throw new Error(`demo-data failed (${response.status})`);
        }
        const payload = (await response.json()) as { data?: { kits?: DemoDataKitState[] } };
        if (!cancelled) {
          setKits(payload.data?.kits ?? []);
        }
      } catch {
        // Fail-safe : sans réponse serveur, on ne propose rien (l'invite
        // reviendra à la prochaine entrée) — jamais de crash.
        if (!cancelled) {
          setKits([]);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const importAll = useCallback(async () => {
    if (importing || actionable.length === 0) return;
    setImporting(true);
    setError(null);
    try {
      // Import séquentiel de chaque kit proposable (réalistement UN par
      // tenant) : l'API est idempotente et throttlée, jamais en parallèle.
      for (const kit of actionable) {
        const response = await apiFetch(`/demo-data/${kit.code}/import`, { method: 'POST' });
        if (!response.ok) {
          throw new Error(`import failed (${response.status})`);
        }
      }
      setImported(true);
    } catch {
      // L'invite reste ouverte : le client peut réessayer, reporter ou refuser.
      setError(t('error', 'Impossible d’importer pour le moment. Réessayez dans un instant.'));
    } finally {
      setImporting(false);
    }
  }, [actionable, importing, t]);

  const later = useCallback(() => {
    if (closedRef.current) return;
    closedRef.current = true;
    // Session uniquement : AUCUN appel serveur, AUCUN localStorage — l'invite
    // reviendra à la prochaine entrée dans l'espace.
    onClose('later');
  }, [onClose]);

  const dismiss = useCallback(async () => {
    if (closedRef.current || dismissing) return;
    setDismissing(true);
    try {
      for (const kit of actionable) {
        await apiFetch(`/demo-data/${kit.code}/dismiss`, { method: 'POST' });
      }
    } catch {
      // Direction d'échec sûre : on ferme quand même, l'invite reviendra.
    }
    closedRef.current = true;
    onClose('dismissed');
  }, [actionable, dismissing, onClose]);

  // Rien à proposer tant que le serveur n'a pas répondu (ou plus rien après
  // l'import) : l'overlay n'existe pas, Échap ne doit alors rien fermer.
  const visible = imported || (kits !== null && actionable.length > 0);

  // Échap = « Plus tard » : l'invite n'est jamais bloquante.
  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && visible && !imported) {
        later();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [imported, later, visible]);

  // Rien tant que le serveur n'a pas confirmé un kit proposable — pas de
  // flash d'overlay pour les tenants sans kit (la pré-garde est large).
  if (!visible) {
    return null;
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="demo-data-prompt-title"
      data-testid="demo-data-prompt"
      className="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm"
    >
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800"
      >
        {/* Leo présente la démo — décoratif (aria-hidden dans LeoMascot). */}
        <div className="relative flex items-end justify-center bg-gradient-to-b from-emerald-500/15 via-cyan-500/10 to-transparent pt-8 dark:from-emerald-500/10 dark:via-cyan-500/5">
          <LeoMascot variant="wave" size={112} float />
        </div>

        {imported ? (
          <div className="p-8 pt-5 text-center" data-testid="demo-data-success">
            <div className="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-900/30 dark:to-cyan-900/20">
              <Sparkles className="h-6 w-6 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
            </div>
            <h2 id="demo-data-prompt-title" className="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
              {t('successTitle', 'Vos données de démonstration sont prêtes')}
            </h2>
            <p className="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">
              {t('successBody', 'Explorez librement : tout peut être modifié ou supprimé, rien n’est facturé.')}
            </p>
            <button
              type="button"
              onClick={() => {
                closedRef.current = true;
                onClose('imported');
              }}
              data-testid="demo-data-success-cta"
              className="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-black text-white transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
            >
              <Check className="h-4 w-4" aria-hidden="true" />
              {t('successCta', 'Découvrir mes données')}
            </button>
          </div>
        ) : (
          <div className="p-8 pt-5 text-center">
            <h2 id="demo-data-prompt-title" className="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
              {t('title', 'Voulez-vous découvrir votre espace avec des données de démonstration ?')}
            </h2>
            <p className="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">
              {t('body', 'Nous installons un jeu d’exemple (menus, commandes, équipes…) pour explorer votre espace librement. Vous pourrez tout modifier ou supprimer à tout moment.')}
            </p>
            {error ? (
              <p role="alert" className="mt-4 text-sm font-semibold text-red-600">
                {error}
              </p>
            ) : null}
            <div className="mt-8 flex flex-col gap-3">
              <button
                type="button"
                disabled={importing || dismissing}
                onClick={() => void importAll()}
                data-testid="demo-data-import"
                className="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:from-emerald-700 hover:to-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 disabled:opacity-60"
              >
                {importing ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : null}
                {importing
                  ? t('importing', 'Import en cours…')
                  : t('import', 'Importer les données de démonstration')}
              </button>
              <button
                type="button"
                disabled={importing || dismissing}
                onClick={later}
                data-testid="demo-data-later"
                className="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-300 dark:hover:border-emerald-800 dark:hover:bg-emerald-900/20"
              >
                {t('later', 'Plus tard')}
              </button>
              <button
                type="button"
                disabled={importing || dismissing}
                onClick={() => void dismiss()}
                data-testid="demo-data-dismiss"
                className="rounded-xl px-4 py-2 text-xs font-black text-slate-400 transition hover:text-slate-700 disabled:opacity-60 dark:hover:text-slate-300"
              >
                {t('dismiss', 'Non merci')}
              </button>
            </div>
          </div>
        )}
      </motion.div>
    </div>
  );
}
