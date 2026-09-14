'use client';

import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Bouton « Continuer avec Google » de la vitrine.
 *
 * QA onboarding 2026-09-14 — le bouton existait déjà, mais enfermé dans
 * `(landing)/checkout/page.tsx` : la page d'inscription n'en avait donc aucun
 * (« créer son compte en un clic » impossible). Composant partagé pour que
 * l'inscription et la connexion offrent le même parcours, avec un seul libellé
 * issu du catalogue i18n (`auth.continue_with_google`, ×4 langues).
 *
 * Le lien passe par le proxy Next.js de la vitrine (`/api/v1/auth/google`) et
 * non par l'API directement : c'est le proxy qui pose le cookie de session sur
 * l'origine vitrine (QA #2277, issue #2725).
 *
 * `intent` distingue la CONNEXION (compte déjà invité) de la CRÉATION DE COMPTE.
 * Sans intention explicite, le backend reste « invitation-first » : un e-mail
 * Google inconnu n'auto-provisionne pas de tenant.
 */
export function GoogleAuthButton({
  intent,
  plan,
  className = '',
}: {
  intent?: 'signup' | 'login';
  plan?: string;
  className?: string;
}) {
  const { locale } = useVitrineLocale();
  const label = t(locale, 'auth.continue_with_google', 'Continue with Google');

  const params = new URLSearchParams();
  if (intent) params.set('intent', intent);
  if (plan) params.set('plan', plan);
  const query = params.toString();

  return (
    <a
      href={`/api/v1/auth/google${query ? `?${query}` : ''}`}
      data-testid="google-auth-button"
      data-intent={intent ?? 'login'}
      className={`flex w-full items-center justify-center gap-3 rounded-2xl border-2 border-slate-200 bg-white py-3.5 text-sm font-bold text-slate-800 shadow-sm transition-all duration-200 hover:border-slate-300 hover:bg-transparent dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800 ${className}`}
    >
      <svg viewBox="0 0 24 24" className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path
          d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
          fill="#4285F4"
        />
        <path
          d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
          fill="#34A853"
        />
        <path
          d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
          fill="#FBBC05"
        />
        <path
          d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
          fill="#EA4335"
        />
      </svg>
      <span>{label}</span>
    </a>
  );
}
