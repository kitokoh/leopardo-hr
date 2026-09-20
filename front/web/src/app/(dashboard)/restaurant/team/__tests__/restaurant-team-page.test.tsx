import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantTeamPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, ok = true, status = 200): Response {
  return { json: async () => payload, ok, status } as unknown as Response;
}

const branches = {
  data: [
    { id: 1, code: 'BR-001', name: 'Branche Centrale', city: 'Abidjan' },
    { id: 2, code: 'BR-002', name: 'Branche Nord', city: 'Bouaké' },
  ],
};

const employees = {
  data: [
    { id: 11, first_name: 'Awa', last_name: 'Koné' },
    { id: 12, first_name: 'Mamadou', last_name: 'Traoré' },
  ],
};

const staff = {
  data: [
    {
      id: 501,
      branch_id: 1,
      employee_id: 11,
      employee: { id: 11, first_name: 'Awa', last_name: 'Koné' },
      role: 'serveur',
      assigned_at: '2026-09-20T10:00:00Z',
      created_at: '2026-09-20T10:00:00Z',
    },
  ],
};

function mockRoutes(overrides: Record<string, (init?: RequestInit) => Response | Promise<Response>> = {}) {
  mockedApiFetch.mockImplementation((endpoint: string, init?: RequestInit) => {
    for (const [prefix, handler] of Object.entries(overrides)) {
      if (endpoint.startsWith(prefix)) return Promise.resolve(handler(init));
    }
    if (endpoint.startsWith('/restaurant/branches?')) return Promise.resolve(jsonResponse(branches));
    if (endpoint.startsWith('/employees?')) return Promise.resolve(jsonResponse(employees));
    if (endpoint.startsWith('/restaurant/branches/1/staff')) return Promise.resolve(jsonResponse(staff));
    return Promise.resolve(jsonResponse({ data: [] }));
  });
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

it('affiche les affectations de la première branche (nom, rôle, date)', async () => {
  mockRoutes();
  render(<RestaurantTeamPage />);

  await waitFor(() => {
    expect(screen.getByTestId('team-row-501')).toBeInTheDocument();
  });

  expect(screen.getAllByText('Awa Koné').length).toBeGreaterThan(0);
  expect(screen.getByText('serveur')).toBeInTheDocument();
  expect(screen.getByText('2026-09-20')).toBeInTheDocument();

  // Sélecteur de succursale partagé (BranchSelect).
  expect(screen.getByRole('option', { name: /Branche Centrale/ })).toBeInTheDocument();
  expect(screen.getByRole('option', { name: /Branche Nord/ })).toBeInTheDocument();
});

it('affecte un employé via le formulaire (POST /restaurant/branches/{id}/staff)', async () => {
  const user = userEvent.setup();
  mockRoutes();
  render(<RestaurantTeamPage />);

  await waitFor(() => {
    expect(screen.getByTestId('team-employee')).toBeInTheDocument();
  });
  await screen.findByRole('option', { name: /Mamadou Traoré/ });

  await user.selectOptions(screen.getByTestId('team-employee'), '12');
  await user.type(screen.getByTestId('team-role'), 'cuisinier');
  await user.click(screen.getByTestId('team-assign'));

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/branches/1/staff',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ employee_id: 12, role: 'cuisinier' }),
      }),
    );
  });
});

it('affiche le message serveur en cas de doublon (409)', async () => {
  const user = userEvent.setup();
  mockRoutes({
    '/restaurant/branches/1/staff?': () => jsonResponse(staff),
  });
  mockedApiFetch.mockImplementation((endpoint: string, init?: RequestInit) => {
    if (init?.method === 'POST' && endpoint === '/restaurant/branches/1/staff') {
      return Promise.resolve(jsonResponse({ message: 'EMPLOYEE_ALREADY_ASSIGNED' }, false, 409));
    }
    if (endpoint.startsWith('/restaurant/branches?')) return Promise.resolve(jsonResponse(branches));
    if (endpoint.startsWith('/employees?')) return Promise.resolve(jsonResponse(employees));
    if (endpoint.startsWith('/restaurant/branches/1/staff')) return Promise.resolve(jsonResponse(staff));
    return Promise.resolve(jsonResponse({ data: [] }));
  });
  render(<RestaurantTeamPage />);

  await waitFor(() => {
    expect(screen.getByTestId('team-employee')).toBeInTheDocument();
  });
  await screen.findByRole('option', { name: /Awa Koné/ });

  await user.selectOptions(screen.getByTestId('team-employee'), '11');
  await user.click(screen.getByTestId('team-assign'));

  await waitFor(() => {
    expect(screen.getByText('EMPLOYEE_ALREADY_ASSIGNED')).toBeInTheDocument();
  });
});

it('modifie le rôle en ligne (PATCH) et retire avec confirmation (DELETE)', async () => {
  const user = userEvent.setup();
  const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
  mockRoutes();
  render(<RestaurantTeamPage />);

  await waitFor(() => {
    expect(screen.getByTestId('team-row-501')).toBeInTheDocument();
  });

  // Édition du rôle.
  await user.click(screen.getByTestId('team-role-edit-501'));
  const roleInput = screen.getByTestId('team-role-input-501');
  await user.clear(roleInput);
  await user.type(roleInput, 'chef de rang');
  await user.click(screen.getByTestId('team-role-save-501'));

  await waitFor(() => {
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/branches/1/staff/501',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({ role: 'chef de rang' }),
      }),
    );
  });

  // Retrait avec confirmation.
  await user.click(screen.getByTestId('team-remove-501'));

  await waitFor(() => {
    expect(confirmSpy).toHaveBeenCalled();
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/restaurant/branches/1/staff/501',
      expect.objectContaining({ method: 'DELETE' }),
    );
  });

  confirmSpy.mockRestore();
});
