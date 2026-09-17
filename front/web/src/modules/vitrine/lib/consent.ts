/**
 * Consentement cookies & mesure d'audience (issue #7593).
 *
 * Constat mesuré (audit vitrine, 2026-09-16) : GA4 et Mixpanel étaient injectés
 * au premier rendu (`strategy="afterInteractive"`), conditionnés uniquement au
 * drapeau `NEXT_PUBLIC_ENABLE_ANALYTICS` — **avant tout choix du visiteur** —,
 * aucun bandeau de consentement n'existait, et aucun choix n'était mémorisé.
 *
 * Ici : trois catégories, dont une seule obligatoire (les cookies nécessaires,
 * qui ne sont pas un choix), un choix explicite mémorisé, et un défaut **refusé**
 * — y compris pour la mesure d'audience (position CNIL par défaut).
 *
 * Le module est volontairement sans dépendance React : les composants
 * (`ConsentProvider`, `ConsentBanner`, `ConsentScripts`) s'appuient dessus, et
 * les tests unitaires le vérifient directement.
 */

/** Catégories facultatives. `necessary` n'est pas un choix : il est toujours actif. */
export type OptionalConsentCategory = 'analytics' | 'marketing';

export const OPTIONAL_CATEGORIES: readonly OptionalConsentCategory[] = ['analytics', 'marketing'];

/** Nom du cookie de consentement (first-party, lisible par le domaine seul). */
export const CONSENT_COOKIE = 'leopardo_consent';

/**
 * Version du texte de consentement. À incrémenter dès que la finalité change :
 * un visiteur ayant consenti à la version N doit être re-sollicité pour la
 * version N+1 (le consentement n'est pas un blanc-seing permanent).
 */
export const CONSENT_VERSION = 1;

export interface ConsentState {
  version: number;
  /** Toujours vrai : les cookies nécessaires ne se désactivent pas. */
  necessary: true;
  analytics: boolean;
  marketing: boolean;
  /** ISO 8601 — quand le choix a été exprimé (preuve, art. 7 §1). */
  decidedAt: string;
}

export interface ConsentPayload {
  analytics: boolean;
  marketing: boolean;
}

/** Défaut : tout est refusé. Sert aussi de base au Consent Mode. */
export function deniedConsent(): ConsentPayload {
  return { analytics: false, marketing: false };
}

export function acceptAllConsent(): ConsentPayload {
  return { analytics: true, marketing: true };
}

/**
 * Décision mémorisée, ou `null` si le visiteur n'a pas (encore) choisi — ou si
 * le choix porte sur une version périmée du texte (re-sollicitation).
 *
 * Lit le cookie d'abord (source de vérité, envoyée au serveur), puis
 * `localStorage` en secours pour les navigateurs qui bloquent les cookies
 * first-party.
 */
export function readConsent(): ConsentState | null {
  if (typeof document === 'undefined') return null;

  const fromCookie = readConsentCookie();
  if (fromCookie) return fromCookie;

  try {
    const raw = window.localStorage.getItem(CONSENT_COOKIE);
    return raw ? parseConsent(raw) : null;
  } catch {
    return null;
  }
}

function readConsentCookie(): ConsentState | null {
  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${CONSENT_COOKIE}=([^;]*)`));
  if (!match) return null;
  try {
    return parseConsent(decodeURIComponent(match[1]));
  } catch {
    return null;
  }
}

function parseConsent(raw: string): ConsentState | null {
  try {
    const parsed = JSON.parse(raw) as Partial<ConsentState>;
    if (parsed.version !== CONSENT_VERSION) return null;
    return {
      version: CONSENT_VERSION,
      necessary: true,
      analytics: parsed.analytics === true,
      marketing: parsed.marketing === true,
      decidedAt: typeof parsed.decidedAt === 'string' ? parsed.decidedAt : new Date().toISOString(),
    };
  } catch {
    return null;
  }
}

/** Mémorise le choix (cookie + localStorage) et prévient l'application. */
export function writeConsent(payload: ConsentPayload, now: Date = new Date()): ConsentState {
  const state: ConsentState = {
    version: CONSENT_VERSION,
    necessary: true,
    analytics: payload.analytics === true,
    marketing: payload.marketing === true,
    decidedAt: now.toISOString(),
  };

  if (typeof document !== 'undefined') {
    const maxAge = 60 * 60 * 24 * 182; // 6 mois (durée de conservation recommandée)
    document.cookie = `${CONSENT_COOKIE}=${encodeURIComponent(JSON.stringify(state))}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
    try {
      window.localStorage.setItem(CONSENT_COOKIE, JSON.stringify(state));
    } catch {
      /* stockage indisponible : le cookie suffit */
    }
    window.dispatchEvent(new CustomEvent(CONSENT_CHANGED_EVENT, { detail: state }));
  }

  return state;
}

/** Événement émis à chaque décision, pour que les composants se mettent à jour. */
export const CONSENT_CHANGED_EVENT = 'leopardo-consent-changed';

/**
 * La mesure d'audience est-elle autorisée ?
 *
 * Garde centrale : `getAnalytics()` renvoie un client inerte tant que ce n'est
 * pas vrai, en plus des scripts qui ne sont pas chargés (double barrière).
 */
export function trackingAllowed(): boolean {
  return readConsent()?.analytics === true;
}

/** Efface le choix (utile aux tests et au bouton « retirer mon consentement »). */
export function clearConsent(): void {
  if (typeof document === 'undefined') return;
  document.cookie = `${CONSENT_COOKIE}=; Max-Age=0; Path=/; SameSite=Lax`;
  try {
    window.localStorage.removeItem(CONSENT_COOKIE);
  } catch {
    /* rien à faire */
  }
}

/** Paramètres Consent Mode v2 correspondant au choix (Google). */
export function toConsentMode(payload: ConsentPayload): Record<string, 'granted' | 'denied'> {
  const value = (granted: boolean) => (granted ? 'granted' : 'denied');
  return {
    ad_storage: value(payload.marketing),
    ad_user_data: value(payload.marketing),
    ad_personalization: value(payload.marketing),
    analytics_storage: value(payload.analytics),
  };
}
