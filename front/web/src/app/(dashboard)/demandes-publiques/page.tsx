import type { Metadata } from 'next';

import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import PublicInboxSpace from './public-inbox-space';

/**
 * #8760 [BOS-CHAT] — Boîte de réception des demandes publiques (Business OS).
 *
 * Étape 9 du parcours cible : « le professionnel traite la demande depuis son
 * Business OS ». Conteneur serveur — l'état (liste, fil, polling) vit dans
 * `PublicInboxSpace`.
 *
 * ⚠️ `robots: noindex` : espace professionnel authentifié. Les titres passent
 * par le catalogue i18n (`publicInbox.*`) : le garde CI PA2-I18N-014 refuse
 * tout nouveau texte visible écrit en littéral dans `src/app/`.
 */
interface PublicInboxPageProps {
  searchParams: Promise<{ lang?: string }>;
}

export async function generateMetadata({ searchParams }: PublicInboxPageProps): Promise<Metadata> {
  const { lang } = await searchParams;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: t(locale, 'publicInbox.pageTitle'),
    alternates: { canonical: `${SITE_URL}/demandes-publiques` },
    robots: { index: false, follow: false },
  };
}

export default async function PublicInboxPage({ searchParams }: PublicInboxPageProps) {
  const params = await searchParams;
  const locale = normalizeLocale(params.lang ?? '');

  return (
    <main className="w-full">
      <PublicInboxSpace />
    </main>
  );
}
