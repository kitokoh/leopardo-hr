import { describe, it, expect } from 'vitest';
import { createRequire } from 'node:module';

// sw-strategies.js est un module CommonJS (chargé par sw.js via
// importScripts) — require() depuis un test ESM via createRequire.
const require = createRequire(import.meta.url);
const strategies = require('../public/sw-strategies.js');

/**
 * Issue #3971 — stratégies de cache du Service Worker Edge (offline-first) :
 * la logique était embarquée dans sw.js, non testable.
 */

describe('sw-strategies classifyRequest', () => {
  it('routes /api/* to network-first', () => {
    expect(strategies.classifyRequest('/api/v1/edge/health')).toBe('api');
    expect(strategies.classifyRequest('/api/v1/edge/sync')).toBe('api');
  });

  it('routes static assets to cache-first', () => {
    expect(strategies.classifyRequest('/')).toBe('static');
    expect(strategies.classifyRequest('/index.html')).toBe('static');
    expect(strategies.classifyRequest('/_next/static/chunks/app.js')).toBe('static');
    expect(strategies.classifyRequest('/manifest.json')).toBe('static');
  });

  it('does not misclassify paths merely containing /api as a substring', () => {
    expect(strategies.classifyRequest('/apix/health')).toBe('static');
  });
});

describe('sw-strategies offlineApiResponse', () => {
  it('produces a stable JSON error payload (503 contract)', () => {
    const payload = JSON.parse(strategies.offlineApiResponse());

    expect(payload).toEqual({ error: 'offline', message: 'Edge non joignable' });
  });
});

describe('sw-strategies navigationFallback', () => {
  it('falls back to the pre-cached app shell for navigations', () => {
    expect(strategies.navigationFallback('/some/route', 'navigate')).toBe('/index.html');
  });

  it('falls back to the app shell for the root path', () => {
    expect(strategies.navigationFallback('/', 'no-cors')).toBe('/index.html');
  });

  it('returns null for non-navigation, non-root requests (no stale fallback)', () => {
    expect(strategies.navigationFallback('/_next/static/app.js', 'no-cors')).toBeNull();
    expect(strategies.navigationFallback('/manifest.json', 'no-cors')).toBeNull();
  });
});

describe('sw-strategies isCacheable', () => {
  it('caches 200 non-opaque responses only', () => {
    expect(strategies.isCacheable(200, 'basic')).toBe(true);
    expect(strategies.isCacheable(200, 'cors')).toBe(true);
    expect(strategies.isCacheable(200, 'opaque')).toBe(false);
    expect(strategies.isCacheable(404, 'basic')).toBe(false);
    expect(strategies.isCacheable(500, 'basic')).toBe(false);
  });
});
