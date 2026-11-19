import {
  AA_NORMAL,
  SURFACE_VERTICALS,
  assertSurfaceIdentity,
  buildPalette,
  contrastRatio,
  createSurfaceRegistry,
  meetsContrast,
  pickReadableOn,
  requireSurface,
  resolveSurface,
  resolveSurfaceDomain,
  surfaceUrl,
  type SurfaceIdentity,
} from '../kernel';

function identity(overrides: Partial<SurfaceIdentity> = {}): SurfaceIdentity {
  return {
    vertical: 'restaurant',
    displayName: 'Leopardo Restaurants',
    tagline: 'Trouvez votre table, réservez en deux gestes.',
    canonicalDomain: 'restaurants.leopardo.app',
    basePath: '/restaurants',
    discoveryType: 'restaurant',
    palette: buildPalette({
      primary: '#C2410C',
      accent: '#F59E0B',
      surface: '#FFFBF5',
      onSurface: '#1C1917',
      muted: '#78716C',
    }),
    locales: ['fr', 'en'],
    ...overrides,
  };
}

describe('contraste WCAG', () => {
  it('calcule les bornes connues', () => {
    expect(contrastRatio('#000000', '#FFFFFF')).toBeCloseTo(21, 1);
    expect(contrastRatio('#FFFFFF', '#FFFFFF')).toBeCloseTo(1, 5);
  });

  it('choisit un texte sombre sur un accent clair', () => {
    expect(pickReadableOn('#F59E0B')).toBe('#0B1220');
  });

  it('choisit un texte clair sur un accent sombre', () => {
    expect(pickReadableOn('#1E3A8A')).toBe('#FFFFFF');
  });

  it('refuse un hex invalide', () => {
    expect(() => contrastRatio('nope', '#FFFFFF')).toThrow(/hex invalide/i);
  });
});

describe('buildPalette', () => {
  it('dérive onPrimary pour garantir AA', () => {
    const palette = buildPalette({
      primary: '#F59E0B',
      accent: '#F59E0B',
      surface: '#FFFFFF',
      onSurface: '#0F172A',
      muted: '#6B7280',
    });

    expect(meetsContrast(palette.primary, palette.onPrimary, AA_NORMAL)).toBe(true);
  });
});

describe('assertSurfaceIdentity', () => {
  it('accepte une identité valide', () => {
    expect(() => assertSurfaceIdentity(identity())).not.toThrow();
  });

  it('refuse un basePath non absolu', () => {
    expect(() => assertSurfaceIdentity(identity({ basePath: 'restaurants' }))).toThrow(/basePath/);
  });

  it('refuse un basePath avec slash final', () => {
    expect(() => assertSurfaceIdentity(identity({ basePath: '/restaurants/' }))).toThrow(/basePath/);
  });

  it('refuse un domaine avec schéma', () => {
    expect(() =>
      assertSurfaceIdentity(identity({ canonicalDomain: 'https://restaurants.leopardo.app' })),
    ).toThrow(/canonicalDomain/);
  });

  it('refuse un discoveryType vide', () => {
    expect(() => assertSurfaceIdentity(identity({ discoveryType: '  ' }))).toThrow(/discoveryType/);
  });

  it('refuse une identité sans locale', () => {
    expect(() => assertSurfaceIdentity(identity({ locales: [] }))).toThrow(/locale/);
  });
});

describe('registre', () => {
  it('résout une verticale déclarée', () => {
    const registry = createSurfaceRegistry([identity()]);

    expect(resolveSurface(registry, 'restaurant')?.displayName).toBe('Leopardo Restaurants');
  });

  it('est fail-closed sur une verticale inconnue', () => {
    const registry = createSurfaceRegistry([identity()]);

    expect(resolveSurface(registry, 'pharmacy')).toBeNull();
    expect(registry.has('pharmacy')).toBe(false);
  });

  it('refuse un doublon de verticale', () => {
    expect(() => createSurfaceRegistry([identity(), identity()])).toThrow(/deux fois/);
  });

  it('requireSurface lève sur une verticale absente', () => {
    const registry = createSurfaceRegistry([identity()]);

    expect(() => requireSurface(registry, 'travel')).toThrow(/Aucune vitrine/);
  });

  it('expose toutes les verticales déclarées', () => {
    const registry = createSurfaceRegistry([identity()]);

    expect(registry.verticals()).toEqual(['restaurant']);
    expect(registry.all()).toHaveLength(1);
  });
});

describe('domaine canonique', () => {
  it('utilise la valeur déclarée sans surcharge', () => {
    expect(resolveSurfaceDomain(identity(), {})).toBe('restaurants.leopardo.app');
  });

  it('accepte une surcharge par variable dédiée', () => {
    expect(
      resolveSurfaceDomain(identity(), {
        NEXT_PUBLIC_SURFACE_DOMAIN_RESTAURANT: 'tables.exemple.tld',
      }),
    ).toBe('tables.exemple.tld');
  });

  it('ignore une surcharge invalide', () => {
    expect(
      resolveSurfaceDomain(identity(), {
        NEXT_PUBLIC_SURFACE_DOMAIN_RESTAURANT: 'https://nope/x',
      }),
    ).toBe('restaurants.leopardo.app');
  });

  it('construit une URL absolue', () => {
    expect(surfaceUrl(identity(), '/restaurants/chez-nous', {})).toBe(
      'https://restaurants.leopardo.app/restaurants/chez-nous',
    );
  });
});

describe('vocabulaire', () => {
  it('couvre les huit verticales du périmètre', () => {
    expect([...SURFACE_VERTICALS]).toEqual([
      'restaurant',
      'retail',
      'hospitality',
      'trade',
      'travel',
      'pharmacy',
      'vtc',
      'careers',
    ]);
  });
});
