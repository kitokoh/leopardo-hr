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
  SurfaceShell,
  SurfaceTenantInvitation,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { CompanyDirectoryResult } from '../application/load-company-directory-view';

/**
 * Annuaire de la vitrine B2B (#9618).
 *
 * ## Composition propre à la verticale
 *
 * Le B2B est un usage de **lecture dense et de qualification** : un acheteur
 * professionnel compare des fournisseurs sur des critères précis (catégories,
 * ville), pas sur une photo. D'où :
 *
 *  1. **Une seule colonne dense** — pas de grille. Une liste verticale se lit
 *     comme un annuaire professionnel, et laisse la place aux catégories.
 *  2. **Les catégories de produits en badges** : c'est le filtre réel.
 *  3. **Aucun badge de direction dans l'annuaire** : l'agrégateur public ne
 *     publie pas import/export au niveau liste. Les afficher supposerait de les
 *     inventer ; ils apparaissent sur la fiche, où ils sont réellement connus.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function CompanyDirectorySurface({
  identity,
  result,
  locale,
  searchParams,
}: {
  identity: SurfaceIdentity;
  result: CompanyDirectoryResult;
  locale?: string;
  /** Filtres actifs, relus depuis l'URL pour pré-remplir la barre de recherche. */
  searchParams?: { q?: string; city?: string };
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.trade.eyebrow}
        action={
          <SurfaceCta identity={identity} href="/decouvrir">
            {shared.allCompanies}
          </SurfaceCta>
        }
      />

      <main style={{ maxWidth: '56rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {/* #9697 — recherche et pagination câblées comme sur retail/hospitality.
            Formulaire GET natif : l'URL reste partageable et le filtre
            fonctionne sans JavaScript. */}
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
          <ul
            style={{
              listStyle: 'none',
              margin: 0,
              padding: 0,
              display: 'flex',
              flexDirection: 'column',
              gap: '0.75rem',
            }}
          >
            {result.cards.map(({ entry, categories }) => (
              <SurfaceCard key={entry.slug} as="li" interactive identity={identity}>
                <Link href={`${identity.basePath}/${entry.slug}`} data-surface-card-link="">
                  <div
                    style={{
                      display: 'flex',
                      flexWrap: 'wrap',
                      alignItems: 'baseline',
                      justifyContent: 'space-between',
                      gap: '0.5rem',
                    }}
                  >
                    <strong style={{ fontSize: '1.05rem', lineHeight: 1.25 }}>{entry.name}</strong>
                    {entry.city ? (
                      <SurfaceMeta identity={identity}>{entry.city}</SurfaceMeta>
                    ) : null}
                  </div>

                  {categories.length > 0 ? (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.35rem' }}>
                      {categories.map((category) => (
                        <SurfaceBadge key={category} identity={identity}>
                          {category}
                        </SurfaceBadge>
                      ))}
                    </div>
                  ) : null}

                  {entry.description ? (
                    <p style={{ margin: 0, fontSize: '0.9rem', lineHeight: 1.5 }}>
                      {entry.description}
                    </p>
                  ) : null}
                </Link>
              </SurfaceCard>
            ))}
          </ul>
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
