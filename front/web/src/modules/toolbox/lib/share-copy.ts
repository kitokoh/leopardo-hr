/**
 * Copie du partage — point unique de résolution des libellés.
 *
 * Ce module existe pour une raison précise : la garde `consistency.test.ts`
 * vérifie que CHAQUE clé de `common` est référencée littéralement dans le code
 * du module. Les trois libellés de partage sont consommés par `ShareButton` via
 * un objet dérivé ; sans ce point unique, la garde les verrait comme morts.
 */

import type { ToolboxCopy } from '../data/copy';

/** Sous-ensemble de `common` consommé par le bouton de partage. */
export interface ShareCopy {
  share: string;
  shared: string;
  shareFailed: string;
}

/** Extrait les libellés de partage depuis la copie commune d'une locale. */
export function shareCopy(common: ToolboxCopy['common']): ShareCopy {
  return {
    share: common.share,
    shared: common.shared,
    shareFailed: common.shareFailed,
  };
}
