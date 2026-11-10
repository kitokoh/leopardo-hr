import { render, screen } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import DashboardLayout from '../layout';

/**
 * #9329 — le panneau « module verrouillé » ne doit pas proposer un ACHAT quand
 * la cause est un RÔLE manquant.
 *
 * Le composant distinguait déjà les deux causes dans son paragraphe
 * d'explication, mais le badge, l'encart d'aide et surtout le CTA restaient
 * ceux du plan : un manager bloqué par son rôle lisait « Module non inclus »
 * puis « Demander l'activation » vers /contact?topic=upgrade. Or son entreprise
 * a le module — la seule action utile est interne (le principal accorde le
 * rôle depuis Équipe).
 *
 * Cas verrouillés ici :
 *  1. `role_locked` → badge de rôle, CTA « Voir mes droits d'équipe » vers
 *     /employees, aucun lien commercial ;
 *  2. `feature_locked` (flag tenant) → comportement commercial conservé ;
 *  3. les deux causes restent identifiables par `data-reason` (analytics).
 */

let mockPathname = '/payroll';

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

function user(role: string, managerRole: string | null) {
  return {
    id: 101,
    first_name: 'Fatima',
    last_name: 'Meziane',
    email: 'fatima.meziane@techcorp-algerie.dz',
    role,
    manager_role: managerRole,
    language: 'fr',
    is_rtl: false,
    capabilities: { can_view_dashboard: true, employees: true },
    company: {
      id: 'company-1',
      name: 'TechCorp Algerie SARL',
      status: 'active',
      language: 'fr',
      metadata: { onboarding_completed: true },
    },
  };
}

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

async function renderAt(pathname: string, authUser: Record<string, unknown>) {
  mockPathname = pathname;
  window.localStorage.setItem('auth_user', JSON.stringify(authUser));
  render(<DashboardLayout>contenu</DashboardLayout>);
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
      return { ok: true, status: 200, json: async () => ({ data: user('manager', 'dept') }) } as Response;
    }
    return {
      ok: true,
      status: 200,
      json: async () => ({ data: [], meta: { total: 0, unread_count: 0 } }),
    } as Response;
  });
});

describe('Panneau « module verrouillé » — cause-aware (#9329)', () => {
  it('cause RÔLE : badge de rôle, aide interne, CTA vers /employees — aucun lien commercial', async () => {
    // `/crm` est réservé à principal/rh : un `dept` est bloqué par son RÔLE
    // alors que le module est actif pour le tenant.
    await renderAt('/crm', user('manager', 'dept'));

    const panel = await screen.findByTestId('feature-locked-panel');
    expect(panel).toHaveAttribute('data-reason', 'role_locked');

    expect(screen.getByTestId('feature-locked-badge')).toHaveTextContent('Accès non accordé à votre rôle');
    expect(screen.getByTestId('feature-locked-hint')).toHaveTextContent('manager principal');

    const cta = screen.getByTestId('feature-locked-cta');
    expect(cta).toHaveTextContent('Voir mes droits d’équipe'.replace('’', "'"));
    expect(cta).toHaveAttribute('href', '/employees');

    // Le symptôme exact de l'issue : plus aucune invitation à acheter.
    expect(cta.getAttribute('href')).not.toContain('contact');
    expect(screen.queryByText("Demander l'activation")).not.toBeInTheDocument();
    expect(screen.queryByText('Module non inclus')).not.toBeInTheDocument();
  });

  it('cause PLAN/FLAG : le parcours commercial est conservé', async () => {
    // `/payroll` est ouvert à tout manager (`'any'`) : sans la capacité
    // `payroll`, le module est fermé par le FLAG tenant, pas par le rôle.
    // C'est exactement la branche qui doit garder le parcours commercial.
    const noPayroll = { ...user('manager', 'dept'), capabilities: { can_view_dashboard: true, employees: true } };
    mockPathname = '/payroll';
    window.localStorage.setItem('auth_user', JSON.stringify(noPayroll));
    render(<DashboardLayout>contenu</DashboardLayout>);
    await screen.findByTestId('user-menu-toggle');

    const panel = await screen.findByTestId('feature-locked-panel');
    expect(panel).toHaveAttribute('data-reason', 'feature_locked');
    expect(screen.getByTestId('feature-locked-badge')).toHaveTextContent('Module non inclus');
    expect(screen.getByTestId('feature-locked-cta')).toHaveAttribute('href', '/contact?topic=upgrade');
  });

  it('les deux causes restent distinguables pour l’analytics', async () => {
    await renderAt('/crm', user('manager', 'dept'));
    expect(await screen.findByTestId('feature-locked-panel')).toHaveAttribute('data-reason', 'role_locked');
  });
});
