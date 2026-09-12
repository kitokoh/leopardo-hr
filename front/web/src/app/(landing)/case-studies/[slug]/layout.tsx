import { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { getCaseStudy } from '@/modules/vitrine/lib/case-studies';

/**
 * #7192 : metadata PROPRE par étude de cas.
 *
 * Le correctif #3435 / PR #3469 avait placé ce `generateMetadata` dans
 * `case-studies/layout.tsx` — le layout du segment **parent**. Un layout ne
 * reçoit les `params` que de son propre segment (et des segments parents) :
 * `params.slug` y était donc toujours `undefined` et les 12 études héritaient
 * des métadonnées du listing (« Etudes de Cas | Success Stories » + canonical
 * `/case-studies`), constaté en live sur `leopardo-prod.vercel.app`.
 *
 * Même correctif que #4611 pour `/blog/[slug]` : un layout dédié au segment
 * dynamique reçoit bien `slug` et applique title/description/canonical propres.
 * Le rendu client de la page reste inchangé (layout serveur, children
 * pass-through).
 */
export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  // #4004 : ?lang= normalisé en en-tête x-vitrine-lang par le middleware
  // (Next 15 ne passe pas searchParams aux generateMetadata des layouts).
  const headerList = await headers();
  const lang = headerList.get('x-vitrine-lang') ?? undefined;
  const study = getCaseStudy(slug);

  if (!study) {
    notFound();
  }

  return generateSEOMetadata({
    title: study.title,
    description: study.description,
    ogType: 'article',
    canonical: `${SITE_URL}/case-studies/${study.slug}`,
    locale: lang,
  });
}

export default function CaseStudyLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
