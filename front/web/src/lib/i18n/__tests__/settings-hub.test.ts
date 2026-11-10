import { SETTINGS_TILES, buildSettingsHub, hiddenSettingsTileCount } from '../settings-hub';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #9330 — hub « Paramètres » : filtrage par rôle.
 *
 * `/settings` était une redirection sèche vers `/settings/account` et la
 * sidebar n'avait aucune entrée « Paramètres » : les réglages du tenant
 * (encaissements, appareils, notifications, image de marque) n'étaient
 * découvrables qu'en connaissant l'URL.
 *
 * Le contrat verrouillé ici : le hub ne rend JAMAIS une tuile dont la page
 * cible afficherait « accès réservé ». Chaque tuile porte la garde exacte de
 * sa destination, et ce test la compare aux gardes réelles des pages.
 */

type Body = {
  role: string;
  manager_role: string | null;
};

function user(role: string, managerRole: string | null): StoredAuthUser {
  return { role, manager_role: managerRole } as unknown as StoredAuthUser;
}

function tileKeys(u: StoredAuthUser | null): string[] {
  return buildSettingsHub(u).flatMap((group) => group.tiles.map((tile) => tile.key));
}

const PRINCIPAL: Body = { role: 'manager', manager_role: 'principal' };

describe('Hub Paramètres — plan et filtrage (#9330)', () => {
  it('le principal voit toutes les tuiles, dans l’ordre des groupes', () => {
    const groups = buildSettingsHub(user(PRINCIPAL.role, PRINCIPAL.manager_role));

    expect(groups.map((group) => group.key)).toEqual(['account', 'company', 'platform']);
    expect(tileKeys(user('manager', 'principal'))).toHaveLength(SETTINGS_TILES.length);
    expect(hiddenSettingsTileCount(user('manager', 'principal'))).toBe(0);
  });

  it('un employé ne voit que les tuiles accessibles sans rôle manager', () => {
    const keys = tileKeys(user('employee', null));

    // `/modules` n'a AUCUNE garde de rôle (page ouverte à tout le tenant :
    // c'est là qu'on voit ce qui est inclus dans le plan), contrairement à
    // `/employees`, `/billing` et `/settings/developer`.
    expect(keys.sort()).toEqual(['account', 'devices', 'modules', 'notifications', 'security']);
    // Aucune tuile d'administration ne doit fuiter.
    for (const forbidden of ['team', 'branding', 'payments', 'billing', 'integrations']) {
      expect(keys).not.toContain(forbidden);
    }
    expect(hiddenSettingsTileCount(user('employee', null))).toBeGreaterThan(0);
  });

  it('un manager `dept` ne voit ni encaissements, ni facturation, ni intégrations, ni image de marque', () => {
    const keys = tileKeys(user('manager', 'dept'));

    // `/settings/encaissements` exige `isPrincipalUser` ; `/billing` et
    // `/settings/developer` sont `principal` dans MANAGER_MODULE_ROLES ;
    // `/settings/branding` est modifiable par `principal|rh` seulement (§ le
    // PATCH renvoie 403 aux autres), donc la tuile ne lui est pas proposée.
    expect(keys).not.toContain('payments');
    expect(keys).not.toContain('billing');
    expect(keys).not.toContain('integrations');
    expect(keys).not.toContain('branding');
    // Mais il garde Équipe (toute la gestion d'équipe lui est ouverte).
    expect(keys).toContain('team');
    expect(keys).toContain('modules');
  });

  it('un `rh` garde l’image de marque mais pas les encaissements', () => {
    const keys = tileKeys(user('manager', 'rh'));

    expect(keys).toContain('branding');
    expect(keys).not.toContain('payments');
    expect(keys).not.toContain('billing');
  });

  it('aucun groupe n’est rendu vide', () => {
    for (const role of ['principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing']) {
      const groups = buildSettingsHub(user('manager', role));
      for (const group of groups) {
        expect(group.tiles.length).toBeGreaterThan(0);
      }
    }
  });

  it('chaque tuile pointe vers une route de réglage existante', () => {
    for (const tile of SETTINGS_TILES) {
      expect(tile.href).toMatch(/^\/(settings|employees|modules|billing)/);
      expect(tile.titleFallback.length).toBeGreaterThan(0);
      expect(tile.bodyFallback.length).toBeGreaterThan(0);
    }
  });

  it('deux tuiles ne partagent jamais la même destination (pas de doublon)', () => {
    const hrefs = SETTINGS_TILES.map((tile) => tile.href);
    expect(new Set(hrefs).size).toBe(hrefs.length);
  });
});
