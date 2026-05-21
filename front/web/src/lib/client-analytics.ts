'use client';

export type ClientAnalyticsEventName =
  | 'login_success'
  | 'login_failed'
  | 'dashboard_loaded'
  | 'feature_blocked'
  | 'demo_user_selected';

export type ClientAnalyticsPayload = {
  name: ClientAnalyticsEventName;
  timestamp: string;
  properties: Record<string, string | number | boolean | null>;
};

declare global {
  interface Window {
    __LEOPARDO_ANALYTICS_EVENTS__?: Array<{
      name: string;
      timestamp: string;
      properties: Record<string, string | number | boolean | null>;
    }>;
  }
}

function sanitizeValue(value: unknown): string | number | boolean | null {
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }

  if (value === null || value === undefined) {
    return null;
  }

  return String(value);
}

export function trackClientEvent(
  name: ClientAnalyticsEventName,
  properties: Record<string, unknown> = {},
): ClientAnalyticsPayload | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const sanitizedProperties = Object.fromEntries(
    Object.entries(properties).map(([key, value]) => [key, sanitizeValue(value)]),
  );

  const payload: ClientAnalyticsPayload = {
    name,
    timestamp: new Date().toISOString(),
    properties: sanitizedProperties,
  };

  window.__LEOPARDO_ANALYTICS_EVENTS__ = [
    ...(window.__LEOPARDO_ANALYTICS_EVENTS__ ?? []),
    payload,
  ].slice(-100);

  window.dispatchEvent(new CustomEvent('leopardo:analytics', { detail: payload }));

  return payload;
}
