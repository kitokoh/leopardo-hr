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
  SurfaceShell,
  SurfaceTenantInvitation,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { ShopDirectoryResult } from '../application/load-shop-directory-view';

/**
 * Annuaire de la vitrine Retail (#9618).
 *
 * ## Composition propre à la verticale
 *
 * Le retail est un usage de **parcours** : on balaie beaucoup de boutiques
 * rapidement, on s'arrête sur une catégorie. D'où :
 *
 *  1. **Grille dense** (`min 14rem`) — le plus grand nombre de boutiques par
 *     écran, l'inverse de la grille large pharmacie.
 *  2. **La catégorie est le signal principal**, en badge accent : c'est le
 *     critère de tri mental du visiteur (« boulangerie », « fleuriste »).
 *  3. **La ville en métadonnée discrète** : elle filtre, elle ne décide pas.
 *
 * Composant pur : aucun fetch, aucun état. Trois états distincts — une
 * verticale pas encore alimentée ne doit jamais ressembler à une recherche
 * sans résultat.
 */
export function ShopDirectorySurface({
  identity,
  result,
  locale,
  searchParams,
}: {
  identity: SurfaceIdentity;
  result: ShopDirectoryResult;
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
        eyebrow={copy.verticals.retail.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/decouvrir">
            {shared.allShops}
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
          <SurfaceGrid min="14rem">
            {result.cards.map(({ entry, meta }) => (
              <SurfaceCard key={entry.slug} as="li" interactive identity={identity}>
                <Link href={`${identity.basePath}/${entry.slug}`} data-surface-card-link="">
                  {/* La catégorie passe avant le nom : c'est le critère de tri. */}
                  {entry.category ? (
                    <div style={{ marginBottom: '0.35rem' }}>
                      <SurfaceBadge identity={identity} tone="accent">
                        {entry.category}
                      </SurfaceBadge>
                    </div>
                  ) : null}

                  <strong
                    style={{
                      fontSize: '1.05rem',
                      lineHeight: 1.25,
                      letterSpacing: '-0.01em',
                      display: 'block',
                    }}
                  >
                    {entry.name}
                  </strong>

                  <SurfaceMeta identity={identity}>{meta ?? shared.shop}</SurfaceMeta>

                  {entry.description ? (
                    <p
                      style={{
                        margin: '0.35rem 0 0',
                        fontSize: '0.85rem',
                        lineHeight: 1.5,
                        color: identity.palette.muted,
                        // Deux lignes maximum : une carte d'annuaire dense ne
                        // doit pas être déformée par une description longue.
                        display: '-webkit-box',
                        WebkitLineClamp: 2,
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
