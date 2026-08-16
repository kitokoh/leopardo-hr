import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';
import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export const metadata: Metadata = {
  alternates: { canonical: `${SITE_URL}/guides/checklist-paie` },
};

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="checklistPaie"
      downloads={{ pdf: '/downloads/checklist-paie.pdf', signup: '/signup?source=guide-checklist-paie', footer: '/signup?source=guide-checklist-paie-footer' }}
    />
  );
}
