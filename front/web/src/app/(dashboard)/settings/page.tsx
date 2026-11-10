'use client';

import { useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import Link from 'next/link';
import {
  Bell,
  Building2,
  CreditCard,
  KeyRound,
  LayoutGrid,
  LockKeyhole,
  Palette,
  Plug,
  Settings,
  ShieldCheck,
  Smartphone,
  Users,
  Wallet,
} from 'lucide-react';

import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, getStoredUser, type AppLocale, type StoredAuthUser } from '@/lib/i18n';
import {
  SETTINGS_HUB_FR,
  buildSettingsHub,
  hiddenSettingsTileCount,
  settingsHubT,
  type SettingsTileKey,
} from '@/lib/i18n/settings-hub';

/**
 * #9330 — hub « Paramètres » de l'espace tenant.
 *
 * Avant : `/settings` était une **redirection sèche** vers `/settings/account`
 * (et `/settings/team` vers `/employees`). La sidebar n'exposait donc aucune
 * entrée « Paramètres », et les réglages du tenant étaient éparpillés sur des
 * routes découvrables seulement en connaissant l'URL : `/settings/encaissements`,
 * `/settings/devices`, `/settings/notifications`, `/settings/branding`,
 * `/billing`, `/modules`, `/employees`.
 *
 * Ce hub ne crée AUCUNE route : il rend des liens vers les écrans existants,
 * groupés en *Mon compte* / *Entreprise* / *Plateforme*. La liste est filtrée
 * par `buildSettingsHub()` (lib/i18n/settings-hub.ts), dont chaque tuile porte
 * la garde EXACTE de sa page cible — le hub ne propose donc jamais un lien qui
 * aboutirait sur un écran « accès réservé ».
 */

const TILE_ICONS: Record<SettingsTileKey, typeof Settings> = {
  account: Settings,
  security: LockKeyhole,
  notifications: Bell,
  devices: Smartphone,
  company: Building2,
  branding: Palette,
  team: Users,
  payments: Wallet,
  modules: LayoutGrid,
  billing: CreditCard,
  integrations: Plug,
};

const GROUP_ICONS: Record<string, typeof Settings> = {
  account: KeyRound,
  company: Building2,
  platform: ShieldCheck,
};

export default function SettingsHubPage() {
  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');
  const [user, setUser] = useState<StoredAuthUser | null>(null);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setUser(getStoredUser());
    setMounted(true);
  }, []);

  const groups = useMemo(() => buildSettingsHub(user), [user]);
  const hidden = useMemo(() => (mounted ? hiddenSettingsTileCount(user) : 0), [mounted, user]);
  const tt = (key: string, fallback: string) => settingsHubT(locale, key, fallback);

  return (
    <ModulePageShell
      title={tt('settingsHub.title', SETTINGS_HUB_FR.title)}
      subtitle={tt('settingsHub.subtitle', SETTINGS_HUB_FR.subtitle)}
      icon={Settings}
    >
      <div className="space-y-6">
        {groups.map((group) => {
          const GroupIcon = GROUP_ICONS[group.key] ?? Settings;
          return (
            <section key={group.key} data-testid={`settings-hub-group-${group.key}`} className="space-y-3">
              <div className="flex items-center gap-2">
                <GroupIcon className="h-4 w-4 text-slate-400" aria-hidden="true" />
                <h2 className="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">
                  {tt(group.titleKey, group.titleFallback)}
                </h2>
              </div>
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {group.tiles.map((tile) => {
                  const Icon = TILE_ICONS[tile.key] ?? Settings;
                  return (
                    <Link
                      key={tile.key}
                      href={tile.href}
                      data-testid={`settings-hub-tile-${tile.key}`}
                      className="group flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/20"
                    >
                      <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-600 transition group-hover:bg-emerald-50 group-hover:text-emerald-700">
                        <Icon className="h-4 w-4" aria-hidden="true" />
                      </span>
                      <span className="min-w-0">
                        <span className="block text-sm font-bold text-slate-950">
                          {tt(tile.titleKey, tile.titleFallback)}
                        </span>
                        <span className="mt-0.5 block text-xs leading-5 text-slate-600">
                          {tt(tile.bodyKey, tile.bodyFallback)}
                        </span>
                      </span>
                    </Link>
                  );
                })}
              </div>
            </section>
          );
        })}

        {mounted && hidden > 0 ? (
          <p
            data-testid="settings-hub-hidden-hint"
            className="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600"
          >
            {tt('settingsHub.notAdminHint', SETTINGS_HUB_FR.notAdminHint)}
          </p>
        ) : null}
      </div>
    </ModulePageShell>
  );
}
