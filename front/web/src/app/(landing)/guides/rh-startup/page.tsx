import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="rhStartup"
      downloads={{ pdf: '/downloads/guide-rh-startup.pdf', signup: '/signup?source=guide-rh-startup', footer: '/signup?source=guide-rh-startup-footer' }}
    />
  );
}
