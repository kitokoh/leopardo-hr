import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="planningEmployes"
      downloads={{ pdf: '/downloads/modele-planning-employes.xlsx', signup: '/signup?source=guide-planning-employes', footer: '/signup?source=guide-planning-employes-footer' }}
    />
  );
}
