import PublicPortalLayout from '@/modules/public-surfaces/portal/components/PublicPortalLayout';

/**
 * Chrome du portail public pour la vitrine `/billets` (#9657).
 *
 * ## Le trou que ce layout comble
 *
 * Les six autres verticales (`/restaurants`, `/boutiques`, `/stay`,
 * `/entreprises`, `/pharmacies`, `/operateurs`) portaient déjà ce layout ; la
 * verticale Billets et la verticale Emploi ne l'avaient pas. Un visiteur arrivé
 * sur `/billets` depuis un moteur de recherche n'avait donc **aucun** moyen de
 * changer de verticale, de changer de langue, de basculer le thème ou de
 * revenir au portail autrement qu'avec le bouton « précédent » du navigateur.
 *
 * C'est une impasse de navigation, pas un détail cosmétique : la vitrine est
 * censée être un point d'entrée, et une page d'entrée sans en-tête n'en est pas
 * un.
 *
 * ## Ce que ce layout ne fait pas
 *
 * Il ne pose ni `<main>` ni `lang`/`dir` : le layout racine s'en charge une
 * seule fois. Il ne fait qu'ajouter le chrome commun, exactement comme les six
 * autres verticales.
 */
export default PublicPortalLayout;
