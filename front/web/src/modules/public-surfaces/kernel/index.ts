/**
 * Noyau des vitrines publiques verticales.
 *
 * Le noyau ne connaît **aucune** verticale : il fournit les contrats
 * (identité, registre, résolution) et le rendu générique piloté par l'identité.
 * Chaque verticale apporte son domaine, ses cas d'usage, son adaptateur et son
 * identité propre.
 */

export {
  AA_LARGE,
  AA_NORMAL,
  contrastRatio,
  meetsContrast,
  parseHex,
  pickReadableOn,
  relativeLuminance,
} from './domain/contrast';

export {
  SURFACE_VERTICALS,
  assertSurfaceIdentity,
  buildPalette,
  type SurfaceIdentity,
  type SurfacePalette,
  type SurfaceVertical,
} from './domain/surface-identity';

export {
  createSurfaceRegistry,
  type SurfaceRegistry,
} from './domain/surface-registry';

export {
  TENANT_INVITATIONS,
  TENANT_SIGNUP_PATH,
  tenantSignupHref,
  type TenantInvitation,
} from './domain/tenant-invitation';

export { requireSurface, resolveSurface } from './application/resolve-surface';

export {
  SURFACE_DOMAIN_ENV,
  resolveSurfaceDomain,
  surfaceUrl,
} from './application/resolve-surface-domain';

export { SurfaceHero } from './components/SurfaceHero';
export { SurfacePagination } from './components/SurfacePagination';
export { SurfaceShell } from './components/SurfaceShell';
export { SurfaceTenantInvitation } from './components/SurfaceTenantInvitation';
export {
  SurfaceBadge,
  SurfaceCard,
  SurfaceCta,
  SurfaceGrid,
  SurfaceMeta,
  SurfaceNotice,
  SurfaceSectionTitle,
} from './components/SurfacePrimitives';
