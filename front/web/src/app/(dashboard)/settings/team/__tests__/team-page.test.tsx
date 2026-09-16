import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, apiFetch } from '@/lib/api-client';
import TeamSettingsPage from '../page';

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

/** Session manager principal (l'acteur du cas nominal). */
const managerUser = {
  id: 1,
  first_name: 'Nadia',
  last_name: 'Bensalem',
  email: 'nadia@acme.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
};

const employeesPayload = {
  data: [
    {
      id: 1,
      first_name: 'Nadia',
      last_name: 'Bensalem',
      email: 'nadia@acme.dz',
      role: 'manager',
      manager_role: 'principal',
      status: 'active',
    },
    {
      id: 2,
      first_name: 'Amina',
      last_name: 'Cherif',
      email: 'amina@acme.dz',
      role: 'employee',
      manager_role: null,
      status: 'active',
    },
    {
      id: 3,
      first_name: 'Karim',
      last_name: 'Haddad',
      email: 'karim@acme.dz',
      role: 'manager',
      manager_role: 'rh',
      status: 'active',
    },
  ],
  meta: { total: 3, current_page: 1, last_page: 1, per_page: 12 },
};

const invitationsPayload = {
  data: [
    {
      id: 'inv-2',
      email: 'amina@acme.dz',
      employee_id: 2,
      role: 'employee',
      manager_role: null,
      status: 'pending',
      last_sent_at: '2026-09-10T09:00:00+00:00',
      expires_at: '2026-09-17T09:00:00+00:00',
      accepted_at: null,
    },
    {
      id: 'inv-3',
      email: 'karim@acme.dz',
      employee_id: 3,
      role: 'manager',
      manager_role: 'rh',
      status: 'accepted',
      last_sent_at: '2026-09-08T09:00:00+00:00',
      expires_at: '2026-09-15T09:00:00+00:00',
      accepted_at: '2026-09-11T09:00:00+00:00',
    },
  ],
};

/** Mock apiFetch par route (liste, invitations, mutations). */
function mockApiRoutes() {
  mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
    const method = (options?.method ?? 'GET').toUpperCase();

    if (url.startsWith('/employees?') && method === 'GET') {
      return { json: async () => employeesPayload } as Response;
    }

    if (url === '/invitations' && method === 'GET') {
      return { json: async () => invitationsPayload } as Response;
    }

    if (url.startsWith('/departments') && method === 'GET') {
      return { json: async () => ({ data: [{ id: 7, name: 'Technique' }] }) } as Response;
    }

    if (url === '/employees' && method === 'POST') {
      return { json: async () => ({ data: { id: 4 } }) } as Response;
    }

    if (method === 'PATCH') {
      return { json: async () => ({ data: { id: 2 } }) } as Response;
    }

    if (method === 'POST') {
      return { json: async () => ({ data: { id: 4 } }) } as Response;
    }

    return { json: async () => ({ data: [] }) } as Response;
  });
}

/** Corps JSON d'un appel de mutation capturé par le mock. */
function bodyOf(url: string, method: string): Record<string, unknown> {
  const call = mockedApiFetch.mock.calls.find(
    ([calledUrl, calledOptions]) =>
      calledUrl === url && (calledOptions as RequestInit | undefined)?.method === method,
  );

  expect(call).toBeDefined();

  return JSON.parse(String((call?.[1] as RequestInit).body)) as Record<string, unknown>;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.setItem('auth_user', JSON.stringify(managerUser));
  mockApiRoutes();
});

describe('TeamSettingsPage (#7555) — lecture', () => {
  it('affiche les collaborateurs et fusionne le statut d’invitation (GET /invitations)', async () => {
    render(<TeamSettingsPage />);

    expect(await screen.findByText('Amina Cherif')).toBeInTheDocument();
    expect(screen.getByText('Karim Haddad')).toBeInTheDocument();
    expect(screen.getByText('Nadia Bensalem')).toBeInTheDocument();

    // Statuts d'invitation fusionnés par `employee_id`.
    expect(screen.getByTestId('team-invitation-status-2')).toHaveTextContent('En attente');
    expect(screen.getByTestId('team-invitation-status-3')).toHaveTextContent('Acceptée');
    // Collaborateur sans invitation.
    expect(screen.getByTestId('team-row-1')).toHaveTextContent('Aucune invitation');

    expect(mockedApiFetch).toHaveBeenCalledWith('/employees?per_page=12', { _cacheBust: true });
    expect(mockedApiFetch).toHaveBeenCalledWith('/invitations', { _cacheBust: true });
  });

  it('affiche un état « accès réservé » pour un non-manager, sans appel API', async () => {
    window.localStorage.setItem(
      'auth_user',
      JSON.stringify({ id: 9, email: 'salarie@acme.dz', role: 'employee', language: 'fr' }),
    );

    render(<TeamSettingsPage />);

    expect(await screen.findByTestId('team-access-denied')).toHaveTextContent('Accès réservé');
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('recherche via GET /employees?search=… et réinitialise la page', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.type(screen.getByTestId('team-search-input'), 'amina');
    await userEvent.click(screen.getByRole('button', { name: /Rechercher/ }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees?per_page=12&search=amina', { _cacheBust: true }),
    );
  });
});

describe('TeamSettingsPage (#7555) — invitation d’un collaborateur', () => {
  it('invite un manager RH avec role/manager_role/send_invitation', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('team-add-toggle'));
    await userEvent.type(screen.getByTestId('team-add-first-name'), 'Yacine');
    await userEvent.type(screen.getByTestId('team-add-last-name'), 'Meziane');
    await userEvent.type(screen.getByTestId('team-add-email'), 'yacine@acme.dz');
    await userEvent.type(screen.getByTestId('team-add-department'), 'Technique');
    await userEvent.selectOptions(screen.getByTestId('team-add-role'), 'manager:rh');
    await userEvent.click(screen.getByRole('button', { name: /Envoyer l'invitation/ }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees', expect.objectContaining({ method: 'POST' })),
    );

    expect(bodyOf('/employees', 'POST')).toEqual({
      first_name: 'Yacine',
      last_name: 'Meziane',
      email: 'yacine@acme.dz',
      role: 'manager',
      manager_role: 'rh',
      send_invitation: true,
      extra_data: { department: 'Technique' },
    });

    // Confirmation affichée et formulaire refermé.
    expect(await screen.findByRole('status')).toHaveTextContent('Invitation envoyée au collaborateur.');
  });

  it('invite un simple employé sans manager_role', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('team-add-toggle'));
    await userEvent.type(screen.getByTestId('team-add-first-name'), 'Sara');
    await userEvent.type(screen.getByTestId('team-add-last-name'), 'Belkacem');
    await userEvent.type(screen.getByTestId('team-add-email'), 'sara@acme.dz');
    await userEvent.click(screen.getByRole('button', { name: /Envoyer l'invitation/ }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees', expect.objectContaining({ method: 'POST' })),
    );

    expect(bodyOf('/employees', 'POST')).toEqual({
      first_name: 'Sara',
      last_name: 'Belkacem',
      email: 'sara@acme.dz',
      role: 'employee',
      send_invitation: true,
    });
  });

  it('bloque la soumission si les champs obligatoires sont vides (validation native)', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('team-add-toggle'));
    await userEvent.click(screen.getByRole('button', { name: /Envoyer l'invitation/ }));

    expect(screen.getByTestId('team-add-first-name')).toBeInvalid();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/employees', expect.objectContaining({ method: 'POST' }));
  });

  it('affiche un message explicite quand un champ requis ne contient que des espaces', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('team-add-toggle'));
    await userEvent.type(screen.getByTestId('team-add-first-name'), ' ');
    await userEvent.type(screen.getByTestId('team-add-last-name'), 'Meziane');
    await userEvent.type(screen.getByTestId('team-add-email'), 'yacine@acme.dz');
    await userEvent.click(screen.getByRole('button', { name: /Envoyer l'invitation/ }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Renseignez le prénom, le nom et un e-mail valide.',
    );
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/employees', expect.objectContaining({ method: 'POST' }));
  });
});

describe('TeamSettingsPage (#7555) — rôles', () => {
  it('envoie un PATCH /employees/{id} avec le bon manager_role', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.selectOptions(screen.getByTestId('team-role-select-2'), 'manager:comptable');

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees/2', expect.objectContaining({ method: 'PATCH' })),
    );

    expect(bodyOf('/employees/2', 'PATCH')).toEqual({ role: 'manager', manager_role: 'comptable' });
  });

  it('ne propose jamais le rôle principal (création/promotion réservées au super admin)', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('team-add-toggle'));

    const options = Array.from(screen.getByTestId('team-add-role').querySelectorAll('option')).map(
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
    expect(screen.queryByRole('option', { name: /principal/i })).toBeNull();
  });

  it('désactive la modification de rôle sur sa propre ligne', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Nadia Bensalem');

    expect(screen.queryByTestId('team-role-select-1')).toBeNull();
    expect(screen.getByTestId('team-row-1')).toHaveTextContent('Manager principal');
  });

  it('affiche un message compréhensible pour un refus API EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY', async () => {
    mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
      const method = (options?.method ?? 'GET').toUpperCase();

      if (url.startsWith('/employees?') && method === 'GET') {
        return { json: async () => employeesPayload } as Response;
      }

      if (url === '/invitations' && method === 'GET') {
        return { json: async () => invitationsPayload } as Response;
      }

      if (method === 'PATCH') {
        throw new ApiError('EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY', 403, 'EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY');
      }

      return { json: async () => ({ data: [] }) } as Response;
    });

    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.selectOptions(screen.getByTestId('team-role-select-2'), 'manager:rh');

    expect(await screen.findByRole('alert')).toHaveTextContent('Seul un manager principal peut modifier un rôle.');
  });
});

describe('TeamSettingsPage (#7555) — invitations et archivage', () => {
  it('renvoie une invitation via POST /invitations/{id}/resend', async () => {
    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    // Une invitation acceptée ne propose pas de renvoi.
    expect(screen.queryByTestId('team-resend-3')).toBeNull();

    await userEvent.click(screen.getByTestId('team-resend-2'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/invitations/inv-2/resend', { method: 'POST' }),
    );
    expect(await screen.findByRole('status')).toHaveTextContent('Invitation renvoyée.');
  });

  it('archive un collaborateur après confirmation inline (sans window.confirm)', async () => {
    const confirmSpy = jest.spyOn(window, 'confirm');

    render(<TeamSettingsPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('team-archive-2'));
    expect(screen.getByTestId('team-archive-confirm-2')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/employees/2/archive', expect.anything());

    await userEvent.click(screen.getByTestId('team-archive-confirm-2'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees/2/archive', { method: 'POST' }),
    );
    expect(await screen.findByRole('status')).toHaveTextContent('Collaborateur archivé.');
    expect(confirmSpy).not.toHaveBeenCalled();

    confirmSpy.mockRestore();
  });
});
