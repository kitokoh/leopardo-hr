'use client';

import { getAnalytics } from './analytics';

/**
 * #7542 — tranche 1 de #7496 : plan de tracking du tunnel d'inscription.
 *
 * Avant cette tranche, le tunnel n'émettait qu'UN seul événement (`trackSignup`,
 * à la soumission réussie du formulaire) : les étapes intermédiaires — ouverture
 * de `/signup`, envoi du code, vérification du code, espace provisionné — étaient
 * invisibles. On ne pouvait donc pas distinguer « personne n'ouvre la page » de
 * « tout le monde abandonne à l'écran du code », alors que les campagnes
 * publicitaires arrivent (#7496).
 *
 * Deux invariants portés par ce module :
 *
 * 1. **Un parcours = un `correlation_id`** (persisté en sessionStorage). C'est ce
 *    qui permet de recoller les étapes d'une même visite — et, plus tard, d'y
 *    rattacher les événements de l'entretien (#7493) et le lead CRM.
 * 2. **L'attribution ne se perd plus.** `source` + `utm_*` sont captés au premier
 *    écran puis conservés pour tout le tunnel : auparavant ils étaient relus de
 *    `window.location` à chaque appel, donc un passage vitrine → `/signup` (ou un
 *    retour de Google avec `?google=1`) les faisait disparaître.
 *
 * Règle de sûreté (critère 4 de #7496) : **aucune donnée sensible** dans les
 * événements — jamais de mot de passe, jamais de code OTP, jamais de valeur de
 * formulaire. Seuls des clés, des compteurs et l'attribution sont transmis.
 *
 * Règle de robustesse (précédent #7479) : la mesure ne doit JAMAIS casser le
 * parcours. `void fetch(...)` avait déjà produit une `ReferenceError` synchrone
 * que le `.catch()` ne rattrapait pas ; ici tout est encapsulé, et
 * `trackFunnelStep` ne peut pas lever.
 */

/** Noms d'événements — alignés sur le plan de #7496, pour la partie observable client. */
export const FUNNEL_EVENTS = {
  signupView: 'signup_view',
  signupEmailSubmitted: 'signup_email_submitted',
  signupOtpSent: 'signup_otp_sent',
  signupOtpVerified: 'signup_otp_verified',
  spaceProvisioned: 'space_provisioned',
} as const;

export type FunnelEventName = (typeof FUNNEL_EVENTS)[keyof typeof FUNNEL_EVENTS];

const CORRELATION_KEY = 'lp_funnel_correlation_id';
const ATTRIBUTION_KEY = 'lp_funnel_attribution';

/** Clés d'attribution conservées entre les écrans du tunnel. */
const ATTRIBUTION_KEYS = [
  'source',
  'plan',
  'module',
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_content',
  'utm_term',
] as const;

function safeStorage(): Storage | null {
  try {
    if (typeof window === 'undefined') return null;
    return window.sessionStorage;
  } catch {
    // sessionStorage indisponible (SSR, navigation privée restrictive) : on
    // dégrade proprement — les événements partiront sans corrélation persistée.
    return null;
  }
}

function newCorrelationId(): string {
  try {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
      return crypto.randomUUID();
    }
  } catch {
    // crypto indisponible : repli ci-dessous
  }
  return `f_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Identifiant stable du parcours en cours. Créé au premier appel, puis réutilisé
 * pour toute la visite (y compris après un rechargement pendant le provisioning).
 */
export function getFunnelCorrelationId(): string {
  const storage = safeStorage();
  if (!storage) return newCorrelationId();

  try {
    const existing = storage.getItem(CORRELATION_KEY);
    if (existing && existing.trim().length > 0) return existing;
  } catch {
    return newCorrelationId();
  }

  const created = newCorrelationId();
  try {
    storage.setItem(CORRELATION_KEY, created);
  } catch {
    // quota / mode restreint : l'id reste utilisable en mémoire pour cette page
  }
  return created;
}

/**
 * Attribution du parcours. Au premier écran on capture `source` + `utm_*` de
 * l'URL et on les persiste ; ensuite on RELIT la valeur persistée plutôt que
 * l'URL courante, pour que le tunnel ne perde pas l'origine du prospect.
 */
export function getFunnelAttribution(): Record<string, string> {
  const storage = safeStorage();
  let stored: Record<string, string> = {};

  if (storage) {
    try {
      const raw = storage.getItem(ATTRIBUTION_KEY);
      if (raw) {
        const parsed: unknown = JSON.parse(raw);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
          stored = parsed as Record<string, string>;
        }
      }
    } catch {
      stored = {};
    }
  }

  if (typeof window === 'undefined') return stored;

  const params = new URLSearchParams(window.location.search);
  const merged: Record<string, string> = { ...stored };
  for (const key of ATTRIBUTION_KEYS) {
    const value = params.get(key);
    // On ne complète que les clés absentes : une valeur déjà captée au premier
    // écran fait foi (un `?google=1` au retour d'OAuth n'écrase pas la source).
    if (value && !merged[key]) merged[key] = value;
  }

  if (storage) {
    try {
      storage.setItem(ATTRIBUTION_KEY, JSON.stringify(merged));
    } catch {
      // quota : l'attribution reste correcte pour cette page
    }
  }

  return merged;
}

/**
 * Émet une étape du funnel. Ne lève jamais : une panne de mesure ne doit pas
 * priver le prospect de son espace.
 */
export function trackFunnelStep(
  event: FunnelEventName,
  props: Record<string, unknown> = {},
): void {
  try {
    getAnalytics().trackEvent(event, {
      correlation_id: getFunnelCorrelationId(),
      ...getFunnelAttribution(),
      ...props,
    });
  } catch {
    // Mesure best-effort : jamais bloquante (précédent #7479).
  }
}
