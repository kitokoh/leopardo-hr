/**
 * Couloir 16 — M1 (#9374, état 1) : squelettes de chargement à dimensions FIXES.
 *
 * ## Pourquoi des dimensions fixes
 *
 * Le critère d'acceptation est explicite : « skeletons à dimensions fixes
 * (aucun décalage de layout) ». Un squelette dont la hauteur dépend de son
 * contenu provoque un CLS au moment où les vraies données arrivent. Les
 * hauteurs ci-dessous sont donc des constantes, identiques à celles des
 * cartes réelles (`min-h` de la carte de résultat).
 *
 * ## Accessibilité
 *
 * Le conteneur porte `aria-busy="true"` et `aria-live="polite"` : un lecteur
 * d'écran annonce le chargement une fois, sans lire les blocs décoratifs
 * (`aria-hidden` sur chaque squelette).
 *
 * ## Mouvement
 *
 * L'animation est désactivée sous `prefers-reduced-motion: reduce` via la
 * classe `motion-reduce:animate-none` — pas de pulsation pour qui la refuse.
 */

/** Hauteur fixe d'une carte de résultat (miroir de la carte réelle). */
export const SKELETON_CARD_HEIGHT = 168;

/** Nombre de cartes squelettes (grille 3 colonnes × 2 rangées). */
export const SKELETON_CARD_COUNT = 6;

interface DiscoverySkeletonProps {
  /** Libellé annoncé aux lecteurs d'écran. */
  label: string;
}

export function DiscoverySkeleton({ label }: DiscoverySkeletonProps) {
  return (
    <div aria-busy="true" aria-live="polite" className="py-2">
      <span className="sr-only">{label}</span>

      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-hidden>
        {Array.from({ length: SKELETON_CARD_COUNT }).map((_, index) => (
          <li
            key={index}
            className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            style={{ height: SKELETON_CARD_HEIGHT }}
          >
            {/* Titre */}
            <div className="h-4 w-3/4 animate-pulse rounded bg-slate-200 motion-reduce:animate-none" />
            {/* Ville */}
            <div className="mt-2 h-3 w-1/3 animate-pulse rounded bg-slate-100 motion-reduce:animate-none" />
            {/* Description, 2 lignes */}
            <div className="mt-3 h-3 w-full animate-pulse rounded bg-slate-100 motion-reduce:animate-none" />
            <div className="mt-1.5 h-3 w-5/6 animate-pulse rounded bg-slate-100 motion-reduce:animate-none" />
            {/* Action */}
            <div className="mt-auto h-4 w-1/3 animate-pulse rounded bg-slate-200 motion-reduce:animate-none" />
          </li>
        ))}
      </ul>
    </div>
  );
}
