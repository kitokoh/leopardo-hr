'use client';

import { useCallback, useRef, useState } from 'react';
import { apiFetch } from '@/lib/api-client';
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
export type WelcomeScreenAction = 'start_setup' | 'later';

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
        className="w-full max-w-lg rounded-3xl bg-white p-8 shadow-2xl"
      >
        <h2
          id="first-login-welcome-title"
          className="text-2xl font-bold text-slate-900"
        >
          {labels.title}
        </h2>

        <p className="mt-4 text-sm leading-relaxed text-slate-600">{labels.body}</p>

        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <button
            type="button"
            disabled={pending}
            onClick={() => void acknowledge('start_setup')}
            className="inline-flex flex-1 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
          >
            {labels.ctaStart}
          </button>
          <button
            type="button"
            disabled={pending}
            onClick={() => void acknowledge('later')}
            className="inline-flex flex-1 items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
          >
            {labels.ctaLater}
          </button>
        </div>
      </div>
    </div>
  );
}
