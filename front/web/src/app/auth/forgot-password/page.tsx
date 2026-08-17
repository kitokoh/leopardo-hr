import { ForgotPasswordForm } from './ForgotPasswordForm';

export const metadata = {
  title: 'Mot de passe oublié — Leopardo RH',
  description:
    'Réinitialisez votre mot de passe Leopardo RH : recevez un lien sécurisé par email.',
};

export default function ForgotPasswordPage() {
  return <ForgotPasswordForm />;
}
