/**
 * BOS-A-05 / #8628 — source de vérité VERSIONNÉE de l'état réel des canaux de
 * distribution mobile.
 *
 * Le catalogue « Mon Logiciel » dérivait le statut d'une application de ses
 * cibles DÉCLARÉES (`softwareAvailability()`), jamais de l'état réel du canal.
 * Un job CI `Distribute <app> to Firebase` pouvait donc être rouge en
 * permanence pendant que la fiche affichait « accès pilote » avec un bouton
 * d'installation : la règle d'honnêteté #6697 était contournée par un job CI
 * rouge, et un client cliquait vers un build qui n'existe pas.
 *
 * Ce fichier est la source unique consultée par `softwareAvailability()` et
 * `mobileTargets()`. Il est **alimenté par la CI** : le workflow
 * `mobile-distribution-status-guard.yml` compare son contenu au dernier run de
 * `mobile-distribute-main.yml` et échoue si un canal déclaré `ok` est en
 * réalité en échec (ou l'inverse). Un état ne peut donc pas pourrir en
 * silence — c'est la garde qui rend la déclaration vérifiable.
 *
 * `unknown` est volontairement traité comme NON publiable : on ne sur-promet
 * jamais un canal dont on ne connaît pas l'état (fail-closed, même exigence
 * que `isOfflineClaimed()`).
 */

export type MobileChannelState = 'ok' | 'failed' | 'unknown';

/**
 * État observé du canal de distribution, par slug d'application du catalogue.
 *
 * Provenance : dernier run de `mobile-distribute-main.yml` sur `main`.
 * Dernière observation — run `40677191254` (2026-10-28) :
 *   - `Distribute employee to Firebase`  → success
 *   - `Distribute manager to Firebase`   → success
 *   - `Distribute platform_admin to Firebase` → success
 *   - `Distribute hr to Firebase`        → success
 *
 * Les quatre canaux sont verts : la table est donc VIDE. Un slug absent n'est
 * pas « en échec » (voir `isDistributionFailed`) — c'est exactement la
 * sémantique voulue ici. La garde `mobile-distribution-status-guard.yml`
 * échouait sur une déclaration périmée (`employee`/`manager` encore `failed`
 * alors que le run du 2026-10-28 est vert) : c'est cette déclaration qui était
 * fausse, pas le canal.
 *
 * Seules les apps dont le canal est un build natif distribué (Firebase/store)
 * figurent ici. Les apps servies par un simple lien web ne sont JAMAIS
 * concernées (critère d'acceptation #8628).
 */
export const MOBILE_DISTRIBUTION_STATUS: Record<string, MobileChannelState> = {};

/** Référence du run CI qui a produit l'état ci-dessus (traçabilité). */
export const MOBILE_DISTRIBUTION_STATUS_SOURCE = 'mobile-distribute-main.yml@40677191254';

/**
 * Le canal de distribution de cette application est-il en échec connu ?
 *
 * Un slug absent de la table n'est PAS en échec : il n'a simplement pas de
 * canal natif suivi (lien web, ou app sans distribution publique).
 */
export function isDistributionFailed(slug: string): boolean {
  return MOBILE_DISTRIBUTION_STATUS[slug] === 'failed';
}

/**
 * Le canal est-il d'état INCONNU ? Traité comme non publiable (fail-closed).
 */
export function isDistributionUnknown(slug: string): boolean {
  return MOBILE_DISTRIBUTION_STATUS[slug] === 'unknown';
}

/**
 * Le canal peut-il être annoncé comme disponible (publié ou pilote) ?
 * Faux dès que l'état est `failed` ou `unknown`.
 */
export function isDistributionHealthy(slug: string): boolean {
  return !isDistributionFailed(slug) && !isDistributionUnknown(slug);
}
