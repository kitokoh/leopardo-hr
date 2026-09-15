import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import DashboardLayout from '../layout';

/**
 * #7422 — Garde de non-régression du MENU D'EN-TÊTE de l'espace client.
 *
 * Pourquoi ce fichier existe : le menu de compte livré par #7350 a été
 * **reverté silencieusement** par le commit d'intégration `a9f7deb` (résolution
 * de conflit « par fichier entier »), et il a fallu un e2e réclamant un
 * `data-testid` disparu pour s'en apercevoir. Un contrat d'interface qui a une
 * valeur produit doit avoir un test qui proteste au niveau unitaire — pas
 * seulement dans un parcours de bout en bout qui peut être ignoré.
 *
 * Ce que ces tests verrouillent :
 *  1. le menu de compte existe, et le nom / l'e-mail ne sont PLUS affichés en
 *     clair dans la barre (c'était le symptôme visible du revert) ;
 *  2. ses quatre entrées sont câblées (profil, mot de passe, 2FA, déconnexion) ;
 *  3. le badge de marque n'est pas rendu en double quand le rail métier est là ;
 *  4. le menu RH de la barre est monté et ouvre ses sous-modules ;
 *  5. les contrôles de la barre portent leur libellé en `sr-only` (règle
 *     propriétaire : icône + texte réservé aux entrées de menu).
 */

const pushMock = jest.fn();

jest.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: jest.fn(),
    prefetch: jest.fn(),
    back: jest.fn(),
    reload: jest.fn(),
  }),
  usePathname: () => '/dashboard',
  useSearchParams: () => new URLSearchParams(),
}));

jest.mock('@/lib/api-client', () => ({ apiFetch: jest.fn() }));
jest.mock('@/lib/client-analytics', () => ({ trackClientEvent: jest.fn() }));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

/**
 * Manager avec une verticale activée : sans elle, le rail métier n'est pas rendu
 * et le test du badge « LRH » ne prouverait rien (le doublon n'apparaît que
 * lorsque les deux surfaces coexistent).
 */
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
    restaurant: true,
  },
  company: {
    id: 'company-1',
    name: 'TechCorp Algerie SARL',
    status: 'active',
    language: 'fr',
    metadata: { onboarding_completed: true },
  },
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

async function renderDashboard() {
  render(<DashboardLayout>contenu</DashboardLayout>);
  return await screen.findByTestId('user-menu-toggle');
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  stubMatchMedia();
  window.localStorage.setItem('auth_user', JSON.stringify(managerUser));

  mockedApiFetch.mockImplementation(async (endpoint: string) => {
    if (endpoint === '/auth/me') {
      return { ok: true, status: 200, json: async () => ({ data: managerUser }) } as Response;
    }

    return {
      ok: true,
      status: 200,
      json: async () => ({ data: [], meta: { total: 0, unread_count: 0 } }),
    } as Response;
  });
});

describe('Barre du haut — menu de compte (#7422 / régression #7350)', () => {
  it('expose un avatar de compte, sans afficher le nom ni l’e-mail en clair dans la barre', async () => {
    const toggle = await renderDashboard();

    // Le contrat : un seul point d'entrée « compte », nommé pour l'accessibilité.
    expect(toggle).toHaveAttribute('aria-label', 'Mon compte');
    expect(toggle).toHaveAttribute('title', 'Fatima Meziane');
    expect(toggle).toHaveAttribute('data-testid', 'user-menu-toggle');

    // Le symptôme exact du revert : nom et e-mail rendus en clair dans la barre.
    expect(screen.queryByText(managerUser.email)).not.toBeInTheDocument();
    expect(screen.queryByText('fatima.meziane@techcorp-algerie.dz')).not.toBeInTheDocument();
  });

  it('ouvre les quatre entrées du compte, dont la déconnexion', async () => {
    const toggle = await renderDashboard();

    expect(screen.queryByTestId('user-menu')).not.toBeInTheDocument();

    await userEvent.click(toggle);

    const menu = await screen.findByTestId('user-menu');

    // Le menu porte l'identité (c'est SA place, pas la barre).
    expect(within(menu).getByText('Fatima Meziane')).toBeInTheDocument();
    expect(within(menu).getByText(managerUser.email)).toBeInTheDocument();

    expect(within(menu).getByRole('menuitem', { name: 'Mon compte' })).toHaveAttribute(
      'href',
      '/settings/account',
    );
    expect(within(menu).getByRole('menuitem', { name: 'Changer mon mot de passe' })).toHaveAttribute(
      'href',
      '/settings/account#password',
    );
    expect(within(menu).getByRole('menuitem', { name: 'Sécurité (2FA)' })).toHaveAttribute(
      'href',
      '/settings/security/2fa',
    );
    expect(within(menu).getByTestId('user-menu-logout')).toHaveTextContent('Déconnexion');
  });

  it('la déconnexion du menu est câblée sur /auth/logout', async () => {
    const toggle = await renderDashboard();
    await userEvent.click(toggle);

    await userEvent.click(await screen.findByTestId('user-menu-logout'));

    expect(pushMock).toHaveBeenCalledWith('/auth/logout');
  });
});

describe('Barre du haut — marque et navigation (#7422)', () => {
  it('ne rend pas le badge de marque en double quand le rail métier est présent', async () => {
    await renderDashboard();

    // Le rail métier est bien monté…
    expect(screen.getByTestId('business-rail')).toBeInTheDocument();
    // …et le badge LRH n'est rendu qu'une fois (il était dupliqué : rail + barre).
    expect(screen.getAllByText('LRH')).toHaveLength(1);
  });

  it('monte le menu RH de la barre et ouvre ses sous-modules', async () => {
    await renderDashboard();

    // La nav horizontale et son sous-menu RH doivent être présents dans le DOM :
    // c'est leur absence/écrasement qui rendait le menu invisible en production.
    expect(screen.getByTestId('dashboard-horizontal-nav')).toBeInTheDocument();

    const hrMenu = screen.getByTestId('dashboard-hr-menu');
    expect(hrMenu).toHaveAttribute('aria-expanded', 'false');

    await userEvent.click(hrMenu);

    await waitFor(() => expect(hrMenu).toHaveAttribute('aria-expanded', 'true'));
    expect(screen.getByRole('link', { name: 'Employés' })).toHaveAttribute('href', '/employees');
    expect(screen.getByRole('link', { name: 'Absences' })).toHaveAttribute('href', '/absences');
  });
});

describe('Barre du haut — règle icône seule (#7422)', () => {
  it('les contrôles de la barre portent leur libellé en sr-only, pas en texte visible', async () => {
    await renderDashboard();

    const modulesPlan = screen.getByTestId('dashboard-modules-plan-toggle');
    expect(modulesPlan).toHaveAttribute('aria-label', 'Modules & plan');
    expect(modulesPlan).toHaveAttribute('title', 'Modules & plan');
    expect(within(modulesPlan).getByText('Modules & plan')).toHaveClass('sr-only');
  });
});
