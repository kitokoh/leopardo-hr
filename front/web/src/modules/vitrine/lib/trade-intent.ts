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

/**
 * BOS-O-01 / #8625 — profil professionnel DANS le marche.
 *
 * Deux dimensions distinctes : le marche (international | domestique) puis le
 * profil dans ce marche. La mission exigeait de rendre visible ce que la
 * vitrine annoncait deja en prose ("importateur, exportateur ou les deux",
 * "grossiste ou detail") sans qu'aucune valeur ne puisse etre transportee.
 *
 * Miroir EXACT de l'allowlist serveur `Company::TRADE_PROFILES_BY_MARKET`
 * (`api/app/Core/Tenant/Domain/Models/Company.php`). Le test de parite cote
 * web echoue si les deux divergent.
 */
export const TRADE_PROFILES_BY_MODE = {
  international: ['importer', 'exporter', 'both'],
  domestic: ['wholesaler', 'retailer'],
} as const satisfies Record<TradeMode, readonly string[]>;

export const TRADE_PROFILES = ['importer', 'exporter', 'both', 'wholesaler', 'retailer'] as const;
export type TradeProfile = (typeof TRADE_PROFILES)[number];

export const TRADE_ACTIONS = ['create', 'join'] as const;
export type TradeAction = (typeof TRADE_ACTIONS)[number];

export type TradeIntent = {
  mode: TradeMode;
  action: TradeAction;
  /** Profil dans le marche ; `null` tant que le visiteur ne l'a pas precise. */
  profile: TradeProfile | null;
};

/**
 * Defaut fail-closed : le mode le plus courant (marche local) et l'action
 * sans effet de bord (creation, qui passe par la validation serveur) — une
 * entree illisible ne doit jamais produire une intention « join » implicite.
 */
export const DEFAULT_TRADE_INTENT: TradeIntent = {
  mode: 'domestic',
  action: 'create',
  profile: null,
};

export function isTradeMode(value: unknown): value is TradeMode {
  return typeof value === 'string' && (TRADE_MODES as readonly string[]).includes(value);
}

export function isTradeAction(value: unknown): value is TradeAction {
  return typeof value === 'string' && (TRADE_ACTIONS as readonly string[]).includes(value);
}

/**
 * Le profil est-il autorise POUR CE marche ? Fail-closed : un profil d'un autre
 * marche ("grossiste" en international) est refuse ici ET par l'API — une
 * intention incoherente ne doit jamais atteindre le provisioning.
 */
export function isTradeProfileForMode(mode: TradeMode, value: unknown): value is TradeProfile {
  return (
    typeof value === 'string' &&
    (TRADE_PROFILES_BY_MODE[mode] as readonly string[]).includes(value)
  );
}

/**
 * Normalise une intention brute (query string, sessionStorage, telemetrie).
 * Toute valeur hors allowlist retombe sur le defaut — jamais d'intention
 * forgee, jamais de `join` implicite.
 */
export function normalizeTradeIntent(
  raw: { mode?: unknown; action?: unknown; profile?: unknown } | null | undefined,
): TradeIntent {
  const mode = isTradeMode(raw?.mode) ? raw.mode : DEFAULT_TRADE_INTENT.mode;

  return {
    mode,
    action: isTradeAction(raw?.action) ? raw.action : DEFAULT_TRADE_INTENT.action,
    // Profil absent = `null` (pas de profil invente), et un profil incoherent
    // avec le marche retombe aussi sur `null` plutot que d'etre conserve.
    profile: isTradeProfileForMode(mode, raw?.profile) ? raw.profile : null,
  };
}

/**
 * Cle stable d'une intention : sert de garde d'idempotence cote client. Deux
 * normalisations identiques produisent la meme cle, donc un seul
 * enregistrement d'intention par choix reel.
 */
export function tradeIntentKey(intent: TradeIntent): string {
  return `trade:${intent.mode}:${intent.action}:${intent.profile ?? '-'}`;
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
export function tradeCreateHref(mode: TradeMode, lang?: string, profile?: TradeProfile | null): string {
  const params = new URLSearchParams(`trade=${encodeURIComponent(mode)}`);
  if (profile) params.set('profile', profile);
  return `/signup?${withLang(params.toString(), lang)}`;
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
