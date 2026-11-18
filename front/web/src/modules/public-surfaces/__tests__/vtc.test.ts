import { currencyLabel, operatorMeta, type MobilityOperator } from '../vtc/domain/operator';
import { MOBILITY_SURFACE } from '../vtc/identity/mobility-identity';
import { surfaceRegistry } from '../registry';
import { SURFACE_VERTICALS } from '../kernel/domain/surface-identity';

/**
 * Tests de la verticale Mobilité (VTC / taxi) — #9622.
 *
 * Cette verticale existait côté backend (`VtcDiscoverableProvider` publie
 * `vtc_operator`) sans vitrine propre : son annuaire `/operateurs` rendait le
 * gabarit partagé. Ces tests verrouillent les trois choses qui la rendent
 * réelle : son identité, sa composition, et les règles d'affichage qui
 * empêchent d'inventer une donnée que le provider ne publie pas.
 */

function operator(overrides: Partial<MobilityOperator> = {}): MobilityOperator {
  return {
    slug: 'taxi-nantes',
    name: 'Taxi Nantes',
    city: 'Nantes',
    country: 'France',
    currency: 'EUR',
    ...overrides,
  };
}

describe('identité mobilité', () => {
  it('est déclarée dans le registre', () => {
    expect(surfaceRegistry.get('vtc')).toBe(MOBILITY_SURFACE);
  });

  it('porte le type de découverte réellement servi par le backend', () => {
    // `VtcDiscoverableProvider::type()` retourne `vtc_operator`. Un type
    // inventé ferait compiler la vitrine et renverrait une liste vide en prod.
    expect(MOBILITY_SURFACE.discoveryType).toBe('vtc_operator');
  });

  it('a un domaine et un chemin distincts des six autres vitrines', () => {
    const domains = surfaceRegistry.all().map((identity) => identity.canonicalDomain);
    const paths = surfaceRegistry.all().map((identity) => identity.basePath);

    expect(new Set(domains).size).toBe(domains.length);
    expect(new Set(paths).size).toBe(paths.length);
  });

  it('ne partage pas le chemin de la vitrine Billets', () => {
    // Deux verticales sur le même chemin produiraient deux pages parallèles
    // Next sur la même route — le build échouerait (cf. #9596).
    expect(MOBILITY_SURFACE.basePath).not.toBe(surfaceRegistry.get('travel')?.basePath);
  });

  it('couvre la verticale dans le vocabulaire du noyau', () => {
    expect([...SURFACE_VERTICALS]).toContain('vtc');
  });
});

describe('métadonnées opérateur', () => {
  it('joint ville et pays', () => {
    expect(operatorMeta(operator())).toBe('Nantes · France');
  });

  it('n’émet jamais de séparateur orphelin', () => {
    // Le défaut classique d'un annuaire alimenté par une API partielle :
    // « Nantes · » à l'écran.
    expect(operatorMeta(operator({ country: null }))).toBe('Nantes');
    expect(operatorMeta(operator({ city: null }))).toBe('France');
  });

  it('renvoie null quand rien n’est connu', () => {
    expect(operatorMeta(operator({ city: null, country: null }))).toBeNull();
    expect(operatorMeta(operator({ city: '   ', country: '' }))).toBeNull();
  });
});

describe('devise', () => {
  it('accepte un code ISO à trois lettres', () => {
    expect(currencyLabel(operator({ currency: 'EUR' }))).toBe('EUR');
    expect(currencyLabel(operator({ currency: 'try' }))).toBe('TRY');
  });

  it('refuse un code malformé plutôt que de l’afficher', () => {
    // Afficher « E » ou « » à l'écran serait pire que de n'afficher aucune
    // information : le visiteur lirait une devise qui n'existe pas.
    expect(currencyLabel(operator({ currency: 'E' }))).toBeNull();
    expect(currencyLabel(operator({ currency: '' }))).toBeNull();
    expect(currencyLabel(operator({ currency: null }))).toBeNull();
  });
});
