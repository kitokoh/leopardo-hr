// Layout Components
export { Navbar } from './Navbar';
export { Footer } from './Footer';
export { MainLayout } from './layout/MainLayout';

// New Reusable Section Components (Phase 3)
export * from './sections';

// PA2-MKT-012: the root-level FeaturesSection, TestimonialsSection,
// FaqSection and CTASection components were fully superseded by their
// sections/ (Phase 3) equivalents and had zero remaining callers outside
// of the LegacyXxx aliases below (grep-audited), so those dead files and
// aliases were removed. HeroSection.tsx (QuickTrialEmailForm) and
// PricingSection.tsx (locale-aware pricing) are still imported directly
// by pages from their file paths, not via this barrel, so no alias is
// re-exported here for them.
export { DemoSection } from './DemoSection';
export { ParticleField } from './ParticleField';
export { LegalPageShell } from './LegalPageShell';

// Common Components
export * from './common';

// Animation Components
export * from './animations';

// Form Components
export * from './forms';
