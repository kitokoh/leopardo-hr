import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { SIDEBAR_GROUPS_STORAGE_KEY } from '@/components/layout/Sidebar';
import DashboardLayout from '../layout';

/**
 * #7908 — Garde de la SIDEBAR GAUCHE UNIFIÉE de l'espace client.
 *
 * Contrats verrouillés :
 *  1. la sidebar est TOUJOURS rendue, même pour un tenant SANS verticale
 *     métier (l'ancien `business-rail` n'était monté que si des modules
 *     business existaient) ;
 *  2. la section « Mon métier » (rail métier) garde son contenu quand une
 *     verticale est activée ;
 *  3. la section « Entreprise » replie les groupes de l'ex-nav horizontale en
 *     ACCORDÉONS : ouverture/fermeture au clic, état persisté en
 *     localStorage, groupe actif auto-ouvert selon le pathname ;
 *  4. la section « Plateforme » relie /modules, /billing et
 *     /settings/developer.
 */

let mockPathname = '/dashboard';

jest.mock('next/navigation', () => ({
  useRouter: () => ({
    push: jest.fn(),
    replace: jest.fn(),
    prefetch: jest.fn(),
    back: jest.fn(),
    reload: jest.fn(),
  }),
  usePathname: () => mockPathname,
  useSearchParams: () => new URLSearchParams(),
}));

jest.mock('@/lib/api-client', () => ({ apiFetch: jest.fn() }));
jest.mock('@/lib/client-analytics', () => ({ trackClientEvent: jest.fn() }));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const baseUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  capabilities: {
    can_view_dashboard: true,
    employees: true,
    attendance: true,
    absences: true,
  },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    status: 'active',
    language: 'fr',
    metadata: { onboarding_completed: true },
  },
};

const businessUser = {
  ...baseUser,
  capabilities: { ...baseUser.capabilities, restaurant: true },
};

function stubMatchMedia() {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: (query: string) => ({
      matches: true,
      media: query,
      onchange: null,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
      addListener: jest.fn(),
      removeListener: jest.fn(),
      dispatchEvent: jest.fn(),
    }),
  });
}

async function renderDashboardAt(pathname: string, user: Record<string, unknown> = baseUser) {
  mockPathname = pathname;
  sessionUser = user;
  window.localStorage.setItem('auth_user', JSON.stringify(user));
  render(<DashboardLayout>contenu</DashboardLayout>);

  return await screen.findByTestId('dashboard-sidebar');
}

// Le rafraîchissement silencieux (#7245) relit /auth/me : le mock doit
// renvoyer LE MÊME utilisateur que la session locale, sinon la surface
// d'activation (ex. restaurant) est écrasée pendant le test.
let sessionUser: Record<string, unknown> = baseUser;

beforeEach(() => {
  jest.clearAllMocks();
  stubMatchMedia();
  window.localStorage.clear();
  window.localStorage.setItem('preferred_locale', 'fr');

  sessionUser = baseUser;

  mockedApiFetch.mockImplementation(async (endpoint: string) => {
    if (endpoint === '/auth/me') {
      return { ok: true, status: 200, json: async () => ({ data: sessionUser }) } as Response;
    }

    return {
      ok: true,
      status: 200,
      json: async () => ({ data: [], meta: { total: 0, unread_count: 0 } }),
    } as Response;
  });
});

describe('Sidebar unifiée — toujours rendue (#7908)', () => {
  it('est montée même pour un tenant SANS verticale métier', async () => {
    const sidebar = await renderDashboardAt('/dashboard', baseUser);

    expect(sidebar).toBeInTheDocument();
    // Pas de verticale activée : la section métier n'existe pas…
    expect(screen.queryByTestId('business-rail')).not.toBeInTheDocument();
    // …mais les sections Entreprise et Plateforme, et le bloc compte, oui.
    expect(within(sidebar).getByTestId('dashboard-enterprise-nav')).toBeInTheDocument();
    expect(within(sidebar).getByTestId('dashboard-platform-nav')).toBeInTheDocument();
    expect(within(sidebar).getByTestId('user-menu-toggle')).toBeInTheDocument();
    // Le nom de la compagnie est dans l'en-tête de la sidebar.
    expect(within(sidebar).getByText('TechCorp Algerie SARL')).toBeInTheDocument();
  });

  it('porte la section métier quand une verticale est activée (contenu inchangé)', async () => {
    const sidebar = await renderDashboardAt('/dashboard', businessUser);

    const railElement = within(sidebar).getByTestId('business-rail');
    expect(within(railElement).getByRole('link', { name: /Restaurant/ })).toHaveAttribute('href', '/restaurant');
  });

  it('relie la section Plateforme : /modules, /billing, /settings/developer', async () => {
    const sidebar = await renderDashboardAt('/dashboard', baseUser);

    expect(within(sidebar).getByTestId('sidebar-modules-link')).toHaveAttribute('href', '/modules');
    expect(within(sidebar).getByTestId('sidebar-billing-link')).toHaveAttribute('href', '/billing');
    expect(within(sidebar).getByTestId('sidebar-integrations-link')).toHaveAttribute('href', '/settings/developer');
  });
});

describe('Sidebar unifiée — accordéons Entreprise (#7908)', () => {
  it('replie le groupe RH, l’ouvre au clic et l’état est persisté en localStorage', async () => {
    const sidebar = await renderDashboardAt('/dashboard', baseUser);

    const hrToggle = within(sidebar).getByTestId('dashboard-hr-menu');
    expect(hrToggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByTestId('dashboard-hr-menu-panel')).not.toBeInTheDocument();

    await userEvent.click(hrToggle);

    await waitFor(() => expect(hrToggle).toHaveAttribute('aria-expanded', 'true'));
    const panel = screen.getByTestId('dashboard-hr-menu-panel');
    expect(within(panel).getByRole('link', { name: 'Employés' })).toHaveAttribute('href', '/employees');
    expect(within(panel).getByRole('link', { name: 'Absences' })).toHaveAttribute('href', '/absences');

    // Persistance : l'état ouvert est écrit en localStorage.
    expect(JSON.parse(window.localStorage.getItem(SIDEBAR_GROUPS_STORAGE_KEY) ?? '{}')).toMatchObject({ hr: true });

    // Refermable au clic, et l'état fermé est persisté aussi.
    await userEvent.click(hrToggle);
    await waitFor(() => expect(screen.queryByTestId('dashboard-hr-menu-panel')).not.toBeInTheDocument());
    expect(JSON.parse(window.localStorage.getItem(SIDEBAR_GROUPS_STORAGE_KEY) ?? '{}')).toMatchObject({ hr: false });
  });

  it('restaure l’état ouvert depuis localStorage au montage', async () => {
    window.localStorage.setItem(SIDEBAR_GROUPS_STORAGE_KEY, JSON.stringify({ hr: true }));

    await renderDashboardAt('/dashboard', baseUser);

    expect(screen.getByTestId('dashboard-hr-menu')).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByTestId('dashboard-hr-menu-panel')).toBeInTheDocument();
  });

  it('ouvre automatiquement le groupe actif selon le pathname', async () => {
    await renderDashboardAt('/employees', baseUser);

    const hrToggle = screen.getByTestId('dashboard-hr-menu');
    await waitFor(() => expect(hrToggle).toHaveAttribute('aria-expanded', 'true'));
    const panel = screen.getByTestId('dashboard-hr-menu-panel');
    expect(within(panel).getByRole('link', { name: 'Employés' })).toHaveAttribute('aria-current', 'page');
  });
});

describe('Sidebar unifiée — module en essai visible comme tel (#8028)', () => {
  const trialUser = {
    ...baseUser,
    capabilities: { ...baseUser.capabilities, reports: 'trial' },
    company: {
      ...baseUser.company,
      features: { reports: 'trial' },
    },
  };

  it('signale un module cœur en essai SANS le verrouiller', async () => {
    const sidebar = await renderDashboardAt('/reports', trialUser);

    // L'essai n'est pas un verrou : le module reste navigable…
    const reportsLink = within(sidebar).getByRole('link', { name: /Rapports/ });
    expect(reportsLink).toHaveAttribute('href', '/reports');
    // …et il est signalé comme tel dans la navigation (régression #7908).
    expect(within(reportsLink).getByTestId('sidebar-module-trial-badge')).toHaveTextContent('Trial');
  });

  it('n’affiche aucun badge « Trial » pour un module réellement inclus', async () => {
    const sidebar = await renderDashboardAt('/reports', baseUser);

    expect(within(sidebar).queryByTestId('sidebar-module-trial-badge')).not.toBeInTheDocument();
  });
});
