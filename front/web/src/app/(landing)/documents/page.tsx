'use client';

import { useState } from 'react';
import {
  Navbar,
  HeroSection,
  ProblemSection,
  SolutionSection,
  FeaturesSection,
  CaseStudiesSection,
  TestimonialsSection,
  FAQSection,
  CTASection,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { modulePageContent } from '@/modules/vitrine/lib/content';
import { Lock, Share2, Archive, Shield } from 'lucide-react';

export default function DocumentsPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  const content = modulePageContent.documents;

  // Transform problem items to include icons
  const problemItems = content.problem.items.map((item, index) => ({
    ...item,
    icon: [
      <Share2 key="share" className="w-6 h-6" />,
      <Lock key="lock" className="w-6 h-6" />,
      <Archive key="archive" className="w-6 h-6" />,
      <Shield key="shield" className="w-6 h-6" />,
    ][index],
  }));

  // Transform solution features
  const solutionFeatures = content.solution.features;

  // Transform features for FeaturesSection with gradients
  const features = content.solution.features.map((feature, index) => ({
    icon: [
      <Lock key="lock" className="w-6 h-6" />,
      <Share2 key="share" className="w-6 h-6" />,
      <Archive key="archive" className="w-6 h-6" />,
      <Shield key="shield" className="w-6 h-6" />,
    ][index],
    title: feature.title,
    description: feature.description,
    gradient: [
      'from-emerald-500 to-cyan-500',
      'from-blue-500 to-cyan-500',
      'from-purple-500 to-pink-500',
      'from-orange-500 to-red-500',
    ][index],
    details: [],
  }));

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* Hero Section */}
      <HeroSection
        headline={content.hero.headline}
        subheadline={content.hero.subheadline}
        ctaPrimary={content.hero.ctaPrimary}
        ctaSecondary={content.hero.ctaSecondary}
        badge={{
          text: 'Cabinet Numérique',
          icon: <Lock className="w-3 h-3" />,
        }}
      />

      {/* Problem Section */}
      <ProblemSection
        title={content.problem.title}
        subtitle={content.problem.subtitle}
        items={problemItems}
        badge={{
          text: 'Les Défis',
          icon: <Share2 className="w-3 h-3" />,
        }}
      />

      {/* Solution Section */}
      <SolutionSection
        title={content.solution.title}
        subtitle={content.solution.subtitle}
        description={content.solution.description}
        features={solutionFeatures}
        badge={{
          text: 'Notre Solution',
          icon: <Lock className="w-3 h-3" />,
        }}
      />

      {/* Features Section */}
      <FeaturesSection
        title="Fonctionnalités Détaillées"
        subtitle="Tout ce dont vous avez besoin"
        features={features}
        columns={4}
        badge={{
          text: 'Sécurisé & Conforme',
          icon: <Shield className="w-3 h-3" />,
        }}
      />

      {/* Case Studies Section */}
      <CaseStudiesSection
        title={content.caseStudies.title}
        subtitle={content.caseStudies.subtitle}
        caseStudies={content.caseStudies.items}
      />

      {/* Testimonials Section */}
      <TestimonialsSection
        title={content.testimonials.title}
        subtitle={content.testimonials.subtitle}
        testimonials={content.testimonials.items}
      />

      {/* FAQ Section */}
      <FAQSection
        title={content.faq.title}
        subtitle={content.faq.subtitle}
        faqs={content.faq.items}
      />

      {/* CTA Section */}
      <CTASection
        headline={content.cta.headline}
        subheadline={content.cta.subheadline}
        ctaPrimary={content.cta.ctaPrimary}
        ctaSecondary={content.cta.ctaSecondary}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
