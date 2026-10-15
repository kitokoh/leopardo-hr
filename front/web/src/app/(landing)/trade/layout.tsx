import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { SITE_URL } from '@/lib/site-url';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

/**
 * #8489 / #8500 — metadonnees du point d'entree « Trade » (/trade).
 *
 * Meme mecanique que `/restaurateur` : la locale vient de l'en-tete
 * `x-vitrine-lang` pose par le middleware (Next 15 ne passe pas searchParams
 * aux generateMetadata des layouts), et les libelles viennent du catalogue
 * partage (`vitrine.trade.meta*`) — aucune chaine en dur, aucune page absente
 * du sitemap.
 */
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const locale = normalizeLocale(headerList.get('x-vitrine-lang') ?? '');

  return generateSEOMetadata({
    title: t(locale, 'vitrine.trade.metaTitle'),
    description: t(locale, 'vitrine.trade.metaDescription'),
    ogType: 'website',
    canonical: `${SITE_URL}/trade`,
    locale,
  });
}

export default function TradeLayout({ children }: { children: React.ReactNode }) {
  return children;
}
