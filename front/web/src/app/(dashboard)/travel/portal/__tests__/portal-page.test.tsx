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

const bookingPayload = {
  data: {
    reference: 'GV-2026-0001',
    status: 'confirmed',
    booking_source: 'online',
    total_amount_minor: 24000,
    currency: 'XAF',
    passenger_count: 2,
    trip: { id: 1, code: 'DLA-YDE-001', departure_date: '2026-09-07', departure_time: '08:00' },
    ticket_numbers: ['TK-001', 'TK-002'],
    ticket_ids: [11, 12],
  },
};

describe('TravelPortalPage (TRAVEL-702)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('affiche le formulaire de suivi', () => {
    render(<TravelPortalPage />);
    expect(screen.getByPlaceholderText(/GV-2026-0001/)).toBeInTheDocument();
  });

  it('suit une réservation par référence + code', async () => {
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
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/travel/shop/bookings/GV-2026-0001?code=ABCD1234',
    );
  });

  it('annule la réservation avec motif', async () => {
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
        '/travel/shop/bookings/GV-2026-0001/cancel',
        expect.objectContaining({ method: 'POST' }),
      );
    });
  });
});
