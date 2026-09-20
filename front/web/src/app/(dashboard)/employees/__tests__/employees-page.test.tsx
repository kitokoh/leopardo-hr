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
  meta: { total: 2, current_page: 1, last_page: 1, per_page: 12 },
};

/** Invitation en attente fusionnée sur Amina (statut synthétique + actions). */
const invitationsPayload = {
  data: [
    {
      id: 'inv-11',
      email: 'amina@acme.dz',
      employee_id: 11,
      role: 'employee',
      manager_role: null,
      status: 'pending',
      last_sent_at: '2026-09-10T09:00:00+00:00',
      expires_at: '2026-09-17T09:00:00+00:00',
      accepted_at: null,
    },
  ],
};

function mockApiRoutes() {
  mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
    const method = (options?.method ?? 'GET').toUpperCase();

    if (url.startsWith('/employees?') && method === 'GET') {
      return { ok: true, json: async () => employeesPayload } as Response;
    }

    if (url === '/invitations' && method === 'GET') {
      return { ok: true, json: async () => invitationsPayload } as Response;
    }

    if (url.startsWith('/departments') && method === 'GET') {
      return { ok: true, json: async () => ({ data: [{ id: 7, name: 'Technique' }] }) } as Response;
    }

    if (url === '/employees/11/module-grants' && method === 'GET') {
      return { ok: true, json: async () => ({ data: { module_keys: ['marketing'] } }) } as Response;
    }

    if (url === '/employees/11/module-grants' && method === 'PUT') {
      return {
        ok: true,
        json: async () => ({ data: { module_keys: ['marketing', 'accounting'] } }),
      } as Response;
    }

    if (url === '/employees' && method === 'POST') {
      return { ok: true, json: async () => ({ data: { id: 13 } }) } as Response;
    }

    return { ok: true, json: async () => ({ data: [] }) } as Response;
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

/** Corps JSON du POST /employees capturé par le mock. */
function createdEmployeeBody(): Record<string, unknown> {
  return bodyOf('/employees', 'POST');
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.setItem(
    'auth_user',
    JSON.stringify({ id: 1, email: 'nadia@acme.dz', role: 'manager', manager_role: 'principal', language: 'fr' }),
  );
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

  it('propose les mêmes options de rôle que l’ancien /settings/team, sans principal', async () => {
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

describe('EmployeesPage (#7862) — page unique de gestion d’équipe', () => {
  it('fusionne le statut d’invitation dans la liste (GET /invitations)', async () => {
    render(<EmployeesPage />);

    expect(await screen.findByText('Amina Cherif')).toBeInTheDocument();

    // Invitation en attente → statut synthétique « En attente », sinon « Actif ».
    expect(screen.getByTestId('employee-status-11')).toHaveTextContent('En attente');
    expect(screen.getByTestId('employee-status-12')).toHaveTextContent('Actif');

    expect(mockedApiFetch).toHaveBeenCalledWith('/invitations', { _cacheBust: true });
  });

  it('ouvre un panneau de détails regroupant rôle, invitation, modules et accès ressources', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('employee-details-toggle-11'));

    const details = await screen.findByTestId('employee-details-11');
    expect(details).toBeInTheDocument();
    // Rôle modifiable (viewer manager, ligne ≠ soi).
    expect(screen.getByTestId('team-role-select-11')).toBeInTheDocument();
    // Accès ressources (#7599) embarqué dans le panneau.
    expect(screen.getByTestId('resource-access-panel')).toBeInTheDocument();
    // Modules délégués (#7762) chargés pour un viewer principal.
    expect(await screen.findByTestId('team-grants-panel-11')).toBeInTheDocument();
  });

  it('renvoie une invitation en attente via POST /invitations/{id}/resend', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('employee-details-toggle-11'));

    expect(screen.getByTestId('team-invitation-status-11')).toHaveTextContent('En attente');
    await userEvent.click(screen.getByTestId('team-resend-11'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/invitations/inv-11/resend', { method: 'POST' }),
    );
    expect(await screen.findByRole('status')).toHaveTextContent('Invitation renvoyée.');
  });

  it('révoque une invitation en attente après confirmation inline', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('employee-details-toggle-11'));
    await userEvent.click(screen.getByTestId('team-revoke-11'));
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/invitations/inv-11', expect.anything());

    await userEvent.click(screen.getByTestId('team-revoke-confirm-11'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/invitations/inv-11', { method: 'DELETE' }),
    );
    expect(await screen.findByRole('status')).toHaveTextContent('Invitation révoquée.');
  });

  it('charge la composition des modules délégués (GET) et envoie le jeu COMPLET en PUT', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('employee-details-toggle-11'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees/11/module-grants', { _cacheBust: true }),
    );

    // Registre fermé ModuleKey : 7 cases, ni plus ni moins.
    const panel = await screen.findByTestId('team-grants-panel-11');
    await waitFor(() => expect(panel.querySelectorAll('input[type="checkbox"]')).toHaveLength(7));

    // La composition existante est cochée.
    expect(screen.getByTestId('team-grant-11-marketing')).toBeChecked();
    expect(screen.getByTestId('team-grant-11-accounting')).not.toBeChecked();

    await userEvent.click(screen.getByTestId('team-grant-11-accounting'));
    await userEvent.click(screen.getByTestId('team-grants-save-11'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/employees/11/module-grants',
        expect.objectContaining({ method: 'PUT' }),
      ),
    );

    expect(bodyOf('/employees/11/module-grants', 'PUT')).toEqual({
      module_keys: ['marketing', 'accounting'],
    });
    expect(await screen.findByRole('status')).toHaveTextContent('Modules délégués mis à jour.');
  });

  it('archive un collaborateur après confirmation inline', async () => {
    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    await userEvent.click(screen.getByTestId('employee-details-toggle-12'));
    await userEvent.click(screen.getByTestId('team-archive-12'));
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/employees/12/archive', expect.anything());

    await userEvent.click(screen.getByTestId('team-archive-confirm-12'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/employees/12/archive', { method: 'POST' }),
    );
    expect(await screen.findByRole('status')).toHaveTextContent('Collaborateur archivé.');
  });

  it('masque les actions de gestion pour un non-manager (miroir RBAC serveur)', async () => {
    window.localStorage.setItem(
      'auth_user',
      JSON.stringify({ id: 9, email: 'salarie@acme.dz', role: 'employee', language: 'fr' }),
    );

    render(<EmployeesPage />);
    await screen.findByText('Amina Cherif');

    // Pas d'appel invitations (policy manageInvitations réservée aux managers).
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/invitations', expect.anything());

    await userEvent.click(screen.getByTestId('employee-details-toggle-11'));
    await screen.findByTestId('employee-details-11');

    expect(screen.queryByTestId('team-role-select-11')).toBeNull();
    expect(screen.queryByTestId('team-resend-11')).toBeNull();
    expect(screen.queryByTestId('team-archive-11')).toBeNull();
    expect(screen.queryByTestId('team-grants-panel-11')).toBeNull();
  });
});
