import {
  TENANT_SOFTWARE,
  isOfflineClaimed,
  type SoftwareApp,
} from '@/lib/software-catalog';

/**
 * BOS F-01 / ADR-08 — garde-fous du mode hors-ligne déclaré.
 *
 * Le catalogue portait auparavant un booléen `offline: true` purement
 * DÉCLARATIF : sept applications annonçaient « fonctionne hors-ligne » alors
 * que deux seulement passent réellement par la file (`dispatch()` de
 * `useOfflineSync`) ou par le pont local du kiosque ZKTeco.
 *
 * Ces tests verrouillent la règle : on ne peut plus revendiquer un
 * fonctionnement hors-ligne sans preuve ni sans câblage constaté.
 */

function app(partial: Partial<SoftwareApp>): SoftwareApp {
  return {
    slug: 'test',
    icon: TENANT_SOFTWARE[0].icon,
    accent: '',
    moduleKeys: [],
    offline: 'absent',
    targets: [],
    details: [],
    ...partial,
  };
}

describe('software-catalog — honnêteté du hors-ligne (BOS F-01)', () => {
  it("n'affiche aucun badge pour une application sans continuité hors-ligne", () => {
    expect(isOfflineClaimed(app({ offline: 'absent' }))).toBe(false);
  });

  it('accepte un mode `implemented` (file branchée, scénario non rejoué)', () => {
    expect(isOfflineClaimed(app({ offline: 'implemented' }))).toBe(true);
  });

  it('exige une référence de scénario pour revendiquer `verified` (fail-closed)', () => {
    expect(isOfflineClaimed(app({ offline: 'verified' }))).toBe(false);
    expect(isOfflineClaimed(app({ offline: 'verified', offlineEvidence: '   ' }))).toBe(false);
    expect(
      isOfflineClaimed(app({ offline: 'verified', offlineEvidence: 'OL-2 offline-replay' })),
    ).toBe(true);
  });

  it("aucune application du catalogue n'affiche un badge sans câblage réel", () => {
    // Les seules apps réellement câblées à la file hors-ligne aujourd'hui :
    // `/restaurant/kitchen`, `/commerce/pos` (dispatch) et le pont local du
    // kiosque ZKTeco. Toute autre app doit être `absent`.
    const claimed = TENANT_SOFTWARE.filter(isOfflineClaimed).map((a) => a.slug).sort();
    expect(claimed).toEqual(['commerce-pos', 'desktop-attendance', 'restaurant-kds']);
  });

  it("n'annonce jamais `verified` sans preuve référencée", () => {
    for (const software of TENANT_SOFTWARE) {
      if (software.offline === 'verified') {
        expect(typeof software.offlineEvidence).toBe('string');
        expect((software.offlineEvidence ?? '').trim().length).toBeGreaterThan(0);
      }
    }
  });

  it('les surfaces qui encaissent ou vendent directement ne revendiquent pas le hors-ligne', () => {
    const directPostSlugs = [
      'restaurant-pos', // POST directs, aucun dispatch
      'restaurant-kiosk', // /public/restaurant/* en direct
      'fuel-console', // POST directs
    ];
    for (const slug of directPostSlugs) {
      const software = TENANT_SOFTWARE.find((a) => a.slug === slug);
      expect(software).toBeDefined();
      expect(isOfflineClaimed(software as SoftwareApp)).toBe(false);
    }
  });
});
