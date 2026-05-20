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
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

export default function LandingPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const { locale } = useVitrineLocale();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <LegacyHeroSection />
      <SocialProofMetrics locale={locale} />
      <LegacyFeaturesSection />
      <ProductScreenshots locale={locale} />
      <DemoSection />
      <TestimonialHighlight locale={locale} />
      <LegacyPricingSection />
      <LegacyTestimonialsSection />
      <MiniCaseStudies locale={locale} />
      <LegacyFaqSection />
      <LegacyCTASection />
      <Footer />
    </div>
  );
}
