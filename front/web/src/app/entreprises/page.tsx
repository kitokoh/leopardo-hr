import {
  buildDirectoryMetadata,
  buildDirectoryPage,
} from '@/modules/public-portal/components/buildDirectoryPage';

/**
 * Annuaire public des entreprises de commerce international (`/entreprises`).
 *
 * P2-01 avait livré la fiche `/entreprises/{slug}` mais AUCUN index : la
 * verticale Trade n'était donc pas parcourable et ses fiches restaient
 * orphelines pour le crawl. Cette page est le point d'entrée manquant —
 * SSR indexable, alimentée par `GET /api/v1/public/discover?type=trade_company`.
 */

const config = { type: 'trade_company', basePath: '/entreprises' } as const;

export const generateMetadata = buildDirectoryMetadata(config);
export default buildDirectoryPage(config);
