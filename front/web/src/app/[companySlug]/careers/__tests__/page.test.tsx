/**
 * BC-10 (#9219) — tests de la page liste du portail carrières.
 *
 * La page est un Server Component asynchrone : on l'appelle directement et on
 * rend l'arbre retourné. Ce qui est verrouillé ici :
 *
 *  - les deux populations d'offres sont SÉPARÉES, parce qu'elles n'ont pas la
 *    même nature : une offre du tenant se candidate sur notre formulaire, une
 *    offre agrégée renvoie chez le partenaire. Les mélanger ferait cliquer le
 *    visiteur sur un formulaire qui n'existe pas pour lui ;
 *  - le lien sortant porte `rel="noopener noreferrer nofollow"` : on ne
 *    transmet pas notre referer au partenaire et on ne lui cède pas de jus SEO ;
 *  - un slug inconnu déclenche `notFound()` plutôt qu'une page vide.
 */

import { render, screen } from '@testing-library/react';
import CareersPortalPage from '@/app/[companySlug]/careers/page';
import * as careersApi from '@/lib/careers-api';
import { notFound } from 'next/navigation';

jest.mock('next/headers', () => ({
  headers: async () => new Map([['accept-language', 'en']]),
}));

jest.mock('next/navigation', () => ({
  // Le setup global mocke déjà ces hooks : on les reprend ici parce qu'un
  // `jest.mock` local REMPLACE le mock global au lieu de le compléter. Les
  // omettre ferait planter la Navbar (`useRouter is not a function`).
  useRouter: () => ({
    push: jest.fn(),
    replace: jest.fn(),
    prefetch: jest.fn(),
    back: jest.fn(),
    reload: jest.fn(),
  }),
  usePathname: () => '/',
  useSearchParams: () => new URLSearchParams(),
  notFound: jest.fn(() => {
    throw new Error('NEXT_NOT_FOUND');
  }),
}));

jest.mock('@/lib/careers-api', () => ({
  ...jest.requireActual('@/lib/careers-api'),
  getPublicJobPostings: jest.fn(),
  getPublicCareersCompany: jest.fn(),
}));

const getJobsMock = careersApi.getPublicJobPostings as jest.MockedFunction<
  typeof careersApi.getPublicJobPostings
>;
const getCompanyMock = careersApi.getPublicCareersCompany as jest.MockedFunction<
  typeof careersApi.getPublicCareersCompany
>;

const company = {
  id: 1,
  name: 'Acme',
  slug: 'acme',
  display_name: 'Acme Careers',
  logo_url: null,
  primary_color: '#FF0000',
  accent_color: '#00FF00',
};

const tenantJob = {
  id: 1,
  title: 'Développeur PHP',
  description: 'Mission longue',
  department: null,
  location: 'Paris',
  remote_policy: 'hybrid' as const,
  contract_type: 'cdi' as const,
  salary_range_min: null,
  salary_range_max: null,
  currency: null,
  skills_required: null,
  status: 'published',
  closes_at: null,
  created_at: null,
};

const externalJob = {
  id: 2,
  external_id: 'adz-1',
  title: 'Data Engineer',
  description: null,
  company_name: 'DataCorp',
  location: 'Lyon',
  country: 'FR',
  remote_policy: 'remote' as const,
  contract_type: 'cdi' as const,
  salary_min: null,
  salary_max: null,
  currency: null,
  apply_url: 'https://partner.example/jobs/1',
  published_at: null,
  expires_at: null,
  origin: 'external' as const,
};

async function renderPage(searchParams: Record<string, string> = {}) {
  const ui = await CareersPortalPage({
    params: Promise.resolve({ companySlug: 'acme' }),
    searchParams: Promise.resolve(searchParams),
  });

  return render(ui);
}

beforeEach(() => {
  getJobsMock.mockReset();
  getCompanyMock.mockReset();
  getCompanyMock.mockResolvedValue(company);
  (notFound as unknown as jest.Mock).mockClear();
});

describe('CareersPortalPage — séparation des populations', () => {
  it('affiche les offres du tenant et les offres agrégées séparément', async () => {
    getJobsMock.mockResolvedValue([tenantJob, externalJob]);

    await renderPage();

    // Les deux titres de section doivent coexister : c'est la séparation qui
    // empêche le visiteur de cliquer sur un formulaire inexistant.
    expect(screen.getByText('Company offers')).toBeInTheDocument();
    expect(screen.getByText('Other offers in the region')).toBeInTheDocument();
    expect(screen.getByText('Développeur PHP')).toBeInTheDocument();
    expect(screen.getByText('Data Engineer')).toBeInTheDocument();
  });

  it('n’affiche pas de titre de section agrégée quand il n’y a que des offres du tenant', async () => {
    getJobsMock.mockResolvedValue([tenantJob]);

    await renderPage();

    expect(screen.queryByText('Other offers in the region')).not.toBeInTheDocument();
  });

  it('affiche le badge « Partner offer » sur une offre agrégée', async () => {
    getJobsMock.mockResolvedValue([externalJob]);

    await renderPage();

    expect(screen.getByText('Partner offer')).toBeInTheDocument();
    expect(screen.getByText('DataCorp')).toBeInTheDocument();
  });
});

describe('CareersPortalPage — lien sortant', () => {
  it('pointe vers le partenaire avec rel noopener noreferrer nofollow', async () => {
    getJobsMock.mockResolvedValue([externalJob]);

    await renderPage();

    const link = screen.getByRole('link', { name: /Data Engineer/i });

    expect(link).toHaveAttribute('href', 'https://partner.example/jobs/1');
    expect(link).toHaveAttribute('target', '_blank');
    // On ne transmet pas notre referer au partenaire et on ne lui cède pas de
    // jus SEO : c'est une offre tierce, pas un partenariat éditorial.
    expect(link).toHaveAttribute('rel', 'noopener noreferrer nofollow');
  });

  it('pointe vers notre page de détail pour une offre du tenant', async () => {
    getJobsMock.mockResolvedValue([tenantJob]);

    await renderPage();

    const link = screen.getByRole('link', { name: /Développeur PHP/i });

    expect(link).toHaveAttribute('href', '/acme/careers/jobs/1');
  });
});

describe('CareersPortalPage — états', () => {
  it('déclenche notFound() pour un slug inconnu', async () => {
    getJobsMock.mockResolvedValue(null);

    await expect(renderPage()).rejects.toThrow('NEXT_NOT_FOUND');
    expect(notFound).toHaveBeenCalled();
  });

  it('affiche l’état vide quand aucune offre n’est publiée', async () => {
    getJobsMock.mockResolvedValue([]);

    await renderPage();

    expect(screen.getByText(/no job openings have been published/i)).toBeInTheDocument();
  });

  it('affiche l’état « aucun résultat » quand des filtres sont actifs', async () => {
    getJobsMock.mockResolvedValue([]);

    await renderPage({ q: 'inexistant' });

    // La distinction compte : « rien n'est publié » et « rien ne correspond à
    // votre recherche » appellent des actions différentes de la part du visiteur.
    expect(screen.getByText(/no offer matches your search/i)).toBeInTheDocument();
  });

  it('affiche le branding du tenant', async () => {
    getJobsMock.mockResolvedValue([tenantJob]);

    await renderPage();

    expect(screen.getByText(/Join Acme Careers/i)).toBeInTheDocument();
  });

  it('expose le lien du flux XML', async () => {
    getJobsMock.mockResolvedValue([tenantJob]);

    await renderPage();

    const feed = screen.getByRole('link', { name: /XML feed/i });
    expect(feed).toHaveAttribute('href', '/api/v1/public/careers/acme/feed.xml');
  });

  it('transmet les filtres actifs à la couche de données', async () => {
    getJobsMock.mockResolvedValue([tenantJob]);

    await renderPage({ q: 'php', contract_type: 'cdi', remote_policy: 'hybrid' });

    expect(getJobsMock).toHaveBeenCalledWith('acme', {
      q: 'php',
      contractType: 'cdi',
      remotePolicy: 'hybrid',
    });
  });
});
