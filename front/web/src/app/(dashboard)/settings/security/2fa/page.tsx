'use client';

/**
 * Issue #5612 / #7861 — Page dédiée de gestion 2FA.
 *
 * La logique (statut, enrôlement QR, confirmation TOTP, codes de récupération,
 * désactivation) est factorisée dans `TwoFactorPanel`, également intégrée à
 * « Mon compte » (/settings/account). Cette route reste fonctionnelle pour les
 * liens existants et rend le même composant partagé.
 *
 * Références backend : TwoFactorAuthController (#5436).
 */

import { useSyncExternalStore } from 'react';
import { ShieldCheck } from 'lucide-react';

import { ModulePageShell } from '@/components/module-page-shell';
import { TwoFactorPanel } from '@/components/settings/two-factor-panel';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';

const emptySubscribe = () => () => {};

export default function TwoFactorSettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');

  return (
    <ModulePageShell
      title={i18nT(locale, 'settingsPage.twoFactorTitle')}
      subtitle={i18nT(locale, 'settingsPage.tenantSubtitle')}
      icon={ShieldCheck}
      accentClassName="from-brand-600 to-emerald-600"
    >
      <div className="max-w-xl">
        <TwoFactorPanel locale={locale} />
      </div>
    </ModulePageShell>
  );
}
