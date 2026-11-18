import PublicPortalLayout from '@/modules/public-portal/components/PublicPortalLayout';

/**
 * Chrome du portail public pour la vitrine `/pharmacies` (#9592).
 *
 * Sans ce layout, un visiteur arrivé sur une fiche officine depuis un moteur de
 * recherche n'aurait aucun lien de retour vers le portail ni vers les autres
 * verticales (impasse de navigation). Même choix que `/boutiques` et `/stay`.
 */
export default PublicPortalLayout;
