import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceGrid,
  SurfaceHero,
  SurfaceMeta,
  SurfaceNotice,
  SurfacePagination,
  SurfaceSearchBar,
  SurfaceSectionTitle,
  SurfaceShell,
  SurfaceTenantInvitation,
  filterByQuery,
  paginateList,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { PropertyView } from '../application/load-property-view';
import { formatMoney } from '../domain/property';

/** Types de chambre affichés par page sur la fiche d'un hébergement. */
const ROOM_TYPES_PER_PAGE = 12;

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
  searchParams,
}: {
  identity: SurfaceIdentity;
  view: PropertyView;
  locale?: string;
  /** Filtres actifs de la fiche (`?q=`, `?page=`), relus depuis l'URL. */
  searchParams?: { q?: string; page?: number };
}) {
  const { property, priceLabel, stars } = view;
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const basePath = `${identity.basePath}/${property.slug}`;

  const roomTypes = paginateList(
    filterByQuery(property.roomTypes, searchParams?.q, (roomType) => [
      roomType.name,
      roomType.description,
    ]),
    searchParams?.page,
    ROOM_TYPES_PER_PAGE,
  );

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

            <SurfaceSearchBar
              identity={identity}
              basePath={basePath}
              defaultQuery={searchParams?.q}
              labels={{
                searchLabel: shared.searchLabel,
                searchPlaceholder: shared.searchPlaceholder,
                searchSubmit: shared.searchSubmit,
                searchReset: shared.searchReset,
              }}
            />

            {roomTypes.items.length === 0 ? (
              <SurfaceNotice identity={identity}>{shared.empty}</SurfaceNotice>
            ) : (
              <SurfaceGrid min="20rem">
                {roomTypes.items.map((roomType) => (
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
            )}

            <SurfacePagination
              identity={identity}
              basePath={basePath}
              page={roomTypes.page}
              lastPage={roomTypes.lastPage}
              searchParams={{ q: searchParams?.q }}
              labels={{
                previous: shared.previousPage,
                next: shared.nextPage,
                pageOf: shared.pageOf,
              }}
            />
          </section>
        ) : null}
        <SurfaceTenantInvitation identity={identity} locale={locale} />
      </main>
    </SurfaceShell>
  );
}
