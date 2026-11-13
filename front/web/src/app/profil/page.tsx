import type { Metadata } from 'next';
import { normalizeLocale } from '@/lib/i18n';
import { getPublicAccountCopy } from '@/modules/public-portal/data/account-copy';
import ProfileView from './profile-view';

/**
 * COULOIR 16 — M3-bis · Onglet 4 « Profil » (`/profil`).
 *
 * ## Pourquoi cette page existe
 *
 * `PublicBottomNav` renvoie vers `/profil` depuis M1, mais la route n'existait
 * pas : **lien mort** (404). Les routes API étaient livrées (`/user/me`,
 * `/user/public-profile`, `/user/data-export`, `/user/logout-all`).
 *
 * ## Ce que la page règle vraiment
 *
 * La **langue**. Jusqu'ici elle était écrite dans le stockage local par la PWA
 * et par l'app Flutter : elle suivait l'APPAREIL. Un client qui change de
 * téléphone perdait sa langue. La page écrit `preferred_locale` sur le COMPTE
 * (`public_profiles`), donc la préférence suit la personne.
 *
 * ## Rendu client
 *
 * Données personnelles dépendant d'un jeton : jamais de SSR mis en cache
 * partagé. `robots: noindex` pour la même raison.
 */

interface ProfilePageProps {
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ searchParams }: ProfilePageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const copy = getPublicAccountCopy(normalizeLocale(lang));

  return {
    title: copy.meta.profileTitle,
    description: copy.meta.profileDescription,
    robots: { index: false, follow: false },
  };
}

export default async function ProfilePage({ searchParams }: ProfilePageProps) {
  const { lang } = await searchParams;

  return <ProfileView locale={normalizeLocale(lang)} />;
}
