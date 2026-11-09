import {
  buildDirectoryMetadata,
  buildDirectoryPage,
} from '@/modules/public-portal/components/buildDirectoryPage';

/**
 * Annuaire public des opérateurs VTC / taxis (`/operateurs`).
 *
 * P1-02 avait livré la fiche `/operateurs/{slug}` mais AUCUN index : la
 * verticale VTC n'était donc pas parcourable et ses fiches restaient
 * orphelines pour le crawl. Cette page est le point d'entrée manquant —
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=vtc_operator`.
 */

const config = { type: 'vtc_operator', basePath: '/operateurs' } as const;

export const generateMetadata = buildDirectoryMetadata(config);
export default buildDirectoryPage(config);
