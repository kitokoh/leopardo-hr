'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Compass, Home, MessageCircle, User } from 'lucide-react';
import type { PublicHomeCopy } from '../data/home-copy';

/**
 * Couloir 16 — M1 (#9372, point I) : navigation basse mobile à 4 onglets.
 *
 * ## Pourquoi seulement 4 onglets
 *
 * Le cahier des charges impose exactement : Accueil, Messages, Activités,
 * Profil. Ce ne sont pas des verticales — ce sont les quatre destinations du
 * client. Les annuaires (`/restaurants`, `/boutiques`…) restent accessibles
 * depuis l'accueil et l'en-tête : les mettre ici ferait de la barre basse un
 * menu de catégories, ce qu'elle n'est pas.
 *
 * ## Règle de vérité (aucune destination inventée)
 *
 * Chaque onglet pointe vers une route qui EXISTE réellement :
 *  - Accueil    → `/`
 *  - Messages   → `/conversations` (espace client public, #8760)
 *  - Activités  → `/activites` (M3, index de références `public`)
 *  - Profil     → `/profil` (M3, profil public du schéma `public`)
 *
 * Un onglet dont la route n'existe pas encore n'est PAS rendu : afficher un
 * lien mort serait pire que l'absence de l'onglet. `availableTabs` est donc
 * piloté par l'appelant, qui sait ce qui est réellement servi.
 *
 * ## Accessibilité (AA)
 *
 * - `<nav aria-label>` : repère de navigation identifiable.
 * - `aria-current="page"` sur l'onglet actif.
 * - Chaque cible fait au moins 44 px de haut (`min-h-[44px]`).
 * - Le compteur de non-lus porte un libellé accessible complet, pas juste un
 *   nombre : un lecteur d'écran annonce « 3 conversation(s) non lue(s) ».
 * - `pb-[env(safe-area-inset-bottom)]` : la barre ne passe pas sous la barre
 *   gestuelle iOS.
 *
 * ## RTL
 *
 * Aucune marge directionnelle codée en dur (`ml-`/`mr-`) : uniquement
 * `gap` et `justify-between`, qui se miroitent d'eux-mêmes en `dir="rtl"`.
 */

export type PublicTabKey = 'home' | 'messages' | 'activities' | 'profile';

interface PublicBottomNavProps {
  copy: PublicHomeCopy['tabs'];
  /** Nombre de conversations non lues (0 ⇒ pas de pastille). */
  unreadCount?: number;
  /**
   * Onglets réellement servis. Par défaut les quatre : l'appelant doit
   * restreindre cette liste si une route n'est pas encore livrée.
   */
  availableTabs?: PublicTabKey[];
}

const TAB_ORDER: PublicTabKey[] = ['home', 'messages', 'activities', 'profile'];

const TAB_HREF: Record<PublicTabKey, string> = {
  home: '/',
  messages: '/conversations',
  activities: '/activites',
  profile: '/profil',
};

const TAB_ICON: Record<PublicTabKey, typeof Home> = {
  home: Home,
  messages: MessageCircle,
  activities: Compass,
  profile: User,
};

export function PublicBottomNav({
  copy,
  unreadCount = 0,
  availableTabs = TAB_ORDER,
}: PublicBottomNavProps) {
  const pathname = usePathname();

  const tabs = TAB_ORDER.filter((key) => availableTabs.includes(key));

  if (tabs.length === 0) {
    return null;
  }

  const isActive = (href: string): boolean =>
    href === '/' ? pathname === '/' : pathname === href || pathname.startsWith(`${href}/`);

  return (
    <nav
      aria-label={copy.home}
      className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl md:hidden dark:border-white/10 dark:bg-slate-950/95"
    >
      <ul className="mx-auto flex max-w-6xl items-stretch justify-between px-2">
        {tabs.map((key) => {
          const href = TAB_HREF[key];
          const Icon = TAB_ICON[key];
          const active = isActive(href);
          const showBadge = key === 'messages' && unreadCount > 0;

          return (
            <li key={key} className="flex-1">
              <Link
                href={href}
                aria-current={active ? 'page' : undefined}
                className={`flex min-h-[44px] flex-col items-center justify-center gap-0.5 px-1 py-2 text-[11px] font-semibold transition-colors ${
                  active
                    ? 'text-emerald-700 dark:text-emerald-400'
                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
                }`}
              >
                <span className="relative">
                  <Icon className="h-5 w-5" aria-hidden />
                  {showBadge && (
                    <span
                      aria-hidden
                      className="absolute -top-1.5 -right-2 inline-flex min-w-[16px] items-center justify-center rounded-full bg-emerald-600 px-1 text-[10px] font-bold leading-4 text-white"
                    >
                      {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                  )}
                </span>
                <span>{copy[key]}</span>
                {showBadge && (
                  <span className="sr-only">
                    {unreadCount} {copy.unread}
                  </span>
                )}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
