'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Footer, Navbar } from '@/modules/vitrine';
import { SignupForm } from '@/modules/vitrine/components/forms';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

/**
 * Page d'inscription.
 *
 * QA onboarding 2026-09-14 — la page empilait un `HeroSection` marketing (badge,
 * titre, sous-titre, CTA) PUIS une colonne de récit à gauche du formulaire
 * (« Workspace available immediately », « Your workspace in 2 minutes »,
 * « Signing up in 3 steps » + 3 étapes), le tout entre navbar et footer. Le
 * formulaire — la seule raison d'être de l'écran — se retrouvait repoussé sous
 * la ligne de flottaison, et le récit marketing décrivait un parcours qui
 * n'existe plus (code à 6 chiffres, « aucun mot de passe à créer ») alors que le
 * parcours réel affiche ensuite « No email required » et demande un mot de
 * passe.
 *
 * Décision : une seule colonne centrée, le formulaire en héros, aucun texte
 * commercial ajouté. Le récit de la vitrine reste sur `/pricing` et l'accueil.
 * Les clés i18n `signupPage.*` restent dans le catalogue pour les autres
 * surfaces (hero de l'accueil, campagnes).
 */
export default function SignupPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { direction } = useVitrineLocale();

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <main id="signup-form" className="relative overflow-hidden py-16 sm:py-24">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/60 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative mx-auto w-full max-w-md px-4 sm:px-6">
          <SignupForm page="/signup" />
        </div>
      </main>

      <Footer />
    </div>
  );
}
