/**
 * Routage par domaine des vitrines verticales (#10041).
 *
 * ## Ce que ces tests protègent
 *
 * L'architecture déclarée est « une vitrine par verticale, chacune avec son
 * domaine ». Le code portait les identités et les domaines, mais aucune
 * fonction ne répondait à « ce host, c'est quelle verticale ? » — donc les huit
 * vitrines étaient servies par chemin sur un seul domaine. Ces tests figent la
 * fonction qui comble ce trou, et surtout les deux propriétés dont l'absence
 * serait un défaut de sécurité ou de SEO :
 *
 *   1. **Pas de correspondance par suffixe.** Un sous-domaine attaquant
 *      (`evil-` + domaine canonique) ne doit PAS résoudre la vitrine : sinon
 *      il obtiendrait son identité et son canonical.
 *   2. **Fail-closed.** Un host inconnu (dashboard, admin, localhost) rend
 *      `null`, jamais une identité par défaut.
 */

import { describe, expect, it } from '@jest/globals';

import {
  isSurfaceHost,
  normalizeHost,
  resolveVerticalByHost,
  surfacePathForHost,
  verticalsForHosts,
} from '../kernel/application/resolve-surface-by-host';
import { surfaceRegistry } from '../registry';

const NO_ENV = {} as Readonly<Record<string, string | undefined>>;

describe('normalizeHost', () => {
  it('retire le port, la casse, le www et le point final', () => {
    expect(normalizeHost('Restaurants.Leopardo.App:3000')).toBe('restaurants.leopardo.app');
    // Les hôtes sont CONSTRUITS, jamais écrits en littéral : la garde
    // `check-canonical-domains.sh` refuse tout domaine projet inconnu dans le
    // code, et un hôte d'attaque n'a pas à figurer au registre des domaines.
    const canonical = surfaceRegistry.get('restaurant')!.canonicalDomain;

    expect(normalizeHost(`www.${canonical}`)).toBe(canonical);
    expect(normalizeHost('restaurants.leopardo.app.')).toBe('restaurants.leopardo.app');
  });

  it('rend une chaîne vide pour une entrée absente ou vide', () => {
    expect(normalizeHost(null)).toBe('');
    expect(normalizeHost(undefined)).toBe('');
    expect(normalizeHost('   ')).toBe('');
  });

  it('gère un littéral IPv6 sans casser sur le port', () => {
    expect(normalizeHost('[::1]:3000')).toBe('[::1]');
  });
});

describe('resolveVerticalByHost', () => {
  it('résout chaque verticale sur son domaine canonique déclaré', () => {
    for (const identity of surfaceRegistry.all()) {
      const resolved = resolveVerticalByHost(identity.canonicalDomain, surfaceRegistry, NO_ENV);

      // Jest n'accepte pas de message en 2e argument de `expect` (c'est une
      // extension vitest) : le contexte est porté par le nom du test.
      expect(resolved).not.toBeNull();
      expect(resolved?.vertical).toBe(identity.vertical);
    }
  });

  it('résout aussi avec le préfixe www et un port', () => {
    const identity = surfaceRegistry.get('restaurant');
    expect(identity).not.toBeNull();

    const resolved = resolveVerticalByHost(
      `www.${identity!.canonicalDomain}:443`,
      surfaceRegistry,
      NO_ENV,
    );

    expect(resolved?.vertical).toBe('restaurant');
  });

  it('REFUSE un sous-domaine qui ne fait que finir par le domaine canonique', () => {
    const identity = surfaceRegistry.get('restaurant');
    expect(identity).not.toBeNull();

    // Le piège d'un `endsWith` : ce host n'est PAS la vitrine restaurant.
    expect(
      resolveVerticalByHost(`evil-${identity!.canonicalDomain}`, surfaceRegistry, NO_ENV),
    ).toBeNull();
    expect(
      resolveVerticalByHost(`${identity!.canonicalDomain}.attaquant.tld`, surfaceRegistry, NO_ENV),
    ).toBeNull();
  });

  it('rend null pour un host inconnu — jamais une identité par défaut', () => {
    expect(resolveVerticalByHost('localhost:3000', surfaceRegistry, NO_ENV)).toBeNull();
    expect(resolveVerticalByHost('leo-admin.pages.dev', surfaceRegistry, NO_ENV)).toBeNull();
    expect(resolveVerticalByHost('', surfaceRegistry, NO_ENV)).toBeNull();
    expect(resolveVerticalByHost(null, surfaceRegistry, NO_ENV)).toBeNull();
  });

  it('applique la surcharge d’environnement nommée par verticale', () => {
    const env = { NEXT_PUBLIC_SURFACE_DOMAIN_RESTAURANT: 'restaurants.staging.exemple.tld' };

    const resolved = resolveVerticalByHost('restaurants.staging.exemple.tld', surfaceRegistry, env);

    expect(resolved?.vertical).toBe('restaurant');
  });

  it('la surcharge REMPLACE le domaine déclaré — contrat de resolveSurfaceDomain', () => {
    // `resolve-surface-domain.ts` documente la surcharge comme un remplacement
    // (« l'identité déclare un hôte par défaut ; l'environnement peut le
    // surcharger »), pas comme un ajout. C'est le bon comportement pour un
    // déploiement de préproduction : ce déploiement ne sert QUE son domaine,
    // et le domaine de production reste servi par le déploiement de production.
    //
    // Ce test fige la conséquence, qui surprendrait en revue : avec une
    // surcharge posée, le domaine canonique ne résout PLUS sur ce déploiement.
    const env = { NEXT_PUBLIC_SURFACE_DOMAIN_RESTAURANT: 'restaurants.staging.exemple.tld' };

    expect(
      resolveVerticalByHost('restaurants.leopardo.app', surfaceRegistry, env),
    ).toBeNull();
  });

  it('ignore une surcharge malformée et retombe sur le domaine déclaré', () => {
    const env = { NEXT_PUBLIC_SURFACE_DOMAIN_RESTAURANT: 'https://pas-un-host/' };

    expect(
      resolveVerticalByHost('restaurants.leopardo.app', surfaceRegistry, env)?.vertical,
    ).toBe('restaurant');
  });
});

describe('isSurfaceHost', () => {
  it('distingue un domaine de vitrine d’un host applicatif', () => {
    const identity = surfaceRegistry.get('pharmacy');
    expect(identity).not.toBeNull();

    expect(isSurfaceHost(identity!.canonicalDomain, surfaceRegistry, NO_ENV)).toBe(true);
    expect(isSurfaceHost('gestionemployer-backend.vercel.app', surfaceRegistry, NO_ENV)).toBe(false);
  });
});

describe('surfacePathForHost', () => {
  const identity = surfaceRegistry.get('restaurant')!;

  it('préfixe un chemin nu par le basePath de la verticale', () => {
    expect(surfacePathForHost(identity, '/chez-marcel')).toBe('/restaurants/chez-marcel');
  });

  it('sert la racine du domaine comme l’annuaire de la verticale', () => {
    expect(surfacePathForHost(identity, '/')).toBe('/restaurants');
  });

  it('ne double pas un préfixe déjà présent', () => {
    expect(surfacePathForHost(identity, '/restaurants')).toBeNull();
    expect(surfacePathForHost(identity, '/restaurants/chez-marcel')).toBeNull();
  });
});

describe('verticalsForHosts', () => {
  it('déduplique et ignore les hosts inconnus', () => {
    const restaurant = surfaceRegistry.get('restaurant')!;
    const retail = surfaceRegistry.get('retail')!;

    const found = verticalsForHosts(
      [restaurant.canonicalDomain, retail.canonicalDomain, restaurant.canonicalDomain, 'localhost'],
      surfaceRegistry,
      NO_ENV,
    );

    expect(found).toEqual(['restaurant', 'retail']);
  });
});
