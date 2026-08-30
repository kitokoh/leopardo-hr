import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantPosPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown): Response {
  return { json: async () => payload, ok: true, status: 200 } as unknown as Response;
}

const branches = { data: [{ id: 1, code: 'BR-001', name: 'Branche Centrale' }] };
const openSession = { data: { id: 9, branch_id: 1, status: 'open', opening_cash_minor: 0 } };
const categories = { data: [{ id: 1, name: 'Plats' }] };
const products = {
  data: [
    { id: 101, code: 'PRD-1', name: 'Poulet braisé', price_minor: 2500, currency: 'XAF', category_id: 1, is_available: true },
  ],
};
const draftOrder = { data: { id: 11, reference: 'RST-ABC', status: 'draft', items: [] } };
const orderWithItem = {
  data: {
    id: 11,
    reference: 'RST-ABC',
    status: 'open',
    items: [{ id: 1, product_id: 101, name: 'Poulet braisé', quantity: 1, line_total_minor: 2500, status: 'active' }],
  },
};
const bill = { data: { subtotal_minor: 2500, tax_minor: 0, discount_minor: 0, total_minor: 2500, currency: 'XAF' } };

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

function mockBaseApis() {
  let orderStatus = 'draft';
  let orderItems: typeof orderWithItem.data.items = [];

  mockedApiFetch.mockImplementation((endpoint: string, options?: RequestInit) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    if (endpoint.startsWith('/restaurant/pos-sessions/current')) {
      return Promise.resolve(jsonResponse(openSession));
    }
    if (endpoint.startsWith('/restaurant/categories')) {
      return Promise.resolve(jsonResponse(categories));
    }
    if (endpoint.startsWith('/restaurant/products')) {
      return Promise.resolve(jsonResponse(products));
    }
    if (endpoint === '/restaurant/orders' && options?.method === 'POST') {
      orderStatus = 'draft';
      orderItems = [];
      return Promise.resolve(jsonResponse({ data: { id: 11, reference: 'RST-ABC', status: 'draft', items: [] } }));
    }
    if (endpoint.startsWith('/restaurant/orders/11/items') && options?.method === 'POST') {
      orderItems = [{ id: 1, product_id: 101, name: 'Poulet braisé', quantity: 1, line_total_minor: 2500, status: 'active' }];
      return Promise.resolve(jsonResponse({ data: { id: 1 } }));
    }
    if (endpoint === '/restaurant/orders/11/submit' && options?.method === 'POST') {
      orderStatus = 'open';
      return Promise.resolve(jsonResponse({ data: { id: 11, reference: 'RST-ABC', status: 'open', items: orderItems } }));
    }
    if (endpoint === '/restaurant/orders/11/confirm' && options?.method === 'POST') {
      orderStatus = 'in_preparation';
      return Promise.resolve(jsonResponse({ data: { id: 11, reference: 'RST-ABC', status: 'in_preparation', items: orderItems } }));
    }
    if (endpoint === '/restaurant/orders/11/bill') {
      return Promise.resolve(jsonResponse(bill));
    }
    if (endpoint === '/restaurant/orders/11/pay' && options?.method === 'POST') {
      orderStatus = 'paid';
      return Promise.resolve(jsonResponse({ data: { id: 1, status: 'confirmed' } }));
    }
    if (endpoint.startsWith('/restaurant/orders/11')) {
      return Promise.resolve(jsonResponse({ data: { id: 11, reference: 'RST-ABC', status: orderStatus, items: orderItems } }));
    }
    return Promise.resolve(jsonResponse({ data: [] }));
  });
}

it('affiche le catalogue et crée une commande', async () => {
  mockBaseApis();

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Nouvelle commande/i })).toBeInTheDocument();
  });
  await userEvent.click(screen.getByRole('button', { name: /Nouvelle commande/i }));

  await waitFor(() => {
    expect(screen.getAllByText('RST-ABC').length).toBeGreaterThan(0);
  });
});

it('ajoute un article, soumet, confirme, encaisse en espèces', async () => {
  mockBaseApis();

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Nouvelle commande/i })).toBeInTheDocument();
  });
  await userEvent.click(screen.getByRole('button', { name: /Nouvelle commande/i }));
  await waitFor(() => {
    expect(screen.getAllByText('RST-ABC').length).toBeGreaterThan(0);
  });

  // Ajout d'article : le premier bouton produit.
  await userEvent.click(screen.getByRole('button', { name: /Poulet braisé/i }));
  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/orders/11/items',
      expect.objectContaining({ method: 'POST' }),
    );
  });

  // La commande rafraîchie contient l'article (dans le panier).
  await waitFor(() => {
    expect(screen.getAllByText(/Poulet braisé/).length).toBeGreaterThan(0);
  });

  // Soumission.
  await userEvent.click(screen.getByRole('button', { name: /Soumettre/i }));
  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Confirmer/i })).toBeInTheDocument();
  });

  // Confirmation (cuisine) puis addition + encaissement.
  await userEvent.click(screen.getByRole('button', { name: /Confirmer/i }));
  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Addition/i })).toBeInTheDocument();
  });

  await userEvent.click(screen.getByRole('button', { name: /Addition/i }));
  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Encaisser espèces/i })).toBeInTheDocument();
  });

  await userEvent.click(screen.getByRole('button', { name: /Encaisser espèces/i }));
  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/orders/11/pay',
      expect.objectContaining({ method: 'POST' }),
    );
  });
});

it('sans caisse ouverte, propose d’ouvrir la caisse', async () => {
  mockedApiFetch.mockImplementation((endpoint: string) => {
    if (endpoint.startsWith('/restaurant/branches')) {
      return Promise.resolve(jsonResponse(branches));
    }
    if (endpoint.startsWith('/restaurant/pos-sessions/current')) {
      return Promise.resolve(jsonResponse({ data: null }));
    }
    if (endpoint.startsWith('/restaurant/categories')) {
      return Promise.resolve(jsonResponse(categories));
    }
    if (endpoint.startsWith('/restaurant/products')) {
      return Promise.resolve(jsonResponse(products));
    }
    return Promise.resolve(jsonResponse({ data: [] }));
  });

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Ouvrir la caisse/i })).toBeInTheDocument();
  });
});
