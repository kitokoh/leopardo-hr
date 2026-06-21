'use client';

import {
  Navbar,
  HeroSection,
  FeaturesSection,
  DemoSection,
  PricingSection,
  TestimonialsSection,
  FAQSection,
  CTASection,
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
  ProblemSection,
  SolutionSection,
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

export default function LandingPage() {
  const { locale, copy } = useVitrineLocale();
  useScrollReveal();

  return (
    <div className="min-h-screen bg-white dark:bg-slate-950 transition-colors duration-500">
      <Navbar />
      <main id="main-content">
        <HeroSection
          headline={copy.hero.titleTop + ' ' + copy.hero.titleBottom}
          subheadline={copy.hero.subtitle}
          badge={{ text: copy.hero.badge, label: copy.hero.badgeNew }}
          ctaPrimary={{ text: copy.hero.primaryCta, href: '/signup' }}
          ctaSecondary={{ text: copy.hero.secondaryCta, href: '/demo' }}
          stats={copy.hero.stats.map((stat) => ({ ...stat, icon: undefined }))}
        />

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

        <TrustedBrands locale={locale} />
        <SocialProofMetrics locale={locale} />
        <OperationalProofSection locale={locale} />

        <FeaturesSection
          title={copy.features.title}
          subtitle={copy.features.titleHighlight}
          badge={{ text: copy.features.badge }}
          features={[]}
          columns={3}
        />

        <ProductScreenshots locale={locale} />
        <LaunchOperatingSystemSection locale={locale} />
        <MarketingReadinessSection locale={locale} />

        <DemoSection />

        <TestimonialHighlight locale={locale} />
        <TestimonialsSection
          title={copy.testimonials.title}
          subtitle={copy.testimonials.titleHighlight}
          badge={{ text: copy.testimonials.badge }}
          testimonials={[]}
          columns={2}
        />
        <MiniCaseStudies locale={locale} />

        <PricingSection
          title={copy.pricing.title}
          subtitle={copy.pricing.titleHighlight}
          badge={{ text: copy.pricing.badge }}
          plans={[]}
          showToggle
        />
        <FAQSection
          title={copy.faq.title}
          subtitle={copy.faq.titleHighlight}
          badge={{ text: copy.faq.badge }}
          items={[]}
        />
        <CTASection
          headline={`${copy.cta.title} ${copy.cta.titleHighlight}`}
          subheadline={copy.cta.subtitle}
          badge={{ text: copy.cta.badge }}
          ctaPrimary={{ text: copy.cta.primary, href: '/signup' }}
          ctaSecondary={{ text: copy.cta.secondary, href: '/demo' }}
          background="gradient"
        />
      </main>
      <Footer />
    </div>
  );
}
