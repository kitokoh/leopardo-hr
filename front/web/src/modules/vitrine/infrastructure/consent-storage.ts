/**
 * Stockage du consentement — **infrastructure** (#9658).
 *
 * Ce fichier possède l'E/S que le domaine n'a pas le droit de faire :
 * `document.cookie`, `localStorage`, et l'émission du `CustomEvent` qui
 * prévient les composants.
 *
 * Il a été extrait de `lib/consent.ts` (devenu `domain/consent.ts`) parce que
 * la garde de couche web refuse `document.cookie` / `localStorage` / `window.`
 * dans un `domain/`. La règle n'est pas cosmétique : tant que la lecture du
 * cookie vivait dans le domaine, `consent.test.ts` ne pouvait pas s'exécuter
 * sans DOM, et le domaine dépendait d'un support qui n'existe pas côté serveur.
 *
 * ## Ordre de lecture, et pourquoi
 *
 * Le cookie d'abord (source de vérité, envoyée au serveur), `localStorage` en
 * secours pour les navigateurs qui bloquent les cookies first-party. C'est
 * l'ordre d'origine, conservé à l'identique : l'inverser changerait le
 * comportement observé sur ces navigateurs.
 */

import {
  CONSENT_CHANGED_EVENT,
  CONSENT_COOKIE,
  buildConsentState,
  parseConsent,
  type ConsentPayload,
  type ConsentState,
} from '../domain/consent';

/**
 * Décision mémorisée, ou `null` si le visiteur n'a pas (encore) choisi — ou si
 * le choix porte sur une version périmée du texte (re-sollicitation).
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

/** Mémorise le choix (cookie + localStorage) et prévient l'application. */
export function writeConsent(payload: ConsentPayload, now: Date = new Date()): ConsentState {
  const state = buildConsentState(payload, now);

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
