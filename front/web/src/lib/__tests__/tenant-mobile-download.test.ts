import { getClientModuleAccess } from '@/lib/client-features';
import { mobileSoftware, tenantMobileDownload } from '@/lib/software-catalog';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #9300 — Accès au téléchargement mobile depuis le profil du tenant.
 *
 * Ce qui est verrouillé ici :
 *  - l'accès n'apparaît QUE pour un tenant dont la verticale de stock est
 *    activée ET dont l'app mobile a un canal de distribution réel ;
 *  - un tenant non concerné (aucune verticale de stock) n'obtient RIEN —
 *    c'est la garantie « aucun tenant non concerné ne le voit » ;
 *  - le mobile reste une OPTION : la fonction ne renvoie qu'une proposition
 *    de lien, jamais une obligation (aucun état bloquant).
 */

function userWith(features: Record<string, boolean>): StoredAuthUser {
  return {
    role: 'manager',
    company: { features },
  } as unknown as StoredAuthUser;
}

describe('tenantMobileDownload (#9300)', () => {
  it('un restaurateur obtient le lien de téléchargement de l’app mobile de stock', () => {
    const access = getClientModuleAccess(userWith({ restaurantmanager: true, dashboard: true }));

    const download = tenantMobileDownload(access);

    expect(download).not.toBeNull();
    expect(download?.app.slug).toBe('manager');
    expect(download?.target.platform).toBe('android');
    expect(download?.target.kind).toBe('link');
    expect(download?.target.href).toBeTruthy();
  });

  it('un tenant sans verticale de stock ne voit AUCUN accès', () => {
    const access = getClientModuleAccess(userWith({ dashboard: true }));

    expect(tenantMobileDownload(access)).toBeNull();
    expect(mobileSoftware(access)).toEqual([]);
  });

  it('une verticale sans app mobile distribuable ne produit pas de bouton mort', () => {
    // Commerce : `leopardo_pos_retail` embarque bien un écran de stock, mais
    // n'a AUCUN canal de distribution public (absent de la matrice
    // `mobile-distribute*.yml`). L'état honnête est « rien », pas un lien mort.
    const access = getClientModuleAccess(userWith({ retail: true, dashboard: true }));

    expect(tenantMobileDownload(access)).toBeNull();
  });

  it('une verticale non liée au stock (station-service) ne voit rien', () => {
    const access = getClientModuleAccess(userWith({ fuel_station: true, dashboard: true }));

    expect(tenantMobileDownload(access)).toBeNull();
  });

  it('toute cible renvoyée est réellement cliquable (jamais `soon`)', () => {
    const access = getClientModuleAccess(userWith({ restaurantmanager: true, dashboard: true }));

    for (const app of mobileSoftware(access)) {
      for (const target of app.targets) {
        if (target.platform === 'android' || target.platform === 'ios') {
          // Une cible mobile non distribuable ne doit jamais être retenue.
          if (target.kind !== 'link') {
            expect(target.href).toBeUndefined();
          }
        }
      }
    }
  });
});
