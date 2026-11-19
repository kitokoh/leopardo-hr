/**
 * Pont de copie — libellés du plan de sièges (verticale Billets).
 *
 * ## Pourquoi ce fichier existe
 *
 * `travel/domain/seat-layout.ts` importait directement
 * `SEAT_STATUS_LABELS` depuis `./retail-copy`. Le domaine dépendait donc du
 * catalogue de copie : une correction éditoriale pouvait changer un
 * comportement du domaine, et la règle de couche n'était pas opposable
 * (#9653).
 *
 * Le domaine ne produit désormais **aucun** texte visible : il reçoit un
 * `SeatLabels` en paramètre. Ce fichier est le seul endroit qui fait le lien
 * entre le catalogue (`retail-copy.ts`) et le type du domaine.
 *
 * ## Pourquoi pas de texte ici
 *
 * Les libellés restent dans `retail-copy.ts` : c'est l'exemption de
 * `check-i18n-diff.js` (`/public-surfaces/copy/`), donc le seul endroit du
 * module où une chaîne visible peut vivre. Ce fichier ne fait que **typer** et
 * **assembler** — il n'ajoute pas une seule chaîne.
 *
 * ## Le littéral est total, exprès
 *
 * `statuses` est annoté `SeatLabels['statuses']`, dont les clés sont
 * `SeatStatusValue | 'aisle' | 'absent'`. Ajouter un état au domaine sans lui
 * donner de libellé **ne compile pas** : la garantie d'exhaustivité qu'apportait
 * le cast en place dans le domaine est conservée, et devient vérifiée par le
 * compilateur au lieu d'être affirmée par un `as`.
 */

import type { SeatLabels } from '../travel/domain/seat-layout';

import { SEAT_STATUS_LABELS } from './retail-copy';

/**
 * Libellés du plan de sièges.
 *
 * `ariaTemplate` porte la forme complète lue par un lecteur d'écran. Elle est
 * ici, et non dans le domaine, pour la même raison que les libellés : c'est du
 * texte visible.
 */
export const SEAT_LABELS: SeatLabels = {
  statuses: SEAT_STATUS_LABELS as SeatLabels['statuses'],
  ariaTemplate: 'Siège {seat}, {status}',
};
