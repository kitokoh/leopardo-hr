'use client';

import { useEffect } from 'react';
import {
  getAnalytics,
  initializeAnalytics,
  setupScrollDepthTracking,
  setupTimeOnPageTracking,
} from '@/modules/vitrine/lib/analytics';

/**
 * Hook for tracking page views and engagement metrics
 * 
 * Usage:
 * ```typescript
 * export default function MyPage() {
 *   useAnalyticsPageView('/my-page', 'My Page Title');
 *   return <div>Page content</div>;
 * }
 * ```
 */
export function useAnalyticsPageView(page: string, title: string) {
  useEffect(() => {
    initializeAnalytics(page, title);
  }, [page, title]);
}

/**
 * Hook for tracking CTA clicks
 * 
 * Usage:
 * ```typescript
 * const { trackCTAClick } = useAnalyticsCTA();
 * 
 * <button onClick={() => trackCTAClick('Sign Up', 'hero')}>
 *   Sign Up
 * </button>
 * ```
 */
export function useAnalyticsCTA() {
  const analytics = getAnalytics();

  return {
    trackCTAClick: (buttonText: string, position: string) => {
      analytics.trackCTAClick(buttonText, window.location.pathname, position);
    },
  };
}

/**
 * Hook for tracking form submissions
 * 
 * Usage:
 * ```typescript
 * const { trackFormSubmit } = useAnalyticsForm();
 * 
 * const handleSubmit = async (data) => {
 *   trackFormSubmit('signup');
 *   // Submit form...
 * };
 * ```
 */
export function useAnalyticsForm() {
  const analytics = getAnalytics();

  return {
    trackFormSubmit: (formType: string) => {
      analytics.trackFormSubmission(formType, window.location.pathname);
    },
    trackSignup: (email?: string, metadata?: Record<string, any>) => {
      analytics.trackSignup(email, {
        page: window.location.pathname,
        ...metadata,
      });
    },
    trackDemoRequest: (email?: string, company?: string, metadata?: Record<string, any>) => {
      analytics.trackDemoRequest(email, company, {
        page: window.location.pathname,
        ...metadata,
      });
    },
    trackContact: (email?: string, subject?: string, metadata?: Record<string, any>) => {
      analytics.trackContact(email, subject, {
        page: window.location.pathname,
        ...metadata,
      });
    },
    trackNewsletterSignup: (email?: string, metadata?: Record<string, any>) => {
      analytics.trackNewsletterSignup(email, {
        page: window.location.pathname,
        ...metadata,
      });
    },
  };
}

/**
 * Hook for tracking scroll depth
 * 
 * Usage:
 * ```typescript
 * useAnalyticsScrollDepth();
 * ```
 */
export function useAnalyticsScrollDepth() {
  useEffect(() => {
    const unsubscribe = setupScrollDepthTracking(window.location.pathname);
    return unsubscribe;
  }, []);
}

/**
 * Hook for tracking time on page
 * 
 * Usage:
 * ```typescript
 * useAnalyticsTimeOnPage();
 * ```
 */
export function useAnalyticsTimeOnPage() {
  useEffect(() => {
    const unsubscribe = setupTimeOnPageTracking(window.location.pathname);
    return unsubscribe;
  }, []);
}

/**
 * Hook for tracking custom events
 * 
 * Usage:
 * ```typescript
 * const { trackEvent } = useAnalyticsEvent();
 * 
 * trackEvent('video_played', { video_id: '123' });
 * ```
 */
export function useAnalyticsEvent() {
  const analytics = getAnalytics();

  return {
    trackEvent: (eventName: string, eventData?: Record<string, any>) => {
      analytics.trackEvent(eventName, {
        page: window.location.pathname,
        ...eventData,
      });
    },
  };
}

/**
 * Hook for setting user properties
 * 
 * Usage:
 * ```typescript
 * const { setUserProperties } = useAnalyticsUser();
 * 
 * setUserProperties({
 *   email: 'user@example.com',
 *   company: 'Acme Corp'
 * });
 * ```
 */
export function useAnalyticsUser() {
  const analytics = getAnalytics();

  return {
    setUserProperties: (properties: Record<string, any>) => {
      analytics.setUserProperties(properties);
    },
    identifyUser: (userId: string) => {
      analytics.identifyUser(userId);
    },
  };
}

/**
 * Hook for complete page analytics setup
 * Combines page view, scroll depth, and time on page tracking
 * 
 * Usage:
 * ```typescript
 * export default function MyPage() {
 *   useAnalyticsPage('/my-page', 'My Page Title');
 *   return <div>Page content</div>;
 * }
 * ```
 */
export function useAnalyticsPage(page: string, title: string) {
  useAnalyticsPageView(page, title);
  useAnalyticsScrollDepth();
  useAnalyticsTimeOnPage();
}
