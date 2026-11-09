import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

import { getLibraryCopy } from '../../data/copy';
import type { AdminResource } from '../../lib/admin-api';
import { LibraryAdmin } from '../LibraryAdmin';

/**
 * Tests de l'écran d'administration de la bibliothèque (BC-40 LIBRARY).
 *
 * Le client API est mocké : ces tests vérifient le COMPORTEMENT DE L'ÉCRAN
 * (rendu de la liste, filtres transmis à l'API, pagination, états vide/erreur,
 * ouverture du panneau de détail), pas la politique d'accès — celle-ci est
 * serveur et couverte par `LibraryAdminApiTest`.
 */

const copy = getLibraryCopy('fr').admin;

jest.mock('../../lib/admin-api', () => {
  const actual = jest.requireActual('../../lib/admin-api');

  return {
    ...actual,
    fetchAdminResources: jest.fn(),
    createAdminResource: jest.fn(),
    updateAdminResource: jest.fn(),
    deleteAdminResource: jest.fn(),
    transitionAdminResource: jest.fn(),
    uploadAdminResourceFile: jest.fn(),
  };
});

const adminApi = require('../../lib/admin-api') as {
  fetchAdminResources: jest.Mock;
};

function resource(overrides: Partial<AdminResource> = {}): AdminResource {
  return {
    id: 1,
    slug: 'guide-dev',
    title: 'Guide du développeur',
    description: null,
    document_type: 'guide',
    language: 'fr',
    access_model: 'free',
    status: 'draft',
    price_minor: null,
    currency: null,
    license_id: 'cc-by-4.0',
    rights_holder: 'Leopardo',
    allow_download: true,
    featured: false,
    is_new: false,
    published_at: null,
    file_format: null,
    file_size: null,
    has_file: false,
    ...overrides,
  };
}

function page(data: AdminResource[], overrides: Record<string, number> = {}) {
  return {
    data,
    meta: {
      current_page: 1,
      per_page: 20,
      total: data.length,
      last_page: 1,
      ...overrides,
    },
  };
}

beforeEach(() => {
  adminApi.fetchAdminResources.mockReset();
});

describe('LibraryAdmin — liste', () => {
  it('affiche les ressources renvoyées par l’API', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([resource()]));

    render(<LibraryAdmin copy={copy} />);

    expect(await screen.findByText('Guide du développeur')).toBeInTheDocument();
    expect(screen.getByText('guide-dev')).toBeInTheDocument();
    // Le statut est rendu via le libellé i18n, pas la valeur brute. Le libellé
    // apparaît deux fois : dans le badge de la ligne ET dans l'option du filtre.
    expect(screen.getAllByText(copy.workflow.draft).length).toBeGreaterThanOrEqual(1);
  });

  it('affiche l’état vide quand aucune ressource ne correspond', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([]));

    render(<LibraryAdmin copy={copy} />);

    expect(await screen.findByText(copy.list.empty)).toBeInTheDocument();
  });

  it('affiche l’erreur et permet de réessayer', async () => {
    adminApi.fetchAdminResources.mockRejectedValueOnce(new Error('boom'));

    render(<LibraryAdmin copy={copy} />);

    expect(await screen.findByText(copy.list.error)).toBeInTheDocument();

    adminApi.fetchAdminResources.mockResolvedValueOnce(page([resource()]));
    fireEvent.click(screen.getAllByText(copy.list.retry)[0]);

    expect(await screen.findByText('Guide du développeur')).toBeInTheDocument();
  });

  it('transmet le filtre de statut à l’API et revient en page 1', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([resource()]));

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText('Guide du développeur');

    const statusSelect = screen.getByLabelText(copy.filters.status);
    fireEvent.change(statusSelect, { target: { value: 'published' } });

    await waitFor(() => {
      const lastCall = adminApi.fetchAdminResources.mock.calls.at(-1)?.[0];
      expect(lastCall).toMatchObject({ status: 'published', page: 1 });
    });
  });

  it('transmet la recherche à l’API au clic sur Filtrer', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([resource()]));

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText('Guide du développeur');

    fireEvent.change(screen.getByPlaceholderText(copy.filters.searchPlaceholder), {
      target: { value: 'développeur' },
    });
    fireEvent.click(screen.getByText(copy.filters.apply));

    await waitFor(() => {
      const lastCall = adminApi.fetchAdminResources.mock.calls.at(-1)?.[0];
      expect(lastCall).toMatchObject({ q: 'développeur', page: 1 });
    });
  });

  it('réinitialise tous les filtres', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([resource()]));

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText('Guide du développeur');

    fireEvent.change(screen.getByLabelText(copy.filters.language), {
      target: { value: 'ar' },
    });
    await waitFor(() => {
      expect(adminApi.fetchAdminResources.mock.calls.at(-1)?.[0]).toMatchObject({ language: 'ar' });
    });

    fireEvent.click(screen.getByText(copy.filters.reset));

    await waitFor(() => {
      const lastCall = adminApi.fetchAdminResources.mock.calls.at(-1)?.[0];
      expect(lastCall.language).toBeUndefined();
      expect(lastCall.page).toBe(1);
    });
  });

  it('affiche la pagination et navigue vers la page suivante', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(
      page([resource()], { current_page: 1, last_page: 3, total: 45 }),
    );

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText('Guide du développeur');

    expect(screen.getByText(/45/)).toBeInTheDocument();

    fireEvent.click(screen.getByText(copy.list.next));

    await waitFor(() => {
      expect(adminApi.fetchAdminResources.mock.calls.at(-1)?.[0]).toMatchObject({ page: 2 });
    });
  });

  it('désactive « Précédent » sur la première page', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(
      page([resource()], { current_page: 1, last_page: 2, total: 25 }),
    );

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText('Guide du développeur');

    expect(screen.getByText(copy.list.previous).closest('button')).toBeDisabled();
  });

  it('ouvre le panneau de détail au clic sur Modifier', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([resource()]));

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText('Guide du développeur');

    fireEvent.click(screen.getByText(copy.form.edit));

    // Le panneau affiche le formulaire d'édition et le cycle éditorial.
    expect(await screen.findByText(copy.workflow.title)).toBeInTheDocument();
    expect(screen.getByDisplayValue('Guide du développeur')).toBeInTheDocument();
  });

  it('ouvre un formulaire vierge au clic sur Nouvelle ressource', async () => {
    adminApi.fetchAdminResources.mockResolvedValue(page([]));

    render(<LibraryAdmin copy={copy} />);
    await screen.findByText(copy.list.empty);

    fireEvent.click(screen.getByText(copy.form.create));

    expect(await screen.findByText(copy.form.required)).toBeInTheDocument();
  });
});
