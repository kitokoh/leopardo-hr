import { redirect } from 'next/navigation';

/**
 * #7862 — « Collaborateurs et rôles » est fusionné dans la page Équipe.
 *
 * Cette page (#7555/#7762) et `/employees` listaient toutes deux les employés :
 * doublon déroutant pour le client. Toute la gestion d'équipe (invitations,
 * rôles, modules délégués, archivage, accès ressources) vit désormais sur
 * `/employees` ; la route est conservée pour compatibilité (liens du menu,
 * favoris, deep-links) et redirige côté serveur.
 */
export default function TeamSettingsPage() {
  redirect('/employees');
}
