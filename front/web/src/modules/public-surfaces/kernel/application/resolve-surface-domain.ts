/**
 * Cas d'usage : domaine canonique et URL absolue d'une vitrine.
 *
 * L'identité déclare un hôte **par défaut** ; l'environnement peut le
 * surcharger (préproduction, domaine client). La surcharge est explicite et
 * nommée par verticale : aucune variable globale ne peut faire pointer toutes
 * les vitrines vers le même hôte par accident.
 */

import type { SurfaceIdentity, SurfaceVertical } from '../domain/surface-identity';

/** Variable d'environnement de surcharge, par verticale. */
export const SURFACE_DOMAIN_ENV: Readonly<Record<SurfaceVertical, string>> = {
  restaurant: 'NEXT_PUBLIC_SURFACE_DOMAIN_RESTAURANT',
  retail: 'NEXT_PUBLIC_SURFACE_DOMAIN_RETAIL',
  hospitality: 'NEXT_PUBLIC_SURFACE_DOMAIN_HOSPITALITY',
  trade: 'NEXT_PUBLIC_SURFACE_DOMAIN_TRADE',
  travel: 'NEXT_PUBLIC_SURFACE_DOMAIN_TRAVEL',
  pharmacy: 'NEXT_PUBLIC_SURFACE_DOMAIN_PHARMACY',
  vtc: 'NEXT_PUBLIC_SURFACE_DOMAIN_VTC',
  careers: 'NEXT_PUBLIC_SURFACE_DOMAIN_CAREERS',
};

type EnvLike = Readonly<Record<string, string | undefined>>;

/**
 * Hôte canonique effectif : surcharge d'environnement si elle est un hôte nu
 * valide, sinon la valeur déclarée par l'identité.
 */
export function resolveSurfaceDomain(
  identity: SurfaceIdentity,
  env: EnvLike = process.env,
): string {
  const override = env[SURFACE_DOMAIN_ENV[identity.vertical]]?.trim();

  if (override && /^[a-z0-9.-]+$/i.test(override) && !override.includes('/')) {
    return override;
  }

  return identity.canonicalDomain;
}

/** URL absolue d'un chemin de la vitrine (canonical, JSON-LD, sitemap). */
export function surfaceUrl(
  identity: SurfaceIdentity,
  path = '',
  env: EnvLike = process.env,
): string {
  const domain = resolveSurfaceDomain(identity, env);
  const suffix = path === '' ? '' : path.startsWith('/') ? path : `/${path}`;

  return `https://${domain}${suffix}`;
}
