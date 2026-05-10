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
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

export default function LandingPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  useVitrineLocale();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <LegacyHeroSection />
      <LegacyFeaturesSection />
      <DemoSection />
      <LegacyPricingSection />
      <LegacyTestimonialsSection />
      <LegacyFaqSection />
      <LegacyCTASection />
      <Footer />
    </div>
  );
}
