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

/**
 * Mode hors-ligne RÉEL d'une application (BOS F-01, ADR-08).
 *
 * Remplace l'ancien booléen `offline` qui était purement DÉCLARATIF : il
 * annonçait un fonctionnement hors-ligne sans qu'aucune preuve ne
 * l'accompagne. Une perte de vente terrain est un incident financier, pas un
 * défaut d'affichage : la preuve doit précéder la promesse.
 *
 *  - `verified`    : un scénario de rejeu exécuté en CI le prouve
 *                    (`offlineEvidence` est alors OBLIGATOIRE) ;
 *  - `implemented` : le code passe réellement par la file hors-ligne
 *                    (`useOfflineSync` / `dispatch`) ou par un pont local,
 *                    mais aucun scénario de coupure réseau ne le prouve ;
 *  - `absent`      : l'écran émet ses requêtes directement — aucune
 *                    continuité hors-ligne, aucun badge ne doit l'affirmer.
 */
export type OfflineMode = 'verified' | 'implemented' | 'absent';

export type SoftwareTargetKind = 'link' | 'pilot' | 'soon';

export type SoftwareTarget = {
  platform: SoftwarePlatform;
  kind: SoftwareTargetKind;
  /** Cible absolue ou relative — absente pour `soon`. */
  href?: string;
};

/**
 * #8597 (BOS-045) — statut de disponibilité RÉEL d'une application, dérivé de
 * ses cibles de distribution (jamais déclaré à la main, donc jamais menteur) :
 *  - `published`       : au moins une cible `link` (surface web ou store) ;
 *  - `pilot`           : distribution sur demande d'accès pilote ;
 *  - `not_distributed` : aucun canal public — l'app est en préparation.
 */
export type SoftwareAvailability = 'published' | 'pilot' | 'not_distributed';

/**
 * Sections de la fiche application (Partie 6.1 de la mission Business OS).
 * Le TEXTE vit dans les catalogues i18n sous
 * `software.apps.<slug>.details.<clé>` — le catalogue ne duplique jamais la
 * copie, il déclare seulement quelles sections existent.
 */
export const SOFTWARE_DETAIL_KEYS = [
  'role',
  'audiences',
  'features',
  'prerequisites',
  'connection',
  'permissions',
  'setup',
] as const;

export type SoftwareDetailKey = (typeof SOFTWARE_DETAIL_KEYS)[number];

export type SoftwareApp = {
  /** Identifiant stable — utilisé pour les clés i18n `software.apps.<slug>.*`. */
  slug: string;
  icon: LucideIcon;
  /** Dégradé Tailwind de la tuile (aligné sur les accents des hubs métier). */
  accent: string;
  /** L'app est visible si AU MOINS un de ces modules est activé pour le tenant. */
  moduleKeys: ClientModuleKey[];
  /**
   * Mode hors-ligne RÉEL (BOS F-01) — dérivé du câblage constaté dans le
   * code, jamais d'une intention. Voir `isOfflineClaimed()`.
   */
  offline: OfflineMode;
  /**
   * Référence du scénario CI qui prouve le hors-ligne. Obligatoire dès que
   * `offline === 'verified'` (vérifié par `isOfflineClaimed()` et par test).
   */
  offlineEvidence?: string;
  targets: SoftwareTarget[];
  /** Sections de la fiche application, dans l'ordre d'affichage. */
  details: SoftwareDetailKey[];
};

/**
 * Une application peut-elle afficher un badge hors-ligne ?
 *
 * `absent` ne s'affiche JAMAIS : c'est le seul moyen d'éviter qu'une app
 * branchée en direct sur l'API soit présentée comme fonctionnant sans
 * réseau. Un `verified` sans référence de scénario est traité comme non
 * prouvé et retombe sur `implemented` (fail-closed : on ne sur-promet pas).
 */
export function isOfflineClaimed(app: SoftwareApp): boolean {
  if (app.offline === 'absent') {
    return false;
  }
  if (app.offline === 'verified') {
    return typeof app.offlineEvidence === 'string' && app.offlineEvidence.trim().length > 0;
  }
  return true;
}

/**
 * Statut de disponibilité dérivé des cibles réelles — source unique de vérité
 * pour le badge de la fiche (règle d'honnêteté #6697 : jamais de faux
 * « Installer »).
 */
export function softwareAvailability(app: SoftwareApp): SoftwareAvailability {
  if (app.targets.some((target) => target.kind === 'link')) {
    return 'published';
  }
  if (app.targets.some((target) => target.kind === 'pilot')) {
    return 'pilot';
  }
  return 'not_distributed';
}

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

const SOFTWARE_APPS: Omit<SoftwareApp, 'details'>[] = [
  // ── Transverse (toute entreprise) ────────────────────────────────────────
  {
    slug: 'manager',
    icon: Smartphone,
    accent: 'from-emerald-500 to-teal-600',
    moduleKeys: ['dashboard'],
    // App mobile manager : écrans Flutter, aucun câblage de file local constaté.
    offline: 'absent',
    targets: mobileTargets('manager'),
  },
  {
    slug: 'employee',
    icon: UserRound,
    accent: 'from-sky-500 to-blue-600',
    moduleKeys: ['employees', 'attendance'],
    offline: 'absent',
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
    // Pont local `/local/punch` réellement hors-ligne (front/zkteco-kiosk/app.js)
    // avec compteur de file visible — mais aucun scénario de coupure rejoué en CI.
    offline: 'implemented',
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
    // `/restaurant/pos` émet ses POST directement (aucun `dispatch`) : la
    // continuité hors-ligne n'est PAS câblée sur cet écran.
    offline: 'absent',
    targets: [
      { platform: 'web', kind: 'link', href: '/restaurant/pos' },
    ],
  },
  {
    slug: 'restaurant-kds',
    icon: ChefHat,
    accent: 'from-rose-500 to-red-600',
    moduleKeys: ['restaurant', 'restaurant_kitchen'],
    // `/restaurant/kitchen` passe par `dispatch()` → file hors-ligne idempotente
    // (`useOfflineSync`). Aucun scénario CI de coupure réelle ne le prouve encore.
    offline: 'implemented',
    targets: [
      { platform: 'web', kind: 'link', href: '/restaurant/kitchen' },
    ],
  },
  {
    slug: 'restaurant-server',
    icon: UtensilsCrossed,
    accent: 'from-violet-500 to-purple-600',
    moduleKeys: ['restaurant'],
    // App serveur native sans canal ni file locale : rien à revendiquer.
    offline: 'absent',
    targets: nativeSoon(),
  },
  {
    // RESTO-807 (#6228) — kiosque libre-service : surface web réelle du
    // portail, installable en plein écran sur tablette de comptoir.
    slug: 'restaurant-kiosk',
    icon: Monitor,
    accent: 'from-cyan-500 to-sky-600',
    moduleKeys: ['restaurant'],
    // `/kiosk` consomme exclusivement `/public/restaurant/*` en direct : aucun
    // `dispatch`, aucune persistance locale → pas de continuité hors-ligne.
    offline: 'absent',
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
    // `/fuel/pump` et `/fuel` émettent leurs POST directement : pas de file
    // hors-ligne sur la verticale station-service aujourd'hui.
    offline: 'absent',
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
    // `/commerce/pos` encaisse via `dispatch()` avec une clé d'idempotence
    // stable (session + panier) → la file hors-ligne est réellement branchée.
    offline: 'implemented',
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
    offline: 'absent',
    targets: nativeSoon(),
  },
  // ── Verticale Éducation ──────────────────────────────────────────────────
  {
    slug: 'edu-portal',
    icon: GraduationCap,
    accent: 'from-blue-500 to-indigo-600',
    moduleKeys: ['edu_manager'],
    offline: 'absent',
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
    offline: 'absent',
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
    offline: 'absent',
    targets: nativeSoon(),
  },
  {
    slug: 'accounting-mobile',
    icon: Wallet,
    accent: 'from-slate-600 to-slate-800',
    moduleKeys: ['accounting'],
    offline: 'absent',
    targets: nativeSoon(),
  },
  {
    slug: 'marketing-mobile',
    icon: Megaphone,
    accent: 'from-fuchsia-500 to-pink-600',
    moduleKeys: ['marketing'],
    offline: 'absent',
    targets: nativeSoon(),
  },
  {
    slug: 'cameras-mobile',
    icon: Video,
    accent: 'from-gray-500 to-slate-600',
    moduleKeys: ['cameras'],
    offline: 'absent',
    targets: nativeSoon(),
  },
];

/**
 * Catalogue « Mon Logiciel » : chaque app reçoit la fiche complète (#8597).
 * Les sections sont identiques pour toutes les apps aujourd'hui ; les rendre
 * explicites par app permet d'en retirer une sans toucher au rendu.
 */
export const TENANT_SOFTWARE: SoftwareApp[] = SOFTWARE_APPS.map((app) => ({
  ...app,
  details: [...SOFTWARE_DETAIL_KEYS],
}));

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
