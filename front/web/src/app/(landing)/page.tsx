'use client';

import { Sparkles, Play, Zap, Users, TrendingUp, Star } from 'lucide-react';
import { useState } from 'react';
import {
  Navbar,
  Footer,
  useScrollReveal,
  SocialProofMetrics,
  TestimonialHighlight,
  MiniCaseStudies,
  ProductScreenshots,
  MarketingReadinessSection,
  TrustedBrands,
  OperationalProofSection,
  LaunchOperatingSystemSection,
  // Phase-3 sections — no more Legacy prefixes
  HeroSection,
  FAQSection,
  CTASection,
  ProblemSection,
  SolutionSection,
  TestimonialsSection,
} from '@/modules/vitrine';
import { FeaturesSection as ModernFeaturesSection } from '@/modules/vitrine/components/sections/FeaturesSection';
// PricingSection: keep the self-contained locale-aware version (not the generic sections/ one)
import { PricingSection as LocalePricingSection } from '@/modules/vitrine/components/PricingSection';
import { DemoSection } from '@/modules/vitrine/components/DemoSection';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getFeatures } from '@/modules/vitrine/data/features';
import { getFaqItems } from '@/modules/vitrine/data/faq';
import { getTestimonials } from '@/modules/vitrine/data/testimonials';
import { StickyMobileCTA } from '@/components/StickyMobileCTA';

const STAT_ICONS = [TrendingUp, Users, Zap, Star] as const;

export default function LandingPage() {
  // Dark mode state — synced with DarkModeProvider via CSS class on root
  const [isDark, setIsDark] = useState(false);
  const toggleDarkMode = () => setIsDark((d) => !d);
  useScrollReveal();
  const { locale, copy } = useVitrineLocale();

  const features = getFeatures(locale);
  const faqItems = getFaqItems(locale);
  const testimonials = getTestimonials(locale);

  return (
    <div
      className={`min-h-screen transition-colors duration-500 ${
        isDark ? 'dark bg-slate-950' : 'bg-white'
      }`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <main id="main-content">
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
          ctaSecondary={{
            text: copy.hero.secondaryCta,
            href: '/demo',
            icon: (
              <Play className="w-4 h-4 text-emerald-600 dark:text-emerald-400 ml-0.5" />
            ),
          }}
          stats={copy.hero.stats.map((s, i) => {
            const Icon = STAT_ICONS[i % STAT_ICONS.length];
            return {
              ...s,
              icon: <Icon className="w-5 h-5 text-emerald-500" />,
            };
          })}
          animated
        />

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

        {/* ─── SOCIAL PROOF ─── */}
        <TrustedBrands locale={locale} />
        <SocialProofMetrics locale={locale} />
        <OperationalProofSection locale={locale} />

        {/* ─── FEATURES ─── Phase-3 */}
        <ModernFeaturesSection
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
        <LaunchOperatingSystemSection locale={locale} />
        <MarketingReadinessSection locale={locale} />

        {/* ─── DEMO ─── */}
        <DemoSection />

        {/* ─── TESTIMONIALS ─── Phase-3 */}
        <TestimonialHighlight locale={locale} />
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
          }))}
          columns={3}
        />
        <MiniCaseStudies locale={locale} />

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
          ctaSecondary={{ text: copy.cta.secondary, href: '/demo' }}
          background="gradient"
        />
      </main>

      <Footer />

      {/* ─── STICKY MOBILE CTA ─── visible on mobile after 400px scroll */}
      <StickyMobileCTA locale={locale} />
    </div>
  );
}
