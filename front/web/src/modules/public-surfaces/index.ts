/**
 * Vitrines publiques des verticales grand public.
 *
 * ## Périmètre
 *
 * Ce module ne concerne **que** les vitrines publiques des verticales
 * (restaurant, retail, hôtellerie, B2B, billets, pharmacie).
 *
 * Il ne touche pas :
 * - la super administration ni sa vitrine ;
 * - la partie Etna ;
 * - la Vitrine existante (`(public)/`, `vitrine/[slug]`, `modules/vitrine`) ;
 * - le branding d'un tenant — sujet distinct, déjà traité ailleurs.
 *
 * ## Structure
 *
 * ```
 * public-surfaces/
 *   kernel/          contrats partagés (identité, registre, contraste, rendu)
 *   <vertical>/      un bounded context par verticale
 *     domain/        modèle public, pur, zéro E/S
 *     application/   cas d'usage
 *     infrastructure/ adaptateur vers le client public existant
 *     identity/      identité propre (nom, palette, domaine canonique)
 *     components/    rendu pur
 * ```
 */

export * from './kernel';
export { surfaceRegistry } from './registry';
export * from './restaurants';
