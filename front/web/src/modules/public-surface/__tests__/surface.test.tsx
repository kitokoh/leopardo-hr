/**
 * #9580 (BC-41 BRANDING) — tests du cas d'usage et du rendu.
 *
 * Le cas d'usage est testé avec un CHARGEUR substitué : pas de serveur HTTP,
 * pas de mock de module. C'est la raison d'être de `LoadSurfaceOptions.loader`.
 */

import React from 'react';
import { render, screen } from '@testing-library/react';

import { loadSurfaceBySlug } from '../application/load-surface';
import { resolveSurface } from '../application/resolve-surface';
import { parsePublicBrand, type PublicBrand } from '../domain/brand';
import { PublicSurfaceShell } from '../components/PublicSurfaceShell';
import { SurfaceCapabilityBar } from '../components/SurfaceCapabilityBar';
import { SurfaceHero } from '../components/SurfaceHero';
import { SurfaceBlocks, hasSurfaceBlockRenderer, registerSurfaceBlockRenderers } from '../components/SurfaceBlocks';

const DTO = {
  slug: 'chez-lea',
  locale: 'fr',
  direction: 'ltr',
  locales: ['fr', 'en', 'ar'],
  default_locale: 'fr',
  vertical: 'restaurant',
  identity: {
    display_name: 'Chez Léa',
    tagline: 'Cuisine de saison',
    logo_url: null,
    favicon_url: null,
    og_image_url: null,
  },
  palette: { primary: '#a3e635', accent: '#0d9488', surface: '#ffffff', on_primary: '#111827' },
  surface: {
    host: 'chez-lea.sn',
    path_prefix: null,
    locales: ['fr', 'en', 'ar'],
    default_locale: 'fr',
  },
  profile: {
    vertical: 'restaurant',
    label: 'Restauration',
    capabilities: ['order', 'reserve', 'contact'],
    block_types: ['core.hero', 'core.features', 'core.footer'],
    theme: { density: 'comfortable' },
    primary_cta: 'order',
  },
  published_at: null,
};

const BRAND = parsePublicBrand(DTO) as PublicBrand;

describe('resolveSurface', () => {
  it('respecte la locale demandée quand elle est publiée', () => {
    expect(resolveSurface(BRAND, 'en').locale).toBe('en');
  });

  /**
   * Une locale demandée mais NON publiée retombe sur la locale par défaut de
   * la Brand, pas sur 'fr' en dur.
   */
  it('retombe sur la locale par défaut de la Brand', () => {
    expect(resolveSurface(BRAND, 'tr').locale).toBe('fr');
  });

  it('déduit la direction de la locale rendue', () => {
    expect(resolveSurface(BRAND, 'ar').tokens.direction).toBe('rtl');
    expect(resolveSurface(BRAND, 'fr').tokens.direction).toBe('ltr');
  });

  it('place le CTA primaire en tête des capacités', () => {
    expect(resolveSurface(BRAND).capabilities[0]).toBe('order');
  });
});

describe('loadSurfaceBySlug', () => {
  it('retourne null quand le chargeur ne trouve rien', async () => {
    const surface = await loadSurfaceBySlug('inconnu', { loader: async () => null });

    expect(surface).toBeNull();
  });

  /**
   * Le cas d'usage revalide même ce qu'un chargeur substitué renvoie : une
   * seule porte d'entrée pour la donnée.
   */
  it('rejette un DTO invalide renvoyé par le chargeur', async () => {
    const surface = await loadSurfaceBySlug('chez-lea', {
      loader: async () => ({ slug: 'chez-lea' }),
    });

    expect(surface).toBeNull();
  });

  it('résout une surface valide', async () => {
    const surface = await loadSurfaceBySlug('chez-lea', { loader: async () => DTO });

    expect(surface?.brand.slug).toBe('chez-lea');
    expect(surface?.tokens.cssVariables['--brand-primary']).toBe('#a3e635');
  });

  it('transmet la locale demandée au chargeur', async () => {
    let received: string | undefined;

    await loadSurfaceBySlug('chez-lea', {
      requestedLang: 'ar',
      loader: async (_slug, lang) => {
        received = lang;

        return DTO;
      },
    });

    expect(received).toBe('ar');
  });
});

describe('rendu', () => {
  const surface = resolveSurface(BRAND);

  it('la coquille pose les variables de marque et la direction', () => {
    const { container } = render(
      <PublicSurfaceShell surface={surface}>
        <p>contenu</p>
      </PublicSurfaceShell>,
    );

    const root = container.firstElementChild as HTMLElement;

    expect(root.getAttribute('dir')).toBe('ltr');
    expect(root.getAttribute('data-density')).toBe('comfortable');
    expect(root.getAttribute('data-vertical')).toBe('restaurant');
    // React normalise les propriétés personnalisées : on vérifie la valeur
    // posée, pas la façon dont le style est sérialisé.
    expect(root.style.getPropertyValue('--brand-primary')).toBe('#a3e635');
  });

  it('le hero affiche le nom public et la baseline', () => {
    render(<SurfaceHero identity={BRAND.identity} verticalLabel={BRAND.profile.label} />);

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Chez Léa');
    expect(screen.getByText('Cuisine de saison')).toBeInTheDocument();
    expect(screen.getByText('Restauration')).toBeInTheDocument();
  });

  it('la barre de capacités affiche les libellés traduits', () => {
    render(
      <SurfaceCapabilityBar
        capabilities={surface.capabilities}
        primaryCta={BRAND.profile.primary_cta}
      />,
    );

    expect(screen.getByText('Commander')).toBeInTheDocument();
    expect(screen.getByText('Réserver')).toBeInTheDocument();
    expect(screen.getByText('Contact')).toBeInTheDocument();
  });

  it('les blocs du socle sont rendus dans l’ordre du profil', () => {
    const { container } = render(<SurfaceBlocks brand={BRAND} />);

    expect(container.textContent).toContain('Chez Léa');
  });

  /**
   * Un bloc déclaré sans renderer ne casse pas la surface : il est simplement
   * absent. Un `throw` ici rendrait une enseigne indisponible pour un renderer
   * oublié.
   */
  it('un bloc sans renderer ne casse pas le rendu', () => {
    const brand = { ...BRAND, profile: { ...BRAND.profile, block_types: ['vertical.unknown'] } };

    expect(() => render(<SurfaceBlocks brand={brand} />)).not.toThrow();
  });

  it('un renderer en double est refusé', () => {
    expect(() => registerSurfaceBlockRenderers({ 'core.features': () => null })).toThrow(
      /SURFACE_BLOCK_RENDERER_DUPLICATE/,
    );
  });

  it('un type du socle a bien un renderer', () => {
    expect(hasSurfaceBlockRenderer('core.features')).toBe(true);
    expect(hasSurfaceBlockRenderer('vertical.inconnu')).toBe(false);
  });
});
