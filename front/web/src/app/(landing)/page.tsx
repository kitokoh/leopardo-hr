'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import {
  Navbar,
  Footer,
  useScrollReveal,
  SocialProofMetrics,
  ProductScreenshots,
  WhyLeopardoSection,
  TrustedBrands,
  // Phase-3 sections — no more Legacy prefixes
  HeroSection,
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
import { HeroScene3DPanel } from '@/modules/vitrine/components/hero/HeroScene3DPanel';

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
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} overDarkStage />

      <main>
        {/* ─── HERO ─── #8437 (vague 3) : layout `stage` — la constellation
            3D prend TOUTE la première page (pleine scène, nuit permanente),
            la phrase vit en toile de fond derrière elle. #8417 (vague 4,
            retour propriétaire) : premier écran = LA PHRASE + LA 3D, rien
            d'autre — CTA, formulaire e-mail rapide et ligne de réassurance
            retirés (le CTA « Créer un compte » vit dans la navbar, le
            formulaire complet sur /signup). */}
        <HeroSection
          headline={`${copy.hero.titleTop} ${copy.hero.titleBottom}`}
          animated
          layout="stage"
          // La constellation interactive (modules cliquables, #8390) est le
          // visuel plein cadre ; pas de seconde scène estompée derrière elle.
          backgroundScene={false}
          visual={<HeroScene3DPanel />}
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
        {/* « Pourquoi Leopardo » : maîtrise de l'outil (données, coût, lock-in)
            — le positionnement Business OS, sans jargon de licence. */}
        <WhyLeopardoSection locale={locale} />

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

        {/* ─── FAQ ─── Phase-3 — #8073 : id="faq" pour l'ancre navbar */}
        <FAQSection
          id="faq"
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
          ctaSecondary={{ text: copy.cta.secondary, href: '/packs' }}
          background="gradient"
        />
      </main>

      <Footer />

      {/* ─── STICKY MOBILE CTA ─── visible on mobile after 400px scroll */}
      <StickyMobileCTA locale={locale} />
    </div>
  );
}
