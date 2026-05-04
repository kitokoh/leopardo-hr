/**
 * Structured Data (JSON-LD) generators for SEO
 * Validates: Requirements 2.1, 2.2
 */

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';
const siteName = 'Leopardo';

/**
 * Organization Schema
 * Describes the company/organization
 */
export function generateOrganizationSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: siteName,
    url: siteUrl,
    logo: `${siteUrl}/logo.png`,
    description: 'Plateforme complète de gestion RH pour PME et startups',
    foundingDate: '2020',
    contactPoint: {
      '@type': 'ContactPoint',
      contactType: 'Customer Support',
      email: 'support@leopardo.com',
      telephone: '+33-1-XX-XX-XX-XX',
      availableLanguage: ['fr', 'en'],
    },
    sameAs: [
      'https://twitter.com/leopardo',
      'https://linkedin.com/company/leopardo',
      'https://facebook.com/leopardo',
    ],
    address: {
      '@type': 'PostalAddress',
      streetAddress: 'Paris, France',
      addressCountry: 'FR',
    },
  };
}

/**
 * Product Schema for each module
 * Describes software products/modules
 */
export function generateProductSchema(
  productName: string,
  description: string,
  price: number = 29,
  ratingValue: number = 4.9,
  ratingCount: number = 500
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: productName,
    description: description,
    applicationCategory: 'BusinessApplication',
    operatingSystem: 'Web',
    url: siteUrl,
    offers: {
      '@type': 'Offer',
      price: price.toString(),
      priceCurrency: 'EUR',
      availability: 'https://schema.org/InStock',
      url: `${siteUrl}/pricing`,
    },
    aggregateRating: {
      '@type': 'AggregateRating',
      ratingValue: ratingValue.toString(),
      ratingCount: ratingCount.toString(),
      bestRating: '5',
      worstRating: '1',
    },
    author: {
      '@type': 'Organization',
      name: siteName,
      url: siteUrl,
    },
  };
}

/**
 * FAQ Schema
 * Describes frequently asked questions
 */
export function generateFAQSchema(
  faqs: Array<{ question: string; answer: string }>
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.map((faq) => ({
      '@type': 'Question',
      name: faq.question,
      acceptedAnswer: {
        '@type': 'Answer',
        text: faq.answer,
      },
    })),
  };
}

/**
 * Review Schema
 * Describes customer reviews and testimonials
 */
export function generateReviewSchema(
  author: string,
  rating: number,
  reviewText: string,
  company?: string
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Review',
    reviewRating: {
      '@type': 'Rating',
      ratingValue: rating.toString(),
      bestRating: '5',
      worstRating: '1',
    },
    author: {
      '@type': 'Person',
      name: author,
      ...(company && { affiliation: company }),
    },
    reviewBody: reviewText,
    datePublished: new Date().toISOString().split('T')[0],
  };
}

/**
 * Aggregate Rating Schema
 * Describes overall ratings for the product
 */
export function generateAggregateRatingSchema(
  ratingValue: number = 4.9,
  ratingCount: number = 500,
  bestRating: number = 5,
  worstRating: number = 1
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'AggregateRating',
    ratingValue: ratingValue.toString(),
    ratingCount: ratingCount.toString(),
    bestRating: bestRating.toString(),
    worstRating: worstRating.toString(),
  };
}

/**
 * BreadcrumbList Schema
 * Describes navigation breadcrumbs
 */
export function generateBreadcrumbSchema(
  items: Array<{ name: string; url: string }>
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: (index + 1).toString(),
      name: item.name,
      item: item.url,
    })),
  };
}

/**
 * Article Schema
 * Describes blog articles
 */
export function generateArticleSchema(
  title: string,
  description: string,
  image: string,
  datePublished: string,
  dateModified: string,
  author: string,
  url: string
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: title,
    description: description,
    image: image,
    datePublished: datePublished,
    dateModified: dateModified,
    author: {
      '@type': 'Person',
      name: author,
    },
    publisher: {
      '@type': 'Organization',
      name: siteName,
      logo: {
        '@type': 'ImageObject',
        url: `${siteUrl}/logo.png`,
      },
    },
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': url,
    },
  };
}

/**
 * LocalBusiness Schema
 * Describes the business location
 */
export function generateLocalBusinessSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'LocalBusiness',
    name: siteName,
    image: `${siteUrl}/logo.png`,
    description: 'Plateforme complète de gestion RH pour PME et startups',
    address: {
      '@type': 'PostalAddress',
      streetAddress: 'Paris, France',
      addressCountry: 'FR',
    },
    telephone: '+33-1-XX-XX-XX-XX',
    url: siteUrl,
    sameAs: [
      'https://twitter.com/leopardo',
      'https://linkedin.com/company/leopardo',
      'https://facebook.com/leopardo',
    ],
  };
}

/**
 * WebSite Schema
 * Describes the website
 */
export function generateWebSiteSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: siteName,
    url: siteUrl,
    description: 'Plateforme complète de gestion RH pour PME et startups',
    potentialAction: {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: `${siteUrl}/search?q={search_term_string}`,
      },
      'query-input': 'required name=search_term_string',
    },
  };
}

/**
 * Service Schema
 * Describes services offered
 */
export function generateServiceSchema(
  serviceName: string,
  description: string,
  price: number = 29
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Service',
    name: serviceName,
    description: description,
    provider: {
      '@type': 'Organization',
      name: siteName,
      url: siteUrl,
    },
    offers: {
      '@type': 'Offer',
      price: price.toString(),
      priceCurrency: 'EUR',
      availability: 'https://schema.org/InStock',
    },
    areaServed: {
      '@type': 'Country',
      name: 'FR',
    },
  };
}

/**
 * Pricing Table Schema
 * Describes pricing plans
 */
export function generatePricingSchema(
  plans: Array<{
    name: string;
    price: number;
    description: string;
    features: string[];
  }>
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'PriceSpecification',
    priceCurrency: 'EUR',
    offers: plans.map((plan) => ({
      '@type': 'Offer',
      name: plan.name,
      price: plan.price.toString(),
      description: plan.description,
      priceCurrency: 'EUR',
      availability: 'https://schema.org/InStock',
    })),
  };
}

/**
 * Video Schema
 * Describes video content
 */
export function generateVideoSchema(
  title: string,
  description: string,
  thumbnailUrl: string,
  uploadDate: string,
  duration: string = 'PT5M'
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'VideoObject',
    name: title,
    description: description,
    thumbnailUrl: thumbnailUrl,
    uploadDate: uploadDate,
    duration: duration,
  };
}

/**
 * Event Schema
 * Describes webinars or events
 */
export function generateEventSchema(
  eventName: string,
  description: string,
  startDate: string,
  endDate: string,
  location: string = 'Online'
) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Event',
    name: eventName,
    description: description,
    startDate: startDate,
    endDate: endDate,
    eventAttendanceMode: 'https://schema.org/OnlineEventAttendanceMode',
    eventStatus: 'https://schema.org/EventScheduled',
    location: {
      '@type': 'VirtualLocation',
      url: siteUrl,
    },
    organizer: {
      '@type': 'Organization',
      name: siteName,
      url: siteUrl,
    },
  };
}

/**
 * Helper function to render structured data as script tag
 */
export function renderStructuredData(data: Record<string, any>): string {
  return JSON.stringify(data);
}

/**
 * Helper function to create multiple structured data schemas
 */
export function combineStructuredData(
  schemas: Array<Record<string, any>>
): Record<string, any> {
  return {
    '@context': 'https://schema.org',
    '@graph': schemas,
  };
}

/**
 * Validate structured data (basic validation)
 */
export function validateStructuredData(data: Record<string, any>): boolean {
  if (!data['@context']) {
    console.warn('Missing @context in structured data');
    return false;
  }
  if (!data['@type']) {
    console.warn('Missing @type in structured data');
    return false;
  }
  return true;
}
