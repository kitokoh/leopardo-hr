import { shopDirectoryMeta, type ShopDirectoryEntry } from '../retail/domain/shop-directory';
import { propertyDirectoryMeta, propertyKindLabel } from '../hospitality/domain/property-directory';
import { directoryDirections, visibleCategories } from '../trade/domain/company-directory';
import { getSurfaceCopy } from '../copy/surfaces-copy';

/**
 * Tests des domaines d'annuaire (#9618, #9619).
 *
 * Ces règles sont celles qui distinguent une vitrine soignée d'un gabarit :
 * pas de séparateur orphelin, pas de code technique à l'écran, pas de badge
 * inventé. Elles vivent dans le domaine pour être testables sans rendu.
 */

function shop(overrides: Partial<ShopDirectoryEntry> = {}): ShopDirectoryEntry {
  return {
    slug: 'boulangerie',
    name: 'Boulangerie Martin',
    city: 'Nantes',
    description: null,
    category: 'Boulangerie',
    ...overrides,
  };
}

describe('annuaire retail — métadonnées', () => {
  it('joint ville et catégorie', () => {
    expect(shopDirectoryMeta(shop())).toBe('Nantes · Boulangerie');
  });

  it('n’affiche pas de séparateur orphelin sans catégorie', () => {
    expect(shopDirectoryMeta(shop({ category: null }))).toBe('Nantes');
  });

  it('n’affiche pas de séparateur orphelin sans ville', () => {
    expect(shopDirectoryMeta(shop({ city: null }))).toBe('Boulangerie');
  });

  it('renvoie null quand rien n’est connu', () => {
    expect(shopDirectoryMeta(shop({ city: null, category: null }))).toBeNull();
  });

  it('ignore les chaînes vides', () => {
    expect(shopDirectoryMeta(shop({ city: '   ', category: '' }))).toBeNull();
  });
});

describe('annuaire hôtellerie — type d’hébergement', () => {
  const labels = getSurfaceCopy('fr').shared.propertyKinds;

  it('traduit un code connu', () => {
    expect(propertyKindLabel('hotel', labels)).toBe('Hôtel');
    expect(propertyKindLabel('guesthouse', labels)).toBe('Maison d’hôtes');
  });

  it('ne montre jamais un code technique inconnu', () => {
    // Afficher `apartment_building` à l'écran serait pire que de n'afficher
    // que la ville : le visiteur lirait un identifiant de base de données.
    expect(propertyKindLabel('space_station', labels)).toBeNull();
    expect(propertyKindLabel(null, labels)).toBeNull();
  });

  it('compose type · ville', () => {
    expect(
      propertyDirectoryMeta(
        { slug: 'a', name: 'A', kind: 'hotel', city: 'Lyon', description: null },
        'Hôtel',
      ),
    ).toBe('Hôtel · Lyon');
  });

  it('retombe sur la ville seule si le type est inconnu', () => {
    expect(
      propertyDirectoryMeta(
        { slug: 'a', name: 'A', kind: 'space_station', city: 'Lyon', description: null },
        null,
      ),
    ).toBe('Lyon');
  });

  it('est traduit dans les 4 locales', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar']) {
      const kinds = getSurfaceCopy(locale).shared.propertyKinds;
      expect(kinds.hotel).toBeTruthy();
      expect(kinds.guesthouse).toBeTruthy();
    }
  });
});

describe('annuaire B2B — directions et catégories', () => {
  const labels = {
    importLabel: 'Import',
    exportLabel: 'Export',
    domesticLabel: 'Marché domestique',
  };

  const base = {
    slug: 'a',
    name: 'A',
    city: null,
    description: null,
    productCategories: [],
    imports: false,
    exports: false,
    domestic: false,
  };

  it('liste les directions dans un ordre stable', () => {
    expect(directoryDirections({ ...base, imports: true, exports: true }, labels)).toEqual([
      'Import',
      'Export',
    ]);
  });

  it('renvoie une liste vide sans direction', () => {
    expect(directoryDirections(base, labels)).toEqual([]);
  });

  it('borne les catégories à trois', () => {
    const entry = {
      ...base,
      productCategories: ['A', 'B', 'C', 'D', 'E'],
    };

    expect(visibleCategories(entry)).toEqual(['A', 'B', 'C']);
  });

  it('déduplique et ignore les vides', () => {
    const entry = {
      ...base,
      productCategories: ['Café', '  ', 'Café', 'Épices'],
    };

    expect(visibleCategories(entry)).toEqual(['Café', 'Épices']);
  });
});
