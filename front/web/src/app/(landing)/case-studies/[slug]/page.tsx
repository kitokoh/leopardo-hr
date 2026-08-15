import type { Metadata } from 'next';
import { SITE_URL } from '@/lib/site-url';
import { getCaseStudy } from '@/modules/vitrine/lib/case-studies';
import { CaseStudyClient } from './CaseStudyClient';

// #3435 : metadata propres par slug (title/description/canonical) au lieu du
// canonical /case-studies fixe du layout pour les 12 études de cas.
export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const study = getCaseStudy(slug);
  if (!study) return {};

  return {
    title: `${study.title} — Leopardo RH`,
    description: study.description,
    alternates: {
      canonical: `${SITE_URL}/case-studies/${study.slug}`,
    },
    openGraph: {
      title: `${study.title} — Leopardo RH`,
      description: study.description,
      type: 'article',
      url: `${SITE_URL}/case-studies/${study.slug}`,
    },
  };
}

export default function CaseStudyPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  return <CaseStudyClient params={params} />;
}
