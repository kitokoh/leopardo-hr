import { redirect } from 'next/navigation';

/**
 * Index de la section Paramètres.
 *
 * Audit 2026-09-13 : cette page redirigeait vers `/settings/developer` — un
 * écran de développement — alors que le besoin naturel d'un utilisateur qui
 * ouvre « Paramètres » (ou « Mon compte » depuis le menu utilisateur) est son
 * profil et son mot de passe. Redirection vers `/settings/account`.
 */
export default function SectionIndexPage() {
  redirect('/settings/account');
}
