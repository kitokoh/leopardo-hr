'use client';

import { GoogleGlyph } from '@/components/GoogleGlyph';
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
  // Sans repli littéral : la clé existe dans les 4 langues du catalogue
  // (garde i18n PA2-I18N-014 : aucun texte utilisateur hors catalogue).
  const label = t(locale, 'auth.continue_with_google');

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
      <GoogleGlyph />
      <span>{label}</span>
    </a>
  );
}
