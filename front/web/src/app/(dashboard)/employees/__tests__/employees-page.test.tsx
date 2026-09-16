import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import EmployeesPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    code?: string;

    constructor(message: string, status = 400, code?: string) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const employeesPayload = {
  data: [
    {
      id: 11,
      first_name: 'Amina',
      last_name: 'Cherif',
      email: 'amina@acme.dz',
      role: 'employee',
      manager_role: null,
      status: 'active',
      matricule: 'EMP-011',
    },
    {
      id: 12,
      first_name: 'Karim',
      last_name: 'Haddad',
      email: 'karim@acme.dz',
      role: 'manager',
      manager_role: 'rh',
      status: 'active',
      matricule: 'EMP-012',
    },
  ],
  meta: { total: 2 },
};

function mockApiRoutes() {
  mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
    const method = (options?.method ?? 'GET').toUpperCase();

    if (url.startsWith('/employees?') && method === 'GET') {
      return { json: async () => employeesPayload } as Response;
    }

    if (url.startsWith('/departments') && method === 'GET') {
      return { json: async () => ({ data: [{ id: 7, name: 'Technique' }] }) } as Response;
    }

    if (url === '/employees' && method === 'POST') {
      return { json: async () => ({ data: { id: 13 } }) } as Response;
    }

    return { json: async () => ({ data: [] }) } as Response;
  });
}

/** Corps JSON du POST /employees capturé par le mock. */
function createdEmployeeBody(): Record<string, unknown> {
  const call = mockedApiFetch.mock.calls.find(
    ([url, options]) => url === '/employees' && (options as RequestInit | undefined)?.method === 'POST',
  );

  expect(call).toBeDefined();

  return JSON.parse(String((call?.[1] as RequestInit).body)) as Record<string, unknown>;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
  window.localStorage.setItem(
    'auth_user',
    JSON.stringify({ id: 1, email: 'nadia@acme.dz', role: 'manager', manager_role: 'principal', language: 'fr' }),
  );
});

beforeEach(() => {
  jest.clearAllMocks();
  mockApiRoutes();
});

describe('EmployeesPage — rôle à l’invitation (#7555)', () => {
  it('affiche un libellé de rôle lisible dans la liste', async () => {
    render(<EmployeesPage />);

    expect(await screen.findByText('Amina Cherif')).toBeInTheDocument();
    // `manager` + `manager_role: rh` → « Manager RH » (et non la clé brute).
    expect(screen.getByText('Manager RH')).toBeInTheDocument();
    expect(screen.getByText('Employé')).toBeInTheDocument();
  });

  it('propose les mêmes options de rôle que /settings/team, sans principal', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByRole('button', { name: /Ajouter un collaborateur/i }));

    const options = Array.from(screen.getByTestId('employees-role-select').querySelectorAll('option')).map(
      (option) => option.getAttribute('value'),
    );

    expect(options).toEqual([
      'employee',
      'manager:rh',
      'manager:dept',
      'manager:comptable',
      'manager:superviseur',
      'manager:marketing',
    ]);
  });

  it('envoie role: employee et send_invitation par défaut', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByRole('button', { name: /Ajouter un collaborateur/i }));
    await userEvent.type(screen.getByLabelText(/Prénom/i), 'Yacine');
    await userEvent.type(screen.getByLabelText(/^Nom/i), 'Meziane');
    await userEvent.type(screen.getByLabelText(/Email/i), 'yacine@acme.dz');
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees', expect.objectContaining({ method: 'POST' })),
    );

    expect(createdEmployeeBody()).toEqual({
      first_name: 'Yacine',
      last_name: 'Meziane',
      email: 'yacine@acme.dz',
      role: 'employee',
      send_invitation: true,
    });
  });

  it('envoie role: manager avec le manager_role choisi (plus de rôle en dur)', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByRole('button', { name: /Ajouter un collaborateur/i }));
    await userEvent.type(screen.getByLabelText(/Prénom/i), 'Sara');
    await userEvent.type(screen.getByLabelText(/^Nom/i), 'Belkacem');
    await userEvent.type(screen.getByLabelText(/Email/i), 'sara@acme.dz');
    await userEvent.selectOptions(screen.getByTestId('employees-role-select'), 'manager:comptable');
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees', expect.objectContaining({ method: 'POST' })),
    );

    expect(createdEmployeeBody()).toEqual({
      first_name: 'Sara',
      last_name: 'Belkacem',
      email: 'sara@acme.dz',
      role: 'manager',
      manager_role: 'comptable',
      send_invitation: true,
    });
  });
});
