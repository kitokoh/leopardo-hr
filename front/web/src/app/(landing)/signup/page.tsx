'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Footer, Navbar } from '@/modules/vitrine';
import { SignupForm } from '@/modules/vitrine/components/forms';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type SignupCopy = {
  headline: string;
  subheadline: string;
};

/**
 * #7235 — le récit de la page vit dans le catalogue i18n partagé
 * (`shared/i18n/locales/*.json` → clé `signupPage`), pas dans le composant :
 * aucun littéral utilisateur ici, et les 4 langues restent synchronisées par
 * l'outillage i18n du dépôt.
 */
function buildSignupCopy(locale: AppLocale): SignupCopy {
  const k = (key: string) => t(locale, `signupPage.${key}`);
  return {
    headline: k('headline'),
    subheadline: k('subheadline'),
  };
}

// #7249 — page d'inscription nue : plus de hero ni de colonne « récit » (badge,
// titre, liste des 3 étapes) au-dessus du formulaire. Le titre de page est
// conservé — référencement, et il donne un cap au lecteur d'écran — mais le
// formulaire occupe le premier écran, sans avoir à dérouler une page marketing.
export default function SignupPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale, direction } = useVitrineLocale();
  const copy = buildSignupCopy(locale);

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <main id="signup-form" className="px-4 py-12 sm:px-6 lg:py-16">
        <div className="mx-auto max-w-xl">
          <h1 className="mb-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white">
            {copy.headline}
          </h1>
          <p className="mb-8 text-sm leading-6 text-slate-600 dark:text-slate-400">
            {copy.subheadline}
          </p>

          <SignupForm page="/signup" />
        </div>
      </main>

      <Footer />
    </div>
  );
}
