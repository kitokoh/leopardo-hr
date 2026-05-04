/**
 * Monitoring Setup
 * 
 * Centralizes all monitoring, analytics, and error tracking configuration
 * Supports: Google Analytics 4, Mixpanel, Sentry, Vercel Analytics
 */

import { useEffect } from 'react';

// ============================================================================
// Types
// ============================================================================

export interface ConversionEvent {
  type: 'signup' | 'demo_request' | 'contact' | 'newsletter' | 'pricing_view';
  page: string;
  source?: string;
  metadata?: Record<string, any>;
}

export interface PerformanceMetric {
  name: string;
  value: number;
  unit: string;
  timestamp: number;
}

export interface ErrorEvent {
  message: string;
  stack?: string;
  context?: Record<string, any>;
  severity: 'info' | 'warning' | 'error' | 'critical';
}

// ============================================================================
// Google Analytics 4
// ============================================================================

export const initializeGA4 = () => {
  if (typeof window === 'undefined') return;

  const gaId = process.env.NEXT_PUBLIC_GA_ID;
  if (!gaId) {
    console.warn('Google Analytics ID not configured');
    return;
  }

  // Load GA4 script
  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${gaId}`;
  document.head.appendChild(script);

  // Initialize gtag
  window.dataLayer = window.dataLayer || [];
  function gtag(...args: any[]) {
    window.dataLayer.push(arguments);
  }
  gtag('js', new Date());
  gtag('config', gaId, {
    page_path: window.location.pathname,
    anonymize_ip: true,
    cookie_flags: 'SameSite=None;Secure',
  });

  (window as any).gtag = gtag;
};

export const trackPageView = (pagePath: string, pageTitle: string) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (!gtag) return;

  gtag('event', 'page_view', {
    page_path: pagePath,
    page_title: pageTitle,
  });
};

export const trackConversion = (event: ConversionEvent) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (!gtag) return;

  const eventName = `conversion_${event.type}`;
  gtag('event', eventName, {
    page: event.page,
    source: event.source || 'direct',
    ...event.metadata,
  });

  // Also track as conversion
  gtag('event', 'conversion', {
    conversion_type: event.type,
    page: event.page,
    ...event.metadata,
  });
};

export const trackCTAClick = (buttonText: string, page: string, position: string) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (!gtag) return;

  gtag('event', 'cta_click', {
    button_text: buttonText,
    page,
    position,
  });
};

export const trackScrollDepth = (page: string, depth: number) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (!gtag) return;

  gtag('event', 'scroll_depth', {
    page,
    depth: `${depth}%`,
  });
};

export const trackFormSubmission = (formType: string, page: string, success: boolean) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (!gtag) return;

  gtag('event', 'form_submit', {
    form_type: formType,
    page,
    success,
  });
};

// ============================================================================
// Mixpanel
// ============================================================================

export const initializeMixpanel = () => {
  if (typeof window === 'undefined') return;

  const token = process.env.NEXT_PUBLIC_MIXPANEL_TOKEN;
  if (!token) {
    console.warn('Mixpanel token not configured');
    return;
  }

  // Load Mixpanel script
  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://cdn.mxpnl.com/libs/mixpanel-latest.min.js';
  document.head.appendChild(script);

  script.onload = () => {
    const mixpanel = (window as any).mixpanel;
    if (mixpanel) {
      mixpanel.init(token, {
        track_pageview: true,
        persistence: 'localStorage',
      });
    }
  };
};

export const trackMixpanelEvent = (eventName: string, properties?: Record<string, any>) => {
  if (typeof window === 'undefined') return;

  const mixpanel = (window as any).mixpanel;
  if (!mixpanel) return;

  mixpanel.track(eventName, {
    timestamp: new Date().toISOString(),
    url: window.location.href,
    ...properties,
  });
};

export const setMixpanelUser = (userId: string, properties?: Record<string, any>) => {
  if (typeof window === 'undefined') return;

  const mixpanel = (window as any).mixpanel;
  if (!mixpanel) return;

  mixpanel.identify(userId);
  if (properties) {
    mixpanel.people.set(properties);
  }
};

// ============================================================================
// Sentry (Error Tracking)
// ============================================================================

export const initializeSentry = () => {
  if (typeof window === 'undefined') return;

  const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;
  if (!dsn) {
    console.warn('Sentry DSN not configured');
    return;
  }

  // Load Sentry script
  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://browser.sentry-cdn.com/7.80.0/bundle.min.js';
  document.head.appendChild(script);

  script.onload = () => {
    const Sentry = (window as any).Sentry;
    if (Sentry) {
      Sentry.init({
        dsn,
        environment: process.env.NEXT_PUBLIC_ENVIRONMENT || 'production',
        tracesSampleRate: 1.0,
        integrations: [
          new Sentry.Replay({
            maskAllText: true,
            blockAllMedia: true,
          }),
        ],
        replaysSessionSampleRate: 0.1,
        replaysOnErrorSampleRate: 1.0,
      });
    }
  };
};

export const captureException = (error: Error, context?: Record<string, any>) => {
  if (typeof window === 'undefined') return;

  const Sentry = (window as any).Sentry;
  if (!Sentry) return;

  Sentry.captureException(error, {
    contexts: {
      custom: context,
    },
  });
};

export const captureMessage = (message: string, level: 'info' | 'warning' | 'error' = 'info') => {
  if (typeof window === 'undefined') return;

  const Sentry = (window as any).Sentry;
  if (!Sentry) return;

  Sentry.captureMessage(message, level);
};

// ============================================================================
// Performance Monitoring
// ============================================================================

export const trackPerformanceMetrics = () => {
  if (typeof window === 'undefined') return;

  // Use Web Vitals API
  if ('web-vital' in window) {
    const vitals = (window as any)['web-vital'];
    
    // Track LCP (Largest Contentful Paint)
    vitals.getLCP((metric: any) => {
      trackMetric({
        name: 'LCP',
        value: metric.value,
        unit: 'ms',
        timestamp: Date.now(),
      });
    });

    // Track FID (First Input Delay)
    vitals.getFID((metric: any) => {
      trackMetric({
        name: 'FID',
        value: metric.value,
        unit: 'ms',
        timestamp: Date.now(),
      });
    });

    // Track CLS (Cumulative Layout Shift)
    vitals.getCLS((metric: any) => {
      trackMetric({
        name: 'CLS',
        value: metric.value,
        unit: 'score',
        timestamp: Date.now(),
      });
    });
  }

  // Track page load time
  if (window.performance && window.performance.timing) {
    const timing = window.performance.timing;
    const pageLoadTime = timing.loadEventEnd - timing.navigationStart;
    
    trackMetric({
      name: 'Page Load Time',
      value: pageLoadTime,
      unit: 'ms',
      timestamp: Date.now(),
    });
  }
};

export const trackMetric = (metric: PerformanceMetric) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (gtag) {
    gtag('event', 'performance_metric', {
      metric_name: metric.name,
      metric_value: metric.value,
      metric_unit: metric.unit,
    });
  }

  const mixpanel = (window as any).mixpanel;
  if (mixpanel) {
    mixpanel.track('performance_metric', {
      metric_name: metric.name,
      metric_value: metric.value,
      metric_unit: metric.unit,
    });
  }
};

// ============================================================================
// Error Handling
// ============================================================================

export const setupErrorHandling = () => {
  if (typeof window === 'undefined') return;

  // Handle uncaught errors
  window.addEventListener('error', (event) => {
    captureException(event.error, {
      type: 'uncaught_error',
      message: event.message,
      filename: event.filename,
      lineno: event.lineno,
      colno: event.colno,
    });
  });

  // Handle unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    captureException(event.reason, {
      type: 'unhandled_rejection',
    });
  });
};

// ============================================================================
// Conversion Tracking
// ============================================================================

export const trackConversionFunnel = (step: string, page: string) => {
  if (typeof window === 'undefined') return;

  const gtag = (window as any).gtag;
  if (gtag) {
    gtag('event', 'conversion_funnel', {
      funnel_step: step,
      page,
    });
  }

  const mixpanel = (window as any).mixpanel;
  if (mixpanel) {
    mixpanel.track('conversion_funnel', {
      funnel_step: step,
      page,
    });
  }
};

// ============================================================================
// Hooks
// ============================================================================

/**
 * Hook to initialize all monitoring services
 */
export const useMonitoring = () => {
  useEffect(() => {
    // Initialize all monitoring services
    initializeGA4();
    initializeMixpanel();
    initializeSentry();
    setupErrorHandling();
    trackPerformanceMetrics();

    // Track page view
    trackPageView(window.location.pathname, document.title);
  }, []);
};

/**
 * Hook to track page views
 */
export const usePageTracking = (pageName: string) => {
  useEffect(() => {
    trackPageView(window.location.pathname, pageName);
    trackMixpanelEvent('page_view', {
      page_name: pageName,
      url: window.location.href,
    });
  }, [pageName]);
};

/**
 * Hook to track scroll depth
 */
export const useScrollTracking = (page: string) => {
  useEffect(() => {
    let maxScroll = 0;

    const handleScroll = () => {
      const scrollPercentage = Math.round(
        (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
      );

      if (scrollPercentage > maxScroll) {
        maxScroll = scrollPercentage;

        // Track at 25%, 50%, 75%, 100%
        if ([25, 50, 75, 100].includes(scrollPercentage)) {
          trackScrollDepth(page, scrollPercentage);
        }
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [page]);
};

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get current environment
 */
export const getEnvironment = (): 'production' | 'staging' | 'development' => {
  return (process.env.NEXT_PUBLIC_ENVIRONMENT as any) || 'development';
};

/**
 * Check if monitoring is enabled
 */
export const isMonitoringEnabled = (): boolean => {
  return process.env.NEXT_PUBLIC_ENABLE_ERROR_TRACKING === 'true';
};

/**
 * Get monitoring status
 */
export const getMonitoringStatus = () => {
  return {
    ga4: !!process.env.NEXT_PUBLIC_GA_ID,
    mixpanel: !!process.env.NEXT_PUBLIC_MIXPANEL_TOKEN,
    sentry: !!process.env.NEXT_PUBLIC_SENTRY_DSN,
    environment: getEnvironment(),
    enabled: isMonitoringEnabled(),
  };
};

export default {
  initializeGA4,
  trackPageView,
  trackConversion,
  trackCTAClick,
  trackScrollDepth,
  trackFormSubmission,
  initializeMixpanel,
  trackMixpanelEvent,
  setMixpanelUser,
  initializeSentry,
  captureException,
  captureMessage,
  trackPerformanceMetrics,
  trackMetric,
  setupErrorHandling,
  trackConversionFunnel,
  useMonitoring,
  usePageTracking,
  useScrollTracking,
  getEnvironment,
  isMonitoringEnabled,
  getMonitoringStatus,
};
