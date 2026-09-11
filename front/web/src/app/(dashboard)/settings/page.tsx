import { redirect } from 'next/navigation';

/**
 * Index de section manquant : /settings répondait 404 alors que des sous-routes
 * existent (/settings/developer). Redirection vers la seule page utile de la section,
 * plutôt qu'un 404 pour une URL saisie à la main.
 */
export default function SectionIndexPage() {
  redirect('/settings/developer');
}
