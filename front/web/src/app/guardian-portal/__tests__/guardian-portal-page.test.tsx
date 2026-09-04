import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import GuardianPortalPage from '../page';

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

jest.mock('next/navigation', () => ({
  ...jest.requireActual('next/navigation'),
  useSearchParams: () => new URLSearchParams('token=' + 'a'.repeat(64)),
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

const portalPayload = {
  data: {
    guardian: { id: 1, first_name: 'Fatou', last_name: 'Ndiaye', verified_at: '2026-08-30T10:00:00Z' },
    children: [
      {
        id: 11,
        student_number: 'S-001',
        display_name: 'Awa Ndiaye',
        status: 'active',
        relationship_code: 'parent',
        can_view_grades: true,
        presence: { today_status: 'present', last_30_days: { present: 18, absent: 1, late: 2, excused: 0 }, recorded_days: 21 },
        report_cards: [{ id: 3, period: 'S1', average: 15.25, published_at: '2026-07-01T00:00:00Z' }],
      },
    ],
  },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Portail guardian (EDU-013)', () => {
  it('consomme le lien et affiche uniquement les enfants liés', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(portalPayload));

    render(<GuardianPortalPage />);

    expect(await screen.findByText('Portail responsable légal')).toBeInTheDocument();
    expect(await screen.findByText('Awa Ndiaye')).toBeInTheDocument();
    expect(screen.getByText('S-001 · parent')).toBeInTheDocument();
    expect(screen.getByText('Présent')).toBeInTheDocument();
    expect(screen.getByText('S1')).toBeInTheDocument();
    expect(screen.getByText('15.25')).toBeInTheDocument();

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        expect.stringContaining('/edu-manager/guardian-portal/access-links/'),
        expect.objectContaining({ method: 'POST' }),
      );
    });
  });

  it('affiche l erreur replay/expiration sur 410', async () => {
    const err = new Error('gone') as Error & { status: number };
    err.status = 410;
    mockedApiFetch.mockRejectedValueOnce(err);

    render(<GuardianPortalPage />);

    expect(await screen.findByText('Ce lien a déjà été utilisé et n\'est plus valide.')).toBeInTheDocument();
  });

  it('affiche le lien invalide sur 404', async () => {
    const err = new Error('not found') as Error & { status: number };
    err.status = 404;
    mockedApiFetch.mockRejectedValueOnce(err);

    render(<GuardianPortalPage />);

    expect(await screen.findByText("Lien d'accès invalide.")).toBeInTheDocument();
  });
});
