/**
 * Copie de l'export — point unique de résolution des libellés.
 *
 * Même raison d'être que `share-copy.ts` : la garde `consistency.test.ts`
 * vérifie que CHAQUE clé de `common` est référencée littéralement dans le code
 * du module. Les trois libellés d'export sont consommés par `ResultPanel` via
 * un objet dérivé ; sans ce point unique, la garde les verrait comme morts.
 */

import type { ToolboxCopy } from '../data/copy';

/** Sous-ensemble de `common` consommé par le bouton d'export. */
export interface ExportCopy {
  export: string;
  exported: string;
  exportFailed: string;
}

/** Extrait les libellés d'export depuis la copie commune d'une locale. */
export function exportCopy(common: ToolboxCopy['common']): ExportCopy {
  return {
    export: common.export,
    exported: common.exported,
    exportFailed: common.exportFailed,
  };
}
