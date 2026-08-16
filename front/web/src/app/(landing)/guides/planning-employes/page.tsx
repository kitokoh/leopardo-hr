import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';
import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export const metadata: Metadata = {
  alternates: { canonical: `${SITE_URL}/guides/planning-employes` },
};

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="planningEmployes"
      downloads={{ pdf: '/downloads/modele-planning-employes.xlsx', signup: '/signup?source=guide-planning-employes', footer: '/signup?source=guide-planning-employes-footer' }}
    />
  );
}
