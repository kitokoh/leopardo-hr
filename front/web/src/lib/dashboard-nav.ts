import type { ClientModuleAccess, ClientModuleKey } from '@/lib/client-features';

/**
 * #7328 — modules regroupés sous le menu « RH » (sous-menus).
 * Ordre d'affichage : Employés · Pointages · Sessions GPS · Absences ·
 * Contrats · Formations.
 */
export const HR_SUBMENU_KEYS: ClientModuleKey[] = [
  'employees',
  'attendance',
  'attendance_geo',
  'absences',
  'contracts',
  'training',
];

/** Un module affichable : `href` garanti par `toNavModules()`. */
export type NavModule = ClientModuleAccess & { href: string };

export type DashboardNavEntry =
  | { kind: 'link'; module: NavModule }
  | { kind: 'menu'; id: 'hr'; modules: NavModule[] };

/** Ne garde que les modules réellement navigables (`href` renseigné). */
export function toNavModules(access: ClientModuleAccess[]): NavModule[] {
  return access.filter((candidate): candidate is NavModule => typeof candidate.href === 'string' && candidate.href !== '');
}

/**
 * Construit le menu **d'une seule ligne** (#7328) : les modules RH sont
 * repliés dans un sous-menu, les autres restent des liens directs, dans
 * l'ordre du catalogue.
 *
 * Le menu RH prend la position du **premier** module RH rencontré ; s'il ne
 * reste qu'un seul module RH activé, il est rendu en lien direct (un menu à un
 * seul élément n'apporte rien).
 */
export function buildDashboardNav(navPills: NavModule[]): DashboardNavEntry[] {
  const hrModules = navPills.filter((candidate) => HR_SUBMENU_KEYS.includes(candidate.key));
  const entries: DashboardNavEntry[] = [];
  let placed = false;

  for (const candidate of navPills) {
    if (HR_SUBMENU_KEYS.includes(candidate.key)) {
      if (placed) {
        continue;
      }
      placed = true;
      entries.push(
        hrModules.length > 1
          ? { kind: 'menu', id: 'hr', modules: hrModules }
          : { kind: 'link', module: hrModules[0] },
      );
      continue;
    }

    entries.push({ kind: 'link', module: candidate });
  }

  return entries;
}

/** Un sous-menu RH est-il actif pour ce chemin ? (état « ouvert / actif ») */
export function isHrEntryActive(entry: DashboardNavEntry, pathname: string): boolean {
  if (entry.kind === 'link') {
    return pathname === entry.module.href;
  }

  return entry.modules.some((candidate) => pathname === candidate.href || pathname.startsWith(`${candidate.href}/`));
}
