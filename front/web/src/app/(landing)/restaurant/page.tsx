'use client';

/**
 * Page vitrine « Je suis restaurateur » — pré-qualification publique.
 * Route : /restaurant
 */

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer } from '@/modules/vitrine';
import { RestaurantSolutionWizard } from '@/modules/vitrine/components/RestaurantSolutionWizard';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

export default function RestaurantPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { direction } = useVitrineLocale();

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <section className="relative pt-32 pb-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <div className="absolute top-24 left-1/2 -translate-x-1/2 w-[36rem] h-72 rounded-full bg-emerald-400/10 blur-3xl" />

        <div className="relative">
          <RestaurantSolutionWizard />
        </div>
      </section>

      <Footer />
    </div>
  );
}
