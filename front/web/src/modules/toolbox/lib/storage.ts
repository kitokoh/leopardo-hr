/**
 * Leopardo Toolbox — façade de stockage « Mes outils » (favoris + historique).
 *
 * Décision #9156 (`docs/DECISIONS/2026-11-09-toolbox-persistance-mes-outils.md`) :
 * `localStorage` reste la source de vérité pour les visiteurs anonymes. Toute
 * lecture/écriture passe par CE module — le jour où une persistance serveur est
 * retenue, seule cette façade change, les composants n'ont pas à être touchés.
 *
 * Contrat :
 * - **clé versionnée** (`leopardo_toolbox_state_v1`) : une future migration de
 *   schéma peut lire `_v1` et écrire `_v2` sans ambiguïté ;
 * - **repli silencieux** : SSR, mode privé et quota dépassé n'échouent jamais
 *   (même contrat que `library/lib/progress.ts`) ;
 * - **aucune donnée personnelle** : seuls des slugs d'outils sont stockés.
 */

/** Clé de stockage versionnée — ne jamais réutiliser une clé non versionnée. */
export const TOOLBOX_STORAGE_KEY = 'leopardo_toolbox_state_v1';

/** Nombre maximal d'entrées d'historique conservées (les plus récentes). */
export const HISTORY_LIMIT = 20;

/** État persisté de « Mes outils ». */
export interface ToolboxState {
  /** Slugs favoris, du plus récent au plus ancien. */
  favorites: string[];
  /** Slugs récemment utilisés, du plus récent au plus ancien. */
  history: string[];
}

/** État vide (valeur de repli). */
export const EMPTY_TOOLBOX_STATE: ToolboxState = { favorites: [], history: [] };

/**
 * Normalise un état lu depuis le stockage.
 *
 * Le contenu de `localStorage` n'est pas fiable (version antérieure, écriture
 * manuelle, JSON tronqué) : on ne fait jamais confiance à sa forme. Toute
 * entrée non conforme est écartée plutôt que propagée dans l'interface.
 */
export function normalizeState(raw: unknown): ToolboxState {
  if (typeof raw !== 'object' || raw === null) return { ...EMPTY_TOOLBOX_STATE };

  const source = raw as Partial<Record<keyof ToolboxState, unknown>>;

  const toSlugList = (value: unknown): string[] => {
    if (!Array.isArray(value)) return [];
    const seen = new Set<string>();
    const out: string[] = [];
    for (const item of value) {
      if (typeof item !== 'string') continue;
      const slug = item.trim();
      if (slug === '' || seen.has(slug)) continue;
      seen.add(slug);
      out.push(slug);
    }
    return out;
  };

  return {
    favorites: toSlugList(source.favorites),
    history: toSlugList(source.history).slice(0, HISTORY_LIMIT),
  };
}

/** Charge l'état persisté (état vide en SSR ou si le stockage est illisible). */
export function loadState(): ToolboxState {
  if (typeof window === 'undefined') return { ...EMPTY_TOOLBOX_STATE };

  try {
    const raw = window.localStorage.getItem(TOOLBOX_STORAGE_KEY);
    if (!raw) return { ...EMPTY_TOOLBOX_STATE };
    return normalizeState(JSON.parse(raw));
  } catch {
    // JSON invalide / stockage inaccessible : on repart d'un état vide.
    return { ...EMPTY_TOOLBOX_STATE };
  }
}

/** Enregistre l'état (repli silencieux si le stockage refuse l'écriture). */
export function saveState(state: ToolboxState): ToolboxState {
  const normalized = normalizeState(state);

  if (typeof window !== 'undefined') {
    try {
      window.localStorage.setItem(TOOLBOX_STORAGE_KEY, JSON.stringify(normalized));
    } catch {
      // Quota dépassé / mode privé : on n'échoue pas.
    }
  }

  return normalized;
}

/** Bascule un favori et retourne l'état mis à jour. */
export function toggleFavorite(slug: string): ToolboxState {
  const state = loadState();
  const favorites = state.favorites.includes(slug)
    ? state.favorites.filter((item) => item !== slug)
    : [slug, ...state.favorites];

  return saveState({ ...state, favorites });
}

/** Enregistre l'usage d'un outil (historique borné, sans doublon). */
export function recordUsage(slug: string): ToolboxState {
  const state = loadState();
  const history = [slug, ...state.history.filter((item) => item !== slug)].slice(0, HISTORY_LIMIT);

  return saveState({ ...state, history });
}

/** Vrai si l'outil est en favori. */
export function isFavorite(slug: string): boolean {
  return loadState().favorites.includes(slug);
}

/** Efface tout l'état « Mes outils ». */
export function clearState(): ToolboxState {
  return saveState({ ...EMPTY_TOOLBOX_STATE });
}
