import { CLIENT_MODULES, getClientModuleAccess } from '../client-features';

/**
 * #6450 — Navigation portail : la console React logait
 * « Encountered two children with the same key, `restaurant` ».
 *
 * La cause : la liste de modules servant à rendre la sidebar/nav peut
 * contenir une clé en double après la maturation multi-verticale (BC-25).
 * `getClientModuleAccess` doit garantir des clés uniques par construction
 * pour que chaque `key={module.key}` React soit unique.
 */
describe('client-features navigation keys (#6450)', () => {
  it('getClientModuleAccess ne renvoie jamais deux modules avec la même clé', () => {
    const access = getClientModuleAccess(null);
    const keys = access.map((m) => m.key);
    const unique = new Set(keys);
    expect(unique.size).toBe(keys.length);
  });

  it('chaque clé de module reste unique quel que soit l’utilisateur', () => {
    const access = getClientModuleAccess({
      role: 'manager',
      capabilities: { restaurant: true },
      features: { restaurantmanager: true },
    } as never);
    const keys = access.map((m) => m.key);
    expect(new Set(keys).size).toBe(keys.length);
  });

  it('la clé restaurant n’apparaît qu’une fois dans le rendu de navigation', () => {
    const access = getClientModuleAccess(null);
    const restaurantEntries = access.filter((m) => m.key === 'restaurant');
    expect(restaurantEntries.length).toBe(1);
  });

  it('CLIENT_MODULES source ne déclare pas deux fois la même clé', () => {
    const keys = CLIENT_MODULES.map((m) => m.key);
    expect(new Set(keys).size).toBe(keys.length);
  });
});
