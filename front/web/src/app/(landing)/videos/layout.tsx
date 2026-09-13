import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { generateMetadata as generateSEOMetadata, getPageMetadata } from '@/modules/vitrine/lib/seo';
import { normalizeLocale, type AppLocale } from '@/lib/i18n';
import { videosPageCopy } from '@/modules/vitrine/data/videos';
import { VideoObjectJsonLd } from '@/components/JsonLd';

async function resolveLocale(): Promise<AppLocale> {
  // #4004 : ?lang= normalisé par le middleware en en-tête x-vitrine-lang
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  return normalizeLocale(headerList.get('x-vitrine-lang'));
}

export async function generateMetadata(): Promise<Metadata> {
  const lang = await resolveLocale();
  const seo = getPageMetadata('videos', lang);
  return generateSEOMetadata({
    title: seo.title,
    description: seo.description,
    keywords: seo.keywords,
    ogImage: seo.ogImage,
    ogType: 'website',
    canonical: `${SITE_URL}/videos`,
    locale: lang,
  });
}

export default async function VideosLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // #AI-SEO : la page /videos est un composant client — le balisage
  // VideoObject est donc émis ici, côté serveur, pour être présent dans le
  // HTML initial (les crawlers IA n'exécutent pas JavaScript).
  const lang = await resolveLocale();
  const demo = videosPageCopy[lang]?.demo ?? videosPageCopy.fr.demo;

  return (
    <>
      <VideoObjectJsonLd name={demo.title} description={demo.description} locale={lang} />
      {children}
    </>
  );
}
