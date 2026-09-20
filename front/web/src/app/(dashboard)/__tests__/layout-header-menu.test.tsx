import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import DashboardLayout from '../layout';

/**
 * #7422 — Garde de non-régression du MENU D'EN-TÊTE de l'espace client.
 *
 * Pourquoi ce fichier existe : le menu de compte livré par #7350 a été
 * **reverté silencieusement** par le commit d'intégration `a9f7deb` (résolution
 * de conflit « par fichier entier »), et seul un e2e réclamant un
 * `data-testid` disparu l'a signalé — un e2e qui, lui, n'a pas bloqué `main`.
 * Un contrat d'interface qui porte une valeur produit doit protester au niveau
 * unitaire, pas seulement dans un parcours de bout en bout.
 *
 * Ce que ce fichier verrouille :
 *  1. le menu de compte existe, et le nom / l'e-mail ne sont PAS rendus en clair
 *     dans la barre (le symptôme visible de la régression) ;
 *  2. ses entrées sont câblées (profil, abonnement & factures, déconnexion) —
 *     #7860 : « Mot de passe », « Sécurité 2FA » et « Collaborateurs & rôles »
 *     sont sortis du menu (ces capacités vivent dans Mon compte / Employés) ;
 *  3. la déconnexion du menu mène bien à `/auth/logout` ;
 *  4. le menu RH de la barre est monté et ouvre ses sous-modules (le sous-menu
 *     #7328 disparaissait de l'écran quand la nav était écrasée) ;
 *  5. les contrôles de la barre portent leur libellé en `sr-only` — règle
 *     propriétaire : icône + texte réservé aux entrées de menu.
 *
 * Ce que ce fichier ne prétend PAS vérifier : la **déduplication visuelle** du
 * badge de marque (assurée par une classe responsive) ni la largeur de la nav.
 * jsdom n'a pas de moteur de rendu : ces deux-là relèvent du navigateur réel
 * (`e2e/dashboard-mobile-nav.spec.ts` et les mesures de #7422).
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
 * Manager avec une verticale activée (`restaurant`) : le rail métier est alors
 * monté, ce qui est le cas où la barre du haut doit s'alléger.
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

describe('Barre du haut — menu de compte (#7422, régression #7350)', () => {
  it('expose un avatar de compte, sans nom ni e-mail en clair dans la barre', async () => {
    const toggle = await renderDashboard();

    // Le contrat : un seul point d'entrée « compte », nommé pour l'accessibilité.
    expect(toggle).toHaveAttribute('aria-label', 'Mon compte');
    expect(toggle).toHaveAttribute('title', 'Fatima Meziane');

    // Le symptôme exact de la régression : l'identité rendue en clair dans la barre.
    expect(screen.queryByText(managerUser.email)).not.toBeInTheDocument();
  });

  it('ouvre les entrées du compte, dont abonnement & factures et la déconnexion', async () => {
    const toggle = await renderDashboard();

    expect(screen.queryByTestId('user-menu')).not.toBeInTheDocument();

    await userEvent.click(toggle);

    const menu = await screen.findByTestId('user-menu');

    // L'identité est à SA place : dans le menu, pas dans la barre.
    expect(within(menu).getByText('Fatima Meziane')).toBeInTheDocument();
    expect(within(menu).getByText(managerUser.email)).toBeInTheDocument();

    expect(within(menu).getByRole('menuitem', { name: 'Mon compte' })).toHaveAttribute(
      'href',
      '/settings/account',
    );
    // #7860 — nouvelle entrée « Abonnement & factures » → /billing.
    expect(within(menu).getByTestId('user-menu-billing')).toHaveAttribute('href', '/billing');
    expect(within(menu).getByRole('menuitem', { name: 'Abonnement & factures' })).toBeInTheDocument();
    expect(within(menu).getByTestId('user-menu-logout')).toHaveTextContent('Déconnexion');
  });

  it('#7860 — mot de passe, 2FA et collaborateurs ne sont plus dans le menu ni le tiroir', async () => {
    const toggle = await renderDashboard();
    await userEvent.click(toggle);
    await screen.findByTestId('user-menu');

    // Ces capacités vivent désormais dans « Mon compte » et « Employés » :
    // plus aucun lien direct dans le shell (menu avatar ET tiroir mobile).
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

describe('Barre du haut — menu de navigation (#7422, sous-menu #7328)', () => {
  it('monte le menu RH de la barre et ouvre ses sous-modules', async () => {
    await renderDashboard();

    // La nav horizontale et son sous-menu RH doivent être montés : leur
    // disparition (ou leur écrasement) rendait le menu métier introuvable.
    expect(screen.getByTestId('dashboard-horizontal-nav')).toBeInTheDocument();

    const hrMenu = screen.getByTestId('dashboard-hr-menu');
    expect(hrMenu).toHaveAttribute('aria-expanded', 'false');

    await userEvent.click(hrMenu);

    await waitFor(() => expect(hrMenu).toHaveAttribute('aria-expanded', 'true'));
    // Le tiroir mobile (#7556) rend la MÊME liste de modules que la barre : on
    // scope donc l'assertion à la nav de la barre, sinon le lien est trouvé
    // deux fois (le tiroir est monté hors écran, pas absent du DOM).
    const headerNav = screen.getByTestId('dashboard-horizontal-nav');
    expect(within(headerNav).getByRole('link', { name: 'Employés' })).toHaveAttribute('href', '/employees');
    expect(within(headerNav).getByRole('link', { name: 'Absences' })).toHaveAttribute('href', '/absences');
  });
});

describe('Barre du haut — hover intent des sous-menus (#7860)', () => {
  it('le survol ouvre le menu RH après temporisation, le quitter le referme', async () => {
    await renderDashboard();

    const hrMenu = screen.getByTestId('dashboard-hr-menu');
    expect(screen.queryByTestId('dashboard-hr-menu-panel')).not.toBeInTheDocument();

    // L'ouverture n'est PAS immédiate (hover intent ~150 ms) : un pointeur qui
    // traverse la barre ne déclenche rien, un survol franc ouvre le panneau.
    await userEvent.hover(hrMenu);
    expect(screen.queryByTestId('dashboard-hr-menu-panel')).not.toBeInTheDocument();
    expect(await screen.findByTestId('dashboard-hr-menu-panel')).toBeInTheDocument();

    // La sortie referme après ~300 ms (tolérance de trajectoire).
    await userEvent.unhover(hrMenu);
    expect(screen.getByTestId('dashboard-hr-menu-panel')).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByTestId('dashboard-hr-menu-panel')).not.toBeInTheDocument());
  });
});

describe('Barre du haut — règle icône seule (#7422)', () => {
  it('les libellés de contrôle sont en sr-only, plus en texte visible', async () => {
    await renderDashboard();

    // Règle propriétaire : icône + texte réservé aux entrées de MENU ; les
    // autres contrôles de la barre ne gardent que l'icône, le libellé restant
    // lu par les lecteurs d'écran.
    for (const label of ['Modules & plan', 'Langue']) {
      // Chaque libellé doit encore exister (il nomme son contrôle) mais être
      // rendu hors flux visuel — jamais en texte visible dans la barre.
      expect(screen.getByText(label)).toBeInTheDocument();
      expect(screen.getByText(label)).toHaveClass('sr-only');
    }
  });
});

describe('Barre du haut — refermeture des panneaux (#7584)', () => {
  /**
   * Le défaut : `closeHeaderPanels(); setX((value) => !value)`. Le helper remet
   * CE panneau à `false`, puis l'updater fonctionnel relit cet état et le
   * repasse à `true` — le panneau se rouvrait donc à chaque clic et ne pouvait
   * jamais être refermé par son propre déclencheur.
   *
   * L'assertion porte sur le **démontage** du panneau (ils sont rendus
   * conditionnellement, `{open ? <div/> : null}`), pas seulement sur
   * `aria-expanded` : c'est le contrat que l'issue demande de tenir.
   */
  it.each([
    ['notifications', 'dashboard-notifications-toggle', 'dashboard-notifications-panel'],
    ['Modules & plan', 'dashboard-plan-toggle', 'dashboard-plan-panel'],
  ])('le panneau « %s » se referme au second clic', async (_nom, toggleId, panelId) => {
    await renderDashboard();

    expect(screen.queryByTestId(panelId)).not.toBeInTheDocument();

    await userEvent.click(screen.getByTestId(toggleId));
    expect(await screen.findByTestId(panelId)).toBeInTheDocument();

    await userEvent.click(screen.getByTestId(toggleId));
    await waitFor(() => expect(screen.queryByTestId(panelId)).not.toBeInTheDocument());
  });

  it('ouvrir un panneau referme le précédent (#7556, non régressé)', async () => {
    await renderDashboard();

    await userEvent.click(screen.getByTestId('dashboard-notifications-toggle'));
    expect(await screen.findByTestId('dashboard-notifications-panel')).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('dashboard-plan-toggle'));
    await waitFor(() => expect(screen.queryByTestId('dashboard-notifications-panel')).not.toBeInTheDocument());
    expect(screen.getByTestId('dashboard-plan-panel')).toBeInTheDocument();
  });
});
