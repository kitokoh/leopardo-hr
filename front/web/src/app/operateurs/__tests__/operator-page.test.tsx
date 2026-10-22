import { render, screen, cleanup } from '@testing-library/react';
import OperatorPage, { generateMetadata } from '../[slug]/page';
import { getPublicVtcOperator, type PublicVtcOperatorProfile } from '@/lib/vtc-public-api';

/**
 * P1-02 — fiche publique d'un opérateur VTC (`/operateurs/{slug}`).
 *
 * Vérifie le contrat de la page : identité publique rendue en SSR, 404
 * fail-closed (`notFound()`) quand l'opérateur n'est pas résolu, et surtout
 * l'ABSENCE de tout CTA de réservation — `VtcDiscoverableProvider` ne déclare
 * que l'action `contact`, donc un bouton « Réserver » serait inopérant
 * (règle P1-03 : une action affichée doit avoir un service métier derrière).
 */

jest.mock('@/lib/vtc-public-api', () => ({
  getPublicVtcOperator: jest.fn(),
}));

const notFoundMock = jest.fn();
jest.mock('next/navigation', () => ({
  notFound: () => notFoundMock(),
}));

const getPublicVtcOperatorMock = getPublicVtcOperator as jest.MockedFunction<
  typeof getPublicVtcOperator
>;

const operator: PublicVtcOperatorProfile = {
  slug: 'taxi-ville',
  name: 'Taxi Ville',
  city: 'Rabat',
  country: 'MA',
  currency: 'MAD',
};

const params = Promise.resolve({ slug: 'taxi-ville' });
const searchParams = Promise.resolve({ lang: 'fr' });

describe('OperatorPage (/operateurs/[slug])', () => {
  beforeEach(() => {
    // `notFound()` de Next.js LÈVE une exception : sans ce comportement, le
    // rendu continuerait après l'appel et le test ne prouverait rien.
    notFoundMock.mockImplementation(() => {
      throw new Error('NEXT_NOT_FOUND');
    });
  });

  afterEach(() => {
    cleanup();
    jest.clearAllMocks();
  });

  it('renders the public operator identity', async () => {
    getPublicVtcOperatorMock.mockResolvedValue(operator);

    await render(await OperatorPage({ params, searchParams }));

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Taxi Ville');
    expect(screen.getByText(/Rabat, MA/)).toBeInTheDocument();
  });

  it('offers contact but never a booking CTA (no business service behind it)', async () => {
    getPublicVtcOperatorMock.mockResolvedValue(operator);

    await render(await OperatorPage({ params, searchParams }));

    expect(screen.getByRole('button', { name: /contacter/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /réserver/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /réserver/i })).not.toBeInTheDocument();
  });

  it('never exposes the internal fare grid', async () => {
    getPublicVtcOperatorMock.mockResolvedValue(operator);

    const { container } = await render(await OperatorPage({ params, searchParams }));

    expect(container.innerHTML).not.toContain('fare_profile_id');
    expect(container.innerHTML).not.toContain('company_id');
  });

  it('calls notFound() when the operator is not published (fail-closed)', async () => {
    getPublicVtcOperatorMock.mockResolvedValue(null);

    await expect(OperatorPage({ params, searchParams })).rejects.toThrow('NEXT_NOT_FOUND');

    expect(notFoundMock).toHaveBeenCalledTimes(1);
  });

  it('builds an indexable canonical metadata for a published operator', async () => {
    getPublicVtcOperatorMock.mockResolvedValue(operator);

    const metadata = await generateMetadata({ params, searchParams });

    expect(metadata.title).toBe('Taxi Ville — Rabat');
    expect(metadata.alternates?.canonical).toContain('/operateurs/taxi-ville');
    expect(metadata.robots).toEqual({ index: true, follow: true });
  });

  it('falls back to a not-found title when the operator is unpublished', async () => {
    getPublicVtcOperatorMock.mockResolvedValue(null);

    const metadata = await generateMetadata({ params, searchParams });

    expect(metadata.title).toBe('Opérateur introuvable');
  });
});
