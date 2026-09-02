import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import EduManagerHomePage from '../page';
import CampusesPage from '../campuses/page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return {
    json: async () => payload,
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    clone: () => jsonResponse(payload, status),
  } as unknown as Response;
}

const meta = (total: number) => ({ meta: { total } });

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('EduManager home (EDU-011)', () => {
  it('affiche les compteurs de l administration scolaire pour un manager', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [], ...meta(2) })) // campuses
      .mockResolvedValueOnce(jsonResponse({ data: [], ...meta(4) })) // classes
      .mockResolvedValueOnce(jsonResponse({ data: [], ...meta(30) })) // students
      .mockResolvedValueOnce(jsonResponse({ data: [], ...meta(1) })) // admissions
      .mockResolvedValueOnce(jsonResponse({ data: [], ...meta(2) })); // report-cards

    render(<EduManagerHomePage />);

    expect(await screen.findByText('EduManager')).toBeInTheDocument();
    expect(await screen.findByText('30')).toBeInTheDocument();
    expect(screen.getByText('Campus')).toBeInTheDocument();
    expect(screen.getByText('Classes')).toBeInTheDocument();
    expect(screen.getByText('Admissions')).toBeInTheDocument();
    // Navigation rapide vers les écrans admin.
    expect(screen.getByText('Gérer les campus')).toBeInTheDocument();
    expect(screen.getByText('Gérer les élèves')).toBeInTheDocument();
  });
});

describe('EduManager campuses CRUD (EDU-011)', () => {
  it('liste, crée et supprime un campus', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({
      data: [
        { id: 1, code: 'MAIN', name: 'Campus Principal', address: null, timezone: 'UTC', status: 'active' },
      ],
    }));

    render(<CampusesPage />);

    expect(await screen.findByText('Campus Principal')).toBeInTheDocument();
    expect(screen.getByText('MAIN')).toBeInTheDocument();

    // Création
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 2 } }, 201));
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({
      data: [
        { id: 1, code: 'MAIN', name: 'Campus Principal', address: null, timezone: 'UTC', status: 'active' },
        { id: 2, code: 'SEC', name: 'Campus Secondaire', address: null, timezone: 'UTC', status: 'active' },
      ],
    })); // reload après création
    await userEvent.click(screen.getByText('Nouveau campus'));
    await userEvent.type(screen.getByLabelText('Code'), 'SEC');
    await userEvent.type(screen.getByLabelText('Nom'), 'Campus Secondaire');
    await userEvent.click(screen.getByText('Enregistrer'));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/campuses',
        expect.objectContaining({ method: 'POST', body: expect.stringContaining('SEC') }),
      );
    });

    // Suppression
    window.confirm = jest.fn(() => true);
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(null, 204));
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));
    await userEvent.click(screen.getAllByTitle('Supprimer')[0]);

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith('/edu-manager/campuses/1', { method: 'DELETE' });
    });
  });
});
