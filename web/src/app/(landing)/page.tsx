'use client';

import { useState } from 'react';
import {
  Navbar,
  HeroSection,
  FeaturesSection,
  DemoSection,
  PricingSection,
  TestimonialsSection,
  FaqSection,
  CTASection,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';

export default function LandingPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <HeroSection />
      <FeaturesSection />
      <DemoSection />
      <PricingSection />
      <TestimonialsSection />
      <FaqSection />
      <CTASection />
      <Footer />
    </div>
  );
}
