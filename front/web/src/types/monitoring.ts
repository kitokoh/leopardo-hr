/**
 * Monitoring Types
 * 
 * Type definitions for monitoring, analytics, and error tracking
 */

// ============================================================================
// Google Analytics Types
// ============================================================================

export interface GA4Event {
  name: string;
  parameters?: Record<string, string | number | boolean>;
}

export interface GA4Config {
  id: string;
  anonymizeIp?: boolean;
  cookieFlags?: string;
}

// ============================================================================
// Mixpanel Types
// ============================================================================

export interface MixpanelEvent {
  name: string;
  properties?: Record<string, any>;
}

export interface MixpanelUser {
  id: string;
  properties?: Record<string, any>;
}

// ============================================================================
// Sentry Types
// ============================================================================

export interface SentryConfig {
  dsn: string;
  environment: 'production' | 'staging' | 'development';
  tracesSampleRate?: number;
  replaysSessionSampleRate?: number;
  replaysOnErrorSampleRate?: number;
}

export interface SentryContext {
  [key: string]: any;
}

// ============================================================================
// Conversion Types
// ============================================================================

export type ConversionType = 'signup' | 'demo_request' | 'contact' | 'newsletter' | 'pricing_view';

export interface Conversion {
  type: ConversionType;
  page: string;
  source?: string;
  timestamp?: number;
  metadata?: Record<string, any>;
}

export interface ConversionFunnel {
  step: string;
  page: string;
  timestamp?: number;
}

// ============================================================================
// Performance Types
// ============================================================================

export interface PerformanceMetric {
  name: string;
  value: number;
  unit: string;
  timestamp: number;
}

export interface CoreWebVitals {
  lcp?: number; // Largest Contentful Paint
  fid?: number; // First Input Delay
  cls?: number; // Cumulative Layout Shift
  ttfb?: number; // Time to First Byte
}

export interface PageLoadMetrics {
  pageLoadTime: number;
  domContentLoaded: number;
  firstContentfulPaint: number;
  largestContentfulPaint: number;
}

// ============================================================================
// Error Types
// ============================================================================

export type ErrorSeverity = 'info' | 'warning' | 'error' | 'critical';

export interface ErrorEvent {
  message: string;
  stack?: string;
  context?: Record<string, any>;
  severity: ErrorSeverity;
  timestamp?: number;
  url?: string;
  userAgent?: string;
}

export interface ErrorReport {
  id: string;
  message: string;
  count: number;
  lastOccurrence: number;
  affectedUsers: number;
  severity: ErrorSeverity;
}

// ============================================================================
// User Tracking Types
// ============================================================================

export interface UserSession {
  sessionId: string;
  userId?: string;
  startTime: number;
  endTime?: number;
  pageViews: number;
  events: string[];
  conversions: ConversionType[];
}

export interface UserBehavior {
  pageViews: number;
  scrollDepth: number;
  timeOnPage: number;
  clicks: number;
  formInteractions: number;
}

// ============================================================================
// Analytics Dashboard Types
// ============================================================================

export interface AnalyticsSummary {
  period: 'day' | 'week' | 'month';
  users: number;
  sessions: number;
  pageViews: number;
  bounceRate: number;
  avgSessionDuration: number;
  conversions: number;
  conversionRate: number;
}

export interface TrafficSource {
  source: string;
  medium: string;
  users: number;
  sessions: number;
  bounceRate: number;
  conversionRate: number;
}

export interface PageMetrics {
  page: string;
  views: number;
  users: number;
  bounceRate: number;
  avgTimeOnPage: number;
  conversions: number;
  conversionRate: number;
}

export interface DeviceMetrics {
  device: 'mobile' | 'tablet' | 'desktop';
  users: number;
  sessions: number;
  bounceRate: number;
  conversionRate: number;
  avgSessionDuration: number;
}

// ============================================================================
// Alert Types
// ============================================================================

export type AlertSeverity = 'info' | 'warning' | 'error' | 'critical';

export interface Alert {
  id: string;
  title: string;
  message: string;
  severity: AlertSeverity;
  timestamp: number;
  resolved?: boolean;
  resolvedAt?: number;
  metadata?: Record<string, any>;
}

export interface AlertRule {
  id: string;
  name: string;
  condition: string;
  threshold: number;
  duration: number; // in seconds
  actions: AlertAction[];
  enabled: boolean;
}

export interface AlertAction {
  type: 'email' | 'slack' | 'pagerduty' | 'webhook';
  target: string;
  template?: string;
}

// ============================================================================
// Report Types
// ============================================================================

export interface Report {
  id: string;
  title: string;
  period: 'daily' | 'weekly' | 'monthly' | 'quarterly';
  generatedAt: number;
  data: Record<string, any>;
}

export interface DailyReport extends Report {
  period: 'daily';
  summary: AnalyticsSummary;
  topPages: PageMetrics[];
  topSources: TrafficSource[];
  errors: ErrorReport[];
  alerts: Alert[];
}

export interface WeeklyReport extends Report {
  period: 'weekly';
  summary: AnalyticsSummary;
  topPages: PageMetrics[];
  topSources: TrafficSource[];
  deviceMetrics: DeviceMetrics[];
  errors: ErrorReport[];
  alerts: Alert[];
  recommendations: string[];
}

export interface MonthlyReport extends Report {
  period: 'monthly';
  summary: AnalyticsSummary;
  topPages: PageMetrics[];
  topSources: TrafficSource[];
  deviceMetrics: DeviceMetrics[];
  errors: ErrorReport[];
  alerts: Alert[];
  trends: Record<string, number[]>;
  recommendations: string[];
}

// ============================================================================
// Monitoring Status Types
// ============================================================================

export interface MonitoringStatus {
  ga4: boolean;
  mixpanel: boolean;
  sentry: boolean;
  vercelAnalytics: boolean;
  environment: 'production' | 'staging' | 'development';
  enabled: boolean;
  lastCheck: number;
}

export interface ServiceHealth {
  service: string;
  status: 'healthy' | 'degraded' | 'down';
  lastCheck: number;
  uptime: number; // percentage
  responseTime: number; // ms
}

// ============================================================================
// Global Window Types
// ============================================================================

declare global {
  interface Window {
    dataLayer?: any[];
    gtag?: (...args: any[]) => void;
    mixpanel?: any;
    Sentry?: any;
  }
}

export {};
