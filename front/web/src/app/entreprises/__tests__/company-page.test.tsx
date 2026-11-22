import { render, screen, cleanup } from '@testing-library/react';
import CompanyPage, { generateMetadata } from '../[slug]/page';
import {
  getPublicTradeCompany,
  getPublicTradeCompanyProducts,
  type PublicTradeCompanyProfile,
  type PublicTradeProduct,
} from '@/lib/trade-public-api';

/**
 * P2-01 (#8802) — fiche publique d'une entreprise de commerce (`/entreprises/{slug}`).
 *
 * Vérifie le contrat de la page : rendu SSR du profil, absence de donnée
 * interne, 404 fail-closed (`notFound()`) quand l'API ne résout pas le slug, et
 * présence du CTA de contact identifié par le couple (`trade_company`, slug) —
 * jamais par un `company_id`.
 *
 * Trade est la première verticale dotée d'un opt-in public a posteriori : ces
 * tests défendent la même propriété que ceux de Retail, à savoir qu'une fiche
 * non publiable est indistinguable d'un slug inexistant côté navigateur.
 *
 * #9768 ajoute le catalogue produits : ces tests verrouillent aussi qu'un
 * `hs_code` (donnée de conformité interne) ne peut pas fuiter dans le HTML
 * public, et qu'une liste vide s'affiche proprement au lieu d'une erreur.
 */

jest.mock('@/lib/trade-public-api', () => ({
  getPublicTradeCompany: jest.fn(),
  getPublicTradeCompanyProducts: jest.fn(),
}));

const notFoundMock = jest.fn();
jest.mock('next/navigation', () => ({
  notFound: () => notFoundMock(),
}));

const getPublicTradeCompanyMock = getPublicTradeCompany as jest.MockedFunction<
  typeof getPublicTradeCompany
>;
const getPublicTradeCompanyProductsMock = getPublicTradeCompanyProducts as jest.MockedFunction<
  typeof getPublicTradeCompanyProducts
>;

const company: PublicTradeCompanyProfile = {
  slug: 'atlas-import-export',
  name: 'Atlas Import Export',
  description: 'Importateur de matériel industriel.',
  city: 'Lyon',
  country: 'FR',
  product_categories: ['machines', 'composants'],
  currency: 'EUR',
  import_enabled: true,
  export_enabled: false,
  domestic_scope: false,
};

const product: PublicTradeProduct = {
  name: 'Pompe hydraulique HP-200',
  description: 'Pompe industrielle 200 bars.',
  category: 'machines',
  origin_country: 'DE',
  unit: 'pièce',
  moq_quantity: '10.000',
  indicative_unit_price_minor: 250_000,
  currency: 'EUR',
  specs: {},
};

const params = Promise.resolve({ slug: 'atlas-import-export' });
const searchParams = Promise.resolve({ lang: 'fr' });

describe('CompanyPage (/entreprises/[slug])', () => {
  beforeEach(() => {
    // `notFound()` de Next.js LÈVE une exception : sans ce comportement, le
    // rendu continuerait après l'appel et le test ne prouverait rien.
    notFoundMock.mockImplementation(() => {
      throw new Error('NEXT_NOT_FOUND');
    });

    // Par défaut, aucune publication de produit : la fiche reste rendue.
    getPublicTradeCompanyProductsMock.mockResolvedValue([]);
  });

  afterEach(() => {
    cleanup();
    jest.clearAllMocks();
  });

  it('renders the public company profile', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);

    await render(await CompanyPage({ params, searchParams }));

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Atlas Import Export');
    expect(screen.getByText('Importateur de matériel industriel.')).toBeInTheDocument();
    expect(screen.getByText('machines')).toBeInTheDocument();
    expect(screen.getByText('composants')).toBeInTheDocument();
  });

  it('shows only the commercial directions the pro published', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);

    await render(await CompanyPage({ params, searchParams }));

    // `import_enabled = true` → « Import » ; `export_enabled = false` et
    // `domestic_scope = false` ne doivent RIEN afficher. Une direction
    // affichée sans opt-in serait une affirmation commerciale fausse.
    //
    // ⚠️ Correspondance EXACTE : la description commence par « Importateur »,
    // donc une regex `/Import/` matcherait aussi la description et le test
    // passerait pour la mauvaise raison (voire échouerait sur « multiple
    // elements »). On assert le libellé de la pastille, pas une sous-chaîne.
    expect(screen.getByText('Import')).toBeInTheDocument();
    expect(screen.queryByText('Export')).not.toBeInTheDocument();
    expect(screen.queryByText('Marché national')).not.toBeInTheDocument();
  });

  it('exposes a contact CTA bound to the (type, slug) pair only', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);

    const { container } = await render(await CompanyPage({ params, searchParams }));

    expect(screen.getByRole('button', { name: /contacter/i })).toBeInTheDocument();
    // Aucun identifiant interne ne doit transiter par le navigateur.
    expect(container.innerHTML).not.toContain('company_id');
  });

  it('calls notFound() when the API does not resolve the slug (fail-closed)', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(null);

    await expect(CompanyPage({ params, searchParams })).rejects.toThrow('NEXT_NOT_FOUND');

    expect(notFoundMock).toHaveBeenCalledTimes(1);
  });

  it('builds an indexable canonical metadata for a published company', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);

    const metadata = await generateMetadata({ params, searchParams });

    expect(metadata.title).toBe('Atlas Import Export — Lyon');
    expect(metadata.alternates?.canonical).toContain('/entreprises/atlas-import-export');
    expect(metadata.robots).toEqual({ index: true, follow: true });
  });

  it('falls back to a not-found title when the company is unpublished', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(null);

    const metadata = await generateMetadata({ params, searchParams });

    expect(metadata.title).toBe('Entreprise introuvable');
  });

  // ── #9768 — catalogue produits public ────────────────────────────────────

  it('renders the published products with MOQ and indicative price', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);
    getPublicTradeCompanyProductsMock.mockResolvedValue([product]);

    await render(await CompanyPage({ params, searchParams }));

    expect(screen.getByText('Produits')).toBeInTheDocument();
    expect(screen.getByText('Pompe hydraulique HP-200')).toBeInTheDocument();
    // MOQ + unité, et l'origine : ce qu'un acheteur pro compare.
    expect(screen.getByText(/MOQ: 10\.000 pièce/)).toBeInTheDocument();
    expect(screen.getByText(/Origine: DE/)).toBeInTheDocument();
    // Le prix est annoncé comme INDICATIF — jamais comme un prix ferme.
    expect(screen.getByText(/Prix indicatif/)).toBeInTheDocument();
  });

  it('never leaks an internal hs_code into the public HTML', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);
    // Même si l'API renvoyait un hs_code par erreur, le type public ne le
    // porte pas : ce test verrouille l'absence de la clé dans le rendu.
    getPublicTradeCompanyProductsMock.mockResolvedValue([product]);

    const { container } = await render(await CompanyPage({ params, searchParams }));

    expect(container.innerHTML).not.toContain('hs_code');
    expect(container.innerHTML).not.toContain('090111');
  });

  it('shows an explicit empty state instead of an error when no product is published', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);
    getPublicTradeCompanyProductsMock.mockResolvedValue(null);

    await render(await CompanyPage({ params, searchParams }));

    expect(screen.getByText('Aucun produit publié pour le moment.')).toBeInTheDocument();
  });

  it('offers no public order button — Trade only declares the contact action', async () => {
    getPublicTradeCompanyMock.mockResolvedValue(company);
    getPublicTradeCompanyProductsMock.mockResolvedValue([product]);

    await render(await CompanyPage({ params, searchParams }));

    // `TradeDiscoverableProvider::supportedActions()` ne déclare que
    // `contact` : un bouton de commande contredirait la capacité déclarée.
    expect(screen.queryByRole('button', { name: /commander|réserver|acheter/i })).not.toBeInTheDocument();
  });
});
