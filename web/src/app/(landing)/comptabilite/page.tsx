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
import { CreditCard, Calculator, TrendingUp, FileText } from 'lucide-react';

export default function ComptabilitePage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  const content = modulePageContent.comptabilite;

  // Transform problem items to include icons
  const problemItems = content.problem.items.map((item, index) => ({
    ...item,
    icon: [
      <Calculator key="calculator" className="w-6 h-6" />,
      <TrendingUp key="trending" className="w-6 h-6" />,
      <FileText key="filetext" className="w-6 h-6" />,
      <CreditCard key="creditcard" className="w-6 h-6" />,
    ][index],
  }));

  // Transform solution features
  const solutionFeatures = content.solution.features;

  // Transform features for FeaturesSection with gradients
  const features = content.solution.features.map((feature, index) => ({
    icon: [
      <Calculator key="calculator" className="w-6 h-6" />,
      <FileText key="filetext" className="w-6 h-6" />,
      <TrendingUp key="trending" className="w-6 h-6" />,
      <CreditCard key="creditcard" className="w-6 h-6" />,
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
          text: 'Paie Automatisée',
          icon: <CreditCard className="w-3 h-3" />,
        }}
      />

      {/* Problem Section */}
      <ProblemSection
        title={content.problem.title}
        subtitle={content.problem.subtitle}
        items={problemItems}
        badge={{
          text: 'Les Défis',
          icon: <Calculator className="w-3 h-3" />,
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
          icon: <CreditCard className="w-3 h-3" />,
        }}
      />

      {/* Features Section */}
      <FeaturesSection
        title="Fonctionnalités Détaillées"
        subtitle="Tout ce dont vous avez besoin"
        features={features}
        columns={4}
        badge={{
          text: 'Complet & Fiable',
          icon: <CreditCard className="w-3 h-3" />,
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
