import { ResetPasswordForm } from './ResetPasswordForm';

export const metadata = {
  title: 'Réinitialiser le mot de passe — Leopardo RH',
  description: 'Choisissez un nouveau mot de passe pour votre compte Leopardo RH.',
};

export default async function ResetPasswordPage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string; email?: string }>;
}) {
  const { token, email } = await searchParams;
  return <ResetPasswordForm token={token ?? ''} email={email ?? ''} />;
}
