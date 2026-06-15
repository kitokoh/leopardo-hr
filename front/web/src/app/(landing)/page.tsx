'use client';

import { useState } from 'react';
import {
  Navbar,
  LegacyHeroSection,
  LegacyFeaturesSection,
  DemoSection,
  LegacyPricingSection,
  LegacyTestimonialsSection,
  LegacyFaqSection,
  LegacyCTASection,
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
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const { locale, copy } = useVitrineLocale();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <main id="main-content">
        <LegacyHeroSection />

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

        <LegacyFeaturesSection />

        <ProductScreenshots locale={locale} />
        <LaunchOperatingSystemSection locale={locale} />
        <MarketingReadinessSection locale={locale} />

        <DemoSection />

        <TestimonialHighlight locale={locale} />
        <LegacyTestimonialsSection />
        <MiniCaseStudies locale={locale} />

        <LegacyPricingSection />
        <LegacyFaqSection />
        <LegacyCTASection />
      </main>
      <Footer />
    </div>
  );
}
