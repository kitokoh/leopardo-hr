/**
 * Progression de lecture — cœur PUR + persistance locale.
 *
 * Les visiteurs ANONYMES conservent leur progression en `localStorage` (aucun
 * compte requis pour lire les ressources publiques). Les utilisateurs
 * connectés pourront synchroniser côté serveur (API) — la clé de stockage est
 * la même, seule la source de vérité change.
 *
 * Le cœur (`clampProgress`, `progressPercent`, `mergeProgress`) est pur et
 * testé ; les fonctions `load*`/`save*` encapsulent `localStorage` avec un
 * repli silencieux (SSR, mode privé, quota dépassé).
 */

export interface ReadingProgress {
  /** Slug de la ressource. */
  slug: string;
  /** Page courante (1-indexée). */
  page: number;
  /** Nombre total de pages (0 si inconnu). */
  totalPages: number;
  /** Horodatage de la dernière lecture (ms epoch). */
  updatedAt: number;
}

const STORAGE_KEY = 'leopardo_library_progress';
const FAVORITES_KEY = 'leopardo_library_favorites';

/** Borne une page dans [1, totalPages] (ou ≥ 1 si total inconnu). */
export function clampProgress(page: number, totalPages: number): number {
  if (Number.isNaN(page)) return 1;
  if (page === Infinity) return totalPages > 0 ? totalPages : 1;
  if (page === -Infinity || page < 1) return 1;
  if (totalPages > 0 && page > totalPages) return totalPages;
  return Math.floor(page);
}

/** Pourcentage de progression (0–100), 0 si le total est inconnu. */
export function progressPercent(page: number, totalPages: number): number {
  if (totalPages <= 0) return 0;
  const clamped = clampProgress(page, totalPages);
  return Math.round((clamped / totalPages) * 100);
}

/** Fusionne une nouvelle position dans une liste de progressions (par slug). */
export function mergeProgress(
  list: ReadingProgress[],
  entry: ReadingProgress,
): ReadingProgress[] {
  const others = list.filter((item) => item.slug !== entry.slug);
  return [entry, ...others].sort((a, b) => b.updatedAt - a.updatedAt);
}

function safeParse<T>(raw: string | null, fallback: T): T {
  if (!raw) return fallback;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}

/** Charge toutes les progressions enregistrées (localStorage). */
export function loadProgress(): ReadingProgress[] {
  if (typeof window === 'undefined') return [];
  return safeParse<ReadingProgress[]>(window.localStorage.getItem(STORAGE_KEY), []);
}

/** Enregistre une position de lecture et retourne la liste mise à jour. */
export function saveProgress(entry: ReadingProgress): ReadingProgress[] {
  const next = mergeProgress(loadProgress(), entry);
  if (typeof window !== 'undefined') {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
      // Quota dépassé / mode privé : on n'échoue pas.
    }
  }
  return next;
}

/** Retourne la progression d'une ressource, ou `null`. */
export function getProgressFor(slug: string): ReadingProgress | null {
  return loadProgress().find((item) => item.slug === slug) ?? null;
}

/** Charge les favoris (slugs). */
export function loadFavorites(): string[] {
  if (typeof window === 'undefined') return [];
  return safeParse<string[]>(window.localStorage.getItem(FAVORITES_KEY), []);
}

/** Bascule un favori et retourne la liste mise à jour. */
export function toggleFavorite(slug: string): string[] {
  const current = loadFavorites();
  const next = current.includes(slug)
    ? current.filter((item) => item !== slug)
    : [slug, ...current];
  if (typeof window !== 'undefined') {
    try {
      window.localStorage.setItem(FAVORITES_KEY, JSON.stringify(next));
    } catch {
      // Repli silencieux.
    }
  }
  return next;
}
