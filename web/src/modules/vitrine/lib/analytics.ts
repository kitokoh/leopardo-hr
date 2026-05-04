/**
 * Analytics and Conversion Tracking
 * Supports Google Analytics 4 and Mixpanel
 */

export interface ConversionEvent {
  id: string;
  type: "signup" | "demo_request" | "contact" | "newsletter";
  page: string;
  timestamp: Date;
  source: string;
  userAgent: string;
  metadata?: Record<string, any>;
}

export interface FormSubmission {
  id: string;
  type: "signup" | "demo" | "contact" | "newsletter";
  email: string;
  name?: string;
  company?: string;
  message?: string;
  timestamp: Date;
  page: string;
}

export interface PageViewEvent {
  page: string;
  title: string;
  referrer?: string;
  timestamp: Date;
}

export interface ScrollDepthEvent {
  page: string;
  depth: number; // 0-100
  timestamp: Date;
}

export interface CTAClickEvent {
  page: string;
  buttonText: string;
  buttonPosition: string;
  timestamp: Date;
}

/**
 * Google Analytics 4 Integration
 */
export class GoogleAnalytics {
  private gaId: string;
  private isInitialized: boolean = false;

  constructor(gaId?: string) {
    this.gaId = gaId || process.env.NEXT_PUBLIC_GA_ID || "";
    this.isInitialized = typeof window !== "undefined" && !!(window as any).gtag;
  }

  /**
   * Check if GA4 is available
   */
  isAvailable(): boolean {
    return typeof window !== "undefined" && !!(window as any).gtag && !!this.gaId;
  }

  /**
   * Track page view
   */
  trackPageView(page: string, title: string) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", "page_view", {
        page_path: page,
        page_title: title,
        page_location: window.location.href,
      });
    } catch (error) {
      console.error("GA4 page view error:", error);
    }
  }

  /**
   * Track conversion event
   */
  trackConversion(
    type: "signup" | "demo_request" | "contact" | "newsletter",
    metadata?: Record<string, any>
  ) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", "conversion", {
        conversion_type: type,
        conversion_label: type,
        ...metadata,
      });
    } catch (error) {
      console.error("GA4 conversion error:", error);
    }
  }

  /**
   * Track signup conversion
   */
  trackSignup(email?: string, metadata?: Record<string, any>) {
    this.trackConversion("signup", {
      email,
      ...metadata,
    });
  }

  /**
   * Track demo request conversion
   */
  trackDemoRequest(email?: string, company?: string, metadata?: Record<string, any>) {
    this.trackConversion("demo_request", {
      email,
      company,
      ...metadata,
    });
  }

  /**
   * Track contact form conversion
   */
  trackContact(email?: string, subject?: string, metadata?: Record<string, any>) {
    this.trackConversion("contact", {
      email,
      subject,
      ...metadata,
    });
  }

  /**
   * Track newsletter signup conversion
   */
  trackNewsletterSignup(email?: string, metadata?: Record<string, any>) {
    this.trackConversion("newsletter", {
      email,
      ...metadata,
    });
  }

  /**
   * Track CTA click
   */
  trackCTAClick(buttonText: string, page: string, position: string) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", "cta_click", {
        button_text: buttonText,
        page: page,
        position: position,
      });
    } catch (error) {
      console.error("GA4 CTA click error:", error);
    }
  }

  /**
   * Track form submission
   */
  trackFormSubmission(formType: string, page: string) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", "form_submit", {
        form_type: formType,
        page: page,
      });
    } catch (error) {
      console.error("GA4 form submission error:", error);
    }
  }

  /**
   * Track scroll depth
   */
  trackScrollDepth(page: string, depth: number) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", "scroll_depth", {
        page: page,
        depth: `${depth}%`,
      });
    } catch (error) {
      console.error("GA4 scroll depth error:", error);
    }
  }

  /**
   * Track time on page
   */
  trackTimeOnPage(page: string, seconds: number) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", "time_on_page", {
        page: page,
        seconds: seconds,
      });
    } catch (error) {
      console.error("GA4 time on page error:", error);
    }
  }

  /**
   * Track custom event
   */
  trackEvent(eventName: string, eventData?: Record<string, any>) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("event", eventName, eventData);
    } catch (error) {
      console.error("GA4 custom event error:", error);
    }
  }

  /**
   * Set user properties
   */
  setUserProperties(properties: Record<string, any>) {
    if (!this.isAvailable()) return;

    try {
      (window as any).gtag?.("config", this.gaId, properties);
    } catch (error) {
      console.error("GA4 set user properties error:", error);
    }
  }
}

/**
 * Mixpanel Integration
 */
export class Mixpanel {
  private token: string;
  private isInitialized: boolean = false;

  constructor(token?: string) {
    this.token = token || process.env.NEXT_PUBLIC_MIXPANEL_TOKEN || "";
    this.isInitialized = typeof window !== "undefined" && !!(window as any).mixpanel;
  }

  /**
   * Check if Mixpanel is available
   */
  isAvailable(): boolean {
    return typeof window !== "undefined" && !!(window as any).mixpanel && !!this.token;
  }

  /**
   * Track event
   */
  trackEvent(eventName: string, properties?: Record<string, any>) {
    if (!this.isAvailable()) return;

    try {
      (window as any).mixpanel?.track(eventName, {
        timestamp: new Date().toISOString(),
        page: window.location.pathname,
        ...properties,
      });
    } catch (error) {
      console.error("Mixpanel track error:", error);
    }
  }

  /**
   * Track conversion
   */
  trackConversion(
    type: "signup" | "demo_request" | "contact" | "newsletter",
    metadata?: Record<string, any>
  ) {
    this.trackEvent("Conversion", {
      conversion_type: type,
      ...metadata,
    });
  }

  /**
   * Track signup conversion
   */
  trackSignup(email?: string, metadata?: Record<string, any>) {
    this.trackEvent("Signup", {
      email,
      ...metadata,
    });
  }

  /**
   * Track demo request conversion
   */
  trackDemoRequest(email?: string, company?: string, metadata?: Record<string, any>) {
    this.trackEvent("Demo Request", {
      email,
      company,
      ...metadata,
    });
  }

  /**
   * Track contact form conversion
   */
  trackContact(email?: string, subject?: string, metadata?: Record<string, any>) {
    this.trackEvent("Contact", {
      email,
      subject,
      ...metadata,
    });
  }

  /**
   * Track newsletter signup conversion
   */
  trackNewsletterSignup(email?: string, metadata?: Record<string, any>) {
    this.trackEvent("Newsletter Signup", {
      email,
      ...metadata,
    });
  }

  /**
   * Track page view
   */
  trackPageView(page: string, title: string) {
    this.trackEvent("Page View", {
      page: page,
      title: title,
    });
  }

  /**
   * Set user properties
   */
  setUserProperties(properties: Record<string, any>) {
    if (!this.isAvailable()) return;

    try {
      (window as any).mixpanel?.people?.set(properties);
    } catch (error) {
      console.error("Mixpanel set user properties error:", error);
    }
  }

  /**
   * Identify user
   */
  identifyUser(userId: string) {
    if (!this.isAvailable()) return;

    try {
      (window as any).mixpanel?.identify(userId);
    } catch (error) {
      console.error("Mixpanel identify error:", error);
    }
  }

  /**
   * Track CTA click
   */
  trackCTAClick(buttonText: string, page: string, position: string) {
    this.trackEvent("CTA Click", {
      button_text: buttonText,
      page: page,
      position: position,
    });
  }

  /**
   * Track form submission
   */
  trackFormSubmission(formType: string, page: string) {
    this.trackEvent("Form Submission", {
      form_type: formType,
      page: page,
    });
  }

  /**
   * Track scroll depth
   */
  trackScrollDepth(page: string, depth: number) {
    this.trackEvent("Scroll Depth", {
      page: page,
      depth: depth,
    });
  }

  /**
   * Track time on page
   */
  trackTimeOnPage(page: string, seconds: number) {
    this.trackEvent("Time on Page", {
      page: page,
      seconds: seconds,
    });
  }
}

/**
 * Analytics Manager (unified interface)
 */
export class AnalyticsManager {
  private ga: GoogleAnalytics;
  private mixpanel: Mixpanel;

  constructor() {
    this.ga = new GoogleAnalytics();
    this.mixpanel = new Mixpanel();
  }

  /**
   * Check if analytics is enabled
   */
  isEnabled(): boolean {
    return this.ga.isAvailable() || this.mixpanel.isAvailable();
  }

  /**
   * Track page view
   */
  trackPageView(page: string, title: string) {
    this.ga.trackPageView(page, title);
    this.mixpanel.trackPageView(page, title);
  }

  /**
   * Track conversion
   */
  trackConversion(
    type: "signup" | "demo_request" | "contact" | "newsletter",
    metadata?: Record<string, any>
  ) {
    this.ga.trackConversion(type, metadata);
    this.mixpanel.trackConversion(type, metadata);
  }

  /**
   * Track signup conversion
   */
  trackSignup(email?: string, metadata?: Record<string, any>) {
    this.ga.trackSignup(email, metadata);
    this.mixpanel.trackSignup(email, metadata);
  }

  /**
   * Track demo request conversion
   */
  trackDemoRequest(email?: string, company?: string, metadata?: Record<string, any>) {
    this.ga.trackDemoRequest(email, company, metadata);
    this.mixpanel.trackDemoRequest(email, company, metadata);
  }

  /**
   * Track contact form conversion
   */
  trackContact(email?: string, subject?: string, metadata?: Record<string, any>) {
    this.ga.trackContact(email, subject, metadata);
    this.mixpanel.trackContact(email, subject, metadata);
  }

  /**
   * Track newsletter signup conversion
   */
  trackNewsletterSignup(email?: string, metadata?: Record<string, any>) {
    this.ga.trackNewsletterSignup(email, metadata);
    this.mixpanel.trackNewsletterSignup(email, metadata);
  }

  /**
   * Track CTA click
   */
  trackCTAClick(buttonText: string, page: string, position: string) {
    this.ga.trackCTAClick(buttonText, page, position);
    this.mixpanel.trackCTAClick(buttonText, page, position);
  }

  /**
   * Track form submission
   */
  trackFormSubmission(formType: string, page: string) {
    this.ga.trackFormSubmission(formType, page);
    this.mixpanel.trackFormSubmission(formType, page);
  }

  /**
   * Track scroll depth
   */
  trackScrollDepth(page: string, depth: number) {
    this.ga.trackScrollDepth(page, depth);
    this.mixpanel.trackScrollDepth(page, depth);
  }

  /**
   * Track time on page
   */
  trackTimeOnPage(page: string, seconds: number) {
    this.ga.trackTimeOnPage(page, seconds);
    this.mixpanel.trackTimeOnPage(page, seconds);
  }

  /**
   * Track custom event
   */
  trackEvent(eventName: string, eventData?: Record<string, any>) {
    this.ga.trackEvent(eventName, eventData);
    this.mixpanel.trackEvent(eventName, eventData);
  }

  /**
   * Set user properties
   */
  setUserProperties(properties: Record<string, any>) {
    this.ga.setUserProperties(properties);
    this.mixpanel.setUserProperties(properties);
  }

  /**
   * Identify user
   */
  identifyUser(userId: string) {
    this.mixpanel.identifyUser(userId);
  }
}

/**
 * Singleton instance
 */
let analyticsInstance: AnalyticsManager | null = null;

export function getAnalytics(): AnalyticsManager {
  if (!analyticsInstance) {
    analyticsInstance = new AnalyticsManager();
  }
  return analyticsInstance;
}

/**
 * Conversion tracking helper
 */
export async function trackConversionEvent(
  submission: FormSubmission
): Promise<void> {
  const analytics = getAnalytics();

  // Track based on submission type
  switch (submission.type) {
    case "signup":
      analytics.trackSignup(submission.email, {
        name: submission.name,
        page: submission.page,
      });
      break;
    case "demo":
      analytics.trackDemoRequest(submission.email, submission.company, {
        name: submission.name,
        page: submission.page,
      });
      break;
    case "contact":
      analytics.trackContact(submission.email, submission.message, {
        name: submission.name,
        page: submission.page,
      });
      break;
    case "newsletter":
      analytics.trackNewsletterSignup(submission.email, {
        page: submission.page,
      });
      break;
  }

  // Also track form submission
  analytics.trackFormSubmission(submission.type, submission.page);
}

/**
 * Scroll depth tracking hook helper
 */
export function setupScrollDepthTracking(page: string): () => void {
  if (typeof window === "undefined") return () => {};

  const analytics = getAnalytics();
  let maxDepth = 0;
  let trackedDepths = new Set<number>();

  const handleScroll = () => {
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const scrollTop = window.scrollY;

    const depth = Math.round(
      ((scrollTop + windowHeight) / documentHeight) * 100
    );

    if (depth > maxDepth) {
      maxDepth = depth;

      // Track at 25%, 50%, 75%, 100%
      [25, 50, 75, 100].forEach((threshold) => {
        if (depth >= threshold && !trackedDepths.has(threshold)) {
          trackedDepths.add(threshold);
          analytics.trackScrollDepth(page, threshold);
        }
      });
    }
  };

  window.addEventListener("scroll", handleScroll, { passive: true });

  return () => {
    window.removeEventListener("scroll", handleScroll);
  };
}

/**
 * Time on page tracking hook helper
 */
export function setupTimeOnPageTracking(page: string): () => void {
  if (typeof window === "undefined") return () => {};

  const analytics = getAnalytics();
  const startTime = Date.now();

  const handleBeforeUnload = () => {
    const timeOnPage = Math.round((Date.now() - startTime) / 1000);
    if (timeOnPage > 5) {
      // Only track if user spent more than 5 seconds
      analytics.trackTimeOnPage(page, timeOnPage);
    }
  };

  window.addEventListener("beforeunload", handleBeforeUnload);

  return () => {
    window.removeEventListener("beforeunload", handleBeforeUnload);
  };
}

/**
 * Initialize analytics on page load
 */
export function initializeAnalytics(page: string, title: string): void {
  if (typeof window === "undefined") return;

  const analytics = getAnalytics();
  
  // Track page view
  analytics.trackPageView(page, title);

  // Setup scroll depth tracking
  const unsubscribeScroll = setupScrollDepthTracking(page);

  // Setup time on page tracking
  const unsubscribeTime = setupTimeOnPageTracking(page);

  // Cleanup on page unload
  const handleUnload = () => {
    unsubscribeScroll();
    unsubscribeTime();
  };

  window.addEventListener("beforeunload", handleUnload);
}
