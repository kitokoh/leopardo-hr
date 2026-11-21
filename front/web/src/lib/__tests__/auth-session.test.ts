/**
 * @jest-environment jsdom
 */
import {
  AUTH_TOKEN_KEY,
  AUTH_USER_KEY,
  clearAuthSession,
  getDisplayName,
  getStoredUser,
  storeAuthSession,
} from '../auth-session';
import { PREFERRED_LOCALE_KEY } from '../locale-core';
import type { StoredAuthUser } from '../auth-session';

/**
 * #9719 — Le store de session du portail web n'avait AUCUN test dédié.
 *
 * `auth-session.ts` décide de ce qui persiste entre deux visites : le profil
 * public, la locale préférée, et — depuis l'audit #1699 — plus jamais le token.
 * C'est aussi lui qui purge la session quand le JSON stocké est corrompu.
 *
 * Les trois contrats verrouillés ici sont ceux dont la régression est
 * silencieuse : un token réintroduit en localStorage ne casse aucun écran, il
 * expose juste la session au premier XSS ; une locale non persistée ne casse
 * rien non plus, elle fait juste revenir l'utilisateur en français à chaque
 * connexion.
 */

function user(overrides: Partial<StoredAuthUser> = {}): StoredAuthUser {
  return {
    id: 42,
    first_name: 'Amina',
    last_name: 'Cherif',
    email: 'amina@example.com',
    role: 'manager',
    language: 'ar',
    ...overrides,
  };
}

describe('auth-session — persistance de la session (#1699, #9719)', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  describe('storeAuthSession', () => {
    it('écrit le profil public dans localStorage', () => {
      storeAuthSession(null, user());

      const raw = window.localStorage.getItem(AUTH_USER_KEY);
      expect(raw).not.toBeNull();
      expect(JSON.parse(raw as string)).toMatchObject({ id: 42, first_name: 'Amina' });
    });

    it("n'écrit JAMAIS le token, même quand l'appelant en fournit un", () => {
      // Audit #1699 : le token vit dans le cookie httpOnly `leopardo_token`,
      // posé par les route handlers. Le paramètre est conservé pour la
      // compatibilité d'appel mais ne doit jamais atteindre le disque.
      storeAuthSession('990|super-secret-token-value', user());

      expect(window.localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
    });

    it('supprime un token hérité déjà présent (migration depuis une session antérieure)', () => {
      // Un navigateur qui revient d'une version pré-#1699 a encore la clé.
      window.localStorage.setItem(AUTH_TOKEN_KEY, 'legacy-token');

      storeAuthSession(null, user());

      expect(window.localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
    });

    it('persiste la locale préférée de l’utilisateur', () => {
      storeAuthSession(null, user({ language: 'tr' }));

      expect(window.localStorage.getItem(PREFERRED_LOCALE_KEY)).toBe('tr');
    });

    it('normalise une langue inconnue vers le français', () => {
      storeAuthSession(null, user({ language: 'xx' }));

      expect(window.localStorage.getItem(PREFERRED_LOCALE_KEY)).toBe('fr');
    });

    it('retombe sur le français quand la langue est absente', () => {
      storeAuthSession(null, user({ language: null }));

      expect(window.localStorage.getItem(PREFERRED_LOCALE_KEY)).toBe('fr');
    });
  });

  describe('getStoredUser', () => {
    it('relit le profil écrit par storeAuthSession', () => {
      storeAuthSession(null, user());

      expect(getStoredUser()).toMatchObject({ id: 42, email: 'amina@example.com' });
    });

    it('renvoie null quand aucune session n’est stockée', () => {
      expect(getStoredUser()).toBeNull();
    });

    it('purge la session quand le JSON stocké est corrompu', () => {
      // Un JSON tronqué (écriture interrompue, quota, extension) ne doit pas
      // faire planter le shell à chaque rendu : on repart d'une session vide.
      window.localStorage.setItem(AUTH_USER_KEY, '{"id":42,"first_name":');
      window.localStorage.setItem(PREFERRED_LOCALE_KEY, 'ar');

      expect(getStoredUser()).toBeNull();
      expect(window.localStorage.getItem(AUTH_USER_KEY)).toBeNull();
      expect(window.localStorage.getItem(PREFERRED_LOCALE_KEY)).toBeNull();
    });
  });

  describe('clearAuthSession', () => {
    it('efface le profil, le token hérité et la locale préférée', () => {
      storeAuthSession(null, user());
      window.localStorage.setItem(AUTH_TOKEN_KEY, 'legacy-token');

      clearAuthSession();

      expect(window.localStorage.getItem(AUTH_USER_KEY)).toBeNull();
      expect(window.localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
      expect(window.localStorage.getItem(PREFERRED_LOCALE_KEY)).toBeNull();
    });

    it('est idempotent sur une session déjà vide', () => {
      expect(() => clearAuthSession()).not.toThrow();
      expect(getStoredUser()).toBeNull();
    });
  });

  describe('getDisplayName', () => {
    it('compose prénom + nom', () => {
      expect(getDisplayName(user())).toBe('Amina Cherif');
    });

    it('retombe sur `name` quand prénom et nom sont vides', () => {
      expect(getDisplayName(user({ first_name: null, last_name: null, name: 'Amina C.' })))
        .toBe('Amina C.');
    });

    it("retombe sur l'e-mail quand aucun nom n'est disponible", () => {
      expect(getDisplayName(user({ first_name: null, last_name: null, name: null })))
        .toBe('amina@example.com');
    });

    it('retombe sur « Leopardo » sans utilisateur', () => {
      expect(getDisplayName(null)).toBe('Leopardo');
      expect(getDisplayName(undefined)).toBe('Leopardo');
    });
  });
});
