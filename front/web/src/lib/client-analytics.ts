'use client';

export type ClientAnalyticsEventName =
  | 'login_success'
  | 'login_failed'
  | 'dashboard_loaded'
  | 'feature_blocked'
  | 'demo_user_selected'
  | 'kiosk_status';

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

const LOCAL_API_BASE_URL = 'http://localhost:8000/api/v1';
const DEPLOYED_API_BASE_URL = 'https://gestionemployerbackend.onrender.com/api/v1';
const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL ||
  (process.env.NODE_ENV === 'production' ? DEPLOYED_API_BASE_URL : LOCAL_API_BASE_URL);

function shouldPersistEvent(name: ClientAnalyticsEventName): boolean {
  return name !== 'login_failed';
}

function persistEvent(payload: ClientAnalyticsPayload): void {
  const token = window.localStorage.getItem('auth_token');

  if (!token || !shouldPersistEvent(payload.name)) {
    return;
  }

  const body = JSON.stringify({
    name: payload.name,
    occurred_at: payload.timestamp,
    surface: payload.name === 'kiosk_status' ? 'kiosk' : 'web',
    duration_ms: typeof payload.properties.duration_ms === 'number' ? payload.properties.duration_ms : undefined,
    properties: payload.properties,
  });

  void fetch(`${API_BASE_URL}/client-events`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body,
    keepalive: body.length < 60000,
  }).catch(() => {
    // Analytics must never block the client experience.
  });
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
  persistEvent(payload);

  return payload;
}
