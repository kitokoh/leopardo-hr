import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceGrid,
  SurfaceHero,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { PharmacySurfaceResult } from '../application/load-pharmacy-surface';

/**
 * Rendu de la vitrine Pharmacie (#9594).
 *
 * ## Composition propre à la verticale
 *
 * La pharmacie est un usage **de proximité et d'urgence** : on ne parcourt pas
 * un catalogue, on cherche l'officine la plus proche et celle qui est de garde.
 * D'où trois choix qui la distinguent des cinq autres vitrines :
 *
 *  1. **La garde est le premier signal**, pas une métadonnée : badge accent en
 *     tête de carte, avant le nom. C'est l'information qui fait choisir.
 *  2. **Grille large** (`min 20rem`) : peu d'officines par écran, lisibilité
 *     maximale — l'inverse du retail dense.
 *  3. **Aucun CTA « Commander »** : la vente en ligne de médicaments est
 *     réglementée. Le provider n'expose que l'action `contact`, et la vitrine
 *     ne promet donc rien de plus.
 *
 * Composant pur : aucun fetch, aucun état. Les trois états (`ready`, `empty`,
 * `unavailable`) sont rendus différemment — une verticale pas encore alimentée
 * ne doit jamais ressembler à une recherche sans résultat.
 */
export function PharmacySurface({
  identity,
  result,
  locale,
}: {
  identity: SurfaceIdentity;
  result: PharmacySurfaceResult;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <SurfaceHero
        identity={identity}
        eyebrow={copy.verticals.pharmacy.eyebrow}
        action={<SurfaceCta identity={identity} href="/decouvrir">{shared.allAddresses}</SurfaceCta>}
      />

      <main style={{ maxWidth: '72rem', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
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
            {result.cards.map(({ pharmacy, duty }) => (
              <SurfaceCard key={pharmacy.slug} as="li" interactive identity={identity}>
                <Link
                  href={`${identity.basePath}/${pharmacy.slug}`}
                  data-surface-card-link=""
                >
                  {/* La garde passe AVANT le nom : c'est le critère de choix. */}
                  {pharmacy.onDuty ? (
                    <div>
                      <SurfaceBadge identity={identity} tone="accent">
                        {duty ?? shared.onDuty}
                      </SurfaceBadge>
                    </div>
                  ) : null}

                  <strong style={{ fontSize: '1.05rem', lineHeight: 1.25 }}>{pharmacy.name}</strong>

                  <SurfaceMeta identity={identity}>
                    {[pharmacy.city, pharmacy.onDuty ? null : duty].filter(Boolean).join(' · ') ||
                      shared.pharmacy}
                  </SurfaceMeta>

                  {pharmacy.description ? (
                    <p style={{ margin: 0, fontSize: '0.875rem', lineHeight: 1.45 }}>
                      {pharmacy.description}
                    </p>
                  ) : null}
                </Link>
              </SurfaceCard>
            ))}
          </SurfaceGrid>
        ) : null}
      </main>
    </SurfaceShell>
  );
}
