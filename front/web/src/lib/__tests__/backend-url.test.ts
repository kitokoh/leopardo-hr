/**
 * @jest-environment node
 *
 * Issue #7842 — fail-fast en production sur URL backend manquante.
 * Verrouille le comportement de `src/lib/backend-url.ts` (copie synchronisée
 * manuellement avec `front/travel-web/src/lib/backend-url.ts`) :
 *   - dev/test : repli sur l'API dev onrender.com + console.warn ;
 *   - production (NODE_ENV/VERCEL_ENV) : erreur actionnable nommant les
 *     variables attendues, plus AUCUN repli silencieux ;
 *   - phase de build Next (`NEXT_PHASE === PHASE_PRODUCTION_BUILD`, cas du
 *     job CI lighthouse qui build sans secrets backend) : JAMAIS de throw,
 *     repli + console.warn — le fail-fast est un contrat de RUNTIME.
 */

import { PHASE_PRODUCTION_BUILD } from 'next/constants';

const DEV_FALLBACK = 'https://gestionemployerbackend.onrender.com/api/v1';

type BackendUrlModule = typeof import('@/lib/backend-url');

function loadModule(): BackendUrlModule {
  let mod: BackendUrlModule | undefined;
  jest.isolateModules(() => {
    mod = require('@/lib/backend-url');
  });
  return mod as BackendUrlModule;
}

describe('backend-url — fail-fast production (#7842)', () => {
  const ORIGINAL_ENV = { ...process.env };
  let warnSpy: jest.SpyInstance;

  beforeEach(() => {
    delete process.env.API_PROXY_TARGET;
    delete process.env.BACKEND_API_URL;
    delete process.env.NEXT_PUBLIC_API_URL;
    delete process.env.VERCEL_ENV;
    delete process.env.NEXT_PHASE;
    warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
  });

  afterEach(() => {
    process.env = { ...ORIGINAL_ENV };
    warnSpy.mockRestore();
  });

  describe('resolveBackendBaseUrl (serveur)', () => {
    it('respecte la priorité API_PROXY_TARGET > BACKEND_API_URL > NEXT_PUBLIC_API_URL', () => {
      process.env.API_PROXY_TARGET = 'https://proxy.example.com/api/v1/';
      process.env.BACKEND_API_URL = 'https://backend.example.com/api/v1';
      expect(loadModule().resolveBackendBaseUrl()).toBe('https://proxy.example.com/api/v1');

      delete process.env.API_PROXY_TARGET;
      expect(loadModule().resolveBackendBaseUrl()).toBe('https://backend.example.com/api/v1');
    });

    it('en dev/test sans variable : repli dev conservé + console.warn explicite', () => {
      const mod = loadModule();
      expect(mod.resolveBackendBaseUrl()).toBe(DEV_FALLBACK);
      expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('#7842'));
    });

    it('en production (VERCEL_ENV) sans variable : erreur actionnable nommant les variables', () => {
      process.env.VERCEL_ENV = 'production';
      const mod = loadModule();
      expect(() => mod.resolveBackendBaseUrl()).toThrow(/API_PROXY_TARGET/);
      expect(() => mod.resolveBackendBaseUrl()).toThrow(/BACKEND_API_URL/);
      expect(() => mod.resolveBackendBaseUrl()).toThrow(/NEXT_PUBLIC_API_URL/);
      expect(() => mod.resolveBackendBaseUrl()).toThrow(/onrender\.com/);
    });

    it('en production avec variable posée : aucune erreur, aucun warn', () => {
      process.env.VERCEL_ENV = 'production';
      process.env.BACKEND_API_URL = 'https://api.leopardo-rh.com/api/v1';
      expect(loadModule().resolveBackendBaseUrl()).toBe('https://api.leopardo-rh.com/api/v1');
      expect(warnSpy).not.toHaveBeenCalled();
    });

    it('pendant `next build` (NEXT_PHASE) sans variable : PAS de throw, repli + console.warn (CI lighthouse)', () => {
      process.env.VERCEL_ENV = 'production';
      process.env.NEXT_PHASE = PHASE_PRODUCTION_BUILD;
      const mod = loadModule();
      expect(mod.resolveBackendBaseUrl()).toBe(DEV_FALLBACK);
      expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('#7842'));
    });

    it('le throw runtime prod reste intact hors phase de build (NEXT_PHASE absent)', () => {
      process.env.VERCEL_ENV = 'production';
      expect(() => loadModule().resolveBackendBaseUrl()).toThrow(/#7842/);
    });
  });

  describe('getApiBaseUrl (client)', () => {
    it('en dev/test sans NEXT_PUBLIC_API_URL : repli dev conservé + console.warn', () => {
      const mod = loadModule();
      expect(mod.getApiBaseUrl()).toBe(DEV_FALLBACK);
      expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('NEXT_PUBLIC_API_URL'));
    });

    it('en production sans NEXT_PUBLIC_API_URL : erreur actionnable', () => {
      process.env.VERCEL_ENV = 'production';
      const mod = loadModule();
      expect(() => mod.getApiBaseUrl()).toThrow(/NEXT_PUBLIC_API_URL/);
      expect(() => mod.getApiBaseUrl()).toThrow(/#7842/);
    });

    it('en production avec NEXT_PUBLIC_API_URL : renvoie la valeur sans slash final', () => {
      process.env.VERCEL_ENV = 'production';
      process.env.NEXT_PUBLIC_API_URL = 'https://api.leopardo-rh.com/api/v1/';
      expect(loadModule().getApiBaseUrl()).toBe('https://api.leopardo-rh.com/api/v1');
    });

    it('pendant `next build` (NEXT_PHASE) sans variable : PAS de throw, repli + console.warn', () => {
      process.env.VERCEL_ENV = 'production';
      process.env.NEXT_PHASE = PHASE_PRODUCTION_BUILD;
      const mod = loadModule();
      expect(mod.getApiBaseUrl()).toBe(DEV_FALLBACK);
      expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('NEXT_PUBLIC_API_URL'));
    });
  });
});
