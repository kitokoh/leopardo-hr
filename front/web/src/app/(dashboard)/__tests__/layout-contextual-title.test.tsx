import { render, screen } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import DashboardLayout from '../layout';

/**
 * #7483 — Garde de non-régression du TITRE CONTEXTUEL de la barre du haut.
 *
 * Le défaut : le `<h2>` de la barre était rendu en dur via
 * `labels.dashboard.heading` — « Tableau de bord » s'affichait donc sur
 * TOUTES les pages, alors que la pastille de navigation active indiquait la
 * vraie section. Le titre doit suivre le pathname, en reprenant la même
 * résolution que les pastilles (catalogue `ROUTE_TO_MODULE` de
 * `client-features.ts` → clé i18n `dashboard.modules`, 4 locales).
 *
 * Ce que ce fichier verrouille :
 *  1. le titre reflète le module de la page courante (et plus « Tableau de
 *     bord » partout) ;
 *  2. la résolution couvre les sous-routes du catalogue (`/attendance/geo` →
 *     « Sessions GPS ») et le match par préfixe (`/employees/12` →
 *     « Employés ») ;
 *  3. une route hors catalogue (ex. `/settings/account`) retombe sur le
 *     titre générique `dashboard.heading` ;
 *  4. le titre est localisé (même catalogue i18n que la navigation — ici EN).
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
    smart_attendance: true,
    absences: true,
    payroll: true,
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

async function renderDashboardAt(pathname: string, user: Record<string, unknown> = managerUser) {
  mockPathname = pathname;
  window.localStorage.setItem('auth_user', JSON.stringify(user));
  render(<DashboardLayout>contenu</DashboardLayout>);

  // La barre n'est rendue qu'après montage (session lue en effet) : on
  // attend l'avatar de compte, témoin du header complètement monté.
  return await screen.findByTestId('user-menu-toggle');
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  stubMatchMedia();

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

describe('Barre du haut — titre contextuel (#7483)', () => {
  it('affiche « Tableau de bord » sur /dashboard (comportement inchangé)', async () => {
    await renderDashboardAt('/dashboard');

    expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });

  it('affiche le libellé du module courant sur /employees', async () => {
    await renderDashboardAt('/employees');

    expect(screen.getByRole('heading', { name: 'Employés' })).toBeInTheDocument();
    // Le symptôme exact de l'issue : le titre ne doit PLUS rester figé.
    expect(screen.queryByRole('heading', { name: 'Tableau de bord' })).not.toBeInTheDocument();
  });

  it('affiche « Paie » sur /payroll', async () => {
    await renderDashboardAt('/payroll');

    expect(screen.getByRole('heading', { name: 'Paie' })).toBeInTheDocument();
  });

  it('résout une sous-route exacte du catalogue (/attendance/geo → Sessions GPS)', async () => {
    await renderDashboardAt('/attendance/geo');

    expect(screen.getByRole('heading', { name: 'Sessions GPS' })).toBeInTheDocument();
  });

  it('résout les sous-routes par préfixe (/employees/12 → Employés)', async () => {
    await renderDashboardAt('/employees/12');

    expect(screen.getByRole('heading', { name: 'Employés' })).toBeInTheDocument();
  });

  it('retombe sur le titre générique hors catalogue (/settings/account)', async () => {
    await renderDashboardAt('/settings/account');

    expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });

  it('localise le titre comme la navigation (locale en → Employees)', async () => {
    await renderDashboardAt('/employees', { ...managerUser, language: 'en' });

    expect(screen.getByRole('heading', { name: 'Employees' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Employés' })).not.toBeInTheDocument();
  });
});
