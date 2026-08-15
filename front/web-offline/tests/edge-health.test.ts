import { describe, it, expect, vi, afterEach } from 'vitest';
import {
  checkEdgeHealth,
  HEALTH_TIMEOUT_MS,
  EDGE_API_DEFAULT,
  type EdgeHealth,
} from '../src/lib/edge-health';

/**
 * Issue #3971 — la machine à états du health-check Edge (contrat #3719/#3772)
 * n'avait aucune couverture de test.
 */

function okResponse(body: EdgeHealth): Response {
  return new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('checkEdgeHealth', () => {
  it('returns online with the health payload when the node responds ok', async () => {
    const fetcher = vi.fn().mockResolvedValue(
      okResponse({ status: 'healthy', node_id: 'edge-1', pending_sync: 3 })
    );

    const result = await checkEdgeHealth('http://edge.test', fetcher);

    expect(result.status).toBe('online');
    expect(result.health).toEqual({ status: 'healthy', node_id: 'edge-1', pending_sync: 3 });
    // Le contrat #3772 : /api/v1/edge/health versionné, timeout borné.
    expect(fetcher).toHaveBeenCalledWith('http://edge.test/api/v1/edge/health', {
      signal: expect.any(AbortSignal),
    });
  });

  it('returns error (node reachable but failing) when the response is not ok', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response('boom', { status: 500 }));

    const result = await checkEdgeHealth('http://edge.test', fetcher);

    expect(result.status).toBe('error');
    expect(result.health).toBeNull();
  });

  it('returns offline when the fetch rejects (node unreachable)', async () => {
    const fetcher = vi.fn().mockRejectedValue(new TypeError('Network request failed'));

    const result = await checkEdgeHealth('http://edge.test', fetcher);

    expect(result.status).toBe('offline');
    expect(result.health).toBeNull();
  });

  it('returns offline when the fetch times out (AbortError)', async () => {
    const fetcher = vi.fn().mockRejectedValue(new DOMException('The operation was aborted', 'AbortError'));

    const result = await checkEdgeHealth('http://edge.test', fetcher);

    expect(result.status).toBe('offline');
    expect(result.health).toBeNull();
  });

  it('returns offline when the JSON payload is malformed', async () => {
    const fetcher = vi.fn().mockResolvedValue(
      new Response('<html>not json</html>', {
        status: 200,
        headers: { 'Content-Type': 'text/html' },
      })
    );

    const result = await checkEdgeHealth('http://edge.test', fetcher);

    expect(result.status).toBe('offline');
    expect(result.health).toBeNull();
  });

  it('never throws — every failure mode maps to a status', async () => {
    const fetcher = vi.fn().mockImplementation(() => {
      throw new Error('sync failure inside fetcher');
    });

    await expect(checkEdgeHealth('http://edge.test', fetcher)).resolves.toEqual({
      status: 'offline',
      health: null,
    });
  });

  it('defaults to the canonical local Edge base URL', async () => {
    const fetcher = vi.fn().mockResolvedValue(okResponse({ status: 'healthy' }));

    await checkEdgeHealth(undefined, fetcher);

    expect(fetcher).toHaveBeenCalledWith(`${EDGE_API_DEFAULT}/api/v1/edge/health`, expect.anything());
  });

  it('bounds the request with a 4s AbortSignal timeout', async () => {
    const fetcher = vi.fn().mockResolvedValue(okResponse({ status: 'healthy' }));

    await checkEdgeHealth('http://edge.test', fetcher);

    const [, init] = fetcher.mock.calls[0] as [string, { signal: AbortSignal }];
    expect(init.signal.aborted).toBe(false);
    expect(HEALTH_TIMEOUT_MS).toBe(4_000);
  });
});
