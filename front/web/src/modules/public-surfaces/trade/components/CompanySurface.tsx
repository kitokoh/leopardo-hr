import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
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
import type { CompanyView } from '../application/load-company-view';

/** Catégories de produits affichées par page sur la fiche d'une entreprise. */
const CATEGORIES_PER_PAGE = 24;

/**
 * Rendu de la vitrine B2B (#9594).
 *
 * ## Composition propre à la verticale
 *
 * Le B2B se lit **comme une fiche technique**, pas comme une vitrine
 * commerciale. D'où :
 *
 *  1. **Pas de grille de cartes** : une seule colonne, dense en information.
 *     Un acheteur professionnel compare des capacités, il ne flashe pas.
 *  2. **Les directions (import/export/domestique) sont des badges** en tête :
 *     c'est le premier filtre d'un acheteur.
 *  3. **Les catégories de produits sont des puces** en pied de fiche, pas des
 *     cartes : elles qualifient l'offre, elles ne la composent pas.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function CompanySurface({
  identity,
  view,
  locale,
  searchParams,
}: {
  identity: SurfaceIdentity;
  view: CompanyView;
  locale?: string;
  /** Filtres actifs de la fiche (`?q=`, `?page=`), relus depuis l'URL. */
  searchParams?: { q?: string; page?: number };
}) {
  const { company, location, directions } = view;
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const basePath = `${identity.basePath}/${company.slug}`;

  const categories = paginateList(
    filterByQuery(company.productCategories, searchParams?.q, (category) => [category]),
    searchParams?.page,
    CATEGORIES_PER_PAGE,
  );

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={location ?? copy.verticals.trade.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/entreprises">
            {shared.allCompanies}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '56rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        <h2 style={{ margin: 0, fontSize: '1.4rem', letterSpacing: '-0.01em' }}>{company.name}</h2>

        {directions.length > 0 ? (
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.4rem', marginTop: '0.7rem' }}>
            {directions.map((direction) => (
              <SurfaceBadge key={direction} identity={identity} tone="accent">
                {direction}
              </SurfaceBadge>
            ))}
          </div>
        ) : null}

        {company.description ? (
          <p style={{ marginTop: '1rem', lineHeight: 1.6 }}>{company.description}</p>
        ) : null}

        {company.productCategories.length > 0 ? (
          <section style={{ marginTop: '2rem' }}>
            <SurfaceSectionTitle identity={identity}>
              {copy.verticals.trade.exploreCta}
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
              <ul
                style={{
                  listStyle: 'none',
                  margin: 0,
                  padding: 0,
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: '0.5rem',
                }}
              >
                {categories.items.map((category) => (
                  <li
                    key={category}
                    style={{
                      border: `1px solid ${identity.palette.muted}33`,
                      borderRadius: '999px',
                      padding: '0.35rem 0.8rem',
                      fontSize: '0.875rem',
                      background: '#FFFFFF',
                    }}
                  >
                    {category}
                  </li>
                ))}
              </ul>
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

        {company.currency ? (
          <SurfaceMeta identity={identity}>{company.currency}</SurfaceMeta>
        ) : null}
        <SurfaceTenantInvitation identity={identity} locale={locale} />
      </main>
    </SurfaceShell>
  );
}
