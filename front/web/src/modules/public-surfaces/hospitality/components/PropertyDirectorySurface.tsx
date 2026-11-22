import Link from 'next/link';

import {
  SurfaceCard,
  SurfaceCta,
  SurfaceGrid,
  SurfaceHero,
  SurfaceMeta,
  SurfaceNotice,
  SurfacePagination,
  SurfaceSearchBar,
  SurfaceShell,
  SurfaceTenantInvitation,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { PropertyDirectoryResult } from '../application/load-property-directory-view';

/**
 * Annuaire de la vitrine Hôtellerie (#9618).
 *
 * ## Composition propre à la verticale
 *
 * Le séjour est un usage de **comparaison lente** : on regarde peu d'options,
 * on lit, on hésite. D'où :
 *
 *  1. **Grille large** (`min 20rem`) — peu d'hébergements par écran, de la
 *     respiration. L'inverse exact du retail dense.
 *  2. **Le type d'hébergement en tête**, en métadonnée structurée : c'est ce
 *     qui cadre l'attente (« hôtel » et « maison d'hôtes » ne se comparent pas).
 *  3. **Aucun prix dans l'annuaire** : l'agrégateur public ne le publie pas au
 *     niveau liste. L'inventer serait pire que de l'omettre — le prix d'appel
 *     n'apparaît que sur la fiche, où il est réellement connu.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function PropertyDirectorySurface({
  identity,
  result,
  locale,
  searchParams,
}: {
  identity: SurfaceIdentity;
  result: PropertyDirectoryResult;
  locale?: string;
  /** Filtres courants, conservés par les liens de pagination. */
  searchParams?: Readonly<Record<string, string | undefined>>;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.hospitality.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/decouvrir">
            {shared.allStays}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <SurfaceSearchBar
          identity={identity}
          basePath={identity.basePath}
          defaultQuery={searchParams?.q}
          defaultCity={searchParams?.city}
          labels={{
            searchLabel: shared.searchLabel,
            searchPlaceholder: shared.searchPlaceholder,
            cityLabel: shared.cityLabel,
            cityPlaceholder: shared.cityPlaceholder,
            searchSubmit: shared.searchSubmit,
            searchReset: shared.searchReset,
          }}
        />

        {result.state === 'unavailable' ? (
          <SurfaceNotice identity={identity} alert>
            {shared.unavailable}
          </SurfaceNotice>
        ) : null}

        {result.state === 'empty' ? (
          <SurfaceNotice identity={identity}>{shared.comingSoon}</SurfaceNotice>
        ) : null}

        {result.state === 'ready' ? (
          <SurfaceGrid min="20rem">
            {result.cards.map(({ entry, meta }) => (
              <SurfaceCard key={entry.slug} as="li" interactive identity={identity}>
                <Link href={`${identity.basePath}/${entry.slug}`} data-surface-card-link="">
                  <strong
                    style={{
                      fontSize: '1.15rem',
                      lineHeight: 1.25,
                      letterSpacing: '-0.01em',
                      fontWeight: 700,
                      display: 'block',
                    }}
                  >
                    {entry.name}
                  </strong>

                  <SurfaceMeta identity={identity}>{meta ?? shared.property}</SurfaceMeta>

                  {entry.description ? (
                    <p
                      style={{
                        margin: '0.4rem 0 0',
                        fontSize: '0.9rem',
                        lineHeight: 1.55,
                        color: identity.palette.muted,
                        display: '-webkit-box',
                        WebkitLineClamp: 3,
                        WebkitBoxOrient: 'vertical',
                        overflow: 'hidden',
                      }}
                    >
                      {entry.description}
                    </p>
                  ) : null}
                </Link>
              </SurfaceCard>
            ))}
          </SurfaceGrid>
        ) : null}

        {result.state === 'ready' ? (
          <SurfacePagination
            identity={identity}
            basePath={identity.basePath}
            page={result.page}
            lastPage={result.lastPage}
            searchParams={searchParams}
            labels={{
              previous: shared.previousPage,
              next: shared.nextPage,
              pageOf: shared.pageOf,
            }}
          />
        ) : null}

        <SurfaceTenantInvitation identity={identity} locale={locale} />
      </main>
    </SurfaceShell>
  );
}
