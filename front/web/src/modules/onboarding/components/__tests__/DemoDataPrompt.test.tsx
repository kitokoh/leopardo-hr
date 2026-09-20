import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import type { StoredAuthUser } from '@/lib/i18n';
import { DemoDataPrompt, shouldShowDemoDataPrompt } from '../DemoDataPrompt';

/**
 * #7866 — invite d'import du jeu de données de démonstration (API #7865).
 *
 * Verrouille : la pré-garde d'affichage (miroir du RBAC serveur + verticale
 * active + metadata `demo_data`), le serveur comme source de vérité (rien
 * n'est rendu sans kit `available` + `not_imported`), l'import séquentiel
 * avec état de succès, le « Non merci » persisté serveur, le « Plus tard »
 * SANS aucun appel serveur, et l'erreur d'import non bloquante.
 */
jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

// framer-motion : rendu synchrone en test (pas d'animations).
jest.mock('framer-motion', () => ({
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  motion: new Proxy(
    {},
    {
      get: (_target, element) => {
        const Component = ({ children, ...props }: Record<string, unknown> & { children?: React.ReactNode }) => {
          const { initial, animate, exit, transition, ...rest } = props;
          void initial; void animate; void exit; void transition;
          const Tag = String(element) as 'div';
          return <Tag {...(rest as object)}>{children}</Tag>;
        };
        return Component;
      },
    },
  ),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const principal: StoredAuthUser = {
  role: 'manager',
  manager_role: 'principal',
  features: { restaurant: true },
  company: { id: 'company-1', features: { restaurant: true }, metadata: {} },
};

function jsonResponse(data: unknown, ok = true, status = 200) {
  return { ok, status, json: async () => ({ data }) } as unknown as Response;
}

function mockKits(kits: Array<Record<string, unknown>>) {
  mockedApiFetch.mockImplementation(async (path: string, init?: RequestInit) => {
    if (path === '/demo-data' && !init?.method) {
      return jsonResponse({ kits });
    }
    if (/^\/demo-data\/[^/]+\/(import|dismiss)$/.test(path) && init?.method === 'POST') {
      return jsonResponse({ code: path.split('/')[2], status: 'imported' });
    }
    return jsonResponse({});
  });
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('shouldShowDemoDataPrompt (#7866)', () => {
  it('propose l’invite à un responsable dont une verticale connue est active', () => {
    expect(shouldShowDemoDataPrompt(principal)).toBe(true);
    // La verticale peut n'être exposée QUE dans company.features.
    expect(
      shouldShowDemoDataPrompt({
        ...principal,
        features: null,
        company: { id: 'c', features: { travelagency: true }, metadata: {} },
      }),
    ).toBe(true);
  });

  it('miroir de la garde serveur : ni employé, ni comptable, ni session sans société', () => {
    expect(shouldShowDemoDataPrompt(null)).toBe(false);
    expect(shouldShowDemoDataPrompt(undefined)).toBe(false);
    expect(shouldShowDemoDataPrompt({ ...principal, role: 'employee', manager_role: null })).toBe(false);
    expect(shouldShowDemoDataPrompt({ ...principal, manager_role: 'comptable' })).toBe(false);
    expect(shouldShowDemoDataPrompt({ role: 'manager', manager_role: 'principal' })).toBe(false);
  });

  it('rien sans verticale connue active (flags absents, faux ou inconnus)', () => {
    expect(
      shouldShowDemoDataPrompt({ ...principal, features: null, company: { id: 'c', metadata: {} } }),
    ).toBe(false);
    expect(
      shouldShowDemoDataPrompt({
        ...principal,
        features: { restaurant: false, rh: true },
        company: { id: 'c', features: { rh: true, finance: true }, metadata: {} },
      }),
    ).toBe(false);
  });

  it('ne redéclenche rien après un import ou un « Non merci » persisté serveur', () => {
    expect(
      shouldShowDemoDataPrompt({
        ...principal,
        company: {
          ...principal.company,
          metadata: { demo_data: { restaurant: { status: 'imported' } } },
        },
      }),
    ).toBe(false);
    expect(
      shouldShowDemoDataPrompt({
        ...principal,
        company: {
          ...principal.company,
          metadata: { demo_data: { restaurant: { status: 'dismissed' } } },
        },
      }),
    ).toBe(false);
    // Une AUTRE verticale active encore non traitée réactive la pré-garde.
    expect(
      shouldShowDemoDataPrompt({
        ...principal,
        features: { restaurant: true, travelagency: true },
        company: {
          ...principal.company,
          metadata: { demo_data: { restaurant: { status: 'dismissed' } } },
        },
      }),
    ).toBe(true);
  });
});

describe('DemoDataPrompt (#7866)', () => {
  it('ne rend RIEN quand le serveur ne propose aucun kit (source de vérité)', async () => {
    mockKits([{ code: 'restaurant', available: false, status: 'not_imported' }]);
    render(<DemoDataPrompt locale="fr" onClose={jest.fn()} />);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/demo-data'));
    expect(screen.queryByTestId('demo-data-prompt')).not.toBeInTheDocument();
  });

  it('affiche l’invite et importe le kit, puis ferme en « imported »', async () => {
    mockKits([{ code: 'restaurant', available: true, status: 'not_imported' }]);
    const onClose = jest.fn();
    render(<DemoDataPrompt locale="fr" onClose={onClose} />);

    await waitFor(() => expect(screen.getByTestId('demo-data-prompt')).toBeInTheDocument());
    expect(
      screen.getByText('Voulez-vous découvrir votre espace avec des données de démonstration ?'),
    ).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('demo-data-import'));

    await waitFor(() => expect(screen.getByTestId('demo-data-success')).toBeInTheDocument());
    expect(mockedApiFetch).toHaveBeenCalledWith('/demo-data/restaurant/import', { method: 'POST' });

    await userEvent.click(screen.getByTestId('demo-data-success-cta'));
    expect(onClose).toHaveBeenCalledWith('imported');
  });

  it('importe SÉQUENTIELLEMENT chaque kit proposable (multi-verticales)', async () => {
    mockKits([
      { code: 'restaurant', available: true, status: 'not_imported' },
      { code: 'travelagency', available: true, status: 'not_imported' },
      { code: 'pharmacy', available: true, status: 'imported' },
    ]);
    render(<DemoDataPrompt locale="fr" onClose={jest.fn()} />);

    await waitFor(() => expect(screen.getByTestId('demo-data-import')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('demo-data-import'));

    await waitFor(() => expect(screen.getByTestId('demo-data-success')).toBeInTheDocument());
    const importCalls = mockedApiFetch.mock.calls.filter(([path]) => String(path).endsWith('/import'));
    expect(importCalls.map(([path]) => path)).toEqual([
      '/demo-data/restaurant/import',
      '/demo-data/travelagency/import',
    ]);
  });

  it('« Non merci » persiste le refus côté serveur puis ferme en « dismissed »', async () => {
    mockKits([{ code: 'restaurant', available: true, status: 'not_imported' }]);
    const onClose = jest.fn();
    render(<DemoDataPrompt locale="fr" onClose={onClose} />);

    await waitFor(() => expect(screen.getByTestId('demo-data-dismiss')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('demo-data-dismiss'));

    await waitFor(() => expect(onClose).toHaveBeenCalledWith('dismissed'));
    expect(mockedApiFetch).toHaveBeenCalledWith('/demo-data/restaurant/dismiss', { method: 'POST' });
  });

  it('« Plus tard » ferme pour la session SANS aucun appel serveur', async () => {
    mockKits([{ code: 'restaurant', available: true, status: 'not_imported' }]);
    const onClose = jest.fn();
    render(<DemoDataPrompt locale="fr" onClose={onClose} />);

    await waitFor(() => expect(screen.getByTestId('demo-data-later')).toBeInTheDocument());
    mockedApiFetch.mockClear();
    await userEvent.click(screen.getByTestId('demo-data-later'));

    expect(onClose).toHaveBeenCalledWith('later');
    // Session uniquement : ni POST, ni localStorage.
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('affiche une erreur i18n et RESTE ouverte quand l’import échoue', async () => {
    mockedApiFetch.mockImplementation(async (path: string, init?: RequestInit) => {
      if (path === '/demo-data' && !init?.method) {
        return jsonResponse({ kits: [{ code: 'restaurant', available: true, status: 'not_imported' }] });
      }
      return jsonResponse({}, false, 500);
    });
    const onClose = jest.fn();
    render(<DemoDataPrompt locale="fr" onClose={onClose} />);

    await waitFor(() => expect(screen.getByTestId('demo-data-import')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('demo-data-import'));

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
    expect(screen.getByTestId('demo-data-prompt')).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });
});
