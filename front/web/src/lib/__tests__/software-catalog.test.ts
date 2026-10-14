import { getClientModuleAccess } from '@/lib/client-features';
import {
  TENANT_SOFTWARE,
  absoluteTargetHref,
  visibleSoftware,
} from '@/lib/software-catalog';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #8447 — garde-fous du catalogue « Mon Logiciel ».
 *
 * Ce qui est verrouillé ici :
 *  - le filtrage suit STRICTEMENT les solutions activées du tenant (un
 *    restaurateur ne voit jamais les apps d'une station-service) ;
 *  - aucune cible `link`/`pilot` sans href (jamais d'ancre morte) et aucune
 *    cible `soon` avec href (jamais de faux bouton actif, règle #6697) ;
 *  - les slugs sont uniques (clés i18n `software.apps.<slug>.*`).
 */

function userWith(features: Record<string, boolean>): StoredAuthUser {
  return {
    role: 'manager',
    company: { features },
  } as unknown as StoredAuthUser;
}

describe('software-catalog (#8447)', () => {
  it('les slugs des apps sont uniques', () => {
    const slugs = TENANT_SOFTWARE.map((app) => app.slug);
    expect(new Set(slugs).size).toBe(slugs.length);
  });

  it('toute cible link/pilot porte un href, toute cible soon n’en porte pas', () => {
    for (const app of TENANT_SOFTWARE) {
      for (const target of app.targets) {
        if (target.kind === 'soon') {
          expect(target.href).toBeUndefined();
        } else {
          expect(typeof target.href).toBe('string');
          expect(target.href).not.toBe('');
        }
      }
    }
  });

  it('un restaurateur voit POS, cuisine et kiosque — jamais la console station-service', () => {
    const access = getClientModuleAccess(userWith({ restaurantmanager: true, dashboard: true }));
    const slugs = visibleSoftware(access).map((app) => app.slug);

    expect(slugs).toContain('restaurant-pos');
    expect(slugs).toContain('restaurant-kds');
    expect(slugs).toContain('restaurant-kiosk');
    expect(slugs).toContain('manager');
    expect(slugs).not.toContain('fuel-console');
    expect(slugs).not.toContain('travel-agent');
  });

  it('une station-service voit sa console — jamais les apps restaurant', () => {
    const access = getClientModuleAccess(userWith({ fuel_station: true, dashboard: true }));
    const slugs = visibleSoftware(access).map((app) => app.slug);

    expect(slugs).toContain('fuel-console');
    expect(slugs).not.toContain('restaurant-pos');
    expect(slugs).not.toContain('restaurant-kds');
  });

  it('un tenant sans verticale ne voit que les apps transverses', () => {
    const access = getClientModuleAccess(userWith({ dashboard: true }));
    const slugs = visibleSoftware(access).map((app) => app.slug);

    expect(slugs).toContain('manager');
    expect(slugs.every((slug) => !slug.startsWith('restaurant-'))).toBe(true);
    expect(slugs).not.toContain('fuel-console');
    expect(slugs).not.toContain('commerce-pos');
  });

  it('un module verrouillé ne débloque pas ses apps', () => {
    // Données de gate présentes mais feature restaurant absente → module
    // verrouillé (fail-closed #3379) → apps restaurant invisibles.
    const access = getClientModuleAccess(userWith({ dashboard: true, fuel_station: true }));
    const slugs = visibleSoftware(access).map((app) => app.slug);

    expect(slugs).not.toContain('restaurant-pos');
  });

  it('absoluteTargetHref résout les liens relatifs sur l’origine courante', () => {
    expect(absoluteTargetHref('/restaurant/pos', 'https://app.example.com'))
      .toBe('https://app.example.com/restaurant/pos');
    expect(absoluteTargetHref('https://store.example.com/app', 'https://app.example.com'))
      .toBe('https://store.example.com/app');
  });
});
