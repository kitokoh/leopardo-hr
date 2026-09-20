/**
 * #7842 — [AUDIT][SECU] Fin du fallback silencieux vers l'API Render de DEV.
 *
 * En production (NODE_ENV=production), une variable d'environnement manquante
 * doit lever une erreur actionnable ; en dev/test, le fallback est conservé
 * avec un warning console explicite.
 */
import { DEFAULT_BACKEND_API_URL, getApiBaseUrl, resolveBackendBaseUrl } from '../backend-url';

const ENV_KEYS = ['API_PROXY_TARGET', 'BACKEND_API_URL', 'NEXT_PUBLIC_API_URL'] as const;

describe('backend-url (#7842)', () => {
  const originalEnv: Record<string, string | undefined> = {};
  let warnSpy: jest.SpyInstance;

  beforeEach(() => {
    for (const key of ENV_KEYS) {
      originalEnv[key] = process.env[key];
      delete process.env[key];
    }
    originalEnv.NODE_ENV = process.env.NODE_ENV;
    warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => undefined);
  });

  afterEach(() => {
    for (const key of [...ENV_KEYS, 'NODE_ENV'] as const) {
      if (originalEnv[key] === undefined) {
        delete (process.env as Record<string, string | undefined>)[key];
      } else {
        (process.env as Record<string, string>)[key] = originalEnv[key] as string;
      }
    }
    warnSpy.mockRestore();
  });

  it('suit la chaîne API_PROXY_TARGET > BACKEND_API_URL > NEXT_PUBLIC_API_URL', () => {
    process.env.NEXT_PUBLIC_API_URL = 'https://c.example.com/api/v1/';
    expect(resolveBackendBaseUrl()).toBe('https://c.example.com/api/v1');

    process.env.BACKEND_API_URL = 'https://b.example.com/api/v1';
    expect(resolveBackendBaseUrl()).toBe('https://b.example.com/api/v1');

    process.env.API_PROXY_TARGET = 'https://a.example.com/api/v1';
    expect(resolveBackendBaseUrl()).toBe('https://a.example.com/api/v1');
  });

  it('en dev/test : fallback DEV conservé mais avec warning console', () => {
    expect(resolveBackendBaseUrl()).toBe(DEFAULT_BACKEND_API_URL);
    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('fallback DEV'));
  });

  it('en production : variable manquante → erreur explicite (serveur)', () => {
    (process.env as Record<string, string>).NODE_ENV = 'production';
    expect(() => resolveBackendBaseUrl()).toThrow(/NODE_ENV=production/);
    expect(() => resolveBackendBaseUrl()).toThrow(/#7842/);
  });

  it('en production : variable manquante → erreur explicite (client)', () => {
    (process.env as Record<string, string>).NODE_ENV = 'production';
    expect(() => getApiBaseUrl()).toThrow(/NEXT_PUBLIC_API_URL/);
  });

  it('en production : variable posée → aucun throw, URL normalisée', () => {
    (process.env as Record<string, string>).NODE_ENV = 'production';
    process.env.NEXT_PUBLIC_API_URL = 'https://api.leopardo-rh.com/api/v1/';
    expect(resolveBackendBaseUrl()).toBe('https://api.leopardo-rh.com/api/v1');
    expect(getApiBaseUrl()).toBe('https://api.leopardo-rh.com/api/v1');
    expect(warnSpy).not.toHaveBeenCalled();
  });
});
