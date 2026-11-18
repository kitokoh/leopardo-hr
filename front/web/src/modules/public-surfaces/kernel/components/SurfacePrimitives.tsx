import type { CSSProperties, ReactNode } from 'react';

import type { SurfaceIdentity } from '../domain/surface-identity';

// États (:hover, :focus-visible, prefers-reduced-motion) — non exprimables en
// style inline. La composition reste dans les composants.
import './surface-interactions.css';

/**
 * Primitives de rendu partagées par les vitrines verticales (#9594).
 *
 * ## Pourquoi elles existent
 *
 * Avant, chaque `*Surface.tsx` réécrivait ses propres `style={{...}}` : bordures
 * `muted33`, rayons `0.75rem`, grilles `auto-fill minmax(16rem…)`. Six copies
 * divergentes du même gabarit — exactement ce que le noyau doit absorber.
 *
 * ## Ce qu'elles ne font PAS
 *
 * Elles ne décident **aucune** composition. Une vitrine pharmacie et une
 * vitrine billets n'ont pas la même hiérarchie visuelle : la première met la
 * garde et la ville en avant, la seconde l'itinéraire et les places. Les
 * primitives fournissent le *vocabulaire* (carte, grille, badge, état vide),
 * chaque verticale écrit sa *phrase*.
 *
 * Toutes les couleurs viennent de `identity.palette`, jamais d'un littéral :
 * le contraste AA est garanti en amont par `buildPalette`.
 */

/** Carte de contenu — la brique de base de toutes les grilles. */
export function SurfaceCard({
  identity,
  children,
  as: Tag = 'div',
  interactive = false,
}: {
  identity: SurfaceIdentity;
  children: ReactNode;
  as?: 'div' | 'li' | 'article';
  /** Ajoute l'ombre et le curseur d'une carte cliquable. */
  interactive?: boolean;
}) {
  const style: CSSProperties = {
    border: `1px solid ${identity.palette.muted}33`,
    borderRadius: '0.9rem',
    padding: '1.1rem',
    background: '#FFFFFF',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.4rem',
    ...(interactive
      ? {
          boxShadow: '0 1px 2px rgba(15, 23, 42, 0.04)',
          transition: 'box-shadow 120ms ease, transform 120ms ease',
        }
      : {}),
  };

  return (
    <Tag style={style} data-surface-card="" data-surface-interactive={interactive ? 'true' : undefined}>
      {children}
    </Tag>
  );
}

/**
 * Grille responsive de cartes.
 *
 * `min` est le seul levier de personnalité : une grille retail dense (14rem)
 * n'a pas la respiration d'une grille hôtellerie (20rem).
 */
export function SurfaceGrid({
  min = '17rem',
  children,
}: {
  min?: string;
  children: ReactNode;
}) {
  return (
    <ul
      style={{
        listStyle: 'none',
        margin: 0,
        padding: 0,
        display: 'grid',
        gap: '1rem',
        gridTemplateColumns: `repeat(auto-fill, minmax(${min}, 1fr))`,
      }}
    >
      {children}
    </ul>
  );
}

/** Badge compact — état, catégorie, service. */
export function SurfaceBadge({
  identity,
  children,
  tone = 'neutral',
}: {
  identity: SurfaceIdentity;
  children: ReactNode;
  /** `accent` = information à faire ressortir (garde, disponibilité). */
  tone?: 'neutral' | 'accent';
}) {
  const isAccent = tone === 'accent';

  return (
    <span
      style={{
        display: 'inline-block',
        padding: '0.2rem 0.55rem',
        borderRadius: '0.5rem',
        fontSize: '0.75rem',
        fontWeight: 700,
        letterSpacing: '0.01em',
        background: isAccent ? `${identity.palette.accent}22` : `${identity.palette.muted}1A`,
        color: isAccent ? identity.palette.primary : identity.palette.muted,
      }}
    >
      {children}
    </span>
  );
}

/** Titre de section — même rythme typographique dans toutes les vitrines. */
export function SurfaceSectionTitle({
  identity,
  children,
}: {
  identity: SurfaceIdentity;
  children: ReactNode;
}) {
  return (
    <h2
      style={{
        margin: '0 0 0.9rem',
        fontSize: '1.05rem',
        fontWeight: 800,
        letterSpacing: '-0.01em',
        color: identity.palette.onSurface,
      }}
    >
      {children}
    </h2>
  );
}

/**
 * État vide / indisponible.
 *
 * `role="alert"` uniquement pour l'indisponibilité : une verticale sans
 * résultat n'est pas une erreur, une API en panne si.
 */
export function SurfaceNotice({
  identity,
  children,
  alert = false,
}: {
  identity: SurfaceIdentity;
  children: ReactNode;
  alert?: boolean;
}) {
  return (
    <p
      role={alert ? 'alert' : undefined}
      style={{
        margin: 0,
        padding: '1rem 1.1rem',
        borderRadius: '0.75rem',
        border: `1px dashed ${identity.palette.muted}55`,
        color: identity.palette.muted,
        fontSize: '0.9rem',
      }}
    >
      {children}
    </p>
  );
}

/** Ligne de métadonnées (ville · catégorie · note). */
export function SurfaceMeta({
  identity,
  children,
}: {
  identity: SurfaceIdentity;
  children: ReactNode;
}) {
  return (
    <p style={{ margin: 0, color: identity.palette.muted, fontSize: '0.85rem' }}>{children}</p>
  );
}

/** CTA principal d'une vitrine — un seul par écran, toujours la même forme. */
export function SurfaceCta({
  identity,
  href,
  children,
}: {
  identity: SurfaceIdentity;
  href: string;
  children: ReactNode;
}) {
  return (
    <a
      href={href}
      data-surface-cta=""
      style={{
        display: 'inline-block',
        padding: '0.7rem 1.2rem',
        borderRadius: '0.6rem',
        background: identity.palette.primary,
        color: identity.palette.onPrimary,
        fontWeight: 600,
        textDecoration: 'none',
      }}
    >
      {children}
    </a>
  );
}
