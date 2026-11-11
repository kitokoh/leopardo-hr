import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="rhStartup"
      downloads={{ pdf: '/downloads/guide-rh-startup.pdf', signup: '/business/signup?source=guide-rh-startup', footer: '/business/signup?source=guide-rh-startup-footer' }}
    />
  );
}
