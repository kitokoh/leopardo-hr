import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceGrid,
  SurfaceHero,
  SurfaceMeta,
  SurfaceSectionTitle,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { PropertyView } from '../application/load-property-view';
import { formatMoney } from '../domain/property';

/**
 * Rendu de la vitrine Hôtellerie (#9594).
 *
 * ## Composition propre à la verticale
 *
 * L'hôtellerie se choisit **sur la photo et le prix d'appel**. D'où :
 *
 *  1. **Grille large** (`min 20rem`) : une chambre se regarde, elle ne se
 *     compare pas dans une grille dense.
 *  2. **Le prix d'appel est mis en avant** en tête de fiche, avec la mention
 *     « à partir de » — et il vient de `fromPrice()`, le vrai minimum, jamais
 *     du premier élément de la liste.
 *  3. **Les étoiles sont un badge**, les équipements une ligne de métadonnées :
 *     la hiérarchie suit ce que le voyageur regarde d'abord.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function PropertySurface({
  identity,
  view,
  locale,
}: {
  identity: SurfaceIdentity;
  view: PropertyView;
  locale?: string;
}) {
  const { property, priceLabel, stars } = view;
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={
          [property.city, property.country].filter(Boolean).join(', ') ||
          copy.verticals.hospitality.eyebrow
        }
        action={<SurfaceCta identity={identity} href="/stay">{shared.allStays}</SurfaceCta>}
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '0.6rem' }}>
          <h2 style={{ margin: 0, fontSize: '1.4rem', letterSpacing: '-0.01em' }}>
            {property.name}
          </h2>

          {stars ? (
            <SurfaceBadge identity={identity} tone="accent">
              {stars}
            </SurfaceBadge>
          ) : null}
        </div>

        {/* Prix d'appel : le vrai minimum, calculé par le domaine. */}
        <p style={{ margin: '0.6rem 0 0', fontSize: '1.05rem', fontWeight: 700 }}>
          {priceLabel
            ? `${shared.from} ${priceLabel} ${shared.perNight}`
            : shared.priceOnRequest}
        </p>

        {property.amenities.length > 0 ? (
          <SurfaceMeta identity={identity}>{property.amenities.join(' · ')}</SurfaceMeta>
        ) : null}

        {property.roomTypes.length > 0 ? (
          <section style={{ marginTop: '2rem' }}>
            <SurfaceSectionTitle identity={identity}>
              {copy.verticals.hospitality.eyebrow}
            </SurfaceSectionTitle>

            <SurfaceGrid min="20rem">
              {property.roomTypes.map((roomType) => (
                <SurfaceCard key={roomType.code} as="li" identity={identity}>
                  <strong style={{ fontSize: '1rem' }}>{roomType.name}</strong>

                  {roomType.description ? (
                    <p style={{ margin: 0, fontSize: '0.875rem', lineHeight: 1.45 }}>
                      {roomType.description}
                    </p>
                  ) : null}

                  <SurfaceMeta identity={identity}>
                    {formatMoney(roomType.basePriceMinor, roomType.currency) ??
                      shared.priceOnRequest}
                  </SurfaceMeta>
                </SurfaceCard>
              ))}
            </SurfaceGrid>
          </section>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
