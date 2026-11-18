/**
 * #9580 (BC-41 BRANDING) — rendu des blocs déclarés par le profil.
 *
 * ## Le point d'architecture
 *
 * Le profil d'une verticale déclare `block_types` (`core.hero`,
 * `restaurant.menu`, `retail.catalog`…). Ce composant dispatch ces types vers
 * un renderer. Les types `core.*` sont fournis par le socle ; les types
 * `vertical.*` sont fournis PAR la verticale, qui enregistre son renderer.
 *
 * Conséquence : **ajouter une verticale ne modifie pas ce fichier**. Elle
 * enregistre ses renderers dans son propre module, comme elle enregistre ses
 * blocs côté API (BC PAGES). C'est la même inversion de dépendance, côté front.
 *
 * ## Un type sans renderer n'est pas une erreur
 *
 * Si un profil déclare un bloc qu'aucun renderer ne couvre, on n'affiche rien
 * plutôt que de lever : la Brand reste en ligne, simplement sans ce bloc. Un
 * `throw` ici transformerait l'oubli d'un renderer en panne de site pour tous
 * les visiteurs d'une enseigne.
 */

import type { ReactNode } from 'react';

import type { PublicBrand } from '../domain/brand';

/** Un renderer reçoit la Brand résolue et rend un fragment. */
export type SurfaceBlockRenderer = (brand: PublicBrand) => ReactNode;

/**
 * Renderers du socle.
 *
 * Volontairement minimal : le socle fournit la STRUCTURE (titre de section,
 * espacement), pas le contenu métier. Le contenu d'un menu ou d'un catalogue
 * appartient à la verticale, qui seule sait le lire.
 */
const CORE_RENDERERS: Record<string, SurfaceBlockRenderer> = {
  'core.hero': () => null, // rendu par `SurfaceHero`, pas par le dispatcher
  'core.features': (brand) => (
    <section key="core.features" className="mx-auto max-w-6xl px-6 py-[var(--brand-section-gap)]">
      <h2 className="text-2xl font-semibold tracking-tight text-slate-900">
        {brand.identity.display_name}
      </h2>
      <p className="mt-3 max-w-2xl text-slate-600">
        {brand.identity.tagline ?? `Bienvenue chez ${brand.identity.display_name}.`}
      </p>
    </section>
  ),
  'core.contact': (brand) => (
    <section
      key="core.contact"
      id="contact"
      className="mx-auto max-w-6xl px-6 py-[var(--brand-section-gap)]"
    >
      <h2 className="text-2xl font-semibold tracking-tight text-slate-900">Contact</h2>
      <p className="mt-3 text-slate-600">
        {brand.surface.host
          ? `Cette enseigne est joignable depuis ${brand.surface.host}.`
          : 'Coordonnées disponibles auprès de l’établissement.'}
      </p>
    </section>
  ),
  'core.footer': (brand) => (
    <footer
      key="core.footer"
      className="border-t border-slate-200/70 px-6 py-10 text-sm text-slate-500"
    >
      <div className="mx-auto max-w-6xl">
        © {new Date().getFullYear()} {brand.identity.display_name}
      </div>
    </footer>
  ),
};

/**
 * Registre mutable des renderers, alimenté par les verticales.
 *
 * Construit à l'import du module, une fois. Une verticale qui veut rendre ses
 * propres blocs appelle `registerSurfaceBlockRenderers({...})` depuis son
 * module ; elle n'a pas besoin que `public-surface` connaisse son existence.
 */
const RENDERERS: Record<string, SurfaceBlockRenderer> = { ...CORE_RENDERERS };

/**
 * Enregistre des renderers de blocs (appelé par une verticale).
 *
 * L'écrasement est refusé : deux renderers sur le même type rendraient le rendu
 * non déterministe, exactement comme deux profils sur la même verticale.
 */
export function registerSurfaceBlockRenderers(renderers: Record<string, SurfaceBlockRenderer>): void {
  for (const [type, renderer] of Object.entries(renderers)) {
    if (type in RENDERERS) {
      throw new Error(
        `SURFACE_BLOCK_RENDERER_DUPLICATE: le type de bloc « ${type} » a déjà un renderer.`,
      );
    }

    RENDERERS[type] = renderer;
  }
}

/** Indique si un type de bloc a un renderer. */
export function hasSurfaceBlockRenderer(type: string): boolean {
  return type in RENDERERS;
}

interface SurfaceBlockRendererProps {
  brand: PublicBrand;
}

/**
 * Rend les blocs déclarés par le profil, dans l'ordre du profil.
 *
 * L'ordre vient du profil, pas du contenu : c'est la verticale qui sait que
 * le menu passe avant les témoignages sur une surface de restauration.
 */
export function SurfaceBlocks({ brand }: SurfaceBlockRendererProps) {
  const blocks = brand.profile.block_types
    // `core.hero` est rendu par `SurfaceHero` — le dispatcher l'ignore pour
    // éviter un doublon de titre.
    .filter((type) => type !== 'core.hero')
    .map((type) => {
      const renderer = RENDERERS[type];

      // Pas de renderer = pas de bloc. La surface reste servie.
      return renderer ? renderer(brand) : null;
    })
    .filter((node): node is ReactNode => node !== null);

  if (blocks.length === 0) {
    return null;
  }

  return <>{blocks}</>;
}
