import { GuidePageContent } from '@/modules/vitrine/components/guides/GuidePageContent';

export default function GuidesPage() {
  return (
    <GuidePageContent
      guide="checklistPaie"
      downloads={{ pdf: '/downloads/checklist-paie.pdf', signup: '/signup?source=guide-checklist-paie', footer: '/signup?source=guide-checklist-paie-footer' }}
    />
  );
}
