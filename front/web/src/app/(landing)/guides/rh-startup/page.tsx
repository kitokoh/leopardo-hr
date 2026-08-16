import { SITE_URL } from '@/lib/site-url';
import type { Metadata } from 'next';
import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export const metadata: Metadata = {
  alternates: { canonical: `${SITE_URL}/guides/rh-startup` },
};

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="rhStartup"
      downloads={{ pdf: '/downloads/guide-rh-startup.pdf', signup: '/signup?source=guide-rh-startup', footer: '/signup?source=guide-rh-startup-footer' }}
    />
  );
}
