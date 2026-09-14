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

// Charge utile RÉELLE de la surface publique (#7395) :
// GET /public/travel/shop/bookings/{reference}?code=…
const bookingPayload = {
  data: {
    reference: 'GV-2026-0001',
    status: 'confirmed',
    payment_status: 'confirmed',
    passenger_count: 2,
    trip: { code: 'DLA-YDE-001', departure_date: '2026-09-07', departure_time: '08:00' },
    tickets: [
      { id: 11, ticket_number: 'TK-001', status: 'issued' },
      { id: 12, ticket_number: 'TK-002', status: 'issued' },
    ],
  },
};

describe('TravelPortalPage (TRAVEL-702 / #7395)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Le portail lit la locale via `useVitrineLocale()` : sans préférence
    // explicite, jsdom retombe sur `navigator.language` (en-US) et les
    // libellés/placeholders FR attendus ci-dessous ne sont plus rendus.
    window.localStorage.setItem('preferred_locale', 'fr');
  });

  it('affiche le formulaire de suivi', () => {
    render(<TravelPortalPage />);
    expect(screen.getByPlaceholderText(/GV-2026-0001/)).toBeInTheDocument();
  });

  it('suit la réservation sur l’endpoint PUBLIC (référence + code)', async () => {
    mockedApiFetch.mockResolvedValueOnce({
      json: async () => bookingPayload,
    } as Response);

    render(<TravelPortalPage />);
    await userEvent.type(screen.getByPlaceholderText(/GV-2026-0001/), 'GV-2026-0001');
    await userEvent.type(screen.getByPlaceholderText(/votre e-billet|e-ticket|تذكرتك|biletinizdeki/), 'ABCD1234');
    await userEvent.click(screen.getByRole('button', { name: /Suivre|Track|تتبع|takip/i }));

    await waitFor(() => {
      expect(screen.getByText('GV-2026-0001')).toBeInTheDocument();
    });
    expect(screen.getByText(/DLA-YDE-001/)).toBeInTheDocument();

    // La surface staff (`/travel/shop/bookings/…`) ne doit PLUS être appelée :
    // elle répond 401 à un passager (aucun compte).
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/public/travel/shop/bookings/GV-2026-0001?code=ABCD1234',
    );
  });

  it('télécharge l’e-billet via l’endpoint PUBLIC (code requis)', async () => {
    const openSpy = jest.spyOn(window, 'open').mockImplementation(() => null);
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => bookingPayload } as Response)
      .mockResolvedValueOnce({
        json: async () => ({ data: { ticket_number: 'TK-001', pdf_url: 'https://cdn.example/ticket.pdf' } }),
      } as Response);

    render(<TravelPortalPage />);
    await userEvent.type(screen.getByPlaceholderText(/GV-2026-0001/), 'GV-2026-0001');
    await userEvent.type(screen.getByPlaceholderText(/votre e-billet|e-ticket|تذكرتك|biletinizdeki/), 'ABCD1234');
    await userEvent.click(screen.getByRole('button', { name: /Suivre|Track|تتبع|takip/i }));

    await waitFor(() => {
      expect(screen.getByText('TK-001')).toBeInTheDocument();
    });

    await userEvent.click(screen.getAllByRole('button', { name: /Télécharger|Download/i })[0]);

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith('/public/travel/tickets/11/pdf?code=ABCD1234');
    });
    expect(openSpy).toHaveBeenCalledWith('https://cdn.example/ticket.pdf', '_blank', 'noopener,noreferrer');

    openSpy.mockRestore();
  });

  it('annule la réservation sur l’endpoint PUBLIC (code + motif)', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => bookingPayload } as Response)
      .mockResolvedValueOnce({
        json: async () => ({
          data: { ...bookingPayload.data, status: 'cancelled' },
        }),
      } as Response);

    render(<TravelPortalPage />);
    await userEvent.type(screen.getByPlaceholderText(/GV-2026-0001/), 'GV-2026-0001');
    await userEvent.type(screen.getByPlaceholderText(/votre e-billet|e-ticket|تذكرتك|biletinizdeki/), 'ABCD1234');
    await userEvent.click(screen.getByRole('button', { name: /Suivre|Track|تتبع|takip/i }));

    await waitFor(() => {
      expect(screen.getByText('GV-2026-0001')).toBeInTheDocument();
    });

    await userEvent.type(screen.getByPlaceholderText(/Expliquez|explain|اشرح|açıklayın/), 'Changement de programme');
    await userEvent.click(screen.getByRole('button', { name: /Confirmer l.annulation|Confirm cancellation/i }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/public/travel/shop/bookings/GV-2026-0001/cancel',
        expect.objectContaining({ method: 'POST' }),
      );
    });
  });
});
