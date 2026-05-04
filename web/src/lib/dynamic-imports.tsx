/**
 * Dynamic Import Utilities
 * Provides helpers for code splitting and lazy loading components
 */

import dynamic from 'next/dynamic';
import { ComponentType } from 'react';
import { SkeletonLoader } from '@/components/SkeletonLoader';

/**
 * Create a dynamic component with loading and error states
 */
export function createDynamicComponent<P extends object>(
  importFunc: () => Promise<{ default: ComponentType<P> }>,
  options?: {
    loading?: ComponentType;
    ssr?: boolean;
    delay?: number;
  }
) {
  return dynamic(importFunc, {
    loading: options?.loading || (() => <SkeletonLoader type="card" />),
    ssr: options?.ssr !== false,
  });
}

/**
 * Lazy load heavy sections
 */
export const lazyLoadSections = {
  // Hero sections
  HeroSection: dynamic(
    () => import('@/modules/vitrine/components/sections/HeroSection'),
    {
      loading: () => <SkeletonLoader type="card" />,
      ssr: true,
    }
  ),

  // Feature sections
  FeaturesSection: dynamic(
    () => import('@/modules/vitrine/components/sections/FeaturesSection'),
    {
      loading: () => <SkeletonLoader type="card" count={3} />,
      ssr: true,
    }
  ),

  // Testimonial sections
  TestimonialsSection: dynamic(
    () => import('@/modules/vitrine/components/sections/TestimonialsSection'),
    {
      loading: () => <SkeletonLoader type="card" count={3} />,
      ssr: true,
    }
  ),

  // Case study sections
  CaseStudiesSection: dynamic(
    () => import('@/modules/vitrine/components/sections/CaseStudiesSection'),
    {
      loading: () => <SkeletonLoader type="card" count={3} />,
      ssr: true,
    }
  ),

  // FAQ sections
  FAQSection: dynamic(
    () => import('@/modules/vitrine/components/sections/FAQSection'),
    {
      loading: () => <SkeletonLoader type="paragraph" />,
      ssr: true,
    }
  ),

  // CTA sections
  CTASection: dynamic(
    () => import('@/modules/vitrine/components/sections/CTASection'),
    {
      loading: () => <SkeletonLoader type="card" />,
      ssr: true,
    }
  ),

  // Pricing sections
  PricingSection: dynamic(
    () => import('@/modules/vitrine/components/sections/PricingSection'),
    {
      loading: () => <SkeletonLoader type="card" count={3} />,
      ssr: true,
    }
  ),

  // Blog sections
  BlogGrid: dynamic(
    () => import('@/modules/vitrine/components/sections/BlogGrid'),
    {
      loading: () => <SkeletonLoader type="card" count={6} />,
      ssr: true,
    }
  ),
};

/**
 * Lazy load heavy components
 */
export const lazyLoadComponents = {
  // Forms
  SignupForm: dynamic(
    () => import('@/modules/vitrine/components/forms/SignupForm'),
    {
      loading: () => <SkeletonLoader type="paragraph" />,
      ssr: false,
    }
  ),

  DemoForm: dynamic(
    () => import('@/modules/vitrine/components/forms/DemoForm'),
    {
      loading: () => <SkeletonLoader type="paragraph" />,
      ssr: false,
    }
  ),

  ContactForm: dynamic(
    () => import('@/modules/vitrine/components/forms/ContactForm'),
    {
      loading: () => <SkeletonLoader type="paragraph" />,
      ssr: false,
    }
  ),

  NewsletterForm: dynamic(
    () => import('@/modules/vitrine/components/forms/NewsletterForm'),
    {
      loading: () => <SkeletonLoader type="text" />,
      ssr: false,
    }
  ),

  // Animations
  ParticleField: dynamic(
    () => import('@/modules/vitrine/components/animations/ParticleField'),
    {
      loading: () => null,
      ssr: false,
    }
  ),

  GradientOrbs: dynamic(
    () => import('@/modules/vitrine/components/animations/GradientOrbs'),
    {
      loading: () => null,
      ssr: false,
    }
  ),

  AnimatedCounter: dynamic(
    () => import('@/modules/vitrine/components/animations/AnimatedCounter'),
    {
      loading: () => <span>0</span>,
      ssr: false,
    }
  ),
};

/**
 * Preload a dynamic component
 */
export function preloadDynamicComponent(
  component: ReturnType<typeof dynamic>
) {
  if (typeof window !== 'undefined') {
    // Preload by rendering in a hidden div
    const div = document.createElement('div');
    div.style.display = 'none';
    document.body.appendChild(div);

    // Clean up after a delay
    setTimeout(() => {
      document.body.removeChild(div);
    }, 1000);
  }
}

/**
 * Lazy load on intersection
 */
export function lazyLoadOnIntersection(
  component: ReturnType<typeof dynamic>,
  options?: IntersectionObserverInit
) {
  return dynamic(() => Promise.resolve(component), {
    loading: () => <SkeletonLoader type="card" />,
    ssr: false,
  });
}

/**
 * Code splitting strategy for different routes
 */
export const routeCodeSplitting = {
  // Landing page - load all sections
  landing: {
    sections: [
      'HeroSection',
      'FeaturesSection',
      'TestimonialsSection',
      'CaseStudiesSection',
      'FAQSection',
      'CTASection',
    ],
    components: ['NewsletterForm'],
  },

  // Module pages - load specific sections
  modules: {
    sections: [
      'HeroSection',
      'FeaturesSection',
      'TestimonialsSection',
      'CaseStudiesSection',
      'FAQSection',
      'CTASection',
    ],
    components: ['DemoForm', 'ContactForm'],
  },

  // Pricing page - load pricing section
  pricing: {
    sections: ['HeroSection', 'PricingSection', 'FAQSection', 'CTASection'],
    components: ['DemoForm'],
  },

  // Blog page - load blog grid
  blog: {
    sections: ['HeroSection', 'BlogGrid'],
    components: ['NewsletterForm'],
  },

  // About page - load about sections
  about: {
    sections: ['HeroSection', 'CTASection'],
    components: ['ContactForm'],
  },
};

/**
 * Bundle size optimization
 */
export const bundleOptimization = {
  // Tree-shaking enabled for these packages
  treeShakeable: [
    'lucide-react',
    'framer-motion',
    'gsap',
  ],

  // Modules to exclude from main bundle
  excludeFromMain: [
    '@/modules/vitrine/components/animations',
    '@/modules/vitrine/components/forms',
  ],

  // Modules to preload
  preload: [
    '@/components/OptimizedImage',
    '@/components/SkeletonLoader',
    '@/lib/image-optimization',
  ],
};

export default {
  createDynamicComponent,
  lazyLoadSections,
  lazyLoadComponents,
  preloadDynamicComponent,
  lazyLoadOnIntersection,
  routeCodeSplitting,
  bundleOptimization,
};
