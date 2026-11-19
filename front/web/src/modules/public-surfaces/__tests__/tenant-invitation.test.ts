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
 *
 * ## #9629 — la verticale Emploi n'a volontairement pas d'invitation
 *
 * Le recrutement n'est pas une verticale qu'on provisionne : c'est un module du
 * dashboard tenant (`/recruitment`). Il n'existe pas de pack d'inscription
 * « recrutement », donc `TENANT_INVITATIONS` est un `Partial` et l'absence
 * d'entrée est le comportement fail-closed attendu (pas de bandeau, plutôt
 * qu'un lien vers un espace sans rapport).
 */

/** Verticales qui portent réellement une invitation professionnelle. */
const INVITED_VERTICALS = SURFACE_VERTICALS.filter(
  (vertical) => TENANT_INVITATIONS[vertical] !== undefined,
);

describe('invitation professionnelle par verticale (#9614)', () => {
  it('couvre toutes les verticales sauf celles explicitement exclues', () => {
    // Emploi est la seule verticale sans pack d'inscription : on l'affirme
    // explicitement pour qu'une future verticale oubliée fasse échouer ce test
    // au lieu de passer inaperçue.
    const missing = SURFACE_VERTICALS.filter(
      (vertical) => TENANT_INVITATIONS[vertical] === undefined,
    );

    expect(missing).toEqual(['careers']);
  });

  it('chaque pack est un slug accepté par le formulaire d’inscription', () => {
    for (const vertical of INVITED_VERTICALS) {
      const invitation = TENANT_INVITATIONS[vertical];

      expect(invitation).toBeDefined();

      const { pack } = invitation as { pack: string };

      expect(isPackSignupSlug(pack)).toBe(true);
      // Le slug doit résoudre vers un code du catalogue serveur : sinon
      // `SignupForm` n'envoie aucun `solutions[]` et l'espace est créé sans
      // verticale.
      expect(packSolutionCode(pack)).not.toBeNull();
    }
  });

  it('chaque secteur est accepté par l’allowlist de l’entretien', () => {
    for (const vertical of INVITED_VERTICALS) {
      const invitation = TENANT_INVITATIONS[vertical];

      expect(invitation).toBeDefined();
      expect(isSignupSector((invitation as { sector: string }).sector)).toBe(true);
    }
  });

  it('le lien porte le pack ET le secteur, vers l’espace existant', () => {
    const href = tenantSignupHref('pharmacy');

    expect(href).not.toBeNull();

    const url = new URL(href as string, 'https://example.test');

    expect(url.pathname).toBe(TENANT_SIGNUP_PATH);
    expect(url.searchParams.get('pack')).toBe('pharmacy');
    expect(url.searchParams.get('sector')).toBe('pharmacy');
  });

  it('propage la locale sans écraser les paramètres métier', () => {
    const href = tenantSignupHref('restaurant', 'tr');

    expect(href).not.toBeNull();

    const url = new URL(href as string, 'https://example.test');

    expect(url.searchParams.get('lang')).toBe('tr');
    expect(url.searchParams.get('pack')).toBe('restaurant');
    expect(url.searchParams.get('sector')).toBe('restaurant');
  });

  it('n’ajoute pas de `lang` quand la locale est absente', () => {
    const href = tenantSignupHref('retail', null);

    expect(href).not.toBeNull();

    const url = new URL(href as string, 'https://example.test');

    expect(url.searchParams.has('lang')).toBe(false);
  });

  it('deux verticales ne partagent jamais le même pack', () => {
    const packs = INVITED_VERTICALS.map(
      (vertical) => (TENANT_INVITATIONS[vertical] as { pack: string }).pack,
    );

    expect(new Set(packs).size).toBe(packs.length);
  });

  it('une verticale sans pack d’inscription ne produit aucun lien (#9629)', () => {
    // Fail-closed : pas de bandeau « Devenir tenant » sur la vitrine Emploi.
    expect(tenantSignupHref('careers')).toBeNull();
  });
});
