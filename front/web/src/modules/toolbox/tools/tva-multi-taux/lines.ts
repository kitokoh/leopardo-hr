import { parseNumber } from '../../lib/validation';
import type { VatLine } from './logic';

/**
 * Lignes d'une facture TVA multi-taux — conversion lignes ↔ état partageable.
 *
 * Le codec de partage (`lib/share.ts`) ne connaît que des paires clé/valeur
 * plates : les lignes sont donc aplaties sous `amount0`, `rate0`, `amount1`, …
 *
 * ## Pourquoi ce module existe (issue #9531)
 *
 * Cette conversion vivait dans le composant. Elle y causait deux défauts réels :
 *
 * 1. **Lignes fantômes.** Supprimer une ligne réécrivait les clés restantes mais
 *    laissait les clés devenues orphelines (`amount1`, `rate1`…). À la relecture,
 *    les clés étaient relues DANS L'ORDRE : la ligne supprimée ressuscitait en
 *    duplicata de la ligne suivante, et le total TTC affiché était faux.
 *    → `linesToState` produit désormais une liste **contiguë** et
 *      `pruneOrphanKeys` dit explicitement quelles clés doivent disparaître.
 * 2. **Plafond silencieux.** `MAX_SHARED_LINES` bornait l'encodage mais pas
 *    l'interface : au-delà, l'URL ne transportait qu'une partie des lignes et un
 *    lien partagé recalculait un total différent, sans avertissement.
 *    → `canAddLine` borne l'ajout à la même constante que l'encodage.
 *
 * Fonctions PURES : aucune dépendance React, aucun accès à `window`. Elles sont
 * testées directement (`lines.test.ts`) sur des cas connus.
 */

/**
 * Nombre maximal de lignes partageables.
 *
 * Cette constante est l'UNIQUE source de vérité : elle borne l'encodage de
 * l'URL *et* l'interface (`canAddLine`). Les désynchroniser est précisément ce
 * qui produisait un partage tronqué sans avertissement.
 */
export const MAX_SHARED_LINES = 10;

/**
 * Motif des clés de ligne dans l'état partageable (`amount0`, `rate7`, …).
 *
 * Sert de **contrat explicite** pour l'hydratation : le formulaire ne porte que
 * 2 lignes par défaut mais jusqu'à `MAX_SHARED_LINES`, et `mergeWithDefaults`
 * n'accepte au-delà des valeurs par défaut que ce motif (`extraKeyPattern`).
 */
export const LINE_KEY_PATTERN = /^(?:amount|rate)\d+$/;

/** Saisies d'une ligne, telles qu'affichées dans le formulaire (chaînes brutes). */
export interface LineState {
  /** Montant HT saisi (chaîne brute, non validée). */
  amount: string;
  /** Taux de TVA saisi en points de pourcentage (chaîne brute, non validée). */
  rate: string;
}

/**
 * Lignes affichées au premier rendu.
 *
 * Valeurs choisies pour couvrir les deux taux français les plus courants
 * (20 % et 5,5 %) : le récapitulatif par taux est ainsi visible immédiatement.
 */
export const INITIAL_LINES: LineState[] = Object.freeze([
  { amount: '1000', rate: '20' },
  { amount: '200', rate: '5.5' },
]) as LineState[];

/** Copie mutable des lignes par défaut (l'état initial ne doit pas être partagé). */
function defaultLines(): LineState[] {
  return INITIAL_LINES.map((line) => ({ ...line }));
}

/**
 * Aplatit des lignes en état partageable (`amount0`, `rate0`, …).
 *
 * Produit une liste **contiguë** : les clés sont numérotées de 0 à n-1, sans
 * trou. Une ligne au-delà de `MAX_SHARED_LINES` est ignorée ici — l'interface
 * l'interdit en amont (`canAddLine`), cette borne reste une défense en
 * profondeur pour un état construit à la main.
 */
export function linesToState(lines: LineState[]): Record<string, string> {
  const state: Record<string, string> = {};
  for (const [index, line] of lines.slice(0, MAX_SHARED_LINES).entries()) {
    state[`amount${index}`] = line.amount;
    state[`rate${index}`] = line.rate;
  }
  return state;
}

/**
 * Reconstitue les lignes depuis un état plat.
 *
 * Relit **uniquement** les indices contigus : dès qu'un `amount<n>` ou `rate<n>`
 * manque, on s'arrête. C'est ce qui rend une clé orpheline inoffensive — elle
 * n'est jamais lue, même si elle traîne encore dans l'URL d'un lien forgé.
 *
 * Retourne les valeurs par défaut si l'état ne contient aucune ligne complète
 * (état vide, lien invalide, version antérieure).
 */
export function stateToLines(state: Record<string, string>): LineState[] {
  const lines: LineState[] = [];
  for (let index = 0; index < MAX_SHARED_LINES; index += 1) {
    const amount = state[`amount${index}`];
    const rate = state[`rate${index}`];
    if (amount === undefined || rate === undefined) break;
    lines.push({ amount, rate });
  }
  return lines.length > 0 ? lines : defaultLines();
}

/**
 * Clés d'état à supprimer pour qu'il ne reste QUE les lignes données.
 *
 * C'est le correctif du défaut des lignes fantômes : après une suppression,
 * l'appelant supprime ces clés au lieu de simplement réécrire les autres.
 * Les clés au-delà du plafond sont incluses — un état hérité d'un lien forgé
 * est ainsi nettoyé au premier rendu.
 */
export function pruneOrphanKeys(
  previous: Record<string, string>,
  lines: LineState[],
): string[] {
  const keep = new Set(Object.keys(linesToState(lines)));
  const orphans: string[] = [];

  for (const key of Object.keys(previous)) {
    const match = /^(?:amount|rate)(\d+)$/.exec(key);
    // Toute clé de ligne au-delà de ce qui est conservé doit partir : cet
    // outil ne possède aucune autre clé de type `amount<n>`/`rate<n>`.
    if (match && !keep.has(key)) orphans.push(key);
  }

  return orphans;
}

/** Vrai si une ligne supplémentaire peut être ajoutée sans tronquer le partage. */
export function canAddLine(lines: LineState[]): boolean {
  return lines.length < MAX_SHARED_LINES;
}

/**
 * Ajoute une ligne vide, dans la limite du plafond de partage.
 *
 * Retourne le tableau **inchangé** (même référence) si le plafond est atteint :
 * l'appelant peut ainsi détecter le refus par identité et afficher un message
 * plutôt que d'ignorer silencieusement le clic.
 *
 * La nouvelle ligne reprend le taux de la dernière ligne quand il est valide :
 * dans une facture, les lignes d'un même taux se suivent presque toujours.
 */
export function addLine(lines: LineState[]): LineState[] {
  if (!canAddLine(lines)) return lines;

  const lastRate = lines.length > 0 ? parseNumber(lines[lines.length - 1].rate) : null;
  const rate = lastRate !== null && lastRate >= 0 ? lines[lines.length - 1].rate : '20';

  return [...lines, { amount: '0', rate }];
}

/**
 * Supprime la ligne à l'index donné.
 *
 * Refuse de vider complètement le formulaire (il doit rester au moins une
 * ligne) et ignore un index hors bornes — retourne alors le tableau inchangé.
 */
export function removeLine(lines: LineState[], index: number): LineState[] {
  if (!Number.isInteger(index) || index < 0 || index >= lines.length) return lines;
  if (lines.length <= 1) return lines;
  return lines.filter((_, position) => position !== index);
}

/**
 * Valide les lignes pour le calcul.
 *
 * Retourne `null` dès qu'une saisie n'est pas un nombre fini : le formulaire
 * affiche alors le message d'entrée invalide plutôt qu'un total trompeur.
 */
export function parseLines(lines: LineState[]): VatLine[] | null {
  const parsed: VatLine[] = [];

  for (const line of lines) {
    const amountHt = parseNumber(line.amount);
    const ratePercent = parseNumber(line.rate);
    if (amountHt === null || ratePercent === null) return null;
    parsed.push({ amountHt, ratePercent });
  }

  return parsed.length > 0 ? parsed : null;
}
