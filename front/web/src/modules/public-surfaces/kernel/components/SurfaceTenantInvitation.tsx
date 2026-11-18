import type { CSSProperties } from 'react';

import { getTenantInvitationCopy } from '../../copy/surfaces-copy';
import { tenantSignupHref } from '../domain/tenant-invitation';
import type { SurfaceIdentity } from '../domain/surface-identity';

/**
 * Bandeau « Devenir tenant » d'une vitrine verticale (#9614).
 *
 * ## Pourquoi un bandeau et pas un CTA dans le héros
 *
 * Le héros d'une vitrine porte l'action du **grand public** (explorer les
 * adresses, réserver). Y ajouter un second bouton de même poids mettrait en
 * concurrence deux publics qui ne cherchent pas la même chose, et le visiteur
 * grand public — l'immense majorité du trafic — verrait son parcours brouillé.
 *
 * Le bandeau vit donc **en fin de page**, après le contenu : le professionnel
 * qui a parcouru l'annuaire et n'a pas trouvé son établissement tombe dessus
 * naturellement. C'est aussi la place où il ne peut pas être confondu avec une
 * action de réservation.
 *
 * ## Ce qu'il fait exactement
 *
 * Il produit un lien vers l'espace professionnel **existant**
 * (`/business/signup`) avec la verticale pré-renseignée. Aucun tunnel n'est
 * dupliqué, aucun espace n'est déplacé : la vitrine ne fait qu'adresser
 * correctement ce qui existe déjà.
 *
 * Composant pur : il reçoit l'identité et la locale, ne fait aucun fetch et ne
 * lit aucun état global — testable sans réseau.
 */
export function SurfaceTenantInvitation({
  identity,
  locale,
}: {
  identity: SurfaceIdentity;
  locale?: string | null;
}) {
  const copy = getTenantInvitationCopy(locale);
  const { palette } = identity;

  const style: CSSProperties = {
    marginTop: '3rem',
    padding: '1.5rem 1.6rem',
    borderRadius: '1rem',
    border: `1px solid ${palette.muted}33`,
    background: `${palette.accent}0F`,
    display: 'flex',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '1.25rem',
  };

  return (
    <aside style={style} data-surface-tenant-invitation={identity.vertical}>
      <div style={{ minWidth: '16rem', flex: '1 1 22rem' }}>
        <p
          style={{
            margin: 0,
            fontSize: '0.72rem',
            letterSpacing: '0.14em',
            textTransform: 'uppercase',
            color: palette.muted,
          }}
        >
          {copy.eyebrow}
        </p>

        <h2
          style={{
            margin: '0.4rem 0 0',
            fontSize: '1.15rem',
            fontWeight: 800,
            letterSpacing: '-0.01em',
            color: palette.onSurface,
          }}
        >
          {copy.titles[identity.vertical]}
        </h2>

        <p style={{ margin: '0.5rem 0 0', fontSize: '0.9rem', color: palette.muted }}>
          {copy.body}
        </p>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
        <a
          href={tenantSignupHref(identity.vertical, locale)}
          data-surface-tenant-cta=""
          style={{
            display: 'inline-block',
            padding: '0.7rem 1.2rem',
            borderRadius: '0.6rem',
            background: palette.primary,
            color: palette.onPrimary,
            fontWeight: 600,
            textDecoration: 'none',
            textAlign: 'center',
          }}
        >
          {copy.cta}
        </a>

        <span style={{ fontSize: '0.75rem', color: palette.muted, textAlign: 'center' }}>
          {copy.note}
        </span>
      </div>
    </aside>
  );
}
