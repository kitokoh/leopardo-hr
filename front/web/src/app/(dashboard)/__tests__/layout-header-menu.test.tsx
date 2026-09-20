import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import DashboardLayout from '../layout';

/**
 * #7422/#7908 — Garde de non-régression du SHELL de l'espace client.
 *
 * Pourquoi ce fichier existe : le menu de compte livré par #7350 a été
 * **reverté silencieusement** par le commit d'intégration `a9f7deb` (résolution
 * de conflit « par fichier entier »), et seul un e2e réclamant un
 * `data-testid` disparu l'a signalé. Un contrat d'interface qui porte une
 * valeur produit doit protester au niveau unitaire.
 *
 * Ce que ce fichier verrouille depuis la refonte #7908 (sidebar unifiée) :
 *  1. le menu de compte vit dans le PIED DE LA SIDEBAR (`user-menu-toggle`),
 *     plus dans la topbar ; l'identité n'est jamais rendue dans la topbar ;
 *  2. ses entrées sont câblées (Mon compte, Encaissements, Image de marque,
 *     Support, Langue en sous-menu, Déconnexion) ;
 *  3. la déconnexion mène à `/auth/logout` ; le changement de langue passe
 *     par PATCH /auth/language (logique `handleLanguageChange` conservée) ;
 *  4. la topbar est réduite à une ligne fine : plus de nav horizontale
 *     (`dashboard-horizontal-nav`), plus de panneau « Modules & plan »
 *     (`dashboard-plan-toggle`, déménagé sur /modules), plus de select de
 *     langue ni de menu avatar ;
 *  5. le panneau de notifications reste dans la topbar (testids inchangés)
 *     et se referme au second clic (#7584).
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
 * Manager avec une verticale activée (`restaurant`) : rail métier monté dans
 * la sidebar unifiée.
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
  window.localStorage.clear();
  window.localStorage.setItem('preferred_locale', 'fr');
  window.localStorage.setItem('auth_user', JSON.stringify(managerUser));

  mockedApiFetch.mockImplementation(async (endpoint: string) => {
    if (endpoint === '/auth/me') {
      return { ok: true, status: 200, json: async () => ({ data: managerUser }) } as Response;
    }
    if (endpoint === '/auth/language') {
      return { ok: true, status: 200, json: async () => ({ data: { ...managerUser, language: 'en' } }) } as Response;
    }

    return {
      ok: true,
      status: 200,
      json: async () => ({ data: [], meta: { total: 0, unread_count: 0 } }),
    } as Response;
  });
});

describe('Sidebar — bloc « Mon compte » (#7908, régressions #7350/#7422)', () => {
  it('expose un bloc compte dans la sidebar, sans identité dans la topbar', async () => {
    const toggle = await renderDashboard();

    // Le contrat : un seul point d'entrée « compte », nommé pour l'accessibilité,
    // dans le pied de la sidebar unifiée.
    expect(toggle).toHaveAttribute('aria-label', 'Mon compte');
    expect(toggle).toHaveAttribute('title', 'Fatima Meziane');
    expect(screen.getByTestId('dashboard-sidebar')).toContainElement(toggle);

    // L'identité ne vit pas dans la topbar (le <header>).
    const header = document.querySelector('header');
    expect(header).not.toBeNull();
    expect(within(header as HTMLElement).queryByText(managerUser.email)).not.toBeInTheDocument();
  });

  it('ouvre les entrées du compte : Mon compte, Encaissements, Image de marque, Support, Déconnexion', async () => {
    const toggle = await renderDashboard();

    expect(screen.queryByTestId('user-menu')).not.toBeInTheDocument();

    await userEvent.click(toggle);

    const menu = await screen.findByTestId('user-menu');

    // L'identité est à SA place : dans le menu.
    expect(within(menu).getByText('Fatima Meziane')).toBeInTheDocument();
    expect(within(menu).getByText(managerUser.email)).toBeInTheDocument();

    expect(within(menu).getByRole('menuitem', { name: 'Mon compte' })).toHaveAttribute(
      'href',
      '/settings/account',
    );
    expect(within(menu).getByRole('menuitem', { name: 'Encaissements' })).toHaveAttribute(
      'href',
      '/settings/encaissements',
    );
    expect(within(menu).getByRole('menuitem', { name: 'Image de marque' })).toHaveAttribute(
      'href',
      '/settings/branding',
    );
    expect(within(menu).getByTestId('user-menu-support')).toHaveAttribute('href', '/support');
    expect(within(menu).getByTestId('user-menu-logout')).toHaveTextContent('Déconnexion');
  });

  it('#7860 — mot de passe, 2FA et collaborateurs ne sont pas dans le shell', async () => {
    const toggle = await renderDashboard();
    await userEvent.click(toggle);
    await screen.findByTestId('user-menu');

    // Ces capacités vivent dans « Mon compte » et « Employés » : plus aucun
    // lien direct dans le shell.
    expect(screen.queryByRole('menuitem', { name: 'Changer mon mot de passe' })).not.toBeInTheDocument();
    expect(screen.queryByRole('menuitem', { name: 'Sécurité (2FA)' })).not.toBeInTheDocument();
    expect(document.querySelector('a[href="/settings/account#password"]')).toBeNull();
    expect(document.querySelector('a[href="/settings/security/2fa"]')).toBeNull();
    expect(document.querySelector('a[href="/settings/team"]')).toBeNull();
  });

  it('la déconnexion du menu est câblée sur /auth/logout', async () => {
    const toggle = await renderDashboard();
    await userEvent.click(toggle);

    await userEvent.click(await screen.findByTestId('user-menu-logout'));

    expect(pushMock).toHaveBeenCalledWith('/auth/logout');
  });

  it('le menu du compte est refermable (aucune entrée fantôme)', async () => {
    const toggle = await renderDashboard();

    await userEvent.click(toggle);
    expect(await screen.findByTestId('user-menu')).toBeInTheDocument();

    await userEvent.click(toggle);
    await waitFor(() => expect(screen.queryByTestId('user-menu')).not.toBeInTheDocument());
  });
});

describe('Sidebar — sous-menu Langue du bloc compte (#7908)', () => {
  it('propose les 4 locales et applique le changement via PATCH /auth/language', async () => {
    const toggle = await renderDashboard();
    await userEvent.click(toggle);
    await screen.findByTestId('user-menu');

    // Le sous-menu est replié par défaut.
    expect(screen.queryByTestId('user-menu-language-panel')).not.toBeInTheDocument();

    await userEvent.click(screen.getByTestId('user-menu-language-toggle'));
    const panel = await screen.findByTestId('user-menu-language-panel');

    for (const code of ['fr', 'en', 'tr', 'ar']) {
      expect(within(panel).getByTestId(`user-menu-language-${code}`)).toBeInTheDocument();
    }
    // La locale courante est marquée.
    expect(within(panel).getByTestId('user-menu-language-fr')).toHaveAttribute('aria-checked', 'true');

    await userEvent.click(within(panel).getByTestId('user-menu-language-en'));

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/language', {
      method: 'PATCH',
      body: JSON.stringify({ language: 'en' }),
    }));
    // Le choix referme le menu (la session est resauvegardée côté layout).
    await waitFor(() => expect(screen.queryByTestId('user-menu')).not.toBeInTheDocument());
  });
});

describe('Topbar — ligne fine (#7908)', () => {
  it('ne porte plus la nav horizontale, le panneau Modules & plan, la langue ni l’avatar', async () => {
    await renderDashboard();

    expect(screen.queryByTestId('dashboard-horizontal-nav')).not.toBeInTheDocument();
    expect(screen.queryByTestId('dashboard-plan-toggle')).not.toBeInTheDocument();
    expect(screen.queryByTestId('dashboard-plan-panel')).not.toBeInTheDocument();
    expect(screen.queryByTestId('dashboard-modules-nav-toggle')).not.toBeInTheDocument();

    // Plus de select de langue dans la topbar : la langue vit dans le menu compte.
    const header = document.querySelector('header') as HTMLElement;
    expect(within(header).queryByRole('combobox')).not.toBeInTheDocument();
    // Plus de menu avatar dans la topbar.
    expect(within(header).queryByTestId('user-menu-toggle')).not.toBeInTheDocument();

    // La pastille de présence reste, libellé en sr-only.
    expect(within(header).getByText('Présents')).toHaveClass('sr-only');
  });

  it('le panneau de notifications reste dans la topbar et se referme au second clic (#7584)', async () => {
    await renderDashboard();

    expect(screen.queryByTestId('dashboard-notifications-panel')).not.toBeInTheDocument();

    await userEvent.click(screen.getByTestId('dashboard-notifications-toggle'));
    const panel = await screen.findByTestId('dashboard-notifications-panel');
    expect(document.querySelector('header')).toContainElement(panel);

    await userEvent.click(screen.getByTestId('dashboard-notifications-toggle'));
    await waitFor(() => expect(screen.queryByTestId('dashboard-notifications-panel')).not.toBeInTheDocument());
  });
});
