/**
 * Image Optimization Utilities
 * Provides helpers for optimized image handling with Next.js Image component
 */

export interface OptimizedImageProps {
  src: string;
  alt: string;
  width?: number;
  height?: number;
  priority?: boolean;
  placeholder?: "blur" | "empty";
  blurDataURL?: string;
  sizes?: string;
  quality?: number;
  className?: string;
}

/**
 * Generate blur placeholder data URL for images
 * This is a simple placeholder - in production, use actual blur hashes
 */
export function generateBlurDataURL(width: number = 10, height: number = 10): string {
  // SVG-based placeholder that's very small
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
      <rect width="${width}" height="${height}" fill="#f3f4f6"/>
    </svg>
  `;
  const encoded = Buffer.from(svg).toString("base64");
  return `data:image/svg+xml;base64,${encoded}`;
}

/**
 * Get responsive image sizes for different breakpoints
 * Used in the 'sizes' prop of Next.js Image component
 */
export const responsiveImageSizes = {
  // Full width images
  fullWidth: "(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1280px",

  // Hero images
  hero: "(max-width: 640px) 100vw, (max-width: 1024px) 100vw, 1920px",

  // Card images
  card: "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw",

  // Thumbnail images
  thumbnail: "(max-width: 640px) 100vw, (max-width: 1024px) 25vw, 20vw",

  // Avatar images
  avatar: "64px",

  // Icon images
  icon: "32px",

  // Feature images
  feature: "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 400px",

  // Testimonial images
  testimonial: "(max-width: 640px) 100vw, (max-width: 1024px) 33vw, 25vw",

  // Case study images
  caseStudy: "(max-width: 640px) 100vw, (max-width: 1024px) 100vw, 600px",

  // Blog images
  blog: "(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 800px",
};

/**
 * Image quality settings for different use cases
 */
export const imageQuality = {
  high: 90,      // Hero, featured images
  medium: 75,    // Regular content images
  low: 60,       // Thumbnails, avatars
  thumbnail: 50, // Very small images
};

/**
 * Common image dimensions for different components
 */
export const imageDimensions = {
  // Hero section
  hero: { width: 1920, height: 1080 },
  heroMobile: { width: 640, height: 720 },

  // Feature cards
  featureCard: { width: 400, height: 300 },
  featureCardMobile: { width: 320, height: 240 },

  // Testimonial avatars
  avatar: { width: 64, height: 64 },
  avatarLarge: { width: 128, height: 128 },

  // Case study images
  caseStudy: { width: 600, height: 400 },
  caseStudyMobile: { width: 320, height: 240 },

  // Blog featured images
  blogFeatured: { width: 800, height: 400 },
  blogFeaturedMobile: { width: 320, height: 200 },

  // Blog thumbnail
  blogThumbnail: { width: 300, height: 200 },

  // Pricing card images
  pricingCard: { width: 400, height: 300 },

  // Icons
  icon: { width: 32, height: 32 },
  iconLarge: { width: 64, height: 64 },

  // Logos
  logo: { width: 200, height: 50 },
  logoSmall: { width: 100, height: 25 },
};

/**
 * Get optimized image props for a specific use case
 */
export function getOptimizedImageProps(
  src: string,
  alt: string,
  useCase: keyof typeof imageDimensions,
  options?: Partial<OptimizedImageProps>
): OptimizedImageProps {
  const dimensions = imageDimensions[useCase];
  const quality = imageQuality.medium;

  return {
    src,
    alt,
    width: dimensions.width,
    height: dimensions.height,
    quality,
    placeholder: "blur",
    blurDataURL: generateBlurDataURL(10, 10),
    ...options,
  };
}

/**
 * Convert image path to WebP format
 * Assumes images are stored with original extension
 */
export function getWebPPath(imagePath: string): string {
  // Remove extension and add .webp
  const withoutExt = imagePath.replace(/\.[^.]+$/, "");
  return `${withoutExt}.webp`;
}

/**
 * Get srcset for high-DPI displays
 * Returns array of image sources for different pixel densities
 */
export function getSrcSet(
  basePath: string,
  width: number,
  height: number
): Array<{ src: string; density: string }> {
  return [
    { src: basePath, density: "1x" },
    { src: basePath.replace(/\.[^.]+$/, "@2x.$&"), density: "2x" },
    { src: basePath.replace(/\.[^.]+$/, "@3x.$&"), density: "3x" },
  ];
}

/**
 * Lazy loading configuration
 */
export const lazyLoadingConfig = {
  // Intersection Observer options for lazy loading
  intersectionObserver: {
    root: null,
    rootMargin: "50px",
    threshold: 0.01,
  },

  // Images that should be loaded immediately (above the fold)
  priorityImages: [
    "/images/hero",
    "/images/logo",
    "/images/favicon",
  ],

  // Images that should be lazy loaded
  lazyLoadImages: [
    "/images/features",
    "/images/testimonials",
    "/images/case-studies",
    "/images/blog",
  ],
};

/**
 * Check if an image should be loaded with priority
 */
export function shouldPrioritizeImage(src: string): boolean {
  return lazyLoadingConfig.priorityImages.some((pattern) =>
    src.includes(pattern)
  );
}

/**
 * Image format support detection
 */
export const imageFormats = {
  webp: "image/webp",
  avif: "image/avif",
  jpeg: "image/jpeg",
  png: "image/png",
  svg: "image/svg+xml",
};

/**
 * Get supported image formats for a browser
 * This is a helper for server-side rendering
 */
export function getSupportedFormats(): string[] {
  // Next.js Image component automatically handles format negotiation
  // This is just for reference
  return ["image/avif", "image/webp", "image/jpeg"];
}

/**
 * Calculate aspect ratio for responsive images
 */
export function getAspectRatio(width: number, height: number): number {
  return width / height;
}

/**
 * Generate CSS for maintaining aspect ratio
 */
export function getAspectRatioPadding(width: number, height: number): string {
  const ratio = (height / width) * 100;
  return `${ratio}%`;
}

/**
 * Image optimization presets for common use cases
 */
export const imagePresets = {
  hero: {
    quality: imageQuality.high,
    priority: true,
    sizes: responsiveImageSizes.hero,
    placeholder: "blur" as const,
  },
  card: {
    quality: imageQuality.medium,
    priority: false,
    sizes: responsiveImageSizes.card,
    placeholder: "blur" as const,
  },
  thumbnail: {
    quality: imageQuality.low,
    priority: false,
    sizes: responsiveImageSizes.thumbnail,
    placeholder: "blur" as const,
  },
  avatar: {
    quality: imageQuality.medium,
    priority: false,
    sizes: responsiveImageSizes.avatar,
    placeholder: "blur" as const,
  },
  blog: {
    quality: imageQuality.high,
    priority: false,
    sizes: responsiveImageSizes.blog,
    placeholder: "blur" as const,
  },
};
