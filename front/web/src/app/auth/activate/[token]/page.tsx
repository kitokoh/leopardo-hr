import { getCopy } from '@/lib/i18n';
import { ActivateAccountForm } from './ActivateAccountForm';

export function generateMetadata() {
  // Metadata servie depuis le copy tree (pas de littéral FR en dur — PA2-I18N-014) ;
  // le template racine « %s | Leopardo RH » complète le titre.
  const copy = getCopy('fr');
  return {
    title: copy.accountActivation.title,
    description: copy.accountActivation.subtitle,
  };
}

export default async function ActivateAccountPage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = await params;
  return <ActivateAccountForm token={token ?? ''} />;
}
