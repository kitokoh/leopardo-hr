import { getCopy } from '@/lib/i18n';
import { ResetPasswordForm } from './ResetPasswordForm';

export function generateMetadata() {
  // Metadata servie depuis le copy tree (pas de littéral FR en dur — PA2-I18N-014) ;
  // le template racine « %s | Leopardo RH » complète le titre.
  const copy = getCopy('fr');
  return {
    title: copy.passwordReset.title,
    description: copy.passwordReset.subtitle,
  };
}

export default async function ResetPasswordPage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string; email?: string }>;
}) {
  const { token, email } = await searchParams;
  return <ResetPasswordForm token={token ?? ''} email={email ?? ''} />;
}
