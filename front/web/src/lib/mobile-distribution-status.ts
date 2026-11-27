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
export const MOBILE_DISTRIBUTION_STATUS: Record<string, MobileChannelState> = {
  // Observation du run `44523068667` (2026-11-19T12:21) : `employee`,
  // `manager`, `platform_admin` et `hr` sont VERTS ; seul `accounting` est en
  // échec, à l'étape « Resolve Firebase distribution readiness » — le
  // `google-services.json` restauré ne déclare aucun client Android pour
  // `com.leopardo.accounting` (#9492). La table déclarait encore les quatre
  // canaux verts comme `failed` sur la base du run `42762379168`
  // (2026-11-10) : c'est cette déclaration qui était devenue fausse, pas les
  // canaux — la garde `mobile-distribution-status-guard.yml` le signalait.
  //
  // Conséquence assumée : `isDistributionHealthy()` rend `false` pour
  // `accounting-mobile` uniquement, donc le catalogue le requalifie en
  // `not_distributed` et n'affiche plus de bouton d'installation vers un
  // build inexistant (règle d'honnêteté #6697). Les quatre autres canaux
  // étant réellement verts, leurs fiches peuvent de nouveau annoncer l'app.
  //
  // `unknown` et non `failed` : le canal n'est pas cassé, il n'EXISTE pas
  // encore — l'app Android n'est pas enregistrée dans le projet Firebase
  // (#9492). Le workflow sort en skip explicite pour ce canal, donc un
  // `failed` mentirait sur la nature du problème. `unknown` reste
  // fail-closed : non publiable, exactement comme `failed`.
  'accounting-mobile': 'unknown',
  // Observation du run `45984755280` (2026-11-27T10:45) : les ONZE canaux sont
  // VERTS, `hr` compris (« Distribute hr to Firebase » → success). La table
  // déclarait encore `hr-mobile` en `failed` sur la base d'un échec transitoire
  // du même run (conclusion `failure` relevée à 10:47:55) : c'est cette
  // déclaration qui était devenue fausse, pas le canal — la garde
  // `mobile-distribution-status-guard.yml` le signalait (« canal VERT en CI
  // mais déclaré failed »).
  //
  // Conséquence : `isDistributionHealthy()` rend de nouveau `true` pour
  // `hr-mobile`, donc le catalogue peut annoncer l'app. La règle d'honnêteté
  // #6697 reste respectée : le canal est réellement vert, vérifié sur le run
  // de référence.
};

/** Référence du run CI qui a produit l'état ci-dessus (traçabilité). */
export const MOBILE_DISTRIBUTION_STATUS_SOURCE = 'mobile-distribute-main.yml@45984755280';

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
