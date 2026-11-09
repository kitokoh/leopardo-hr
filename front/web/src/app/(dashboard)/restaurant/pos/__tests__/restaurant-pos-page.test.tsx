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
// #9210 — plan de salle : une commande `dine_in` exige une table.
const tables = {
  data: [
    { id: 7, label: 'T1', seats: 4, zone_id: 1, is_active: true },
    { id: 8, label: 'T2', seats: 2, zone_id: 1, is_active: true },
  ],
};

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
    if (endpoint.startsWith('/restaurant/tables')) {
      return Promise.resolve(jsonResponse(tables));
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

  // #9210 — une commande sur place exige une table : on en choisit une.
  await waitFor(() => {
    expect(screen.getByRole('option', { name: /T1/ })).toBeInTheDocument();
  });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /Table/i }), '7');

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

  // #9210 — une commande sur place exige une table : on en choisit une.
  await waitFor(() => {
    expect(screen.getByRole('option', { name: /T1/ })).toBeInTheDocument();
  });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /Table/i }), '7');

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
    if (endpoint.startsWith('/restaurant/tables')) {
      return Promise.resolve(jsonResponse(tables));
    }
    return Promise.resolve(jsonResponse({ data: [] }));
  });

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Ouvrir la caisse/i })).toBeInTheDocument();
  });
});

/**
 * #9210 — le POS web envoyait `order_type: 'dine_in'` et `covers: 1` en dur.
 * Ces tests verrouillent le nouveau contrat : le type de commande, la table et
 * les couverts sont choisis en caisse et transmis au serveur.
 */
it('transmet le type de commande, la table et les couverts choisis', async () => {
  mockBaseApis();

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  // Choix d'une table (obligatoire pour une commande sur place).
  await waitFor(() => {
    expect(screen.getByRole('option', { name: /T1/ })).toBeInTheDocument();
  });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /Table/i }), '7');

  await userEvent.click(screen.getByRole('button', { name: /Nouvelle commande/i }));

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/orders',
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('"order_type":"dine_in"'),
      }),
    );
  });

  const call = mockedApiFetch.mock.calls.find(
    ([endpoint, options]) =>
      endpoint === '/restaurant/orders' && (options as RequestInit | undefined)?.method === 'POST',
  );
  const body = JSON.parse(String((call?.[1] as RequestInit).body)) as Record<string, unknown>;

  expect(body.order_type).toBe('dine_in');
  expect(body.table_id).toBe(7);
  expect(body.covers).toBe(1);
});

it('refuse une commande sur place sans table selectionnee', async () => {
  mockBaseApis();

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Nouvelle commande/i })).toBeInTheDocument();
  });
  await userEvent.click(screen.getByRole('button', { name: /Nouvelle commande/i }));

  // Aucun appel de creation : le garde-fou client a bloque avant le serveur.
  await waitFor(() => {
    expect(screen.getByRole('alert')).toHaveTextContent(/Sélectionnez une table/i);
  });
  expect(mockedApiFetch).not.toHaveBeenCalledWith(
    '/restaurant/orders',
    expect.objectContaining({ method: 'POST' }),
  );
});

it('permet une vente a emporter sans table', async () => {
  mockBaseApis();

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  // Le selecteur de type de commande n'apparait qu'une fois la session
  // chargee (appel asynchrone) : on l'attend au lieu de cliquer a l'aveugle.
  const takeaway = await screen.findByRole('button', { name: /emporter/i });
  await userEvent.click(takeaway);
  await userEvent.click(screen.getByRole('button', { name: /Nouvelle commande/i }));

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/orders',
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('"order_type":"takeaway"'),
      }),
    );
  });
});

it('transmet le code promo a l\'addition', async () => {
  mockBaseApis();

  render(<RestaurantPosPage />);

  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  await waitFor(() => {
    expect(screen.getByRole('option', { name: /T1/ })).toBeInTheDocument();
  });
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /Table/i }), '7');
  await userEvent.click(screen.getByRole('button', { name: /Nouvelle commande/i }));

  await waitFor(() => {
    expect(screen.getAllByText('RST-ABC').length).toBeGreaterThan(0);
  });

  await userEvent.click(screen.getByRole('button', { name: /Poulet braisé/i }));
  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Soumettre/i })).toBeInTheDocument();
  });
  await userEvent.click(screen.getByRole('button', { name: /Soumettre/i }));
  await waitFor(() => {
    expect(screen.getByRole('button', { name: /Confirmer/i })).toBeInTheDocument();
  });
  await userEvent.click(screen.getByRole('button', { name: /Confirmer/i }));

  await waitFor(() => {
    expect(screen.getByPlaceholderText(/Code promo/i)).toBeInTheDocument();
  });
  await userEvent.type(screen.getByPlaceholderText(/Code promo/i), 'PROMO10');
  await userEvent.click(screen.getByRole('button', { name: /Addition/i }));

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/orders/11/bill?promotion_code=PROMO10',
      expect.anything(),
    );
  });
});
