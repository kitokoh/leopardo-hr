import {
  TENANT_INVITATIONS,
  TENANT_SIGNUP_PATH,
  tenantSignupHref,
} from '../kernel/domain/tenant-invitation';
import { SURFACE_VERTICALS } from '../kernel/domain/surface-identity';
import { isPackSignupSlug, packSolutionCode } from '@/modules/vitrine/lib/pack-signup';
import { isSignupSector } from '@/lib/signup-sector';

/**
 * #9614 — invitation professionnelle depuis une vitrine verticale.
 *
 * Ce test verrouille la seule chose qui peut casser silencieusement : le lien
 * produit par une vitrine doit être **accepté par l'espace professionnel
 * existant**. Un slug de pack inconnu est ignoré par `SignupForm` (fail-closed)
 * et un secteur hors allowlist est rejeté par `signup-sector` : dans les deux
 * cas le visiteur arriverait sur un formulaire générique, sans que rien ne
 * signale l'erreur. D'où la vérification croisée contre les deux allowlists
 * réelles, pas contre une copie.
 */
describe('invitation professionnelle par verticale (#9614)', () => {
  it('couvre exactement les verticales déclarées', () => {
    expect(Object.keys(TENANT_INVITATIONS).sort()).toEqual([...SURFACE_VERTICALS].sort());
  });

  it('chaque pack est un slug accepté par le formulaire d’inscription', () => {
    for (const vertical of SURFACE_VERTICALS) {
      const { pack } = TENANT_INVITATIONS[vertical];

      expect(isPackSignupSlug(pack)).toBe(true);
      // Le slug doit résoudre vers un code du catalogue serveur : sinon
      // `SignupForm` n'envoie aucun `solutions[]` et l'espace est créé sans
      // verticale.
      expect(packSolutionCode(pack)).not.toBeNull();
    }
  });

  it('chaque secteur est accepté par l’allowlist de l’entretien', () => {
    for (const vertical of SURFACE_VERTICALS) {
      expect(isSignupSector(TENANT_INVITATIONS[vertical].sector)).toBe(true);
    }
  });

  it('le lien porte le pack ET le secteur, vers l’espace existant', () => {
    const href = tenantSignupHref('pharmacy');
    const url = new URL(href, 'https://example.test');

    expect(url.pathname).toBe(TENANT_SIGNUP_PATH);
    expect(url.searchParams.get('pack')).toBe('pharmacy');
    expect(url.searchParams.get('sector')).toBe('pharmacy');
  });

  it('propage la locale sans écraser les paramètres métier', () => {
    const url = new URL(tenantSignupHref('restaurant', 'tr'), 'https://example.test');

    expect(url.searchParams.get('lang')).toBe('tr');
    expect(url.searchParams.get('pack')).toBe('restaurant');
    expect(url.searchParams.get('sector')).toBe('restaurant');
  });

  it('n’ajoute pas de `lang` quand la locale est absente', () => {
    const url = new URL(tenantSignupHref('retail', null), 'https://example.test');

    expect(url.searchParams.has('lang')).toBe(false);
  });

  it('deux verticales ne partagent jamais le même pack', () => {
    const packs = SURFACE_VERTICALS.map((vertical) => TENANT_INVITATIONS[vertical].pack);

    expect(new Set(packs).size).toBe(packs.length);
  });
});
