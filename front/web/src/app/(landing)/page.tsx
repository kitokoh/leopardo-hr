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
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

export default function LandingPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const { locale } = useVitrineLocale();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <main id="main-content">
        <LegacyHeroSection />
        <SocialProofMetrics locale={locale} />
        <TrustedBrands locale={locale} />
        <OperationalProofSection locale={locale} />
        <LegacyFeaturesSection />
        <ProductScreenshots locale={locale} />
        <MarketingReadinessSection locale={locale} />
        <DemoSection />
        <TestimonialHighlight locale={locale} />
        <LegacyPricingSection />
        <LegacyTestimonialsSection />
        <MiniCaseStudies locale={locale} />
        <LegacyFaqSection />
        <LegacyCTASection />
      </main>
      <Footer />
    </div>
  );
}
