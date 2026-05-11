# Phase 5 Implementation Guide - Tasks 5.3-5.7

## Quick Start

This guide provides step-by-step instructions for using the new optimization features implemented in Phase 5, Tasks 5.3-5.7.

---

## 1. Image Optimization

### Using OptimizedImage Component

```typescript
import { OptimizedImage } from '@/components/OptimizedImage';

// Basic usage
<OptimizedImage
  src="/images/hero.jpg"
  alt="Hero image"
  width={1920}
  height={1080}
/>

// With all options
<OptimizedImage
  src="/images/feature.jpg"
  alt="Feature image"
  width={400}
  height={300}
  priority={false}
  placeholder="blur"
  sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
  quality={75}
  className="rounded-lg"
  containerClassName="aspect-video"
  objectFit="cover"
  objectPosition="center"
  onLoad={() => console.log('Image loaded')}
  onError={() => console.log('Image failed')}
/>
```

### Using Image Optimization Utilities

```typescript
import {
  getOptimizedImageProps,
  responsiveImageSizes,
  imageDimensions,
  imageQuality,
} from '@/lib/image-optimization';

// Get optimized props for a specific use case
const heroProps = getOptimizedImageProps(
  '/images/hero.jpg',
  'Hero image',
  'hero',
  { priority: true }
);

// Use responsive sizes
const sizes = responsiveImageSizes.card;

// Use predefined dimensions
const { width, height } = imageDimensions.featureCard;

// Use quality presets
const quality = imageQuality.high;
```

### Image Optimization Checklist

- [ ] Replace all `<img>` tags with `<OptimizedImage>`
- [ ] Set `priority={true}` for above-the-fold images
- [ ] Use appropriate `sizes` prop for responsive images
- [ ] Provide `blurDataURL` for better perceived performance
- [ ] Use `quality` prop based on image type
- [ ] Test images on different devices
- [ ] Verify WebP format is served
- [ ] Check Lighthouse performance score

---

## 2. Lazy Loading & Code Splitting

### Using Skeleton Loaders

```typescript
import {
  SkeletonLoader,
  CardSkeleton,
  ImageSkeleton,
  TextSkeleton,
  AvatarSkeleton,
  FeatureCardSkeleton,
} from '@/components/SkeletonLoader';

// Generic skeleton
<SkeletonLoader type="card" count={3} />

// Specific skeletons
<CardSkeleton count={6} />
<ImageSkeleton width="100%" height="300px" />
<TextSkeleton lines={3} />
<AvatarSkeleton size="md" />
<FeatureCardSkeleton count={3} />
```

### Using Intersection Observer

```typescript
import { useIntersectionObserver } from '@/hooks/useIntersectionObserver';

export function LazySection() {
  const { ref, isVisible, hasBeenVisible } = useIntersectionObserver({
    threshold: 0.1,
    triggerOnce: true,
  });

  return (
    <div ref={ref}>
      {hasBeenVisible ? (
        <ExpensiveComponent />
      ) : (
        <SkeletonLoader type="card" />
      )}
    </div>
  );
}
```

### Using Dynamic Imports

```typescript
import { lazyLoadSections, lazyLoadComponents } from '@/lib/dynamic-imports';

export default function LandingPage() {
  return (
    <>
      <lazyLoadSections.HeroSection />
      <lazyLoadSections.FeaturesSection />
      <lazyLoadSections.TestimonialsSection />
      <lazyLoadSections.CaseStudiesSection />
      <lazyLoadSections.FAQSection />
      <lazyLoadSections.CTASection />
    </>
  );
}

export function SignupModal() {
  return <lazyLoadComponents.SignupForm />;
}
```

### Code Splitting Checklist

- [ ] Replace heavy sections with lazy loaded versions
- [ ] Add skeleton loaders for loading states
- [ ] Use Intersection Observer for animations
- [ ] Monitor bundle size
- [ ] Test lazy loading on slow networks
- [ ] Verify no layout shift during loading
- [ ] Check performance metrics

---

## 3. PWA Features

### Registering Service Worker

The service worker is automatically registered in the root layout. No additional setup needed.

### Using PWA Provider

```typescript
import { PWAProvider, usePWA } from '@/components/PWAProvider';

// In root layout (already done)
<PWAProvider>
  {children}
</PWAProvider>

// In components
export function InstallButton() {
  const { canInstall, isInstalled, promptInstall } = usePWA();

  if (isInstalled) {
    return <span>App installed ✓</span>;
  }

  if (!canInstall) {
    return null;
  }

  return (
    <button onClick={promptInstall}>
      Installer l'app
    </button>
  );
}

export function OnlineStatus() {
  const { isOnline } = usePWA();

  return (
    <div>
      {isOnline ? '🟢 Online' : '🔴 Offline'}
    </div>
  );
}
```

### PWA Checklist

- [ ] Create PWA icons (192x192, 512x512, maskable)
- [ ] Create app screenshots
- [ ] Test installation on mobile devices
- [ ] Test offline functionality
- [ ] Test app shortcuts
- [ ] Verify manifest.json is valid
- [ ] Test on different browsers
- [ ] Monitor service worker updates

---

## 4. Caching & CDN

### Using Caching Configuration

```typescript
import {
  getCacheHeaders,
  getISRConfig,
  getRevalidationTime,
} from '@/lib/caching-config';

// Get cache headers for a content type
const headers = getCacheHeaders('images');
// Returns: 'public, max-age=31536000, immutable'

// Get ISR configuration for a page
const isrConfig = getISRConfig('landing');
// Returns: { revalidate: 86400, tags: ['landing'] }

// Get revalidation time for a path
const revalidationTime = getRevalidationTime('/blog/article');
// Returns: 86400 (24 hours)
```

### Implementing ISR in Pages

```typescript
// app/(landing)/page.tsx
import { getISRConfig } from '@/lib/caching-config';

export const revalidate = getISRConfig('landing').revalidate;

export default function LandingPage() {
  return (
    // Page content
  );
}
```

### Caching Checklist

- [ ] Verify cache headers are set correctly
- [ ] Test ISR revalidation
- [ ] Monitor cache hit rates
- [ ] Verify compression is enabled
- [ ] Test CDN caching
- [ ] Monitor bandwidth usage
- [ ] Verify stale-while-revalidate works
- [ ] Check Vercel Analytics

---

## 5. Dark Mode

### Using Dark Mode Provider

The dark mode provider is automatically set up in the root layout. No additional setup needed.

### Using useDarkMode Hook

```typescript
import { useDarkMode } from '@/components/DarkModeProvider';

export function ThemeToggle() {
  const { theme, isDark, setTheme, toggleDarkMode } = useDarkMode();

  return (
    <div>
      <button onClick={toggleDarkMode}>
        {isDark ? '☀️ Light' : '🌙 Dark'}
      </button>

      <select value={theme} onChange={(e) => setTheme(e.target.value as any)}>
        <option value="light">Light</option>
        <option value="dark">Dark</option>
        <option value="system">System</option>
      </select>
    </div>
  );
}
```

### Styling with Dark Mode

```typescript
// Using Tailwind dark mode
<div className="bg-white dark:bg-slate-900 text-slate-900 dark:text-white">
  Content
</div>

// Using CSS variables
<div style={{
  backgroundColor: 'var(--background)',
  color: 'var(--foreground)',
}}>
  Content
</div>
```

### Dark Mode Checklist

- [ ] Test all components in dark mode
- [ ] Verify color contrast ratios
- [ ] Test theme persistence
- [ ] Test system preference detection
- [ ] Verify smooth transitions
- [ ] Test on different devices
- [ ] Check accessibility
- [ ] Monitor user preferences

---

## Performance Optimization Tips

### Image Optimization
1. Always use `OptimizedImage` component
2. Set `priority={true}` for above-the-fold images
3. Use appropriate `sizes` prop
4. Provide `blurDataURL` for better UX
5. Use `quality` prop based on image type
6. Convert images to WebP format
7. Use responsive image sizes
8. Lazy load below-the-fold images

### Code Splitting
1. Use `lazyLoadSections` for heavy sections
2. Use `lazyLoadComponents` for heavy components
3. Provide appropriate loading states
4. Use `useIntersectionObserver` for animations
5. Monitor bundle size
6. Test on slow networks
7. Verify no layout shift
8. Check performance metrics

### Caching
1. Use appropriate cache headers
2. Implement ISR for frequently updated content
3. Monitor cache hit rates
4. Use CDN for global distribution
5. Implement cache invalidation
6. Test cache behavior
7. Monitor bandwidth usage
8. Verify compression

### PWA
1. Create all required icons
2. Test installation on multiple devices
3. Handle offline scenarios
4. Implement background sync
5. Use app shortcuts
6. Monitor service worker updates
7. Test on different browsers
8. Verify manifest validity

### Dark Mode
1. Test all components
2. Maintain color contrast
3. Use CSS variables
4. Provide system preference option
5. Persist user preference
6. Smooth transitions
7. No flash of wrong theme
8. Accessible colors

---

## Troubleshooting

### Images Not Loading
- Check image paths
- Verify image files exist
- Check Next.js Image configuration
- Verify WebP format support
- Check browser console for errors

### Skeleton Loaders Not Showing
- Verify component is imported correctly
- Check if loading state is true
- Verify Tailwind CSS is configured
- Check for CSS conflicts
- Verify animation is enabled

### Service Worker Not Registering
- Check browser console for errors
- Verify service worker file exists
- Check HTTPS is enabled
- Verify manifest.json is valid
- Check browser support

### Dark Mode Not Working
- Verify DarkModeProvider is in layout
- Check localStorage is enabled
- Verify CSS variables are set
- Check Tailwind dark mode config
- Verify browser support

### Performance Issues
- Check bundle size
- Verify lazy loading is working
- Check image optimization
- Verify caching headers
- Monitor network requests

---

## Best Practices

### Image Optimization
- Use WebP format for modern browsers
- Provide JPEG fallback for older browsers
- Use responsive image sizes
- Lazy load below-the-fold images
- Optimize image dimensions
- Use appropriate quality settings
- Provide alt text for accessibility
- Test on different devices

### Code Splitting
- Split at route level
- Split heavy components
- Provide loading states
- Use Intersection Observer
- Monitor bundle size
- Test on slow networks
- Verify no layout shift
- Check performance metrics

### Caching
- Use appropriate cache headers
- Implement ISR for dynamic content
- Monitor cache hit rates
- Use CDN for global distribution
- Implement cache invalidation
- Test cache behavior
- Monitor bandwidth usage
- Verify compression

### PWA
- Create all required icons
- Test on multiple devices
- Handle offline scenarios
- Implement background sync
- Use app shortcuts
- Monitor service worker updates
- Test on different browsers
- Verify manifest validity

### Dark Mode
- Test all components
- Maintain color contrast
- Use CSS variables
- Provide system preference option
- Persist user preference
- Smooth transitions
- No flash of wrong theme
- Accessible colors

---

## Performance Targets

### Page Load Time
- Target: < 2 seconds
- Measurement: Lighthouse, WebPageTest
- Optimization: Image optimization, code splitting, caching

### Lighthouse Score
- Target: > 90
- Measurement: Lighthouse
- Optimization: Performance, accessibility, best practices

### Core Web Vitals
- LCP (Largest Contentful Paint): < 2.5s
- FID (First Input Delay): < 100ms
- CLS (Cumulative Layout Shift): < 0.1
- Measurement: Web Vitals, Lighthouse
- Optimization: Image optimization, code splitting, caching

### Bundle Size
- Target: < 200KB (gzipped)
- Measurement: webpack-bundle-analyzer
- Optimization: Code splitting, tree-shaking, minification

---

## Monitoring & Analytics

### Performance Monitoring
- Use Vercel Analytics
- Monitor Core Web Vitals
- Track page load time
- Monitor bundle size
- Track conversion metrics

### User Analytics
- Track dark mode preference
- Track PWA installation
- Track offline usage
- Track form submissions
- Track conversion events

### Error Monitoring
- Monitor service worker errors
- Monitor image loading errors
- Monitor API errors
- Monitor JavaScript errors
- Monitor network errors

---

## Resources

- [Next.js Image Optimization](https://nextjs.org/docs/app/building-your-application/optimizing/images)
- [Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
- [Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Intersection Observer API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API)
- [Web Performance](https://web.dev/performance/)
- [PWA Best Practices](https://web.dev/progressive-web-apps/)
- [Core Web Vitals](https://web.dev/vitals/)
- [Lighthouse](https://developers.google.com/web/tools/lighthouse)

---

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review the best practices
3. Check the resources
4. Review the implementation guide
5. Check the Phase 5 summary

