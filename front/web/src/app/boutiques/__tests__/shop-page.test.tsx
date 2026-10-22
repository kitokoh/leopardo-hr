import { render, screen, cleanup } from '@testing-library/react';
import ShopPage, { generateMetadata } from '../[slug]/page';
import { getPublicShop, type PublicShopProfile } from '@/lib/retail-public-api';

/**
 * P1-02 — fiche publique d'une boutique Leopardo Marché (`/boutiques/{slug}`).
 *
 * Vérifie le contrat de la page : rendu SSR du profil, absence de donnée
 * interne, 404 fail-closed (`notFound()`) quand l'API ne résout pas le slug, et
 * présence du CTA de contact identifié par le couple (`retail_shop`, slug) —
 * jamais par un `company_id`.
 */

jest.mock('@/lib/retail-public-api', () => ({
  getPublicShop: jest.fn(),
}));

const notFoundMock = jest.fn();
jest.mock('next/navigation', () => ({
  notFound: () => notFoundMock(),
}));

const getPublicShopMock = getPublicShop as jest.MockedFunction<typeof getPublicShop>;

const shop: PublicShopProfile = {
  name: 'Épicerie du Marché',
  slug: 'epicerie-du-marche',
  city: 'Casablanca',
  description: 'Produits frais et épicerie fine.',
  contact_phone: '+212600000000',
  contact_email: 'contact@epicerie.test',
  currency: 'MAD',
  products_count: 12,
  rating_avg: 4.5,
  rating_count: 8,
  categories: [{ id: 3, name: 'Épicerie', products_count: 7 }],
};

const params = Promise.resolve({ slug: 'epicerie-du-marche' });
const searchParams = Promise.resolve({ lang: 'fr' });

describe('ShopPage (/boutiques/[slug])', () => {
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

  it('renders the public shop profile', async () => {
    getPublicShopMock.mockResolvedValue(shop);

    await render(await ShopPage({ params, searchParams }));

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Épicerie du Marché');
    expect(screen.getByText('Produits frais et épicerie fine.')).toBeInTheDocument();
    expect(screen.getByText('Épicerie')).toBeInTheDocument();
  });

  it('exposes a contact CTA bound to the (type, slug) pair only', async () => {
    getPublicShopMock.mockResolvedValue(shop);

    const { container } = await render(await ShopPage({ params, searchParams }));

    expect(screen.getByRole('button', { name: /contacter/i })).toBeInTheDocument();
    // Aucun identifiant interne ne doit transiter par le navigateur.
    expect(container.innerHTML).not.toContain('company_id');
  });

  it('calls notFound() when the API does not resolve the slug (fail-closed)', async () => {
    getPublicShopMock.mockResolvedValue(null);

    await expect(ShopPage({ params, searchParams })).rejects.toThrow('NEXT_NOT_FOUND');

    expect(notFoundMock).toHaveBeenCalledTimes(1);
  });

  it('builds an indexable canonical metadata for a published shop', async () => {
    getPublicShopMock.mockResolvedValue(shop);

    const metadata = await generateMetadata({ params, searchParams });

    expect(metadata.title).toBe('Épicerie du Marché — Casablanca');
    expect(metadata.alternates?.canonical).toContain('/boutiques/epicerie-du-marche');
    expect(metadata.robots).toEqual({ index: true, follow: true });
  });

  it('falls back to a not-found title when the shop is unpublished', async () => {
    getPublicShopMock.mockResolvedValue(null);

    const metadata = await generateMetadata({ params, searchParams });

    expect(metadata.title).toBe('Boutique introuvable');
  });
});
