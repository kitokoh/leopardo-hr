'use client';

import { useSyncExternalStore } from 'react';
import { Sparkles } from 'lucide-react';

import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import { AssistantPanel } from '@/modules/assistant/components/AssistantPanel';

const emptySubscribe = () => () => {};

/**
 * BOS-035 (#8224) — page « Assistant Leo IA » du portail tenant : chat,
 * affichage des tools utilisés, cartes de confirmation des actions en
 * attente et historique des conversations. Le front n'a aucune logique de
 * permission : il affiche uniquement ce que l'API `/ai/*` retourne.
 */
export default function AssistantPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');

  return (
    <ModulePageShell
      title={i18nT(locale, 'assistant.title')}
      subtitle={i18nT(locale, 'assistant.subtitle')}
      icon={Sparkles}
    >
      <AssistantPanel />
    </ModulePageShell>
  );
}
