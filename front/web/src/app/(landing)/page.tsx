'use client';

import { Sparkles, Server, Zap, Users, TrendingUp, Star } from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { GITHUB_REPO_URL } from '@/modules/vitrine/data/github-repo';
import {
  Navbar,
  Footer,
  useScrollReveal,
  SocialProofMetrics,
  ProductScreenshots,
  WhyOpenSourceSection,
  TrustedBrands,
  // Phase-3 sections — no more Legacy prefixes
  HeroSection,
  HeroProductShowcase,
  SolutionStackSection,
  FAQSection,
  CTASection,
  ProblemSection,
  SolutionSection,
  TestimonialsSection,
  ZKTecoHookSection,
  VerticalsSection,
} from '@/modules/vitrine';
import { FeaturesSection as ModernFeaturesSection } from '@/modules/vitrine/components/sections/FeaturesSection';
// PricingSection: keep the self-contained locale-aware version (not the generic sections/ one)
import { PricingSection as LocalePricingSection } from '@/modules/vitrine/components/PricingSection';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getFeatures } from '@/modules/vitrine/data/features';
import { getFaqItems } from '@/modules/vitrine/data/faq';
import { getTestimonials, TESTIMONIALS_ARE_DEMO } from '@/modules/vitrine/data/testimonials';
import { StickyMobileCTA } from '@/components/StickyMobileCTA';
import { QuickTrialEmailForm } from '@/modules/vitrine/components/HeroSection';

const STAT_ICONS = [TrendingUp, Users, Zap, Star] as const;

export default function LandingPage() {
  // Dark mode state — synced with DarkModeProvider via CSS class on root
  const { isDark, toggleDarkMode } = useDarkMode();
  
  useScrollReveal();
  const { locale, copy, direction } = useVitrineLocale();

  const features = getFeatures(locale);
  const faqItems = getFaqItems(locale);
  const testimonials = getTestimonials(locale);

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${
        isDark ? 'dark bg-slate-950' : 'bg-white'
      }`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <main>
        {/* ─── HERO ─── Phase-3 */}
        <HeroSection
          badge={{
            icon: <Sparkles className="w-3.5 h-3.5" />,
            text: copy.hero.badge,
            label: copy.hero.badgeNew,
          }}
          headline={`${copy.hero.titleTop} ${copy.hero.titleBottom}`}
          subheadline={copy.hero.subtitle}
          ctaPrimary={{ text: copy.hero.primaryCta, href: '/signup' }}
          // #8068 — double funnel : l'essai cloud ET l'install self-host dès
          // le hero (le dépôt public = page d'installation pour un technicien).
          ctaSecondary={{
            text: copy.hero.secondaryCta,
            href: GITHUB_REPO_URL,
            icon: (
              <Server className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            ),
          }}
          ctaReassurance={copy.hero.ctaReassurance}
          stats={copy.hero.stats.map((s, i) => {
            const Icon = STAT_ICONS[i % STAT_ICONS.length];
            return {
              ...s,
              icon: <Icon className="w-5 h-5 text-emerald-500" />,
            };
          })}
          animated
          quickTrialForm={
            <QuickTrialEmailForm locale={locale} copy={copy.heroQuickTrial} />
          }
          layout="split"
          // #8067 — produit-first : screenshot réel + badge GitHub à la place de la mascotte (LeoHeroVisual reste dispo en marque secondaire)
          visual={<HeroProductShowcase locale={locale} />}
        />

        {/* ─── PREUVE SOCIALE + HOOK ZKTECO — remontés juste après le hero (#8072) ─── */}
        <TrustedBrands locale={locale} />
        <SocialProofMetrics locale={locale} />
        <ZKTecoHookSection locale={locale} />

        {/* ─── PROBLEM / SOLUTION ─── */}
        <ProblemSection
          badge={{ text: copy.problem.badge }}
          title={copy.problem.title}
          subtitle={copy.problem.subtitle}
          items={copy.problem.items}
        />

        <SolutionSection
          badge={{ text: copy.solution.badge }}
          title={copy.solution.title}
          subtitle={copy.solution.subtitle}
          description={copy.solution.description}
          features={copy.solution.features}
        />

        {/* ─── PILE LEOPARDO ─── architecture de l'offre, ex-visuel héro (#7851) */}
        <SolutionStackSection locale={locale} />

        {/* ─── PRODUCT DEMO VIDEO ─── #8071 option A : retirée de la home,
            conservée sur /demo et /videos (nouveau poster = dashboard réel) */}

        {/* ─── FEATURES ─── Phase-3 */}
        {/* id="fonctionnalites": PA2-MKT-013 — Footer links here via /#fonctionnalites */}
        <ModernFeaturesSection
          id="fonctionnalites"
          badge={{ text: copy.features.badge }}
          title={copy.features.title}
          subtitle={copy.features.titleHighlight}
          features={features.map((f) => ({
            title: f.title,
            description: f.description,
            icon: <f.icon className="w-6 h-6" />,
            gradient: f.gradient,
            // Map flat string stats → object expected by FeatureCard
            stats: { value: f.stats, label: f.statsLabel },
            details: f.details,
          }))}
          columns={3}
        />

        {/* ─── PRODUCT VISUAL ─── */}
        <ProductScreenshots locale={locale} />
        {/* #8065 : « Pourquoi open source » remplace MarketingReadinessSection
            (langage de pilotage interne — composant retiré par #8075). */}
        <WhyOpenSourceSection locale={locale} />

        {/* ─── VERTICALES en cartes cliquables (#8072) ─── */}
        <VerticalsSection locale={locale} />

        {/* ─── TESTIMONIALS ─── Phase-3 */}
        <TestimonialsSection
          badge={{ text: copy.testimonials.badge }}
          title={copy.testimonials.title}
          subtitle={copy.testimonials.titleHighlight}
          testimonials={testimonials.map((t) => ({
            // TestimonialCardProps: quote + author (not content + name)
            quote: t.content,
            author: t.name,
            role: t.role,
            company: t.company,
            avatar: t.avatar,
            rating: t.rating,
            // #8070 — flag démo explicite : tant qu'il n'y a pas de clients
            // réels, chaque témoignage porte le badge « Exemple illustratif ».
            demo: TESTIMONIALS_ARE_DEMO,
          }))}
          columns={3}
        />

        {/* ─── PRICING ─── locale-aware self-contained component */}
        <LocalePricingSection />

        {/* ─── FAQ ─── Phase-3 */}
        <FAQSection
          badge={{ text: copy.faq.badge }}
          title={copy.faq.title}
          subtitle={copy.faq.titleHighlight}
          items={faqItems.map((item, i) => ({
            id: `faq-${i}`,
            question: item.question,
            answer: item.answer,
          }))}
        />

        {/* ─── CTA FINAL ─── Phase-3 */}
        <CTASection
          badge={{ text: copy.cta.badge }}
          headline={copy.cta.title}
          subheadline={copy.cta.subtitle}
          ctaPrimary={{ text: copy.cta.primary, href: '/signup' }}
          ctaSecondary={{ text: copy.cta.secondary, href: GITHUB_REPO_URL }}
          background="gradient"
        />
      </main>

      <Footer />

      {/* ─── STICKY MOBILE CTA ─── visible on mobile after 400px scroll */}
      <StickyMobileCTA locale={locale} />
    </div>
  );
}
