/**
 * Import Tests for Phase 3 Components
 * Validates that all components can be imported without errors
 */

describe('Phase 3 Components - Imports', () => {
  it('should import HeroSection', () => {
    const { HeroSection } = require('../HeroSection');
    expect(HeroSection).toBeDefined();
  });

  it('should import FeatureCard and FeaturesSection', () => {
    const { FeatureCard } = require('../FeatureCard');
    const { FeaturesSection } = require('../FeaturesSection');
    expect(FeatureCard).toBeDefined();
    expect(FeaturesSection).toBeDefined();
  });

  it('should import PricingCard and PricingSection', () => {
    const { PricingCard } = require('../PricingCard');
    const { PricingSection } = require('../PricingSection');
    expect(PricingCard).toBeDefined();
    expect(PricingSection).toBeDefined();
  });

  it('should import TestimonialCard and TestimonialsSection', () => {
    const { TestimonialCard } = require('../TestimonialCard');
    const { TestimonialsSection } = require('../TestimonialsSection');
    expect(TestimonialCard).toBeDefined();
    expect(TestimonialsSection).toBeDefined();
  });

  it('should import CaseStudyCard and CaseStudiesSection', () => {
    const { CaseStudyCard } = require('../CaseStudyCard');
    const { CaseStudiesSection } = require('../CaseStudiesSection');
    expect(CaseStudyCard).toBeDefined();
    expect(CaseStudiesSection).toBeDefined();
  });

  it('should import FAQSection', () => {
    const { FAQSection } = require('../FAQSection');
    expect(FAQSection).toBeDefined();
  });

  it('should import CTASection', () => {
    const { CTASection } = require('../CTASection');
    expect(CTASection).toBeDefined();
  });

  it('should import BlogCard and BlogGrid', () => {
    const { BlogCard } = require('../BlogCard');
    const { BlogGrid } = require('../BlogGrid');
    expect(BlogCard).toBeDefined();
    expect(BlogGrid).toBeDefined();
  });

  it('should export all components from index', () => {
    const exports = require('../index');
    expect(exports.HeroSection).toBeDefined();
    expect(exports.FeatureCard).toBeDefined();
    expect(exports.FeaturesSection).toBeDefined();
    expect(exports.PricingCard).toBeDefined();
    expect(exports.PricingSection).toBeDefined();
    expect(exports.TestimonialCard).toBeDefined();
    expect(exports.TestimonialsSection).toBeDefined();
    expect(exports.CaseStudyCard).toBeDefined();
    expect(exports.CaseStudiesSection).toBeDefined();
    expect(exports.FAQSection).toBeDefined();
    expect(exports.CTASection).toBeDefined();
    expect(exports.BlogCard).toBeDefined();
    expect(exports.BlogGrid).toBeDefined();
  });
});
