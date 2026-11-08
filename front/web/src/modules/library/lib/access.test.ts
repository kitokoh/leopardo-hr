import { getAccessCapabilities, isPubliclyReadable, requiresAuthentication } from '../lib/access';
import { getResource } from '../lib/registry';
import type { LibraryResource } from '../types';

/**
 * Tests de la politique d'accès — lecture, téléchargement, achat, abonnement,
 * accès privé (isolation tenant).
 */

function makeResource(overrides: Partial<LibraryResource>): LibraryResource {
  return {
    slug: 'test',
    category: 'documents-pratiques',
    documentType: 'article',
    language: 'fr',
    metaLanguages: ['fr'],
    accessModel: 'free',
    status: 'published',
    author: 'Test',
    license: { id: 'cc-by-4.0', holder: 'Test', allowDownload: true, allowRedistribution: true },
    keywords: [],
    ...overrides,
  };
}

describe('access — ressource gratuite', () => {
  it('autorise lecture et téléchargement si la licence le permet', () => {
    const caps = getAccessCapabilities(makeResource({ accessModel: 'free' }), { authenticated: false });
    expect(caps.canRead).toBe(true);
    expect(caps.canDownload).toBe(true);
    expect(caps.visible).toBe(true);
  });

  it('désactive le téléchargement si la licence l’interdit', () => {
    const resource = makeResource({
      accessModel: 'free',
      license: { id: 'x', holder: 'x', allowDownload: false, allowRedistribution: true },
    });
    const caps = getAccessCapabilities(resource, { authenticated: false });
    expect(caps.canRead).toBe(true);
    expect(caps.canDownload).toBe(false);
  });
});

describe('access — lecture seule', () => {
  it('autorise la lecture mais jamais le téléchargement', () => {
    const caps = getAccessCapabilities(makeResource({ accessModel: 'read_only' }), {
      authenticated: false,
    });
    expect(caps.canRead).toBe(true);
    expect(caps.canDownload).toBe(false);
  });
});

describe('access — achat à l’unité', () => {
  it('exige l’achat pour un visiteur anonyme', () => {
    const caps = getAccessCapabilities(makeResource({ accessModel: 'purchase' }), {
      authenticated: false,
    });
    expect(caps.canRead).toBe(false);
    expect(caps.requiresPurchase).toBe(true);
  });

  it('autorise après achat', () => {
    const caps = getAccessCapabilities(makeResource({ accessModel: 'purchase' }), {
      authenticated: true,
      purchased: true,
    });
    expect(caps.canRead).toBe(true);
    expect(caps.canDownload).toBe(true);
  });
});

describe('access — abonnement', () => {
  it('exige un abonnement actif', () => {
    const caps = getAccessCapabilities(makeResource({ accessModel: 'subscription' }), {
      authenticated: true,
      subscribed: false,
    });
    expect(caps.canRead).toBe(false);
    expect(caps.requiresSubscription).toBe(true);
  });

  it('autorise avec abonnement actif', () => {
    const caps = getAccessCapabilities(makeResource({ accessModel: 'subscription' }), {
      authenticated: true,
      subscribed: true,
    });
    expect(caps.canRead).toBe(true);
  });
});

describe('access — contenu privé (isolation tenant)', () => {
  const privateResource = makeResource({ accessModel: 'private', tenantId: 'tenant-a' });

  it('refuse un visiteur anonyme', () => {
    const caps = getAccessCapabilities(privateResource, { authenticated: false });
    expect(caps.visible).toBe(false);
    expect(caps.canRead).toBe(false);
    expect(caps.requiresPrivateAccess).toBe(true);
  });

  it('refuse un utilisateur d’un autre tenant', () => {
    const caps = getAccessCapabilities(privateResource, {
      authenticated: true,
      tenantId: 'tenant-b',
    });
    expect(caps.visible).toBe(false);
    expect(caps.canRead).toBe(false);
  });

  it('autorise le tenant propriétaire', () => {
    const caps = getAccessCapabilities(privateResource, {
      authenticated: true,
      tenantId: 'tenant-a',
    });
    expect(caps.visible).toBe(true);
    expect(caps.canRead).toBe(true);
  });
});

describe('access — helpers', () => {
  it('isPubliclyReadable distingue free/read_only des autres', () => {
    expect(isPubliclyReadable(makeResource({ accessModel: 'free' }))).toBe(true);
    expect(isPubliclyReadable(makeResource({ accessModel: 'read_only' }))).toBe(true);
    expect(isPubliclyReadable(makeResource({ accessModel: 'purchase' }))).toBe(false);
    expect(isPubliclyReadable(makeResource({ accessModel: 'private' }))).toBe(false);
  });

  it('requiresAuthentication est l’inverse de isPubliclyReadable', () => {
    expect(requiresAuthentication(makeResource({ accessModel: 'free' }))).toBe(false);
    expect(requiresAuthentication(makeResource({ accessModel: 'subscription' }))).toBe(true);
  });

  it('le catalogue réel expose des ressources publiquement lisibles', () => {
    const resource = getResource('pride-and-prejudice')!;
    expect(isPubliclyReadable(resource)).toBe(true);
  });
});
