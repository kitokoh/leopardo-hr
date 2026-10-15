/**
 * #8490 (I-03) — intention « Trade » : creer un espace OU en rejoindre un.
 *
 * Le point d'entree Trade (vitrine) ne cree JAMAIS d'entite : il traduit un
 * choix utilisateur en une intention normalisee, puis delegue aux mecanismes
 * existants de la plateforme :
 *  - `create` -> tunnel d'inscription existant (`/signup`), qui provisionne
 *    l'espace via `VerifyTrialSignup` / `ProvisionGuidedTrial` ;
 *  - `join`   -> connexion existante (`/auth/login`), puis rattachement a
 *    l'espace DEJA existant (demande d'acces / QR entreprise, #8447) — un
 *    utilisateur qui appartient deja a un tenant ne cree pas de doublon.
 *
 * Idempotence : `normalizeTradeIntent` est pure et fail-closed (valeur
 * inconnue -> defaut), et `tradeIntentKey` donne une cle STABLE qui sert a
 * dedupliquer l'intention (analytics, reprise de parcours). Deux appels avec
 * les memes entrees produisent la meme cle — jamais deux intentions
 * concurrentes pour le meme choix.
 *
 * Aucune nouvelle entite tenant/role/permission n'est creee ici : tout
 * repose sur l'existant (regle de la mission).
 */

export const TRADE_MODES = ['international', 'domestic'] as const;
export type TradeMode = (typeof TRADE_MODES)[number];

export const TRADE_ACTIONS = ['create', 'join'] as const;
export type TradeAction = (typeof TRADE_ACTIONS)[number];

export type TradeIntent = {
  mode: TradeMode;
  action: TradeAction;
};

/**
 * Defaut fail-closed : le mode le plus courant (marche local) et l'action
 * sans effet de bord (creation, qui passe par la validation serveur) — une
 * entree illisible ne doit jamais produire une intention « join » implicite.
 */
export const DEFAULT_TRADE_INTENT: TradeIntent = { mode: 'domestic', action: 'create' };

export function isTradeMode(value: unknown): value is TradeMode {
  return typeof value === 'string' && (TRADE_MODES as readonly string[]).includes(value);
}

export function isTradeAction(value: unknown): value is TradeAction {
  return typeof value === 'string' && (TRADE_ACTIONS as readonly string[]).includes(value);
}

/**
 * Normalise une intention brute (query string, sessionStorage, telemetrie).
 * Toute valeur hors allowlist retombe sur le defaut — jamais d'intention
 * forgee, jamais de `join` implicite.
 */
export function normalizeTradeIntent(
  raw: { mode?: unknown; action?: unknown } | null | undefined,
): TradeIntent {
  return {
    mode: isTradeMode(raw?.mode) ? raw.mode : DEFAULT_TRADE_INTENT.mode,
    action: isTradeAction(raw?.action) ? raw.action : DEFAULT_TRADE_INTENT.action,
  };
}

/**
 * Cle stable d'une intention : sert de garde d'idempotence cote client. Deux
 * normalisations identiques produisent la meme cle, donc un seul
 * enregistrement d'intention par choix reel.
 */
export function tradeIntentKey(intent: TradeIntent): string {
  return `trade:${intent.mode}:${intent.action}`;
}

/** Ajoute `lang` a une query existante (continuite de locale vitrine). */
function withLang(query: string, lang?: string): string {
  const params = new URLSearchParams(query);
  if (lang) params.set('lang', lang);
  const rendered = params.toString();
  return rendered;
}

/**
 * Cible de l'action « creer » : le tunnel d'inscription existant, avec le
 * mode d'activite transmis pour pre-remplir la configuration metier.
 * `/signup` ignore les parametres qu'il ne connait pas (fail-closed) — ce
 * parametre ne casse donc aucun parcours existant.
 */
export function tradeCreateHref(mode: TradeMode, lang?: string): string {
  const query = withLang(`trade=${encodeURIComponent(mode)}`, lang);
  return `/signup?${query}`;
}

/**
 * Cible de l'action « rejoindre » : la connexion existante. Le rattachement a
 * l'espace deja provisionne s'effectue ensuite par le mecanisme existant
 * (demande d'acces / QR entreprise) — aucun second tenant n'est cree.
 */
export function tradeJoinHref(lang?: string): string {
  const query = withLang('', lang);
  return query ? `/auth/login?${query}` : '/auth/login';
}
