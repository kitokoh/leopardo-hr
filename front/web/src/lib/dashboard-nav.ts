import { CLIENT_MODULES, type ClientModuleAccess, type ClientModuleGroup, type ClientModuleKey } from '@/lib/client-features';

/**
 * #7724 — groupes VISUELS de la barre, dans l'ordre d'affichage. La seule
 * source de vérité du groupement est le champ `group` du catalogue
 * (`CLIENT_MODULES`) : ce module ne fait que le dériver — plus de liste
 * parallèle qui dérive (#7432 : `HR_SUBMENU_KEYS` avait déjà divergé une fois).
 */
export const NAV_MENU_GROUP_IDS = ['hr', 'finance', 'growth', 'operations'] as const;

export type NavMenuGroupId = (typeof NAV_MENU_GROUP_IDS)[number];

function isMenuGroup(group: ClientModuleGroup): group is NavMenuGroupId {
  return (NAV_MENU_GROUP_IDS as readonly string[]).includes(group);
}

/**
 * Clés d'un groupe visuel, DÉRIVÉES du catalogue (ordre du catalogue).
 */
export function navGroupKeys(group: NavMenuGroupId): ClientModuleKey[] {
  return CLIENT_MODULES.filter((module) => module.group === group).map((module) => module.key);
}

/**
 * #7328/#7724 — modules regroupés sous le menu « RH ». Conservé pour
 * compatibilité : c'est désormais une DÉRIVATION du catalogue, plus une
 * seconde source de vérité.
 */
export const HR_SUBMENU_KEYS: ClientModuleKey[] = navGroupKeys('hr');

/** Un module affichable : `href` garanti par `toNavModules()`. */
export type NavModule = ClientModuleAccess & { href: string };

export type DashboardNavEntry =
  | { kind: 'link'; module: NavModule }
  | { kind: 'menu'; id: NavMenuGroupId; modules: NavModule[] };

/** Ne garde que les modules réellement navigables (`href` renseigné). */
export function toNavModules(access: ClientModuleAccess[]): NavModule[] {
  return access.filter((candidate): candidate is NavModule => typeof candidate.href === 'string' && candidate.href !== '');
}

/**
 * Construit le menu **d'une seule ligne** (#7328, généralisé par #7724) :
 * chaque groupe visuel (RH, Finance, Clients & croissance, Opérations) est
 * replié dans un sous-menu, les modules `general` restent des liens directs,
 * dans l'ordre du catalogue.
 *
 * Chaque menu prend la position du **premier** module de son groupe ; s'il ne
 * reste qu'un seul module activé dans un groupe, il est rendu en lien direct
 * (un menu à un seul élément n'apporte rien).
 */
export function buildDashboardNav(navPills: NavModule[]): DashboardNavEntry[] {
  const entries: DashboardNavEntry[] = [];
  const placedGroups = new Set<NavMenuGroupId>();

  for (const candidate of navPills) {
    if (isMenuGroup(candidate.group)) {
      if (placedGroups.has(candidate.group)) {
        continue;
      }
      placedGroups.add(candidate.group);
      const groupModules = navPills.filter((pill) => pill.group === candidate.group);
      entries.push(
        groupModules.length > 1
          ? { kind: 'menu', id: candidate.group, modules: groupModules }
          : { kind: 'link', module: groupModules[0] },
      );
      continue;
    }

    entries.push({ kind: 'link', module: candidate });
  }

  return entries;
}

/**
 * Une entrée (lien ou sous-menu) est-elle active pour ce chemin ?
 * (état « ouvert / actif » — nom historique conservé depuis le sous-menu RH.)
 */
export function isHrEntryActive(entry: DashboardNavEntry, pathname: string): boolean {
  if (entry.kind === 'link') {
    return pathname === entry.module.href;
  }

  return entry.modules.some((candidate) => pathname === candidate.href || pathname.startsWith(`${candidate.href}/`));
}

/** Alias explicite : l'état actif vaut pour TOUT sous-menu de groupe (#7724). */
export const isNavEntryActive = isHrEntryActive;

/** Une carte du rail « Mon métier » et ses sous-écrans rattachés (#7724). */
export type BusinessRailEntry = {
  module: ClientModuleAccess;
  children: ClientModuleAccess[];
};

/**
 * #7724 — hiérarchise le rail « Mon métier » : les sous-écrans métier
 * (`parentKey` — Cuisine sous Restaurant, Portail voyageur sous Agence de
 * voyage) sont rendus SOUS leur carte parente au lieu d'être promus au
 * premier niveau. Un sous-écran dont le parent n'est pas activé redevient une
 * carte de premier niveau (aucun écran accessible ne doit disparaître).
 */
export function buildBusinessRail(business: ClientModuleAccess[]): BusinessRailEntry[] {
  const byKey = new Map(business.map((module) => [module.key, module]));
  const entries: BusinessRailEntry[] = [];

  for (const entry of business) {
    if (entry.parentKey && byKey.has(entry.parentKey)) {
      continue;
    }
    entries.push({
      module: entry,
      children: business.filter((candidate) => candidate.parentKey === entry.key),
    });
  }

  return entries;
}
