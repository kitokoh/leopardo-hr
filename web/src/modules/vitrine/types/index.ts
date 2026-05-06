// Page and Section Types
export interface PageMetadata {
  title: string;
  description: string;
  keywords: string[];
  ogImage?: string;
  canonical?: string;
}

export interface Section {
  id: string;
  type: 'hero' | 'value-prop' | 'features' | 'case-studies' | 'testimonials' | 'faq' | 'cta' | 'pricing';
  content: Record<string, any>;
  animations?: AnimationConfig;
}

export interface PageContent {
  id: string;
  slug: string;
  title: string;
  description: string;
  metadata: PageMetadata;
  sections: Section[];
  seo: {
    structuredData: Record<string, any>;
    canonical: string;
  };
}

// Animation Types
export interface AnimationConfig {
  type: 'fadeIn' | 'slideUp' | 'slideDown' | 'slideLeft' | 'slideRight' | 'scaleIn';
  duration?: number;
  delay?: number;
  stagger?: number;
}

// Form Types
export interface FormField {
  name: string;
  type: 'text' | 'email' | 'password' | 'number' | 'tel' | 'textarea' | 'select';
  label: string;
  placeholder?: string;
  required?: boolean;
  validation?: {
    minLength?: number;
    maxLength?: number;
    pattern?: RegExp;
    custom?: (value: any) => boolean | string;
  };
}

export interface FormConfig {
  id: string;
  name: string;
  fields: FormField[];
  submitText?: string;
  successMessage?: string;
  errorMessage?: string;
}

export interface FormSubmission {
  id: string;
  type: 'signup' | 'demo' | 'contact' | 'newsletter';
  email: string;
  name?: string;
  company?: string;
  message?: string;
  timestamp: Date;
  page: string;
}

// Feature Types
export interface Feature {
  id: string;
  icon: string;
  title: string;
  description: string;
  details?: string[];
  image?: string;
  highlighted?: boolean;
}

// Pricing Types
export interface PricingPlan {
  id: string;
  name: string;
  price: number;
  currency: string;
  period: string;
  description: string;
  features: string[];
  cta: {
    text: string;
    href: string;
  };
  highlighted?: boolean;
  badge?: string;
}

// Testimonial Types
export interface Testimonial {
  id: string;
  quote: string;
  author: string;
  role: string;
  company: string;
  avatar: string;
  rating: number;
}

// Case Study Types
export interface CaseStudy {
  id: string;
  title: string;
  description: string;
  industry: string;
  metrics: Array<{
    label: string;
    value: string;
  }>;
  image: string;
  link: string;
}

// FAQ Types
export interface FAQItem {
  id: string;
  question: string;
  answer: string;
  category?: string;
}

// Blog Types
export interface BlogPost {
  id: string;
  slug: string;
  title: string;
  excerpt: string;
  content: string;
  author: {
    name: string;
    avatar: string;
    bio: string;
  };
  category: string;
  tags: string[];
  image: string;
  publishedAt: Date;
  updatedAt: Date;
  readingTime: number;
  seo: {
    title: string;
    description: string;
    keywords: string[];
  };
}

// Navigation Types
export interface NavItem {
  label: string;
  href: string;
  icon?: string;
  children?: NavItem[];
}

// Social Types
export interface SocialLink {
  platform: 'twitter' | 'linkedin' | 'github' | 'facebook' | 'instagram';
  url: string;
  label: string;
}
