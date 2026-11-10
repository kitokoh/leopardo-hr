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
import { isDistributionHealthy } from '@/lib/mobile-distribution-status';
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
  // BOS-A-05 / #8628 — un statut ne peut pas être `published`/`pilot` si le
  // canal de distribution correspondant est en échec CONNU (ou d'état inconnu).
  // Sans cette garde, un job CI `Distribute <app> to Firebase` rouge en
  // permanence laissait la fiche annoncer « accès pilote » avec un bouton
  // d'installation : la règle d'honnêteté #6697 était contournée par la CI.
  if (!isDistributionHealthy(app.slug)) {
    return 'not_distributed';
  }
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

  // BOS-A-05 / #8628 — canal en échec connu : AUCUN `href` d'installation.
  // Le lien Firebase/store pointerait vers un build qui n'existe pas ; on
  // retombe sur l'état honnête « soon » (bouton désactivé, jamais d'ancre
  // morte), exactement comme le repli vitrine.
  if (!isDistributionHealthy(slug)) {
    return platforms.map((platform) => ({ platform, kind: 'soon' as const }));
  }

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

/**
 * #9300 — Apps mobiles de GESTION DE STOCK, par verticale.
 *
 * Le mobile reste une OPTION (#9275) : cette table ne sert qu'à PROPOSER
 * l'accès au téléchargement depuis le profil, jamais à l'imposer.
 *
 * Elle est EXPLICITE et volontairement étroite : seules les verticales dont
 * l'application mobile embarque réellement un écran de stock y figurent.
 *  - `restaurant` → `manager` : `leopardo_manager` embarque
 *    `features/restaurant/screens/restaurant_stock_screen.dart`.
 *
 * Aucune autre verticale n'a d'app mobile de stock DANS LE CATALOGUE :
 * `commerce-pos` (Retail) n'expose qu'une cible web, et `leopardo_pos_retail`
 * n'a aucun canal de distribution public (absent de la matrice
 * `mobile-distribute*.yml`). Un tenant Commerce n'obtient donc rien — état
 * honnête (règle #6697), pas un oubli.
 */
const MOBILE_STOCK_APPS: { moduleKeys: ClientModuleKey[]; slug: string }[] = [
  { moduleKeys: ['restaurant'], slug: 'manager' },
];

/**
 * #9300 — Apps mobiles de stock réellement DISTRIBUABLES pour le tenant.
 *
 * Deux conditions cumulatives, jamais une liste en dur côté appelant :
 *  1. la verticale de stock du tenant est activée (`getClientModuleAccess`) ;
 *  2. l'app correspondante a une cible mobile réellement cliquable
 *     (`kind === 'link'`) — donc un canal de distribution sain
 *     (`isDistributionHealthy`, règle d'honnêteté #6697).
 *
 * `leopardo_pos_retail` n'a aujourd'hui AUCUN canal public (absent de la
 * matrice `mobile-distribute*.yml`) : un tenant Commerce obtient donc une
 * liste vide plutôt qu'un bouton mort. C'est l'état honnête, pas un oubli.
 */
export function mobileSoftware(access: ClientModuleAccess[]): SoftwareApp[] {
  const enabled = new Set(access.filter((module) => module.enabled).map((module) => module.key));

  return MOBILE_STOCK_APPS.filter((entry) => entry.moduleKeys.some((key) => enabled.has(key)))
    .map((entry) => TENANT_SOFTWARE.find((app) => app.slug === entry.slug))
    .filter((app): app is SoftwareApp => !!app && mobileTargetOf(app) !== null)
    // #9300 — un canal en échec ou d'état inconnu n'est JAMAIS proposé
    // (règle d'honnêteté #6697, source de vérité #8628). Sans cette
    // condition, la carte de profil enverrait vers un build inexistant.
    .filter((app) => isDistributionHealthy(app.slug));
}

/** Première cible mobile cliquable d'une app, ou `null`. */
function mobileTargetOf(app: SoftwareApp): SoftwareTarget | null {
  return (
    app.targets.find(
      (target) =>
        (target.platform === 'android' || target.platform === 'ios') &&
        target.kind === 'link' &&
        typeof target.href === 'string' &&
        target.href.length > 0,
    ) ?? null
  );
}

/**
 * #9300 — Cible de téléchargement mobile principale du tenant (celle de la
 * carte de profil) : la première app mobile de stock distribuable et sa
 * première cible mobile cliquable.
 *
 * `null` quand le tenant n'a aucune app mobile de stock distribuable —
 * l'appelant ne rend alors RIEN. C'est ce `null` qui garantit qu'un tenant
 * non concerné ne voit jamais l'accès.
 */
export function tenantMobileDownload(
  access: ClientModuleAccess[],
): { app: SoftwareApp; target: SoftwareTarget } | null {
  for (const app of mobileSoftware(access)) {
    const target = mobileTargetOf(app);
    if (target) {
      return { app, target };
    }
  }
  return null;
}

/** Résout une cible en URL absolue pour le QR d'installation (origine courante). */
export function absoluteTargetHref(href: string, origin: string): string {
  if (/^https?:\/\//.test(href)) {
    return href;
  }
  return `${origin}${href.startsWith('/') ? '' : '/'}${href}`;
}
