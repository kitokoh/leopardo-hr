import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import ModulesPage from '../modules/page';

/**
 * #7908 — Garde de la page « Modules » (/modules), qui reprend le contenu de
 * l'ex-panneau « Modules & plan » de la topbar :
 *  1. statut des modules de plateforme (Trial / Présent / Lock) ;
 *  2. auto-activation d'un outil horizontal (#7322 : POST
 *     /company/modules/{key}/activate puis rechargement de /auth/me) via les
 *     boutons `activate-module-<key>` conservés ;
 *  3. les verticales non activées renvoient vers /contact?topic=upgrade.
 */

jest.mock('next/navigation', () => ({
  useRouter: () => ({
    push: jest.fn(),
    replace: jest.fn(),
    prefetch: jest.fn(),
    back: jest.fn(),
    reload: jest.fn(),
  }),
  usePathname: () => '/modules',
  useSearchParams: () => new URLSearchParams(),
}));

jest.mock('@/lib/api-client', () => ({ apiFetch: jest.fn() }));
jest.mock('@/lib/client-analytics', () => ({ trackClientEvent: jest.fn() }));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const managerUser = {
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

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.clear();
  window.localStorage.setItem('preferred_locale', 'fr');
  window.localStorage.setItem('auth_user', JSON.stringify(managerUser));

  mockedApiFetch.mockImplementation(async (endpoint: string) => {
    if (endpoint === '/auth/me') {
      return { ok: true, status: 200, json: async () => ({ data: managerUser }) } as Response;
    }

    return {
      ok: true,
      status: 200,
      json: async () => ({ data: {} }),
    } as Response;
  });
});

describe('Page /modules (#7908, ex-panneau « Modules & plan »)', () => {
  it('affiche le statut des modules de plateforme', async () => {
    render(<ModulesPage />);

    const page = await screen.findByTestId('modules-page');
    expect(page).toBeInTheDocument();

    // Titre et sections localisés (fr).
    expect(screen.getByRole('heading', { name: 'Modules & plan' })).toBeInTheDocument();
    const platform = screen.getByTestId('modules-platform-section');
    expect(platform).toHaveTextContent('Facturation');
    expect(platform).toHaveTextContent('Intégrations');
  });

  it('active un outil horizontal via activate-module-<key> (#7322)', async () => {
    render(<ModulesPage />);
    await screen.findByTestId('modules-page');

    // `crm` est self-activable et non activé pour ce manager : bouton monté.
    const activate = screen.getByTestId('activate-module-crm');
    await userEvent.click(activate);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith(
      '/company/modules/crm/activate',
      { method: 'POST' },
    ));
    // La surface d'activation est rechargée sans reconnexion.
    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me'));
  });

  it('renvoie les verticales non activées vers /contact?topic=upgrade', async () => {
    render(<ModulesPage />);
    await screen.findByTestId('modules-page');

    const discover = screen.getByTestId('modules-discover-section');
    const upgradeLinks = discover.querySelectorAll('a[href="/contact?topic=upgrade"]');
    expect(upgradeLinks.length).toBeGreaterThan(0);
  });

  it('affiche une erreur localisée quand l’activation échoue', async () => {
    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      if (endpoint.startsWith('/company/modules/')) {
        return { ok: false, status: 422, json: async () => ({}) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ data: {} }) } as Response;
    });

    render(<ModulesPage />);
    await screen.findByTestId('modules-page');

    await userEvent.click(screen.getByTestId('activate-module-crm'));

    expect(await screen.findByRole('alert')).toHaveTextContent("L'activation a échoué. Réessayez.");
  });
});
