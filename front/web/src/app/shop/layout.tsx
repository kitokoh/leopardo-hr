import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * Métadonnées de la boutique en ligne publique (RESTO-805).
 *
 * AVANT : aucune métadonnée → la page héritait du `canonical` de la **page
 * d'accueil** (vérifié sur le HTML servi le 2026-09-16 : `/shop` déclarait
 * `rel="canonical"` = l'URL racine). Plusieurs pages revendiquaient donc la
 * même URL canonique — du contenu dupliqué qui dilue l'index.
 *
 * ICI : canonical sur la page elle-même, et `noindex, nofollow` car la page
 * n'est atteignable qu'avec un jeton boutique (`?token=`) propre à un client — elle n'a aucun sens pour un robot
 * (l'indexer exposerait en plus le contenu d'un client à un crawl).
 */
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const locale = normalizeLocale(lang ?? '');

  return {
    title: t(locale, 'restaurant.shop.title'),
    alternates: { canonical: `${SITE_URL}/shop` },
    robots: { index: false, follow: false },
  };
}

export default function Layout({ children }: { children: React.ReactNode }) {
  return children;
}
