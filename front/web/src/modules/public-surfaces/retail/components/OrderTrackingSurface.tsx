import {
  SurfaceBadge,
  SurfaceCard,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceSectionTitle,
  SurfaceShell,
  type SurfaceIdentity,
} from '../../kernel';
import { getSurfaceCopy } from '../../copy/surfaces-copy';
import type { OrderTrackingView } from '../application/load-order-tracking';

/**
 * Suivi de commande de la vitrine Retail (#9627).
 *
 * Repris de `front/marketplace/src/app/suivi/page.tsx`. Composition propre :
 * la timeline est **linéaire et ordonnée**, pas une liste de badges — le
 * client cherche « où en est ma commande », pas « quels statuts existent ».
 *
 * Une commande annulée n'affiche aucune étape franchie : `reachedSteps` rend
 * un ensemble vide pour `cancelled`, et le composant le respecte.
 *
 * Composant pur : aucun fetch, aucun état.
 */
export function OrderTrackingSurface({
  identity,
  tracking,
  locale,
}: {
  identity: SurfaceIdentity;
  tracking: OrderTrackingView | null;
  locale?: string;
}) {
  const copy = getSurfaceCopy(locale);
  const shared = copy.shared;

  return (
    <SurfaceShell identity={identity}>
      <main style={{ maxWidth: '48rem', margin: '0 auto', padding: '2.5rem 1.25rem 4rem' }}>
        <SurfaceSectionTitle identity={identity}>{shared.trip}</SurfaceSectionTitle>

        {tracking === null ? (
          <SurfaceNotice identity={identity} alert>
            {shared.unavailable}
          </SurfaceNotice>
        ) : (
          <SurfaceCard identity={identity}>
            <div
              style={{
                display: 'flex',
                alignItems: 'baseline',
                justifyContent: 'space-between',
                gap: '0.75rem',
              }}
            >
              <strong style={{ fontFamily: 'monospace', fontSize: '0.95rem' }}>
                {tracking.reference}
              </strong>
              <SurfaceBadge identity={identity} tone="accent">
                {tracking.statusLabel}
              </SurfaceBadge>
            </div>

            <ol
              style={{
                listStyle: 'none',
                margin: '1.25rem 0 0',
                padding: 0,
                display: 'grid',
                gap: '0.5rem',
              }}
            >
              {tracking.steps.map((step) => (
                <li
                  key={step.status}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.6rem',
                    fontSize: '0.9rem',
                    color: step.reached ? identity.palette.onSurface : identity.palette.muted,
                    fontWeight: step.reached ? 600 : 400,
                  }}
                >
                  <span
                    aria-hidden
                    style={{
                      display: 'inline-block',
                      width: '0.6rem',
                      height: '0.6rem',
                      borderRadius: '50%',
                      background: step.reached ? identity.palette.primary : `${identity.palette.muted}44`,
                    }}
                  />
                  {step.label}
                  {step.at ? (
                    <SurfaceMeta identity={identity}>{step.at.slice(0, 10)}</SurfaceMeta>
                  ) : null}
                </li>
              ))}
            </ol>

            {tracking.items.length > 0 ? (
              <ul style={{ listStyle: 'none', margin: '1.25rem 0 0', padding: 0 }}>
                {tracking.items.map((item, index) => (
                  <li
                    key={`${item.name}-${index}`}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      gap: '0.75rem',
                      padding: '0.4rem 0',
                      borderTop: `1px solid ${identity.palette.muted}22`,
                      fontSize: '0.9rem',
                    }}
                  >
                    <span>
                      {item.name} <span style={{ color: identity.palette.muted }}>× {item.quantity}</span>
                    </span>
                    <span style={{ fontVariantNumeric: 'tabular-nums' }}>
                      {item.lineTotalLabel ?? shared.priceOnRequest}
                    </span>
                  </li>
                ))}
              </ul>
            ) : null}

            <div style={{ marginTop: '1rem', textAlign: 'right' }}>
              <strong style={{ fontSize: '1.05rem' }}>
                {tracking.totalLabel ?? shared.priceOnRequest}
              </strong>
            </div>
          </SurfaceCard>
        )}
      </main>
    </SurfaceShell>
  );
}
