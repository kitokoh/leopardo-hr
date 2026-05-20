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
  SocialProofTestimonial,
  SocialProofCases,
  ProductScreenshots,
} from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getSocialProofCopy, productScreenshots } from '@/modules/vitrine/data/social-proof';

export default function LandingPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const { locale } = useVitrineLocale();
  const socialProof = getSocialProofCopy(locale);

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />
      <LegacyHeroSection />
      <SocialProofMetrics metrics={socialProof.metricsSection} />
      <LegacyFeaturesSection />
      <DemoSection />
      <ProductScreenshots
        title={socialProof.screenshotsSection.title}
        titleHighlight={socialProof.screenshotsSection.titleHighlight}
        subtitle={socialProof.screenshotsSection.subtitle}
        badge={socialProof.screenshotsSection.badge}
        screenshots={productScreenshots}
      />
      <LegacyPricingSection />
      <SocialProofTestimonial {...socialProof.featuredTestimonial} />
      <LegacyTestimonialsSection />
      <SocialProofCases {...socialProof.casesSection} />
      <LegacyFaqSection />
      <LegacyCTASection />
      <Footer />
    </div>
  );
}
