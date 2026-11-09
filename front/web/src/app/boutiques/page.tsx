import {
  buildDirectoryMetadata,
  buildDirectoryPage,
} from '@/modules/public-portal/components/buildDirectoryPage';

/**
 * Annuaire public des boutiques Leopardo Marché (`/boutiques`).
 *
 * P1-02 avait livré la fiche `/boutiques/{slug}` mais AUCUN index : la
 * verticale Retail n'était donc pas parcourable et ses fiches restaient
 * orphelines pour le crawl. Cette page est le point d'entrée manquant —
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=retail_shop`.
 */

const config = { type: 'retail_shop', basePath: '/boutiques' } as const;

export const generateMetadata = buildDirectoryMetadata(config);
export default buildDirectoryPage(config);
