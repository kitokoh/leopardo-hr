/**
 * #9580 (BC-41 BRANDING) — tests du domaine « surface publique ».
 *
 * Le domaine est PUR : ces tests n'ont besoin ni de réseau, ni de DOM, ni de
 * mock. C'est précisément ce que la couche `domain/` achète — les règles
 * (parsing, contraste reçu, locale, thème) sont vérifiables en millisecondes.
 */

import {
  directionFor,
  normalizeLocale,
  parsePalette,
  parsePublicBrand,
  type PublicBrand,
} from '../domain/brand';
import { buildThemeTokens, capabilityOrder, themeVariablesToStyle } from '../domain/theme';

const VALID_DTO = {
  slug: 'chez-lea',
  locale: 'fr',
  direction: 'ltr',
  locales: ['fr', 'en'],
  default_locale: 'fr',
  vertical: 'restaurant',
  identity: {
    display_name: 'Chez Léa',
    tagline: 'Cuisine de saison',
    logo_url: 'https://cdn.example.com/logo.png',
    favicon_url: null,
    og_image_url: null,
  },
  palette: {
    primary: '#a3e635',
    accent: '#0d9488',
    surface: '#ffffff',
    on_primary: '#111827',
  },
  surface: {
    host: 'chez-lea.sn',
    path_prefix: null,
    locales: ['fr', 'en'],
    default_locale: 'fr',
  },
  profile: {
    vertical: 'restaurant',
    label: 'Restauration',
    capabilities: ['order', 'reserve', 'contact', 'hours'],
    block_types: ['core.hero', 'restaurant.menu', 'core.contact', 'core.footer'],
    theme: { density: 'comfortable', radius: '0.75rem' },
    primary_cta: 'order',
  },
  published_at: '2026-11-01T10:00:00+00:00',
};

describe('parsePublicBrand', () => {
  it('accepte un DTO complet', () => {
    const brand = parsePublicBrand(VALID_DTO);

    expect(brand).not.toBeNull();
    expect(brand?.slug).toBe('chez-lea');
    expect(brand?.identity.display_name).toBe('Chez Léa');
    expect(brand?.profile.vertical).toBe('restaurant');
  });

  it('rejette un DTO sans slug', () => {
    expect(parsePublicBrand({ ...VALID_DTO, slug: '' })).toBeNull();
  });

  /**
   * Le nom public est l'invariant fort : sans lui, ni titre ni métadonnées.
   * Une Brand publiée avant l'ajout de la règle côté API doit aussi être
   * rejetée ici, donc 404 plutôt que page sans titre.
   */
  it('rejette une identité sans nom public', () => {
    const broken = { ...VALID_DTO, identity: { ...VALID_DTO.identity, display_name: '' } };

    expect(parsePublicBrand(broken)).toBeNull();
  });

  it('rejette une palette incomplète', () => {
    const broken = { ...VALID_DTO, palette: { primary: '#a3e635' } };

    expect(parsePublicBrand(broken)).toBeNull();
  });

  it('rejette une couleur non hexadécimale', () => {
    const broken = { ...VALID_DTO, palette: { ...VALID_DTO.palette, primary: 'lime' } };

    expect(parsePublicBrand(broken)).toBeNull();
  });

  it('rejette un profil absent', () => {
    const { profile: _profile, ...withoutProfile } = VALID_DTO;

    expect(parsePublicBrand(withoutProfile)).toBeNull();
  });

  /**
   * Un CTA primaire qui n'est pas une capacité déclarée est neutralisé : le
   * rendu ne doit pas mettre en avant une action que la surface ne sait pas
   * faire.
   */
  it('neutralise un CTA primaire non déclaré', () => {
    const broken = {
      ...VALID_DTO,
      profile: { ...VALID_DTO.profile, capabilities: ['contact'], primary_cta: 'order' },
    };

    const brand = parsePublicBrand(broken);

    expect(brand?.profile.primary_cta).toBeNull();
  });

  it('respecte la direction RTL fournie par l’API', () => {
    const arabic = { ...VALID_DTO, locale: 'ar', direction: 'rtl' };

    expect(parsePublicBrand(arabic)?.direction).toBe('rtl');
  });
});

describe('parsePalette', () => {
  it('accepte quatre couleurs hexadécimales', () => {
    expect(parsePalette(VALID_DTO.palette)).not.toBeNull();
  });

  it('rejette une valeur non-objet', () => {
    expect(parsePalette(null)).toBeNull();
    expect(parsePalette('nope')).toBeNull();
    expect(parsePalette([])).toBeNull();
  });
});

describe('normalizeLocale', () => {
  it('accepte les quatre locales du produit', () => {
    expect(normalizeLocale('fr')).toBe('fr');
    expect(normalizeLocale('EN')).toBe('en');
    expect(normalizeLocale('ar')).toBe('ar');
    expect(normalizeLocale('tr')).toBe('tr');
  });

  /**
   * Une locale inconnue retombe sur '' (donc la locale par défaut de la Brand),
   * PAS sur 'fr' : une enseigne arabophone ne doit pas basculer en français
   * parce qu'un visiteur a demandé `?lang=xx`.
   */
  it('retombe sur vide pour une locale inconnue', () => {
    expect(normalizeLocale('xx')).toBe('');
    expect(normalizeLocale('')).toBe('');
    expect(normalizeLocale(null)).toBe('');
    expect(normalizeLocale(undefined)).toBe('');
  });
});

describe('directionFor', () => {
  it('donne RTL pour l’arabe uniquement', () => {
    expect(directionFor('ar')).toBe('rtl');
    expect(directionFor('fr')).toBe('ltr');
    expect(directionFor('tr')).toBe('ltr');
  });
});

describe('buildThemeTokens', () => {
  const brand = parsePublicBrand(VALID_DTO) as PublicBrand;

  it('produit les variables de marque depuis la palette', () => {
    const tokens = buildThemeTokens(brand.palette, brand.profile);

    expect(tokens.cssVariables['--brand-primary']).toBe('#a3e635');
    expect(tokens.cssVariables['--brand-accent']).toBe('#0d9488');
    expect(tokens.cssVariables['--brand-surface']).toBe('#ffffff');
  });

  /**
   * `on_primary` vient du serveur (calcul WCAG) et n'est PAS recalculé côté
   * front : deux implémentations de la même formule finiraient par diverger.
   */
  it('utilise on_primary fourni par le serveur sans le recalculer', () => {
    const tokens = buildThemeTokens(brand.palette, brand.profile);

    expect(tokens.cssVariables['--brand-on-primary']).toBe('#111827');
  });

  it('applique la densité du profil', () => {
    const tokens = buildThemeTokens(brand.palette, brand.profile);

    expect(tokens.density).toBe('comfortable');
    expect(tokens.cssVariables['--brand-section-gap']).toBe('2.5rem');
  });

  it('retombe sur une densité sûre si le profil en déclare une inconnue', () => {
    const weird = { ...brand.profile, theme: { density: 'ultra-tight' } };
    const tokens = buildThemeTokens(brand.palette, weird);

    expect(tokens.density).toBe('comfortable');
  });

  it('applique le rayon du profil', () => {
    const tokens = buildThemeTokens(brand.palette, brand.profile);

    expect(tokens.cssVariables['--brand-radius']).toBe('0.75rem');
  });

  it('propage la direction demandée', () => {
    expect(buildThemeTokens(brand.palette, brand.profile, 'rtl').direction).toBe('rtl');
  });
});

describe('themeVariablesToStyle', () => {
  it('sérialise en déclarations CSS', () => {
    const brand = parsePublicBrand(VALID_DTO) as PublicBrand;
    const style = themeVariablesToStyle(buildThemeTokens(brand.palette, brand.profile));

    expect(style).toContain('--brand-primary: #a3e635');
    expect(style).toContain('--brand-radius: 0.75rem');
  });
});

describe('capabilityOrder', () => {
  it('place le CTA primaire en tête', () => {
    const brand = parsePublicBrand(VALID_DTO) as PublicBrand;

    expect(capabilityOrder(brand.profile)[0]).toBe('order');
  });

  it('conserve l’ordre du profil sans CTA primaire', () => {
    const brand = parsePublicBrand(VALID_DTO) as PublicBrand;
    const ordered = capabilityOrder({ ...brand.profile, primary_cta: null });

    expect(ordered).toEqual(brand.profile.capabilities);
  });
});
