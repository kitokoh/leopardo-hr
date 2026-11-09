import {
  buildDirectoryMetadata,
  buildDirectoryPage,
} from '@/modules/public-portal/components/buildDirectoryPage';

/**
 * Annuaire public des hébergements HospitalityManager (`/stay`).
 *
 * HOSP-008 avait livré la fiche `/stay/{slug}` mais AUCUN index : la verticale
 * Hospitality n'était donc pas parcourable et ses fiches restaient orphelines
 * pour le crawl. Cette page est le point d'entrée manquant — SSR indexable,
 * alimentée par `GET /api/v1/public/discover?type=hospitality_property`.
 */

const config = { type: 'hospitality_property', basePath: '/stay' } as const;

export const generateMetadata = buildDirectoryMetadata(config);
export default buildDirectoryPage(config);
