import PublicPortalLayout from '@/modules/public-surfaces/portal/components/PublicPortalLayout';

/**
 * Chrome du portail public pour la vitrine `/emploi` (#9657).
 *
 * ## Le trou que ce layout comble
 *
 * `/emploi` est le point d'entrée cross-tenant qui manquait aux candidats
 * (#9629) : sans lui, il fallait connaître le slug d'une entreprise pour voir
 * ses offres. Mais la page était servie **sans en-tête ni pied de page** — un
 * candidat venu d'un moteur de recherche ne pouvait ni changer de verticale, ni
 * changer de langue, ni revenir au portail.
 *
 * Une page d'entrée sans navigation n'est pas une page d'entrée.
 *
 * ## Ce que ce layout ne fait pas
 *
 * Il ne pose ni `<main>` ni `lang`/`dir` : le layout racine s'en charge une
 * seule fois. Il ne fait qu'ajouter le chrome commun, exactement comme les six
 * autres verticales.
 */
export default PublicPortalLayout;
