/**
 * Invitation d'un professionnel vers son espace, depuis une vitrine verticale.
 *
 * ## Le problème résolu
 *
 * Les vitrines publiques servent le grand public. Un restaurateur qui visite
 * `/restaurants` n'avait aucun chemin vers la création de son espace, alors que
 * la vitrine principale en dispose depuis toujours (`/business/signup` +
 * entretien de préparation). Il fallait donc un point d'entrée par verticale —
 * mais **sans** reconstruire un tunnel d'inscription.
 *
 * ## Ce que ce module fait — et ne fait pas
 *
 * Il ne décrit **pas** un parcours : il produit un *lien* vers l'espace
 * professionnel existant, avec la verticale pré-renseignée. L'espace tenant
 * reste là où il est ; la vitrine ne fait que l'adresser correctement.
 *
 * Le pré-remplissage réutilise les deux mécanismes déjà en production :
 *
 *  - `?pack=<slug>` → `solutions[]` au provisioning (BC-25 #6693), donc
 *    l'espace est créé AVEC sa verticale ;
 *  - `?sector=<code>` → l'entretien de préparation ne repose pas la question
 *    du métier (#8454 / #7493).
 *
 * ## Pourquoi c'est du domaine et pas du composant
 *
 * La correspondance verticale → (pack, secteur) est une **règle métier** : elle
 * doit être vérifiée par un test, pas décidée dans un `onClick`. Ce fichier est
 * pur — aucun import de Next, React ou client HTTP — donc la garde de couche
 * (`__tests__/layer-guard.test.ts`) l'accepte et il se teste sans réseau.
 */

import type { SurfaceVertical } from './surface-identity';

/** Espace professionnel visé par une verticale. */
export interface TenantInvitation {
  /** Slug de pack attendu par `/business/signup?pack=` (allowlist `pack-signup`). */
  readonly pack: string;
  /** Secteur attendu par `?sector=` (allowlist `SetupInterviewPlanner::QUESTIONS`). */
  readonly sector: string;
}

/**
 * Correspondance verticale publique → espace professionnel.
 *
 * Chaque entrée est **volontairement explicite** : une verticale sans entrée
 * n'obtient pas de CTA (fail-closed), plutôt qu'un lien générique qui
 * créerait un espace sans verticale. Ajouter une vitrine sans compléter cette
 * table fait échouer `tenant-invitation.test.ts`.
 *
 * Notes sur les cas non triviaux :
 *
 *  - `retail` et `trade` partagent le secteur `commerce` : c'est le seul
 *    secteur de l'allowlist serveur qui couvre l'achat-revente. Le `pack`, lui,
 *    diffère — c'est lui qui décide de la verticale réellement activée.
 *  - `travel` (vitrine « Billets ») adresse le pack `agence-de-voyage` : le
 *    professionnel des billets est un opérateur de voyage, et c'est le pack qui
 *    provisionne `travelagency`.
 */
export const TENANT_INVITATIONS: Readonly<Record<SurfaceVertical, TenantInvitation>> = {
  restaurant: { pack: 'restaurant', sector: 'restaurant' },
  retail: { pack: 'retail', sector: 'commerce' },
  hospitality: { pack: 'hospitality', sector: 'hospitality' },
  trade: { pack: 'trade', sector: 'commerce' },
  travel: { pack: 'agence-de-voyage', sector: 'travel' },
  pharmacy: { pack: 'pharmacy', sector: 'pharmacy' },
  // #9622 — l'exploitant VTC est un prestataire de mobilité : `mobility` est
  // un secteur dédié (allowlist serveur `SetupInterviewPlanner::QUESTIONS`),
  // et il active la verticale `vtc` via `SECTOR_SOLUTIONS`. Le pack `vtc`
  // provisionne le même code : les deux chemins mènent au bon espace.
  vtc: { pack: 'vtc', sector: 'mobility' },
};

/** Chemin de l'espace professionnel existant — jamais dupliqué, jamais déplacé. */
export const TENANT_SIGNUP_PATH = '/business/signup';

/**
 * Construit le lien d'inscription professionnelle d'une verticale.
 *
 * Le `?sector=` est ajouté en plus du `?pack=` : le pack décide de la
 * verticale provisionnée, le secteur évite que l'entretien repose la question
 * du métier. Les deux sont nécessaires — n'en envoyer qu'un produit un espace
 * correct mais un entretien qui redemande ce que le visiteur vient de déclarer.
 *
 * `locale` est propagée quand elle est fournie : la vitrine est servie en 4
 * langues et le tunnel d'inscription ne doit pas basculer en français au clic.
 */
export function tenantSignupHref(vertical: SurfaceVertical, locale?: string | null): string {
  const invitation = TENANT_INVITATIONS[vertical];

  const params = new URLSearchParams();
  params.set('pack', invitation.pack);
  params.set('sector', invitation.sector);

  if (locale) {
    params.set('lang', locale);
  }

  return `${TENANT_SIGNUP_PATH}?${params.toString()}`;
}
