import { t as i18nT } from '@/lib/i18n/locale-catalog';

import { SetPasswordForm } from './SetPasswordForm';

/**
 * #7490 — page publique de définition de mot de passe (lien magique de
 * l'e-mail de bienvenue, `provisioning_token` à usage unique, TTL 72 h).
 */
export function generateMetadata() {
  // Metadata depuis le catalogue (pas de littéral FR en dur — PA2-I18N-014).
  return {
    title: i18nT('fr', 'setPassword.title'),
    description: i18nT('fr', 'setPassword.subtitle'),
  };
}

export default async function SetPasswordPage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string }>;
}) {
  const { token } = await searchParams;
  return <SetPasswordForm tokenFromUrl={token ?? ''} />;
}
