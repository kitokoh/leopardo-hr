import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { getCaseStudy } from '@/modules/vitrine/lib/case-studies';

interface CaseStudyLayoutProps {
  params: Promise<{
    slug: string;
  }>;
  children: React.ReactNode;
}

/**
 * Issue #3435 : chaque page /case-studies/[slug] doit avoir ses propres
 * title/description/canonical (avant : héritage du layout parent avec un
 * canonical fixe /case-studies → title/description dupliqués sur 12 pages
 * et canonical pointant vers la mauvaise URL).
 */
export async function generateMetadata({
  params,
}: CaseStudyLayoutProps): Promise<Metadata> {
  const { slug } = await params;
  const study = getCaseStudy(slug);

  if (!study) {
    return {
      title: 'Cas client non trouvé',
      description: "L'étude de cas que vous recherchez n'existe pas.",
    };
  }

  return generateSEOMetadata({
    title: study.title,
    description: study.description,
    ogType: 'website',
    canonical: `${SITE_URL}/case-studies/${study.slug}`,
  });
}

export default function CaseStudyLayout({ children }: CaseStudyLayoutProps) {
  return children;
}
