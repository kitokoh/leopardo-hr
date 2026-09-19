import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import TravelStaffPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

// Contrats RÉELS du pont RH #7638 (TravelStaffAssignmentResource) et des
// référentiels consommés par la page (/employees, /travel/offices, /travel/trips).
const assignments = {
  data: [
    {
      id: 1,
      employee_id: 10,
      role: 'driver',
      office_id: null,
      trip_id: 5,
      status: 'active',
      revoked_at: null,
      created_at: '2026-09-20T08:00:00Z',
    },
    {
      id: 2,
      employee_id: 11,
      role: 'agent',
      office_id: 3,
      trip_id: null,
      status: 'revoked',
      revoked_at: '2026-09-19T10:00:00Z',
      created_at: '2026-09-10T08:00:00Z',
    },
  ],
};

const employees = {
  data: [
    { id: 10, first_name: 'Awa', last_name: 'Ndiaye' },
    { id: 11, first_name: 'Malik', last_name: 'Sow' },
  ],
};

const offices = { data: [{ id: 3, name: 'Bureau Douala Centre' }] };
const trips = { data: [{ id: 5, code: 'DLA-YDE-001', departure_date: '2026-09-25' }] };

function jsonResponse(body: unknown, ok = true, status = 200): Response {
  return { ok, status, json: async () => body } as Response;
}

function mockLoad() {
  mockedApiFetch.mockImplementation(async (path: string) => {
    if (path.startsWith('/travel/staff-assignments?')) return jsonResponse(assignments);
    if (path.startsWith('/employees')) return jsonResponse(employees);
    if (path.startsWith('/travel/offices')) return jsonResponse(offices);
    if (path.startsWith('/travel/trips')) return jsonResponse(trips);
    if (path === '/travel/staff-assignments') return jsonResponse({ data: {} }, true, 201);
    if (path.endsWith('/revoke')) return jsonResponse({ data: {} });
    throw new Error(`unexpected path: ${path}`);
  });
}

describe('TravelStaffPage (TRAVEL-UI / #7639)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.localStorage.setItem('preferred_locale', 'fr');
    mockLoad();
  });

  it('liste les affectations actives avec le nom de l’employé et le scope', async () => {
    render(<TravelStaffPage />);
    await waitFor(() => {
      expect(screen.getByTestId('staff-row-1')).toBeInTheDocument();
    });
    const row = within(screen.getByTestId('staff-row-1'));
    expect(row.getByText('Awa Ndiaye')).toBeInTheDocument();
    expect(row.getByText('Chauffeur')).toBeInTheDocument();
    expect(row.getByText(/DLA-YDE-001/)).toBeInTheDocument();
    // L'affectation révoquée n'apparaît pas dans le tableau actif.
    expect(screen.queryByTestId('staff-row-2')).not.toBeInTheDocument();
  });

  it('révoque une affectation en un clic (POST …/revoke)', async () => {
    render(<TravelStaffPage />);
    await waitFor(() => {
      expect(screen.getByTestId('staff-revoke-1')).toBeInTheDocument();
    });
    await userEvent.click(screen.getByTestId('staff-revoke-1'));
    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith('/travel/staff-assignments/1/revoke', {
        method: 'POST',
      });
    });
  });

  it('nomme un employé sur un bureau (POST /travel/staff-assignments)', async () => {
    render(<TravelStaffPage />);
    await waitFor(() => {
      expect(screen.getByTestId('staff-row-1')).toBeInTheDocument();
    });
    await userEvent.selectOptions(screen.getByTestId('staff-employee'), '11');
    await userEvent.selectOptions(screen.getByTestId('staff-role'), 'agent');
    await userEvent.selectOptions(screen.getByTestId('staff-office'), '3');
    await userEvent.click(screen.getByTestId('staff-assign'));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith('/travel/staff-assignments', {
        method: 'POST',
        body: JSON.stringify({ employee_id: 11, role: 'agent', office_id: 3 }),
      });
    });
  });

  it('montre l’historique des affectations révoquées', async () => {
    render(<TravelStaffPage />);
    await waitFor(() => {
      expect(screen.getByTestId('staff-row-1')).toBeInTheDocument();
    });
    await userEvent.click(screen.getByTestId('staff-history-toggle'));
    expect(screen.getByText('Révoquée')).toBeInTheDocument();
    expect(screen.getByText(/Bureau — Bureau Douala Centre/)).toBeInTheDocument();
  });
});
