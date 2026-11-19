/**
 * Consentement cookies & mesure d'audience (issue #7593) — **domaine**.
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
 * ## Ce que ce fichier est, et ce qu'il n'est pas (#9658)
 *
 * Ce fichier est le **domaine** : la forme d'un consentement, sa validation de
 * version, et sa traduction en Consent Mode v2. Il ne touche ni au cookie, ni à
 * `localStorage`, ni au DOM — c'est `infrastructure/consent-storage.ts` qui
 * possède l'E/S.
 *
 * Avant #9658, ce fichier vivait dans `lib/` et faisait les deux : il lisait
 * `document.cookie`, écrivait `localStorage` et émettait un `CustomEvent`. Un
 * domaine qui fait de l'E/S n'est pas testable sans DOM, et la garde de couche
 * (`dev-hub/tools/check-web-layer-guard.sh`) le refuse désormais.
 *
 * La séparation suit exactement le précédent de `retail/domain/buyer.ts`
 * (ADR-0035, révision #9652-#9654) : le domaine garde la règle, l'infrastructure
 * garde le support.
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

/**
 * Événement émis à chaque décision, pour que les composants se mettent à jour.
 *
 * La **constante** est du domaine (c'est le nom du contrat entre le domaine et
 * les composants) ; l'**émission** est de l'infrastructure.
 */
export const CONSENT_CHANGED_EVENT = 'leopardo-consent-changed';

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
 * Construit l'état mémorisable à partir d'un choix et d'un instant.
 *
 * `now` est un paramètre (et non `new Date()` interne) pour que l'état soit
 * reproductible en test — c'est ce qui permet à `consent.test.ts` d'asserter
 * `decidedAt` exactement.
 */
export function buildConsentState(payload: ConsentPayload, now: Date = new Date()): ConsentState {
  return {
    version: CONSENT_VERSION,
    necessary: true,
    analytics: payload.analytics === true,
    marketing: payload.marketing === true,
    decidedAt: now.toISOString(),
  };
}

/**
 * Décode une décision mémorisée, ou `null` si elle est illisible ou porte sur
 * une version périmée du texte (re-sollicitation).
 *
 * Fail-closed : une entrée corrompue, un JSON invalide ou une version inconnue
 * rendent `null` — jamais un consentement accordé par défaut.
 */
export function parseConsent(raw: string): ConsentState | null {
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
