import PublicPortalLayout from '@/modules/public-portal/components/PublicPortalLayout';

/**
 * Chrome du portail public pour l'annuaire `/stay`.
 *
 * Sans ce layout, un visiteur arrivé sur une fiche hébergement depuis un moteur
 * de recherche n'avait aucun lien de retour vers le portail ni vers les autres
 * verticales (impasse de navigation).
 */
export default PublicPortalLayout;
