import { dutyLabel, hasServices, type Pharmacy } from '../pharmacy/domain/pharmacy';
import { PHARMACY_SURFACE } from '../pharmacy/identity/pharmacy-identity';
import { getSurfaceCopy } from '../copy/surfaces-copy';
import { surfaceRegistry } from '../registry';

const words = getSurfaceCopy('fr').shared;

function pharmacy(overrides: Partial<Pharmacy> = {}): Pharmacy {
  return {
    slug: 'officine-centrale',
    name: 'Officine Centrale',
    city: 'Lille',
    description: null,
    onDuty: false,
    services: [],
    ...overrides,
  };
}

describe('domaine pharmacie — garde', () => {
  it('nomme la garde', () => {
    expect(dutyLabel(true, words)).toBe('De garde');
  });

  it('nomme les horaires habituels', () => {
    expect(dutyLabel(false, words)).toBe('Horaires habituels');
  });

  it('renvoie null quand l’information n’est pas publiée', () => {
    expect(dutyLabel(null, words)).toBeNull();
  });
});

describe('domaine pharmacie — services', () => {
  it('détecte la présence de services', () => {
    expect(hasServices(pharmacy())).toBe(false);
    expect(hasServices(pharmacy({ services: ['Vaccination'] }))).toBe(true);
  });
});

describe('identité pharmacie', () => {
  it('est déclarée avec son propre domaine', () => {
    expect(surfaceRegistry.get('pharmacy')).toBe(PHARMACY_SURFACE);
    expect(PHARMACY_SURFACE.canonicalDomain).toBe('pharmacies.leopardo.app');
    expect(PHARMACY_SURFACE.basePath).toBe('/pharmacies');
  });
});

describe('registre complet', () => {
  it('déclare les huit verticales du périmètre', () => {
    expect([...surfaceRegistry.verticals()].sort()).toEqual([
      'careers',
      'hospitality',
      'pharmacy',
      'restaurant',
      'retail',
      'trade',
      'travel',
      'vtc',
    ]);
  });

  it('donne à chaque verticale un domaine distinct', () => {
    const domains = surfaceRegistry.all().map((identity) => identity.canonicalDomain);

    expect(new Set(domains).size).toBe(domains.length);
  });

  it('donne à chaque verticale un chemin distinct', () => {
    const paths = surfaceRegistry.all().map((identity) => identity.basePath);

    expect(new Set(paths).size).toBe(paths.length);
  });

  it('donne à chaque verticale une palette distincte', () => {
    const primaries = surfaceRegistry.all().map((identity) => identity.palette.primary);

    expect(new Set(primaries).size).toBe(primaries.length);
  });
});
