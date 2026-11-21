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
import type { ShopView } from '../application/load-shop-view';

/** Catégories affichées par page sur la fiche d'une boutique. */
const CATEGORIES_PER_PAGE = 12;

/**
 * Rendu de la vitrine Retail (#9594).
 *
 * ## Composition propre à la verticale
 *
 * Le retail est un usage **de catalogue** : on compare, on balaie, on repère
 * une catégorie. D'où :
 *
 *  1. **Grille dense** (`min 14rem`) : le plus grand nombre de catégories par
 *     écran — l'inverse de la pharmacie et de l'hôtellerie.
 *  2. **Le comptage produit est le signal principal** de chaque carte : c'est
 *     ce qui dit si la boutique vaut le détour.
 *  3. **La note est un badge**, pas une phrase : elle se lit en un coup d'œil
 *     dans une grille dense. Sans avis, aucun badge — jamais « 0,0 ».
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function ShopSurface({
  identity,
  view,
  locale,
  searchParams,
}: {
  identity: SurfaceIdentity;
  view: ShopView;
  locale?: string;
  /**
   * Filtres actifs de la fiche (`?q=`, `?page=`), relus depuis l'URL.
   *
   * Une boutique peut publier des dizaines de catégories : sans champ, un
   * visiteur qui cherche « boulangerie » dans une épicerie de 80 rayons doit
   * faire défiler la page entière. Le filtre est rendu côté serveur pour rester
   * partageable et crawlable.
   */
  searchParams?: { q?: string; page?: number };
}) {
  const { shop, rating, catalogLabel } = view;
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const basePath = `${identity.basePath}/${shop.slug}`;

  const categories = paginateList(
    filterByQuery(shop.categories, searchParams?.q, (category) => [category.name]),
    searchParams?.page,
    CATEGORIES_PER_PAGE,
  );

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={shop.city ?? copy.verticals.retail.eyebrow}
        action={<SurfaceCta identity={identity} href="/boutiques">{shared.allShops}</SurfaceCta>}
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '0.6rem' }}>
          <h2 style={{ margin: 0, fontSize: '1.4rem', letterSpacing: '-0.01em' }}>{shop.name}</h2>

          {rating !== null ? (
            <SurfaceBadge identity={identity} tone="accent">
              ★ {rating} ({shop.ratingCount})
            </SurfaceBadge>
          ) : (
            <SurfaceBadge identity={identity}>{shared.noReviews}</SurfaceBadge>
          )}
        </div>

        <SurfaceMeta identity={identity}>{catalogLabel}</SurfaceMeta>

        {shop.description ? (
          <p style={{ marginTop: '0.9rem', maxWidth: '46rem' }}>{shop.description}</p>
        ) : null}

        {shop.categories.length > 0 ? (
          <section style={{ marginTop: '2rem' }}>
            <SurfaceSectionTitle identity={identity}>
              {copy.verticals.retail.exploreCta}
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

            {categories.items.length === 0 ? (
              <SurfaceNotice identity={identity}>{shared.empty}</SurfaceNotice>
            ) : (
              <SurfaceGrid min="14rem">
                {categories.items.map((category) => (
                  <SurfaceCard key={category.name} as="li" identity={identity}>
                    <strong style={{ fontSize: '0.95rem' }}>{category.name}</strong>
                    <SurfaceMeta identity={identity}>
                      {category.productsCount}{' '}
                      {category.productsCount > 1 ? shared.products : shared.product}
                    </SurfaceMeta>
                  </SurfaceCard>
                ))}
              </SurfaceGrid>
            )}

            <SurfacePagination
              identity={identity}
              basePath={basePath}
              page={categories.page}
              lastPage={categories.lastPage}
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
