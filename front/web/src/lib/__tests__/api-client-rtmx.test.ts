/**
 * RTMX client (#5446) — tests du wrapper HTTP web :
 * GET conditionnels (ETag/If-None-Match/304) + Idempotency-Key.
 */
import { apiFetch } from '@/lib/api-client';

describe('apiFetch RTMX (#5446)', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
    window.sessionStorage.clear();
  });

  it('envoie If-None-Match quand un ETag est en cache, et sert le corps sur 304', async () => {
    const body = { ok: true, value: 42 };
    const calls: RequestInfo[] = [];

    // 1er appel : 200 + ETag
    global.fetch = jest.fn().mockResolvedValueOnce(
      new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json', ETag: '"abc123"' },
      }),
    ) as unknown as typeof fetch;

    const first = await apiFetch('/me');
    await first.json();
    expect(global.fetch).toHaveBeenCalledTimes(1);

    // 2e appel : le client doit envoyer If-None-Match et accepter un 304.
    global.fetch = jest.fn().mockImplementation(async (input: RequestInfo, init?: RequestInit) => {
      calls.push(input);
      const headers = new Headers(init?.headers);
      expect(headers.get('If-None-Match')).toBe('"abc123"');
      return new Response(null, { status: 304, headers: { ETag: '"abc123"' } });
    }) as unknown as typeof fetch;

    const second = await apiFetch('/me');
    const json = await second.json();
    expect(second.status).toBe(200);
    expect(json).toEqual(body);
    expect(calls.length).toBe(1);
  });

  it('ajoute une Idempotency-Key stable pour une même action POST', async () => {
    global.fetch = jest.fn().mockImplementation(async (_input: RequestInfo, init?: RequestInit) => {
      const headers = new Headers(init?.headers);
      // UUID v4 attendu par le middleware serveur (#5277).
      expect(headers.get('Idempotency-Key')).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
      );
      return new Response(JSON.stringify({ id: 1 }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    }) as unknown as typeof fetch;

    const payload = JSON.stringify({ label: 'X' });
    await apiFetch('/accounting/documents', { method: 'POST', body: payload });
    await apiFetch('/accounting/documents', { method: 'POST', body: payload });

    expect(global.fetch).toHaveBeenCalledTimes(2);
  });

  it('respecte _cacheBust (pas de If-None-Match)', async () => {
    // Remplit d'abord le cache.
    global.fetch = jest.fn().mockResolvedValueOnce(
      new Response(JSON.stringify({ a: 1 }), {
        status: 200,
        headers: { 'Content-Type': 'application/json', ETag: '"etag1"' },
      }),
    ) as unknown as typeof fetch;
    await (await apiFetch('/me')).json();

    global.fetch = jest.fn().mockImplementation(async (_input: RequestInfo, init?: RequestInit) => {
      const headers = new Headers(init?.headers);
      expect(headers.get('If-None-Match')).toBeNull();
      return new Response(JSON.stringify({ a: 2 }), {
        status: 200,
        headers: { 'Content-Type': 'application/json', ETag: '"etag2"' },
      });
    }) as unknown as typeof fetch;

    const r = await apiFetch('/me', { _cacheBust: true });
    expect(await r.json()).toEqual({ a: 2 });
  });
});
