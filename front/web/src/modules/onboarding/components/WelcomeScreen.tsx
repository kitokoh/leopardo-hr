'use client';

import { useCallback, useRef, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
import { LeoMascot } from '@/components/ui/LeoMascot';
import { type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

/**
 * #7604 — écran de bienvenue de PREMIÈRE CONNEXION (tranche du critère 2 de
 * #7490 : « l'écran s'affiche UNE fois, persisté côté serveur, pas en
 * localStorage »).
 *
 * À quoi il sert : après la validation du code d'inscription, l'utilisateur
 * atterrit dans son espace sans qu'on lui explique ce qui vient de se passer ni
 * comment il se reconnectera demain. Cet écran le dit une fois, puis disparaît
 * définitivement (y compris sur un autre appareil, parce que l'acquittement
 * vit sur `company.metadata.welcome_seen_at`, exposé par `/auth/me`).
 *
 * Il est **non bloquant par construction** : les deux issues acquittent
 * l'écran. Si l'appel d'acquittement échoue (réseau), l'écran se FERME quand
 * même — on ne séquestre pas l'utilisateur — et le drapeau restant absent côté
 * serveur, l'écran se réaffichera à la prochaine connexion : la direction
 * d'échec est sûre (au pire on ré-explique, jamais on bloque).
 *
 * L'écran n'est pas rendu par ce composant : c'est le layout de l'espace qui
 * décide (`shouldShowFirstLoginWelcome`), une seule source de vérité.
 */
export type WelcomeScreenAction = 'set_password' | 'later';

type Props = {
  /** Locale de l'espace (source : catalogue i18n, `shared/i18n/locales`). */
  locale: AppLocale;
  /** Fin d'acquittement : le layout applique la mise à jour optimiste. */
  onAcknowledged: (seenAt: string, action: WelcomeScreenAction) => void;
};

/**
 * Faut-il afficher l'écran de bienvenue de première connexion ?
 *
 * Miroir EXACT de la garde serveur (`WelcomeScreenController`) :
 * `role = manager` ET `manager_role ∈ {principal, rh}` — sinon l'utilisateur
 * verrait un écran dont l'acquittement répondrait `403`, et l'écran
 * reviendrait à chaque connexion. Aucun `company` ou aucun `manager_role`
 * connu ⇒ pas d'écran (fail-safe : on ne montre pas un écran qu'on ne saurait
 * pas acquitter).
 */
export function shouldShowFirstLoginWelcome(user?: StoredAuthUser | null): boolean {
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

  const seenAt = company.metadata?.welcome_seen_at;
  return typeof seenAt !== 'string' || seenAt === '';
}

export function WelcomeScreen({ locale, onAcknowledged }: Props) {
  // Copie lue depuis le CATALOGUE (`shared/i18n/locales`, 4 locales, repli `fr`
  // intégré) et non depuis l'arbre `getCopy` de `lib/i18n` : une seule source
  // de vérité pour les libellés, celle que la garde i18n du dépôt vérifie.
  const labels = {
    title: i18nT(locale, 'firstLoginWelcome.title'),
    body: i18nT(locale, 'firstLoginWelcome.body'),
    ctaStart: i18nT(locale, 'firstLoginWelcome.ctaStart'),
    ctaLater: i18nT(locale, 'firstLoginWelcome.ctaLater'),
  };
  const [pending, setPending] = useState(false);
  // Garde SYNCHRONE anti-double acquittement : `pending` (state) n'est pas
  // encore à jour au second clic d'un double-clic rapide — deux POST partaient
  // (constaté par le test). Le ref est lu immédiatement.
  const inFlightRef = useRef(false);

  const acknowledge = useCallback(
    async (action: WelcomeScreenAction) => {
      if (inFlightRef.current) {
        return;
      }

      inFlightRef.current = true;
      setPending(true);

      // `welcome_seen_at` est la date SERVEUR : on préfère celle qu'il renvoie
      // (elle ne sera jamais réécrite). En cas d'échec ou de réponse
      // inattendue, on retombe sur l'heure locale — le layout referme l'écran
      // et le serveur reproposera l'écran à la prochaine connexion.
      let seenAt = new Date().toISOString();

      try {
        const response = await apiFetch('/onboarding/welcome-ack', { method: 'POST' });

        if (response.ok) {
          const payload = (await response.json()) as {
            data?: { welcome_seen_at?: string | null };
          };
          seenAt = payload.data?.welcome_seen_at ?? seenAt;
        }
      } catch {
        // Silencieux : un acquittement qui échoue ne doit jamais bloquer
        // l'accès à l'espace (politique « non bloquant » de cet écran).
      }

      setPending(false);
      onAcknowledged(seenAt, action);
    },
    [onAcknowledged],
  );

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="first-login-welcome-title"
        className="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-800"
      >
        {/* Bandeau d'accueil : Leo salue le nouvel arrivant. Décoratif
            (aria-hidden dans LeoMascot), la copie reste la seule information. */}
        <div className="relative flex items-end justify-center bg-gradient-to-b from-emerald-500/15 via-cyan-500/10 to-transparent pt-8 dark:from-emerald-500/10 dark:via-cyan-500/5">
          <LeoMascot variant="wave" size={132} float />
        </div>

        <div className="p-8 pt-5 text-center">
          <h2
            id="first-login-welcome-title"
            className="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
          >
            {labels.title}
          </h2>

          <p className="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">{labels.body}</p>

          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <button
              type="button"
              disabled={pending}
              onClick={() => void acknowledge('set_password')}
              className="inline-flex flex-1 items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:from-emerald-700 hover:to-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 disabled:opacity-60"
            >
              {labels.ctaStart}
            </button>
            <button
              type="button"
              disabled={pending}
              onClick={() => void acknowledge('later')}
              className="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-300 dark:hover:border-emerald-800 dark:hover:bg-emerald-900/20"
            >
              {labels.ctaLater}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
