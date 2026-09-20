'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { Banknote, Check, ChevronsUpDown, Globe, LifeBuoy, LogOut, Paintbrush, User, UserCircle } from 'lucide-react';
import { t as i18nT } from '@/lib/i18n/locale-catalog';
import { paymentProfilesT } from '@/lib/i18n/payment-profiles';
import { supportTicketsT } from '@/lib/i18n/support-tickets';
import { getDisplayName, type AppLocale, type CopyTree, type StoredAuthUser } from '@/lib/i18n';
import { usePanelDismiss } from '@/components/layout/panel-dismiss';

/**
 * #7908 — bloc « Mon compte » du pied de la sidebar unifiée.
 *
 * Remplace le menu avatar de l'ancienne topbar (`user-menu-toggle` / `user-menu`
 * / `user-menu-logout` : data-testid conservés, les parcours e2e existants
 * restent valides) et la section compte dupliquée du tiroir mobile
 * (`dashboard-drawer-account`, supprimée).
 *
 * Le menu s'ouvre VERS LE HAUT (le bloc vit en bas de colonne) et porte :
 * Mon compte, Encaissements, Image de marque, Support, un sous-menu Langue
 * (fr/en/tr/ar — même logique `handleLanguageChange` qu'avant : PATCH
 * /auth/language côté layout) et la déconnexion.
 */

/** Choix de langue : noms natifs (données techniques, identiques à l'ancien <select>). */
const LANGUAGE_OPTIONS: ReadonlyArray<{ code: AppLocale; nativeName: string }> = [
  { code: 'fr', nativeName: 'Français' },
  { code: 'en', nativeName: 'English' },
  { code: 'tr', nativeName: 'Türkçe' },
  { code: 'ar', nativeName: 'العربية' },
];

const MENU_ITEM_CLASS =
  'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40';

export function AccountMenu({
  user,
  labels,
  locale,
  onLanguageChange,
  onLogout,
  onNavigate,
}: {
  user: StoredAuthUser | null;
  labels: CopyTree;
  locale: AppLocale;
  onLanguageChange: (value: string) => Promise<void> | void;
  onLogout: () => void;
  onNavigate?: () => void;
}) {
  const [open, setOpen] = useState(false);
  const [languageOpen, setLanguageOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement | null>(null);

  const close = () => {
    setOpen(false);
    setLanguageOpen(false);
  };

  // Échap referme le menu ; le scroll du document est verrouillé tant qu'il
  // est ouvert (même contrat que les panneaux de la barre, #7556).
  usePanelDismiss(open, close);

  // Clic extérieur : le menu vit dans la sidebar (pas de voile dédié).
  useEffect(() => {
    if (!open) {
      return;
    }

    const onPointerDown = (event: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        close();
      }
    };

    document.addEventListener('mousedown', onPointerDown);
    return () => document.removeEventListener('mousedown', onPointerDown);
  }, [open]);

  const displayName = getDisplayName(user);

  const handleItemNavigate = () => {
    close();
    onNavigate?.();
  };

  return (
    <div ref={rootRef} className="relative shrink-0 border-t border-slate-200/50 p-3">
      <button
        type="button"
        onClick={() => {
          if (open) {
            close();
          } else {
            setOpen(true);
          }
        }}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-controls="dashboard-user-menu"
        aria-label={labels.dashboard.userMenuAccount}
        title={displayName}
        data-testid="user-menu-toggle"
        className="flex w-full items-center gap-3 rounded-2xl border border-transparent px-2.5 py-2 text-start transition hover:border-slate-200 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
      >
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-gradient-to-br from-slate-100 to-slate-200 text-slate-600">
          {/* #7860 — icône de compte universelle à la place des initiales. */}
          <User className="h-5 w-5" aria-hidden="true" />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-black text-slate-900">{displayName}</span>
          <span className="block truncate text-xs text-slate-500">{user?.email}</span>
        </span>
        <ChevronsUpDown className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
      </button>
      {open ? (
        <div
          role="menu"
          id="dashboard-user-menu"
          data-testid="user-menu"
          className="absolute bottom-full start-3 end-3 z-30 mb-2 max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl"
        >
          <div className="border-b border-slate-100 px-4 py-3">
            <p className="truncate text-sm font-black text-slate-900">{displayName}</p>
            <p className="truncate text-xs text-slate-500">{user?.email}</p>
          </div>
          <div className="p-1.5">
            <Link
              href="/settings/account"
              role="menuitem"
              onClick={handleItemNavigate}
              className={MENU_ITEM_CLASS}
            >
              <UserCircle className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {labels.dashboard.userMenuAccount}
            </Link>
            {/* #7727 — encaissements : profils de paiement du tenant (principal). */}
            <Link
              href="/settings/encaissements"
              role="menuitem"
              onClick={handleItemNavigate}
              className={MENU_ITEM_CLASS}
            >
              <Banknote className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {paymentProfilesT(locale, 'menuLabel')}
            </Link>
            {/* #7713 — image de marque du tenant. */}
            <Link
              href="/settings/branding"
              role="menuitem"
              onClick={handleItemNavigate}
              className={MENU_ITEM_CLASS}
            >
              <Paintbrush className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {i18nT(locale, 'brandingPage.title')}
            </Link>
            {/* #7759 — tickets support côté client. */}
            <Link
              href="/support"
              role="menuitem"
              onClick={handleItemNavigate}
              data-testid="user-menu-support"
              className={MENU_ITEM_CLASS}
            >
              <LifeBuoy className="h-4 w-4 text-slate-400" aria-hidden="true" />
              {supportTicketsT(locale, 'menu_label')}
            </Link>
          </div>
          {/* #7908 — la langue quitte la topbar : sous-menu du bloc compte,
              même logique `handleLanguageChange` (PATCH /auth/language,
              storeAuthSession, applyDocumentLocale/RTL côté layout). */}
          <div className="border-t border-slate-100 p-1.5">
            <button
              type="button"
              role="menuitem"
              aria-expanded={languageOpen}
              aria-haspopup="true"
              aria-controls="dashboard-user-menu-language"
              data-testid="user-menu-language-toggle"
              onClick={() => setLanguageOpen((value) => !value)}
              className={`w-full ${MENU_ITEM_CLASS}`}
            >
              <Globe className="h-4 w-4 text-slate-400" aria-hidden="true" />
              <span className="flex-1 text-start">{labels.dashboard.language}</span>
              <span className="text-xs font-bold uppercase text-slate-400">{locale}</span>
            </button>
            {languageOpen ? (
              <div id="dashboard-user-menu-language" data-testid="user-menu-language-panel" className="mt-1 space-y-0.5 ps-4">
                {LANGUAGE_OPTIONS.map((option) => {
                  const current = option.code === locale;
                  return (
                    <button
                      key={option.code}
                      type="button"
                      role="menuitemradio"
                      aria-checked={current}
                      lang={option.code}
                      data-testid={`user-menu-language-${option.code}`}
                      onClick={() => {
                        void onLanguageChange(option.code);
                        close();
                      }}
                      className="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-1.5 text-start text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                    >
                      {option.nativeName}
                      {current ? <Check className="h-4 w-4 text-emerald-600" aria-hidden="true" /> : null}
                    </button>
                  );
                })}
              </div>
            ) : null}
          </div>
          <div className="border-t border-slate-100 p-1.5">
            <button
              type="button"
              role="menuitem"
              onClick={() => {
                close();
                onLogout();
              }}
              data-testid="user-menu-logout"
              className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40"
            >
              <LogOut className="h-4 w-4" aria-hidden="true" />
              {labels.dashboard.logout}
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
