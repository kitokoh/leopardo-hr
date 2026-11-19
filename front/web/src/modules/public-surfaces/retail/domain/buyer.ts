/**
 * Domaine public — verticale Retail : le compte acheteur.
 *
 * Repris de `front/marketplace/src/lib/buyer.ts` et `src/lib/api.ts` (#9627).
 *
 * ## Ce que le client conserve — et ce qu'il ne conserve plus
 *
 * Depuis #8096, la session est portée par un cookie `HttpOnly; Secure;
 * SameSite=None` posé par l'API. **Le jeton n'est plus jamais écrit en
 * localStorage** : une XSS ne peut plus l'exfiltrer.
 *
 * Seul le **profil public** (nom, e-mail affichés à l'écran) reste stocké comme
 * indice d'affichage instantané. Ce n'est pas une credential : sans cookie
 * valide, l'API répond 401 et l'indice est purgé.
 *
 * ## Migration douce
 *
 * Les sessions legacy `{token, buyer}` sont détectées à la lecture : le jeton
 * est échangé **une fois** contre le cookie, puis purgé du stockage. Le jeton
 * rendu par `takeLegacyBuyerToken` ne doit jamais être repersisté.
 */

/** Profil public de l'acheteur — jamais de credential ici. */
export interface BuyerProfile {
  readonly name: string;
  readonly email: string;
  readonly phone: string | null;
  readonly createdAt: string | null;
}

/** Session côté client : le profil seul, le cookie fait le reste. */
export interface BuyerSession {
  readonly buyer: BuyerProfile;
}

/** Clé de stockage du profil (pas du jeton). */
export const BUYER_STORAGE_KEY = 'leopardo_marche_buyer';

/** Événement de synchronisation intra-onglet. */
export const BUYER_EVENT = 'leopardo-marche:buyer';

/** Vrai si l'objet a la forme minimale d'un profil exploitable. */
export function isValidBuyer(value: unknown): value is BuyerProfile {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const candidate = value as Partial<BuyerProfile>;

  return typeof candidate.name === 'string' && typeof candidate.email === 'string';
}

/** Normalise un profil reçu de l'API (champs optionnels rendus explicites). */
export function normalizeBuyer(buyer: BuyerProfile): BuyerProfile {
  return {
    name: buyer.name,
    email: buyer.email,
    phone: typeof buyer.phone === 'string' ? buyer.phone : null,
    createdAt: typeof buyer.createdAt === 'string' ? buyer.createdAt : null,
  };
}

/** Une commande du compte acheteur (historique). */
export interface AccountOrder {
  readonly reference: string;
  readonly sellerName: string;
  readonly totalMinor: number;
  readonly currency: string;
  readonly status: string;
  readonly createdAt: string | null;
  readonly trackingToken: string;
}

/** Un avis publié sur un produit. */
export interface ProductReview {
  readonly rating: number;
  readonly comment: string | null;
  readonly buyerName: string;
  readonly createdAt: string | null;
}

/** Agrégat d'avis d'un produit. */
export interface ProductReviews {
  readonly reviews: readonly ProductReview[];
  readonly ratingAvg: number | null;
  readonly ratingCount: number;
}

/**
 * Vrai si l'acheteur peut déposer un avis.
 *
 * Règle reprise de l'app d'origine : un avis est **vérifié** — il n'est
 * possible qu'après une commande livrée contenant le produit. Le domaine
 * l'exprime pour que l'UI n'affiche pas un formulaire qui serait refusé.
 */
export function canReview(status: string): boolean {
  return status === 'delivered';
}

/** Note valide : entier de 1 à 5. */
export function isValidRating(rating: number): boolean {
  return Number.isInteger(rating) && rating >= 1 && rating <= 5;
}
