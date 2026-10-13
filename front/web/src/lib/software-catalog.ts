import type { LucideIcon } from 'lucide-react';
import {
  ChefHat,
  Fingerprint,
  Fuel,
  GraduationCap,
  HeartPulse,
  Monitor,
  Plane,
  Smartphone,
  Store,
  UserRound,
  UsersRound,
  UtensilsCrossed,
  Wallet,
  Megaphone,
  Video,
} from 'lucide-react';
import type { ClientModuleAccess, ClientModuleKey } from '@/lib/client-features';
import { mobileDownloadTarget, type MobileAppSlug } from '@/modules/vitrine/lib/mobile-download';

/**
 * #8447 — Catalogue « Mon Logiciel » : toutes les applications qu'un tenant
 * peut installer selon son profil métier et ses solutions activées.
 *
 * Règles d'honnêteté (même exigence que la PWA web-offline, #6697) :
 *  - `link` : cible réellement cliquable (surface web du portail, store ou
 *    build Firebase App Distribution configuré) ;
 *  - `pilot` : distribution sur demande (accès pilote accompagné) — le bouton
 *    mène à la page de demande, jamais à un faux installateur ;
 *  - `soon`  : l'app existe dans l'écosystème mais n'a AUCUN canal public de
 *    distribution — le bouton est désactivé, jamais d'ancre morte.
 *
 * La visibilité d'une app suit STRICTEMENT les modules activés du tenant
 * (`getClientModuleAccess`) : un restaurateur ne voit jamais l'app d'une
 * station-service, et réciproquement.
 */

export type SoftwarePlatform = 'android' | 'ios' | 'web' | 'desktop';

export type SoftwareTargetKind = 'link' | 'pilot' | 'soon';

export type SoftwareTarget = {
  platform: SoftwarePlatform;
  kind: SoftwareTargetKind;
  /** Cible absolue ou relative — absente pour `soon`. */
  href?: string;
};

export type SoftwareApp = {
  /** Identifiant stable — utilisé pour les clés i18n `software.apps.<slug>.*`. */
  slug: string;
  icon: LucideIcon;
  /** Dégradé Tailwind de la tuile (aligné sur les accents des hubs métier). */
  accent: string;
  /** L'app est visible si AU MOINS un de ces modules est activé pour le tenant. */
  moduleKeys: ClientModuleKey[];
  /** Badge « hors-ligne » : l'app continue de fonctionner sans internet. */
  offline: boolean;
  targets: SoftwareTarget[];
};

/** Cibles mobiles factorisées sur le mécanisme vitrine (stores → Firebase → repli). */
function mobileTargets(slug: MobileAppSlug): SoftwareTarget[] {
  const platforms = ['android', 'ios'] as const;
  return platforms.map((platform) => {
    const target = mobileDownloadTarget(slug, platform);
    // Le repli vitrine renvoie vers /signup (capture de lead) — hors sujet
    // pour un tenant DÉJÀ connecté : on le traduit en état honnête « soon ».
    if (target.isFallback) {
      return { platform, kind: 'soon' as const };
    }
    return { platform, kind: 'link' as const, href: target.href };
  });
}

/**
 * Apps natives sans canal de distribution public aujourd'hui : déclarées en
 * `soon` (état honnête), jamais en lien mort.
 */
function nativeSoon(): SoftwareTarget[] {
  return [
    { platform: 'android', kind: 'soon' },
    { platform: 'ios', kind: 'soon' },
  ];
}

export const TENANT_SOFTWARE: SoftwareApp[] = [
  // ── Transverse (toute entreprise) ────────────────────────────────────────
  {
    slug: 'manager',
    icon: Smartphone,
    accent: 'from-emerald-500 to-teal-600',
    moduleKeys: ['dashboard'],
    offline: false,
    targets: mobileTargets('manager'),
  },
  {
    slug: 'employee',
    icon: UserRound,
    accent: 'from-sky-500 to-blue-600',
    moduleKeys: ['employees', 'attendance'],
    offline: false,
    targets: mobileTargets('employee'),
  },
  // Kiosque de pointage & client desktop ZKTeco : distribution sur accès
  // pilote accompagné (la page /download est le canal canonique, aucun
  // installateur public n'est diffusé — état honnête, cf. son copy).
  {
    slug: 'desktop-attendance',
    icon: Fingerprint,
    accent: 'from-slate-500 to-slate-700',
    moduleKeys: ['attendance'],
    offline: true,
    targets: [
      { platform: 'desktop', kind: 'pilot', href: '/download' },
    ],
  },
  // ── Verticale Restaurant ─────────────────────────────────────────────────
  {
    slug: 'restaurant-pos',
    icon: Store,
    accent: 'from-orange-500 to-amber-600',
    moduleKeys: ['restaurant'],
    offline: true,
    targets: [
      { platform: 'web', kind: 'link', href: '/restaurant/pos' },
    ],
  },
  {
    slug: 'restaurant-kds',
    icon: ChefHat,
    accent: 'from-rose-500 to-red-600',
    moduleKeys: ['restaurant', 'restaurant_kitchen'],
    offline: true,
    targets: [
      { platform: 'web', kind: 'link', href: '/restaurant/kitchen' },
    ],
  },
  {
    slug: 'restaurant-server',
    icon: UtensilsCrossed,
    accent: 'from-violet-500 to-purple-600',
    moduleKeys: ['restaurant'],
    offline: true,
    targets: nativeSoon(),
  },
  {
    // RESTO-807 (#6228) — kiosque libre-service : surface web réelle du
    // portail, installable en plein écran sur tablette de comptoir.
    slug: 'restaurant-kiosk',
    icon: Monitor,
    accent: 'from-cyan-500 to-sky-600',
    moduleKeys: ['restaurant'],
    offline: true,
    targets: [
      { platform: 'web', kind: 'link', href: '/kiosk' },
    ],
  },
  // ── Verticale Station-service ────────────────────────────────────────────
  {
    slug: 'fuel-console',
    icon: Fuel,
    accent: 'from-amber-500 to-yellow-600',
    moduleKeys: ['fuel'],
    offline: true,
    targets: [
      { platform: 'web', kind: 'link', href: '/fuel' },
    ],
  },
  // ── Verticale Commerce ───────────────────────────────────────────────────
  {
    slug: 'commerce-pos',
    icon: Wallet,
    accent: 'from-teal-500 to-emerald-600',
    moduleKeys: ['commerce'],
    offline: true,
    targets: [
      { platform: 'web', kind: 'link', href: '/commerce/pos' },
    ],
  },
  // ── Verticale Travel ─────────────────────────────────────────────────────
  {
    slug: 'travel-agent',
    icon: Plane,
    accent: 'from-indigo-500 to-blue-600',
    moduleKeys: ['travel'],
    offline: false,
    targets: nativeSoon(),
  },
  // ── Verticale Éducation ──────────────────────────────────────────────────
  {
    slug: 'edu-portal',
    icon: GraduationCap,
    accent: 'from-blue-500 to-indigo-600',
    moduleKeys: ['edu_manager'],
    offline: false,
    targets: [
      { platform: 'web', kind: 'link', href: '/edu-manager' },
    ],
  },
  // ── Verticale Santé ──────────────────────────────────────────────────────
  {
    slug: 'health-portal',
    icon: HeartPulse,
    accent: 'from-red-500 to-rose-600',
    moduleKeys: ['health'],
    offline: false,
    targets: [
      { platform: 'web', kind: 'link', href: '/health' },
    ],
  },
  // ── Apps métier horizontales (distribution publique à venir) ─────────────
  {
    slug: 'hr-mobile',
    icon: UsersRound,
    accent: 'from-emerald-600 to-green-700',
    moduleKeys: ['payroll', 'contracts'],
    offline: false,
    targets: nativeSoon(),
  },
  {
    slug: 'accounting-mobile',
    icon: Wallet,
    accent: 'from-slate-600 to-slate-800',
    moduleKeys: ['accounting'],
    offline: false,
    targets: nativeSoon(),
  },
  {
    slug: 'marketing-mobile',
    icon: Megaphone,
    accent: 'from-fuchsia-500 to-pink-600',
    moduleKeys: ['marketing'],
    offline: false,
    targets: nativeSoon(),
  },
  {
    slug: 'cameras-mobile',
    icon: Video,
    accent: 'from-gray-500 to-slate-600',
    moduleKeys: ['cameras'],
    offline: false,
    targets: nativeSoon(),
  },
];

/**
 * Apps visibles pour le tenant : filtrage STRICT par modules activés
 * (`enabled`), jamais par rôle seul — le catalogue suit les solutions
 * réellement souscrites (critère d'acceptation #8447).
 */
export function visibleSoftware(access: ClientModuleAccess[]): SoftwareApp[] {
  const enabled = new Set(access.filter((module) => module.enabled).map((module) => module.key));
  return TENANT_SOFTWARE.filter((app) => app.moduleKeys.some((key) => enabled.has(key)));
}

/** Résout une cible en URL absolue pour le QR d'installation (origine courante). */
export function absoluteTargetHref(href: string, origin: string): string {
  if (/^https?:\/\//.test(href)) {
    return href;
  }
  return `${origin}${href.startsWith('/') ? '' : '/'}${href}`;
}
