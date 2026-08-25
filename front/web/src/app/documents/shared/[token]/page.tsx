import type { Metadata } from 'next';
import { cache } from 'react';
import { headers } from 'next/headers';

import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

import { SharedDocumentView } from './shared-document-view';

/**
 * Portail client des documents comptables partagés (issue #5233).
 *
 * Route PUBLIQUE : le token de partage (64 caractères opaques, généré côté
 * backend #5225) est la credential — pas de session, pas de gate middleware.
 * La locale SSR vient de l'en-tête `x-vitrine-lang` posé par le middleware
 * pour le préfixe `/documents` (source unique #3377/#4004).
 */

const getSsrLocale = cache(async (): Promise<AppLocale> => {
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang');
  return (lang === 'fr' || lang === 'en' || lang === 'tr' || lang === 'ar' ? lang : 'fr') as AppLocale;
});

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getSsrLocale();

  return {
    title: t(locale, 'accountingPortal.title'),
    description: t(locale, 'accountingPortal.subtitle'),
    robots: {
      // URL tokenisées — jamais indexables (RGPD, accès limité au destinataire).
      index: false,
      follow: false,
    },
  };
}

export default async function SharedDocumentPage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = await params;
  const locale = await getSsrLocale();

  return <SharedDocumentView token={token} locale={locale} />;
}
