'use client';

import { useConsent } from '@/modules/vitrine/components/ConsentProvider';
import { t } from '@/lib/i18n/locale-catalog';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

/**
 * Contrôle de réouverture du panneau de consentement (issue #7593).
 * Un consentement doit pouvoir être **retiré aussi facilement qu'il est donné** :
 * ce bouton vit dans le pied de page, sur toutes les pages publiques.
 */
export function CookieSettingsButton({ className }: { className?: string }) {
  const { openPreferences } = useConsent();
  const { locale } = useVitrineLocale();

  return (
    <button type="button" onClick={openPreferences} className={className}>
      {t(locale, 'consent.manage')}
    </button>
  );
}
