import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { getPublicAccountCopy } from '@/modules/public-portal/data/account-copy';
import ActivitiesView from './activities-view';

/**
 * COULOIR 16 — M3-bis · Onglet 3 « Activités » (`/activites`).
 *
 * ## Pourquoi cette page existe
 *
 * `PublicBottomNav` renvoie vers `/activites` depuis M1, mais la route
 * n'existait pas : c'était un **lien mort** (404). Les routes API qu'elle
 * consomme étaient pourtant déjà livrées (`/user/activities`,
 * `/user/favorites`, `/user/search-history`).
 *
 * ## Ce que la page affiche, et ce qu'elle n'affiche pas
 *
 * Elle affiche des **références** : identifiant, type, statut, date. Jamais de
 * contenu métier — l'index `public_activity_index` n'en stocke pas, et la page
 * ne va pas le chercher dans un tenant (règle d'architecture n°1).
 *
 * ## Rendu client, pas SSR
 *
 * Les données sont celles du client connecté : elles dépendent d'un jeton, donc
 * d'une session. Un rendu serveur les mettrait en cache partagé — exactement ce
 * qu'il ne faut pas pour des données personnelles. La page est donc un composant
 * client, et le premier rendu est un squelette (pas un écran vide).
 */

interface ActivitiesPageProps {
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ searchParams }: ActivitiesPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const copy = getPublicAccountCopy(normalizeLocale(lang));

  return {
    title: copy.meta.activitiesTitle,
    description: copy.meta.activitiesDescription,
    // Données personnelles : jamais indexées.
    robots: { index: false, follow: false },
  };
}

export default async function ActivitiesPage({ searchParams }: ActivitiesPageProps) {
  const { lang } = await searchParams;

  return <ActivitiesView locale={normalizeLocale(lang)} />;
}
