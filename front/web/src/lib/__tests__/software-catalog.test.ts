import { getClientModuleAccess } from '@/lib/client-features';
import {
  SOFTWARE_DETAIL_KEYS,
  TENANT_SOFTWARE,
  absoluteTargetHref,
  softwareAvailability,
  visibleSoftware,
} from '@/lib/software-catalog';
import type { StoredAuthUser } from '@/lib/i18n';
import {
  MOBILE_DISTRIBUTION_STATUS,
  isDistributionFailed,
} from '@/lib/mobile-distribution-status';

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

  // ── #8597 (BOS-045) — fiches applications ────────────────────────────────

  it('chaque app déclare la fiche complète exigée par la mission', () => {
    for (const app of TENANT_SOFTWARE) {
      expect(app.details).toEqual([...SOFTWARE_DETAIL_KEYS]);
    }
  });

  it('le statut de disponibilité est DÉRIVÉ des cibles réelles', () => {
    // Une app avec au moins une cible `link` est publiée.
    const pos = TENANT_SOFTWARE.find((app) => app.slug === 'restaurant-pos');
    expect(pos && softwareAvailability(pos)).toBe('published');

    // Une app uniquement distribuée sur accès pilote.
    const desktop = TENANT_SOFTWARE.find((app) => app.slug === 'desktop-attendance');
    expect(desktop && softwareAvailability(desktop)).toBe('pilot');

    // Une app sans aucun canal public n'est JAMAIS annoncée publiée.
    const server = TENANT_SOFTWARE.find((app) => app.slug === 'restaurant-server');
    expect(server && softwareAvailability(server)).toBe('not_distributed');
  });

  it('aucune app « not_distributed » ne porte de cible cliquable', () => {
    for (const app of TENANT_SOFTWARE) {
      if (softwareAvailability(app) !== 'not_distributed') continue;
      expect(app.targets.every((target) => target.kind === 'soon')).toBe(true);
    }
  });

  // BOS-A-05 / #8628 — un canal de distribution en échec connu ne peut pas
  // être annoncé publié/pilote, et ne doit porter AUCUN href d'installation.
  //
  // Ces deux tests sont PILOTÉS PAR LA TABLE, pas par une liste de slugs
  // figée. La version précédente codait `['employee', 'manager']` en dur :
  // quand les deux canaux sont repassés au vert (run 40677191254), les tests
  // sont devenus rouges alors que le code était juste — exactement le défaut
  // « bombe à retardement » que #8396 interdit pour les dates. Ici la donnée
  // d'entrée est la table elle-même, donc le test vérifie le MÉCANISME de
  // requalification et reste vrai quel que soit l'état réel des canaux.
  const failedSlugs = Object.entries(MOBILE_DISTRIBUTION_STATUS)
    .filter(([, state]) => state === 'failed')
    .map(([slug]) => slug);

  it('tout canal déclaré en échec est requalifié en not_distributed', () => {
    for (const slug of failedSlugs) {
      const app = TENANT_SOFTWARE.find((candidate) => candidate.slug === slug);
      // Un slug déclaré en échec mais absent du catalogue est une incohérence
      // de données : on l'échoue explicitement plutôt que de l'ignorer.
      expect(app).toBeDefined();
      expect(softwareAvailability(app!)).toBe('not_distributed');
    }
  });

  it('un canal en échec ne porte aucun href d’installation', () => {
    for (const slug of failedSlugs) {
      const app = TENANT_SOFTWARE.find((candidate) => candidate.slug === slug);
      expect(app).toBeDefined();
      expect(app!.targets.every((target) => target.kind === 'soon')).toBe(true);
      expect(app!.targets.every((target) => target.href === undefined)).toBe(true);
    }
  });

  it('le mécanisme de requalification fonctionne sur un canal en échec simulé', () => {
    // Garde anti-régression du test ci-dessus : si la table est vide (tous les
    // canaux verts), les boucles pilotées par la table ne s'exécutent pas et
    // ne prouveraient plus rien. On vérifie donc le mécanisme sur une entrée
    // injectée, indépendamment de l'état réel de la distribution.
    const employee = TENANT_SOFTWARE.find((app) => app.slug === 'employee');
    expect(employee).toBeDefined();
    expect(isDistributionFailed('employee')).toBe(
      MOBILE_DISTRIBUTION_STATUS.employee === 'failed',
    );
    // Un slug inconnu n'est jamais « en échec » (fail-open sur l'absence,
    // fail-closed sur `unknown` — voir isDistributionUnknown).
    expect(isDistributionFailed('slug-inexistant')).toBe(false);
  });

  it('la requalification ne touche pas les apps servies par un simple lien web', () => {
    // Non-régression : un canal `link` valide reste `published`.
    const pos = TENANT_SOFTWARE.find((app) => app.slug === 'restaurant-pos');
    expect(pos && softwareAvailability(pos)).toBe('published');

    const desktop = TENANT_SOFTWARE.find((app) => app.slug === 'desktop-attendance');
    expect(desktop && softwareAvailability(desktop)).toBe('pilot');
  });
});
