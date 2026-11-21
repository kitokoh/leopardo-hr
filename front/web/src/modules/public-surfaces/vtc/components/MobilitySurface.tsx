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
import type { MobilitySurfaceResult } from '../application/load-mobility-surface';

/**
 * Rendu de la vitrine Mobilité — VTC / taxi (#9622).
 *
 * ## Composition propre à la verticale
 *
 * Une course se choisit **par ville et par opérateur disponible maintenant**,
 * pas en parcourant un catalogue de produits. D'où trois choix qui la
 * distinguent des six autres vitrines :
 *
 *  1. **Liste en lignes, pas en grille.** Comme Billets, mais pour une raison
 *     différente : ici la comparaison porte sur des *prestataires*, et le nom
 *     commercial doit se lire d'un seul balayage vertical. Une grille de
 *     vignettes ferait ressembler la mobilité à un annuaire de boutiques.
 *  2. **La ville d'ancrage est le premier signal** après le nom : c'est le
 *     seul critère de choix réellement publié. Le provider ne divulgue ni
 *     position de chauffeur, ni flotte, ni note — la carte n'affiche donc rien
 *     de tel. Afficher une note « 0,0 » serait un mensonge, l'omettre un choix
 *     assumé.
 *  3. **Un CTA « Demander un trajet », jamais « Réserver ».** Le provider ne
 *     déclare que l'action `contact` (la course se demande par
 *     `POST /public/vtc/marketplace/rides`, qui exige une origine ET une
 *     destination). Un bouton « Réserver » ici serait inopérant — exactement ce
 *     que le contrat de découverte interdit.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function MobilitySurface({
  identity,
  result,
  locale,
  searchParams,
}: {
  identity: SurfaceIdentity;
  result: MobilitySurfaceResult;
  locale?: string;
  /** Filtres actifs, relus depuis l'URL pour pré-remplir la barre de recherche. */
  searchParams?: { q?: string; city?: string };
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;
  const vertical = copy.verticals.vtc;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={vertical.eyebrow}
        action={
          vertical.actionHref && vertical.actionCta ? (
            <SurfaceCta identity={identity} href={vertical.actionHref}>
              {vertical.actionCta}
            </SurfaceCta>
          ) : undefined
        }
      />

      <main style={{ maxWidth: '64rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
        {/* #9697 — une course se cherche par ville : la barre de recherche est
            le premier contrôle, avant la liste des opérateurs. */}
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
              display: 'grid',
              gap: '0.75rem',
            }}
          >
            {result.cards.map(({ operator, meta, currency }) => (
              <SurfaceCard key={operator.slug} as="li" interactive identity={identity}>
                <Link
                  href={`${identity.basePath}/${operator.slug}`}
                  data-surface-card-link=""
                  style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '0.75rem',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                  }}
                >
                  <div style={{ minWidth: '12rem' }}>
                    <strong style={{ display: 'block', fontSize: '1rem' }}>
                      {operator.name}
                    </strong>
                    <SurfaceMeta identity={identity}>
                      {meta ?? shared.allAddresses}
                    </SurfaceMeta>
                  </div>

                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.75rem',
                      marginLeft: 'auto',
                    }}
                  >
                    {currency ? (
                      <SurfaceBadge identity={identity}>{currency}</SurfaceBadge>
                    ) : null}
                    <SurfaceBadge identity={identity} tone="accent">
                      {vertical.bookableLabel}
                    </SurfaceBadge>
                  </div>
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
