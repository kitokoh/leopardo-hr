import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DiscoveryExplorer from '../discovery-explorer';
import type { PublicDiscoveryItem, PublicDiscoveryMeta } from '@/lib/public-discovery-api';

/**
 * SUPERAPP-P1 (#8760) — contact public depuis `/decouvrir`.
 *
 * Couvre le parcours client réel : clic sur « Contacter » → remplissage →
 * envoi → accusé avec référence. Et surtout les états NON optimistes :
 * profil indisponible (404 uniforme) et panne réseau ne doivent JAMAIS
 * s'afficher comme un succès.
 *
 * Vérifie aussi qu'AUCUN identifiant interne (tenant) ne part du navigateur :
 * seuls `target_type` et `target_slug` sont transmis.
 */

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), prefetch: jest.fn() }),
  usePathname: () => '/decouvrir',
  useSearchParams: () => new URLSearchParams(),
}));

jest.mock('@/lib/api-client', () => ({ apiFetch: jest.fn() }));

const meta: PublicDiscoveryMeta = {
  total: 1,
  per_page: 20,
  page: 1,
  last_page: 1,
  types: ['restaurant', 'showcase'],
  radius_km: null,
};

const items: PublicDiscoveryItem[] = [
  {
    type: 'showcase',
    slug: 'atelier-bois',
    name: 'Atelier Bois',
    category: 'menuiserie',
    city: 'Rabat',
    description: 'Menuiserie sur mesure',
    url: '/vitrine/atelier-bois',
  },
];

const baseProps = {
  locale: 'fr' as const,
  initialItems: items,
  initialMeta: meta,
  initialError: false,
  initialQuery: { q: '', city: '', type: '', page: 1 },
};

function mockFetchOnce(status: number, body: unknown = {}) {
  return jest.fn().mockResolvedValue({
    status,
    ok: status >= 200 && status < 300,
    json: async () => body,
  });
}

function fillForm() {
  fireEvent.change(screen.getByLabelText(/votre nom/i), { target: { value: 'Amina Client' } });
  fireEvent.change(screen.getByLabelText(/votre e-mail/i), {
    target: { value: 'amina@example.test' },
  });
  fireEvent.change(screen.getByLabelText(/votre message/i), {
    target: { value: 'Bonjour, je souhaite un devis.' },
  });
  fireEvent.click(screen.getByRole('checkbox'));
}

describe('DiscoveryExplorer — public contact (#8760)', () => {
  const realFetch = global.fetch;

  afterEach(() => {
    global.fetch = realFetch;
    jest.clearAllMocks();
  });

  it('opens the contact form from a result and sends only the public identifiers', async () => {
    const fetchMock = mockFetchOnce(201, {
      data: { status: 'received', reference: 'pc-ref-123' },
    });
    global.fetch = fetchMock as unknown as typeof fetch;

    render(<DiscoveryExplorer {...baseProps} />);

    fireEvent.click(screen.getByRole('button', { name: /contacter/i }));
    fillForm();
    fireEvent.click(screen.getByRole('button', { name: /^envoyer$/i }));

    await waitFor(() => {
      expect(screen.getByText(/bien été envoyée/i)).toBeInTheDocument();
    });
    expect(screen.getByText(/pc-ref-123/)).toBeInTheDocument();

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const payload = JSON.parse(String(init.body)) as Record<string, unknown>;

    expect(payload).toMatchObject({
      target_type: 'showcase',
      target_slug: 'atelier-bois',
      sender_name: 'Amina Client',
      sender_email: 'amina@example.test',
      consent: true,
    });
    // Aucun identifiant interne ne quitte le navigateur.
    expect(payload).not.toHaveProperty('company_id');
    expect(payload).not.toHaveProperty('resolved_company_id');
    // Clé d'idempotence fournie (retry sans doublon).
    expect(typeof payload.idempotency_key).toBe('string');
    expect(String(payload.idempotency_key).length).toBeGreaterThan(8);
  });

  it('shows an explicit unavailable state on 404 (no fake success)', async () => {
    global.fetch = mockFetchOnce(404, {
      error: 'PROFILE_NOT_AVAILABLE',
    }) as unknown as typeof fetch;

    render(<DiscoveryExplorer {...baseProps} />);

    fireEvent.click(screen.getByRole('button', { name: /contacter/i }));
    fillForm();
    fireEvent.click(screen.getByRole('button', { name: /^envoyer$/i }));

    await waitFor(() => {
      expect(screen.getByText(/n'accepte pas de demande/i)).toBeInTheDocument();
    });
    expect(screen.queryByText(/bien été envoyée/i)).not.toBeInTheDocument();
  });

  it('shows a retryable error state when the network fails', async () => {
    global.fetch = jest.fn().mockRejectedValue(new Error('offline')) as unknown as typeof fetch;

    render(<DiscoveryExplorer {...baseProps} />);

    fireEvent.click(screen.getByRole('button', { name: /contacter/i }));
    fillForm();
    fireEvent.click(screen.getByRole('button', { name: /^envoyer$/i }));

    await waitFor(() => {
      expect(screen.getByText(/l'envoi a échoué/i)).toBeInTheDocument();
    });
    expect(screen.queryByText(/bien été envoyée/i)).not.toBeInTheDocument();
  });

  it('keeps the same idempotency key across retries', async () => {
    const fetchMock = jest
      .fn()
      .mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValueOnce({
        status: 201,
        ok: true,
        json: async () => ({ data: { status: 'received', reference: 'pc-retry' } }),
      });
    global.fetch = fetchMock as unknown as typeof fetch;

    render(<DiscoveryExplorer {...baseProps} />);

    fireEvent.click(screen.getByRole('button', { name: /contacter/i }));
    fillForm();
    fireEvent.click(screen.getByRole('button', { name: /^envoyer$/i }));

    await waitFor(() => {
      expect(screen.getByText(/l'envoi a échoué/i)).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /^envoyer$/i }));

    await waitFor(() => {
      expect(screen.getByText(/pc-retry/)).toBeInTheDocument();
    });

    const first = JSON.parse(String((fetchMock.mock.calls[0] as [string, RequestInit])[1].body));
    const second = JSON.parse(String((fetchMock.mock.calls[1] as [string, RequestInit])[1].body));

    expect(first.idempotency_key).toBe(second.idempotency_key);
  });
});
