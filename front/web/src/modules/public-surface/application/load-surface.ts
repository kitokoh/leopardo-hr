/**
 * #9580 (BC-41 BRANDING) — cas d'usage : charger une surface publique.
 *
 * ## La couture entre application et infrastructure
 *
 * C'est le SEUL fichier de la couche application qui touche l'infrastructure,
 * et il ne fait que la brancher. `resolveSurface()` (la composition des règles)
 * reste pur et testable sans réseau : un test peut lui passer un `PublicBrand`
 * littéral.
 *
 * Ce découpage permet aussi de tester le cas d'usage complet en substituant le
 * chargeur, sans monter de serveur HTTP.
 */

import { parsePublicBrand, type PublicBrand } from '../domain/brand';
import { fetchPublicBrand, fetchPublicBrandByHost } from '../infrastructure/brand-api';
import { resolveSurface, type ResolvedSurface } from './resolve-surface';

/** Signature d'un chargeur — substituable en test. */
export type SurfaceLoader = (slug: string, lang?: string) => Promise<unknown>;

export interface LoadSurfaceOptions {
  /** Substitution pour les tests — par défaut, l'appel API réel. */
  loader?: SurfaceLoader;
  requestedLang?: string | null;
}

/**
 * Charge puis résout une surface par slug. `null` = `notFound()`.
 */
export async function loadSurfaceBySlug(
  slug: string,
  options: LoadSurfaceOptions = {},
): Promise<ResolvedSurface | null> {
  const loader: SurfaceLoader = options.loader ?? fetchPublicBrand;
  const raw = await loader(slug, options.requestedLang ?? undefined);

  if (!raw) {
    return null;
  }

  // Le chargeur par défaut renvoie déjà un DTO parsé ; une substitution de
  // test peut renvoyer un objet brut. On revalide dans les deux cas : une
  // seule porte d'entrée pour la donnée, donc le rendu ne voit jamais un
  // objet non vérifié.
  const brand: PublicBrand | null = parsePublicBrand(raw);

  return brand ? resolveSurface(brand, options.requestedLang) : null;
}

/**
 * Charge puis résout une surface par domaine (domaine personnalisé).
 */
export async function loadSurfaceByHost(
  host: string,
  options: LoadSurfaceOptions = {},
): Promise<ResolvedSurface | null> {
  const raw = await fetchPublicBrandByHost(host, options.requestedLang ?? undefined);

  if (!raw) {
    return null;
  }

  return resolveSurface(raw, options.requestedLang);
}
