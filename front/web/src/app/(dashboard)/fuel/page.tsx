import { redirect } from 'next/navigation';

/**
 * Index de section manquant : /fuel répondait 404 alors que des sous-routes
 * existent (/fuel/pump). Redirection vers la seule page utile de la section,
 * plutôt qu'un 404 pour une URL saisie à la main.
 */
export default function SectionIndexPage() {
  redirect('/fuel/pump');
}
