'use client';

export type ClientAnalyticsEventName =
  | 'login_success'
  | 'login_failed'
  // Issue #7479 — le login a réussi mais le profil n'a pas pu être chargé
  // (incident de service, pas un échec d'identifiants). Événement distinct pour
  // que la mesure ne compte pas ces cas comme des échecs de connexion.
  | 'login_profile_unavailable'
  | 'dashboard_loaded'
  | 'feature_blocked'
  | 'demo_user_selected'
  | 'kiosk_status'
  | 'leo_ia_announcement_sent'
  | 'leo_ia_card_dismissed';

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

function shouldPersistEvent(name: ClientAnalyticsEventName): boolean {
  return name !== 'login_failed';
}

function persistEvent(payload: ClientAnalyticsPayload): void {
  // Audit #1699 : plus de token en localStorage — l'événement passe par le
  // proxy same-origin /api/v1/[...path] qui injecte le cookie httpOnly.
  if (!shouldPersistEvent(payload.name)) {
    return;
  }

  const body = JSON.stringify({
    name: payload.name,
    occurred_at: payload.timestamp,
    surface: payload.name === 'kiosk_status' ? 'kiosk' : 'web',
    duration_ms: typeof payload.properties.duration_ms === 'number' ? payload.properties.duration_ms : undefined,
    properties: payload.properties,
  });

  // Issue #7479 — « analytics must never block the client experience » ne tenait
  // que pour un échec ASYNCHRONE : `fetch` absent (environnement de test, moteur
  // restreint, extension agressive) lève une ReferenceError SYNCHRONE que le
  // `.catch()` ne rattrape pas. L'appel remontait alors jusqu'au `catch` du
  // formulaire de connexion et remplaçait le message utilisateur par
  // « fetch is not defined » — un incident de mesure présenté comme un échec de
  // connexion. La mesure est désormais réellement best-effort.
  try {
    void fetch('/api/v1/client-events', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body,
      keepalive: body.length < 60000,
    }).catch(() => {
      // Analytics must never block the client experience.
    });
  } catch {
    // Idem : aucun canal de mesure ne doit interrompre le parcours utilisateur.
  }
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
