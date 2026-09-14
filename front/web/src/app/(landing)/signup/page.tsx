'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Footer, Navbar } from '@/modules/vitrine';
import { SignupForm } from '@/modules/vitrine/components/forms';
import { SignupArtwork } from '@/modules/vitrine/components/SignupArtwork';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

/**
 * Page d'inscription.
 *
 * QA onboarding 2026-09-14 — la page empilait un `HeroSection` marketing (badge,
 * titre, sous-titre, CTA) PUIS une colonne de récit à gauche du formulaire. Le
 * formulaire — la seule raison d'être de l'écran — se retrouvait repoussé sous la
 * ligne de flottaison. Décision : plus aucun bloc de texte au-dessus, et le
 * récit de gauche remplacé par un VISUEL.
 *
 * Retour propriétaire (2026-09-14) : « le texte explicatif de gauche n'a pas sa
 * place, un truc beau artistique genre 3D hero sera mieux là ». D'où
 * `SignupArtwork` : scène 3D isométrique (CSS 3D natif, aucune dépendance, AUCUN
 * texte — donc rien à traduire, et rien de superflu à lire).
 *
 * Sur mobile, le visuel est masqué : seule l'action compte, et le formulaire
 * reste centré comme avant.
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

      <main id="signup-form" className="relative overflow-hidden py-10 sm:py-16">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/60 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative mx-auto grid w-full max-w-5xl grid-cols-1 items-center gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_minmax(0,28rem)] lg:px-8">
          <SignupArtwork className="hidden lg:flex" />

          <div className="mx-auto w-full max-w-md lg:mx-0 lg:max-w-none">
            <SignupForm page="/signup" />
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
}
