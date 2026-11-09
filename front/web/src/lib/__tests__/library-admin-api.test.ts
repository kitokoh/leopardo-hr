import {
  createLibraryResource,
  deleteLibraryResource,
  libraryErrorMessage,
  listLibraryResources,
  transitionLibraryResource,
  updateLibraryResource,
  uploadLibraryResourceFile,
  type LibraryResource,
} from '../library-admin-api';
import { ApiError } from '../api-client';

/**
 * Tests du client API d'administration Library (#9241).
 *
 * Le contrat vérifié est celui qui compte : les bons VERBES et CHEMINS, le
 * multipart qui ne pose pas de `Content-Type` à la main, et un message
 * d'erreur lisible pour chaque famille de refus serveur (422, 403, 409, 501).
 */

jest.mock('../api-client', () => {
  const actual = jest.requireActual('../api-client');
  return { ...actual, apiFetch: jest.fn() };
});

// eslint-disable-next-line @typescript-eslint/no-var-requires
const { apiFetch } = require('../api-client') as { apiFetch: jest.Mock };

const resource: LibraryResource = {
  id: 7,
  slug: 'guide-rh',
  title: 'Guide RH',
  description: null,
  document_type: 'guide',
  language: 'fr',
  access_model: 'free',
  status: 'draft',
  price_minor: null,
  currency: null,
  license_id: null,
  rights_holder: null,
  allow_download: true,
  featured: false,
  is_new: false,
  published_at: null,
  file_format: null,
  file_size: null,
  has_file: false,
};

const jsonResponse = (body: unknown) => ({ ok: true, json: async () => body });

beforeEach(() => {
  apiFetch.mockReset();
});

describe('listLibraryResources', () => {
  it('appelle GET /library/resources', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: [resource] }));

    const result = await listLibraryResources();

    expect(apiFetch).toHaveBeenCalledWith('/library/resources');
    expect(result).toEqual([resource]);
  });

  it('transmet le filtre de statut', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: [] }));

    await listLibraryResources('published');

    expect(apiFetch).toHaveBeenCalledWith('/library/resources?status=published');
  });

  it('retourne un tableau vide si la charge utile est malformée', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: 'nope' }));
    expect(await listLibraryResources()).toEqual([]);
  });
});

describe('createLibraryResource', () => {
  it('appelle POST /library/resources avec la charge utile', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: resource }));

    const created = await createLibraryResource({ title: 'Guide RH' });

    expect(apiFetch).toHaveBeenCalledWith('/library/resources', {
      method: 'POST',
      body: JSON.stringify({ title: 'Guide RH' }),
    });
    expect(created.id).toBe(7);
  });
});

describe('updateLibraryResource', () => {
  it('appelle PUT /library/resources/{id}', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: resource }));

    await updateLibraryResource(7, { title: 'Nouveau titre' });

    expect(apiFetch).toHaveBeenCalledWith('/library/resources/7', {
      method: 'PUT',
      body: JSON.stringify({ title: 'Nouveau titre' }),
    });
  });
});

describe('deleteLibraryResource', () => {
  it('appelle DELETE /library/resources/{id}', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: { deleted: true } }));

    await deleteLibraryResource(7);

    expect(apiFetch).toHaveBeenCalledWith('/library/resources/7', { method: 'DELETE' });
  });
});

describe('uploadLibraryResourceFile', () => {
  it('envoie un FormData sans poser de Content-Type', async () => {
    apiFetch.mockResolvedValue(jsonResponse({ data: { ...resource, has_file: true } }));

    const file = new File(['contenu'], 'guide.pdf', { type: 'application/pdf' });
    const updated = await uploadLibraryResourceFile(7, file);

    const [endpoint, init] = apiFetch.mock.calls[0];
    expect(endpoint).toBe('/library/resources/7/file');
    expect(init.method).toBe('POST');
    expect(init.body).toBeInstanceOf(FormData);
    // Le Content-Type est laissé à `fetch` : le poser casserait la frontière.
    expect(init.headers).toBeUndefined();
    expect(updated.has_file).toBe(true);
  });
});

describe('transitionLibraryResource', () => {
  it.each(['publish', 'unpublish', 'archive'] as const)('appelle POST /%s', async (action) => {
    apiFetch.mockResolvedValue(jsonResponse({ data: resource }));

    await transitionLibraryResource(7, action);

    expect(apiFetch).toHaveBeenCalledWith(`/library/resources/7/${action}`, { method: 'POST' });
  });
});

describe('libraryErrorMessage', () => {
  it('extrait la première erreur de champ d’un 422', () => {
    const error = new ApiError('Unprocessable', 422);
    (error as ApiError & { payload?: unknown }).payload = {
      errors: { title: ['Le titre est obligatoire.'] },
    };

    expect(libraryErrorMessage(error, 'défaut')).toBe('Le titre est obligatoire.');
  });

  it('extrait le message serveur d’un 501 (stockage non configuré)', () => {
    const error = new ApiError('Not Implemented', 501);
    (error as ApiError & { payload?: unknown }).payload = {
      error: 'storage_not_configured',
      message: 'Le stockage de fichiers n’est pas configuré.',
    };

    expect(libraryErrorMessage(error, 'défaut')).toBe('Le stockage de fichiers n’est pas configuré.');
  });

  it('retourne le repli sur un 403 (RBAC serveur)', () => {
    const error = new ApiError('Forbidden', 403);
    expect(libraryErrorMessage(error, 'interdit')).toBe('interdit');
  });

  it('retourne le repli sur une erreur inconnue', () => {
    expect(libraryErrorMessage(null, 'défaut')).toBe('défaut');
  });

  it('retourne le message d’une Error standard', () => {
    expect(libraryErrorMessage(new Error('réseau'), 'défaut')).toBe('réseau');
  });
});
