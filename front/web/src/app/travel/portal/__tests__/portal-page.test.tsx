import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import TravelPortalPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status?: number;
    body?: unknown;
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

/**
 * Réponse de la surface PASSAGÈRE (#7395) — pas de PII, pas de `ticket_ids`
 * techniques : seulement ce que le passager détient déjà.
 */
const bookingPayload = {
  data: {
    reference: 'GV-2026-0001',
    status: 'confirmed',
    payment_status: 'confirmed',
    passenger_count: 2,
    total_amount_minor: 24000,
    currency: 'XAF',
    trip: {
      code: 'DLA-YDE-001',
      departure_date: '2026-09-07',
      departure_time: '08:00',
      arrival_date: '2026-09-07',
      arrival_time: '11:30',
      origin: 'Douala',
      destination: 'Yaoundé',
    },
    ticket_numbers: ['#GV-AAAA111111', '#GV-BBBB222222'],
  },
};

const CODE_INPUT = /votre e-billet|e-ticket|تذكرتك|biletinizdeki/;
const TRACK_BUTTON = /Suivre|Track|تتبع|takip/i;

describe('TravelPortalPage (TRAVEL-702 / #7395)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Le portail lit la locale via `useVitrineLocale()` : sans préférence
    // explicite, jsdom retombe sur `navigator.language` (en-US) et les
    // libellés/placeholders FR attendus ci-dessous ne sont plus rendus.
    window.localStorage.setItem('preferred_locale', 'fr');
    window.open = jest.fn();
  });

  it('affiche le formulaire de suivi', () => {
    render(<TravelPortalPage />);
    expect(screen.getByPlaceholderText(/GV-2026-0001/)).toBeInTheDocument();
  });

  it('suit une réservation sur la surface PASSAGÈRE (référence + code)', async () => {
    mockedApiFetch.mockResolvedValueOnce({
      json: async () => bookingPayload,
    } as Response);

    render(<TravelPortalPage />);
    await userEvent.type(screen.getByPlaceholderText(/GV-2026-0001/), 'GV-2026-0001');
    await userEvent.type(screen.getByPlaceholderText(CODE_INPUT), 'ABCD1234');
    await userEvent.click(screen.getByRole('button', { name: TRACK_BUTTON }));

    await waitFor(() => {
      expect(screen.getByText('GV-2026-0001')).toBeInTheDocument();
    });
    expect(screen.getByText(/DLA-YDE-001/)).toBeInTheDocument();

    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/public/travel/passenger/bookings/GV-2026-0001?code=ABCD1234',
    );
  });

  it("n'appelle JAMAIS les endpoints staff (garde anti-régression #7395)", async () => {
    mockedApiFetch.mockResolvedValueOnce({
      json: async () => bookingPayload,
    } as Response);

    render(<TravelPortalPage />);
    await userEvent.type(screen.getByPlaceholderText(/GV-2026-0001/), 'GV-2026-0001');
    await userEvent.type(screen.getByPlaceholderText(CODE_INPUT), 'ABCD1234');
    await userEvent.click(screen.getByRole('button', { name: TRACK_BUTTON }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalled();
    });

    // Avant #7395, la page appelait `/travel/shop/...` et `/travel/tickets/...`
    // (endpoints authentifiés staff) : un vrai passager recevait 401.
    const calls = mockedApiFetch.mock.calls.map(([path]) => String(path));
    expect(calls.some((path) => path.startsWith('/public/travel/passenger/'))).toBe(true);
    expect(calls.some((path) => path.startsWith('/travel/shop/'))).toBe(false);
    expect(calls.some((path) => path.startsWith('/travel/tickets/'))).toBe(false);
  });

  it("télécharge l'e-billet par NUMÉRO sur la surface passagère", async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => bookingPayload } as Response)
      .mockResolvedValueOnce({
        json: async () => ({
          data: { ticket_number: '#GV-AAAA111111', pdf_url: 'https://example.test/t.pdf' },
        }),
      } as Response);

    render(<TravelPortalPage />);
    await userEvent.type(screen.getByPlaceholderText(/GV-2026-0001/), 'GV-2026-0001');
    await userEvent.type(screen.getByPlaceholderText(CODE_INPUT), 'ABCD1234');
    await userEvent.click(screen.getByRole('button', { name: TRACK_BUTTON }));

    await waitFor(() => {
      expect(screen.getByText('#GV-AAAA111111')).toBeInTheDocument();
    });

    const downloadButtons = screen.getAllByRole('button', { name: /Télécharger|Download/i });
    await userEvent.click(downloadButtons[0]);

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/public/travel/passenger/bookings/GV-2026-0001/ticket'
          + '?number=%23GV-AAAA111111&code=ABCD1234',
      );
    });
  });
});
