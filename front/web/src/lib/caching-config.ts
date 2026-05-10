/**
 * Caching and ISR Configuration
 * Defines caching strategies for different content types
 */

/**
 * Cache control headers for different content types
 */
export const cacheHeaders = {
  // Static assets - cache for 1 year
  staticAssets: {
    'Cache-Control': 'public, max-age=31536000, immutable',
  },

  // Images - cache for 1 year
  images: {
    'Cache-Control': 'public, max-age=31536000, immutable',
  },

  // Fonts - cache for 1 year
  fonts: {
    'Cache-Control': 'public, max-age=31536000, immutable',
  },

  // CSS/JS - cache for 1 month
  scripts: {
    'Cache-Control': 'public, max-age=2592000, immutable',
  },

  // HTML pages - cache for 1 hour, revalidate
  pages: {
    'Cache-Control': 'public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400',
  },

  // API responses - cache for 5 minutes
  api: {
    'Cache-Control': 'public, max-age=300, s-maxage=300, stale-while-revalidate=3600',
  },

  // No cache - for dynamic content
  noCache: {
    'Cache-Control': 'no-cache, no-store, must-revalidate',
  },
};

/**
 * ISR (Incremental Static Regeneration) configuration
 */
export const isrConfig = {
  // Landing page - revalidate every 24 hours
  landing: {
    revalidate: 86400, // 24 hours
    tags: ['landing'],
  },

  // Module pages - revalidate every 24 hours
  modules: {
    revalidate: 86400, // 24 hours
    tags: ['modules'],
  },

  // Pricing page - revalidate every 12 hours
  pricing: {
    revalidate: 43200, // 12 hours
    tags: ['pricing'],
  },

  // Blog listing - revalidate every 6 hours
  blogListing: {
    revalidate: 21600, // 6 hours
    tags: ['blog', 'blog-listing'],
  },

  // Blog article - revalidate every 24 hours
  blogArticle: {
    revalidate: 86400, // 24 hours
    tags: ['blog', 'blog-article'],
  },

  // About page - revalidate every 7 days
  about: {
    revalidate: 604800, // 7 days
    tags: ['about'],
  },

  // API data - revalidate every 1 hour
  apiData: {
    revalidate: 3600, // 1 hour
    tags: ['api-data'],
  },
};

/**
 * Compression configuration
 */
export const compressionConfig = {
  // Enable gzip compression
  gzip: {
    enabled: true,
    level: 6, // 1-9, higher = better compression but slower
  },

  // Enable brotli compression (better than gzip)
  brotli: {
    enabled: true,
    level: 11, // 0-11, higher = better compression but slower
  },

  // File types to compress
  compressibleTypes: [
    'text/html',
    'text/css',
    'text/javascript',
    'application/javascript',
    'application/json',
    'text/xml',
    'application/xml',
    'image/svg+xml',
    'font/woff',
    'font/woff2',
  ],

  // Minimum file size to compress (in bytes)
  minSize: 1024, // 1KB
};

/**
 * CDN configuration
 */
export const cdnConfig = {
  // Vercel CDN settings
  vercel: {
    // Enable edge caching
    edgeCaching: true,

    // Enable edge functions
    edgeFunctions: true,

    // Enable image optimization
    imageOptimization: true,

    // Enable analytics
    analytics: true,
  },

  // Custom CDN headers
  headers: {
    // Enable CORS
    'Access-Control-Allow-Origin': '*',

    // Enable compression
    'Accept-Encoding': 'gzip, deflate, br',

    // Security headers
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
    'X-XSS-Protection': '1; mode=block',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
  },
};

/**
 * Browser caching configuration
 */
export const browserCacheConfig = {
  // Cache manifest for offline support
  manifest: {
    version: '1.0.0',
    name: 'Leopardo',
    description: 'Plateforme de gestion RH',
  },

  // Service worker cache strategies
  strategies: {
    // Cache first - for static assets
    cacheFirst: {
      cacheName: 'leopardo-cache-v1',
      maxAge: 31536000, // 1 year
    },

    // Network first - for dynamic content
    networkFirst: {
      cacheName: 'leopardo-runtime-v1',
      maxAge: 3600, // 1 hour
    },

    // Stale while revalidate - for API data
    staleWhileRevalidate: {
      cacheName: 'leopardo-api-v1',
      maxAge: 300, // 5 minutes
      staleMaxAge: 3600, // 1 hour
    },
  },
};

/**
 * Performance optimization configuration
 */
export const performanceConfig = {
  // Image optimization
  images: {
    // Supported formats
    formats: ['image/avif', 'image/webp', 'image/jpeg'],

    // Device sizes
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],

    // Image sizes
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],

    // Minimum cache TTL
    minimumCacheTTL: 31536000, // 1 year

    // Quality settings
    quality: {
      high: 90,
      medium: 75,
      low: 60,
    },
  },

  // Font optimization
  fonts: {
    // Preload fonts
    preload: [
      '/fonts/inter-regular.woff2',
      '/fonts/inter-bold.woff2',
    ],

    // Font display strategy
    display: 'swap', // Show fallback while loading
  },

  // Script optimization
  scripts: {
    // Defer non-critical scripts
    defer: true,

    // Async load analytics
    async: true,
  },

  // CSS optimization
  css: {
    // Critical CSS
    critical: true,

    // Inline critical CSS
    inline: true,

    // Minify CSS
    minify: true,
  },
};

/**
 * Get cache headers for a specific content type
 */
export function getCacheHeaders(contentType: keyof typeof cacheHeaders) {
  return cacheHeaders[contentType] || cacheHeaders.noCache;
}

/**
 * Get ISR configuration for a specific page
 */
export function getISRConfig(pageType: keyof typeof isrConfig) {
  return isrConfig[pageType] || isrConfig.landing;
}

/**
 * Generate cache key for content
 */
export function generateCacheKey(
  path: string,
  params?: Record<string, string | number>
): string {
  let key = path;

  if (params) {
    const queryString = Object.entries(params)
      .map(([k, v]) => `${k}=${v}`)
      .join('&');
    key += `?${queryString}`;
  }

  return key;
}

/**
 * Check if content should be cached
 */
export function shouldCache(path: string): boolean {
  // Don't cache admin routes
  if (path.startsWith('/admin')) return false;

  // Don't cache auth routes
  if (path.startsWith('/auth')) return false;

  // Don't cache API routes (handled separately)
  if (path.startsWith('/api')) return false;

  // Cache everything else
  return true;
}

/**
 * Get revalidation time for a path
 */
export function getRevalidationTime(path: string): number {
  if (path === '/') return isrConfig.landing.revalidate;
  if (path.startsWith('/blog/')) return isrConfig.blogArticle.revalidate;
  if (path === '/blog') return isrConfig.blogListing.revalidate;
  if (path === '/pricing') return isrConfig.pricing.revalidate;
  if (path === '/about') return isrConfig.about.revalidate;
  if (path.startsWith('/employes') || path.startsWith('/documents') || path.startsWith('/comptabilite') || path.startsWith('/marketing')) {
    return isrConfig.modules.revalidate;
  }

  return 3600; // Default 1 hour
}

export default {
  cacheHeaders,
  isrConfig,
  compressionConfig,
  cdnConfig,
  browserCacheConfig,
  performanceConfig,
  getCacheHeaders,
  getISRConfig,
  generateCacheKey,
  shouldCache,
  getRevalidationTime,
};
