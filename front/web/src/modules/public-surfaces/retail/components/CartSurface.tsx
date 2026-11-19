import Link from 'next/link';

import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceSectionTitle,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { CartView } from '../application/load-cart';

/**
 * Panier de la vitrine Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/panier/page.tsx`. Composition propre :
 * le panier est **groupé par vendeur**, parce qu'une commande = un vendeur
 * côté API. Afficher un total unique masquerait qu'il y aura plusieurs
 * commandes — le client découvrirait la séparation au paiement.
 *
 * Composant pur : aucun fetch, aucun état. Les mutations passent par les
 * actions du cas d'usage, appelées depuis un îlot client.
 */
export function CartSurface({
  identity,
  cart,
  locale,
}: {
  identity: SurfaceIdentity;
  cart: CartView;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <main style={{ maxWidth: '56rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <SurfaceSectionTitle identity={identity}>{shared.allShops}</SurfaceSectionTitle>

        {cart.empty ? (
          <SurfaceNotice identity={identity}>{shared.catalogEmpty}</SurfaceNotice>
        ) : (
          <div style={{ display: 'grid', gap: '1.25rem' }}>
            {cart.groups.map((group) => (
              <SurfaceCard key={group.sellerSlug} identity={identity}>
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'baseline',
                    justifyContent: 'space-between',
                    gap: '0.75rem',
                  }}
                >
                  <strong style={{ fontSize: '1rem' }}>{group.sellerName}</strong>
                  <SurfaceMeta identity={identity}>
                    {group.sellerCity ?? shared.shop}
                  </SurfaceMeta>
                </div>

                <ul style={{ listStyle: 'none', margin: '0.75rem 0 0', padding: 0 }}>
                  {group.items.map((item) => (
                    <li
                      key={item.productId}
                      style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        gap: '0.75rem',
                        padding: '0.45rem 0',
                        borderTop: `1px solid ${identity.palette.muted}22`,
                        fontSize: '0.9rem',
                      }}
                    >
                      <span>
                        {item.name} <span style={{ color: identity.palette.muted }}>× {item.quantity}</span>
                      </span>
                      <span style={{ fontVariantNumeric: 'tabular-nums' }}>
                        {((item.priceMinor * item.quantity) / 100).toFixed(2)} {item.currency}
                      </span>
                    </li>
                  ))}
                </ul>

                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    marginTop: '0.75rem',
                  }}
                >
                  <SurfaceBadge identity={identity} tone="accent">
                    {group.subtotalLabel ?? shared.priceOnRequest}
                  </SurfaceBadge>

                  <SurfaceCta identity={identity} href={`/commande?seller=${group.sellerSlug}`}>
                    {shared.bookable}
                  </SurfaceCta>
                </div>
              </SurfaceCard>
            ))}

            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                paddingTop: '0.5rem',
              }}
            >
              <strong style={{ fontSize: '1.1rem' }}>{cart.totalLabel ?? shared.priceOnRequest}</strong>
              <Link
                href="/produits"
                style={{ fontSize: '0.85rem', color: identity.palette.primary, textDecoration: 'none' }}
              >
                {shared.allShops}
              </Link>
            </div>
          </div>
        )}
      </main>
    </SurfaceShell>
  );
}
