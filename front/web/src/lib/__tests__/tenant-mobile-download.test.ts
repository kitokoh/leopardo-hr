import { getClientModuleAccess } from '@/lib/client-features';
import { MOBILE_DISTRIBUTION_STATUS } from '@/lib/mobile-distribution-status';
import { mobileSoftware, tenantMobileDownload } from '@/lib/software-catalog';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #9300 — Accès au téléchargement mobile depuis le profil du tenant.
 *
 * Ce qui est verrouillé ici :
 *  - l'accès n'apparaît QUE pour un tenant dont la verticale de stock est
 *    activée ET dont l'app mobile a un canal de distribution réellement sain ;
 *  - un tenant non concerné (aucune verticale de stock) n'obtient RIEN —
 *    c'est la garantie « aucun tenant non concerné ne le voit » ;
 *  - un canal en échec ou d'état inconnu n'est JAMAIS proposé (règle
 *    d'honnêteté #6697) : pas de bouton vers un build inexistant ;
 *  - le mobile reste une OPTION : la fonction ne renvoie qu'une proposition
 *    de lien, jamais une obligation.
 */

function userWith(features: Record<string, boolean>): StoredAuthUser {
  return {
    role: 'manager',
    company: { features },
  } as unknown as StoredAuthUser;
}

describe('tenantMobileDownload (#9300)', () => {
  it('un tenant sans verticale de stock ne voit AUCUN accès', () => {
    const access = getClientModuleAccess(userWith({ dashboard: true }));

    expect(tenantMobileDownload(access)).toBeNull();
    expect(mobileSoftware(access)).toEqual([]);
  });

  it('une verticale non liée au stock (station-service) ne voit rien', () => {
    const access = getClientModuleAccess(userWith({ fuel_station: true, dashboard: true }));

    expect(tenantMobileDownload(access)).toBeNull();
  });

  it('une verticale sans app mobile distribuable (commerce) ne produit pas de bouton mort', () => {
    // Commerce : `leopardo_pos_retail` embarque bien un écran de stock, mais
    // n'a AUCUN canal de distribution public (absent de la matrice
    // `mobile-distribute*.yml`). L'état honnête est « rien », pas un lien mort.
    const access = getClientModuleAccess(userWith({ retail: true, dashboard: true }));

    expect(tenantMobileDownload(access)).toBeNull();
  });

  it('un restaurateur n’obtient le lien QUE si le canal de distribution est sain', () => {
    const access = getClientModuleAccess(userWith({ restaurantmanager: true, dashboard: true }));

    const download = tenantMobileDownload(access);

    if (MOBILE_DISTRIBUTION_STATUS.manager === 'failed' || MOBILE_DISTRIBUTION_STATUS.manager === 'unknown') {
      // Canal en échec : on ne propose RIEN plutôt qu'un build inexistant.
      expect(download).toBeNull();
    } else {
      expect(download).not.toBeNull();
      expect(download?.app.slug).toBe('manager');
      expect(download?.target.kind).toBe('link');
      expect(download?.target.href).toBeTruthy();
    }
  });

  it('aucune app renvoyée n’a de canal en échec ou inconnu', () => {
    const access = getClientModuleAccess(
      userWith({ restaurantmanager: true, retail: true, dashboard: true }),
    );

    for (const app of mobileSoftware(access)) {
      expect(MOBILE_DISTRIBUTION_STATUS[app.slug]).not.toBe('failed');
      expect(MOBILE_DISTRIBUTION_STATUS[app.slug]).not.toBe('unknown');
    }
  });
});
