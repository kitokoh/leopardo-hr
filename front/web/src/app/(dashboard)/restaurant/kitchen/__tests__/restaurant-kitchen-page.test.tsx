import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantKitchenPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;
function jsonResponse(payload: unknown): Response {
  return { json: async () => payload, ok: true, status: 200 } as unknown as Response;
}


const branches = {
  data: [{ id: 1, code: 'BR-001', name: 'Branche Centrale' }],
};

const orders = {
  data: [
    {
      id: 11,
      reference: 'RST-ABC',
      branch_id: 1,
      table_id: 3,
      order_type: 'dine_in',
      status: 'in_preparation',
      covers: 4,
      created_at: '2026-08-30T10:00:00Z',
      items: [
        { id: 1, product_id: 101, name: 'Poulet braisé', quantity: 2, line_index: 1, status: 'active' },
      ],
    },
    {
      id: 12,
      reference: 'RST-DEF',
      branch_id: 1,
      table_id: null,
      order_type: 'takeaway',
      status: 'ready',
      covers: null,
      created_at: '2026-08-30T10:05:00Z',
      items: [
        { id: 2, product_id: 102, name: 'Salade', quantity: 1, line_index: 1, status: 'active' },
      ],
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

it('affiche les commandes en préparation et prêtes avec leurs articles', async () => {
  mockedApiFetch.mockImplementation((endpoint: string) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    return Promise.resolve(jsonResponse(orders));
  });

  render(<RestaurantKitchenPage />);

  await waitFor(() => {
    expect(screen.getByText('RST-ABC')).toBeInTheDocument();
  });

  expect(screen.getByText('RST-DEF')).toBeInTheDocument();
  expect(screen.getByText(/2 × Poulet braisé/)).toBeInTheDocument();
  expect(screen.getByText(/1 × Salade/)).toBeInTheDocument();
  expect(screen.getByText('En préparation')).toBeInTheDocument();
  expect(screen.getByText('Prêtes')).toBeInTheDocument();
});

it('marque une commande prête via POST /ready puis rafraîchit', async () => {
  mockedApiFetch.mockImplementation((endpoint: string, options?: RequestInit) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    if (options?.method === 'POST' && endpoint.includes('/ready')) {
      return Promise.resolve(jsonResponse({ data: { id: 11, status: 'ready' } }));
    }
    return Promise.resolve(jsonResponse(orders));
  });

  render(<RestaurantKitchenPage />);

  const readyButtons = await screen.findAllByRole('button', { name: /Prête/i });
  await userEvent.click(readyButtons[0]);

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/kitchen/orders/11/ready',
      expect.objectContaining({ method: 'POST' }),
    );
  });
});

it('les commandes prêtes n’ont pas de bouton d’action cuisine', async () => {
  mockedApiFetch.mockImplementation((endpoint: string) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    return Promise.resolve(jsonResponse(orders));
  });

  render(<RestaurantKitchenPage />);

  await waitFor(() => {
    expect(screen.getByText('RST-DEF')).toBeInTheDocument();
  });

  // Un seul bouton d'action (sur RST-ABC en préparation), aucun sur RST-DEF.
  const actionButtons = screen.getAllByRole('button', { name: /Prête|Démarrer/i });
  expect(actionButtons).toHaveLength(1);
});

it('affiche une alerte quand le chargement échoue', async () => {
  mockedApiFetch.mockRejectedValue(new Error('network'));

  render(<RestaurantKitchenPage />);

  await waitFor(() => {
    expect(screen.getByRole('alert')).toBeInTheDocument();
  });
});

// REST-OS-5 (#8607) — bandeau d'état imprimante + réimpression.
const printJobsReady = {
  data: [
    {
      id: 91,
      status: 'printed',
      printer_ref: 'PRN-01',
      attempts: 1,
      last_error: null,
      created_at: new Date().toISOString(),
    },
  ],
};

const printJobsFailed = {
  data: [
    {
      id: 92,
      status: 'failed',
      printer_ref: 'PRN-01',
      attempts: 3,
      last_error: 'paper jam',
      created_at: new Date().toISOString(),
    },
  ],
};

function mockWithPrintJobs(printJobs: unknown) {
  mockedApiFetch.mockImplementation((endpoint: string) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    if (endpoint.startsWith('/restaurant/print-jobs')) {
      return Promise.resolve(jsonResponse(printJobs));
    }
    return Promise.resolve(jsonResponse(orders));
  });
}

it('affiche « Prête » quand une imprimante est configurée et aucun ticket bloqué', async () => {
  mockWithPrintJobs(printJobsReady);

  render(<RestaurantKitchenPage />);

  // « Prête » est aussi le libellé du bouton de transition cuisine : on scope
  // la requête au bandeau d'état (role="status").
  await waitFor(() => {
    expect(screen.getByTestId('printer-status-banner')).toHaveTextContent('Prête');
  });
});

it('affiche « Non configurée » quand aucun ticket ne porte de printer_ref', async () => {
  mockWithPrintJobs({ data: [] });

  render(<RestaurantKitchenPage />);

  await waitFor(() => {
    expect(screen.getByTestId('printer-status-banner')).toHaveTextContent('Non configurée');
  });
});

it('affiche « Hors ligne » quand un ticket attend au-delà du seuil', async () => {
  mockWithPrintJobs({
    data: [
      {
        id: 93,
        status: 'pending',
        printer_ref: 'PRN-01',
        attempts: 0,
        last_error: null,
        created_at: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
      },
    ],
  });

  render(<RestaurantKitchenPage />);

  await waitFor(() => {
    expect(screen.getByTestId('printer-status-banner')).toHaveTextContent('Hors ligne');
  });
});

it('un échec d’impression expose « Relancer l’impression » et POST requeue', async () => {
  mockedApiFetch.mockImplementation((endpoint: string, options?: RequestInit) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    if (options?.method === 'POST' && endpoint.includes('/requeue')) {
      return Promise.resolve(jsonResponse({ data: { id: 92, status: 'pending' } }));
    }
    if (endpoint.startsWith('/restaurant/print-jobs')) {
      return Promise.resolve(jsonResponse(printJobsFailed));
    }
    return Promise.resolve(jsonResponse(orders));
  });

  render(<RestaurantKitchenPage />);

  const retry = await screen.findByRole('button', { name: /Relancer l'impression/i });
  await userEvent.click(retry);

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/print-jobs/92/requeue',
      expect.objectContaining({ method: 'POST' }),
    );
  });
});

it('un ticket imprimé expose « Réimprimer » (geste explicite)', async () => {
  mockWithPrintJobs(printJobsReady);

  render(<RestaurantKitchenPage />);

  const reprint = await screen.findByRole('button', { name: /Réimprimer/i });
  await userEvent.click(reprint);

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/print-jobs/91/requeue',
      expect.objectContaining({ method: 'POST' }),
    );
  });
});
