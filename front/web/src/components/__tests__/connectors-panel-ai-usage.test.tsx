import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import { ConnectorsPanel } from '../connectors-panel';

/**
 * #9925 (BC-14 INTEGRATION) — panneau Connecteurs tenant : consommation de
 * tokens d'un modèle d'IA.
 *
 * Ce que ce fichier verrouille :
 *  1. une connexion `ai_*` affiche sa consommation de tokens et son budget ;
 *  2. sans budget, l'écran dit « budget illimité » (jamais un 0 trompeur) ;
 *  3. une connexion NON-IA n'affiche aucun bloc de consommation ;
 *  4. un échec de l'endpoint usage ne casse pas le panneau (facultatif).
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return {
    json: async () => payload,
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    clone: () => jsonResponse(payload, status),
  } as unknown as Response;
}

const aiCatalog = {
  data: {
    items: [
      {
        key: 'ai_openai',
        label: 'Modèle OpenAI',
        category: 'ai',
        fields: [
          { key: 'model', label: 'Modèle', type: 'text', required: true },
          { key: 'api_key', label: 'Clé API', type: 'password', secret: true, required: true },
        ],
      },
    ],
  },
};

const aiConnection = {
  id: 42,
  catalog_key: 'ai_openai',
  label: 'Assistant IA',
  status: 'active',
  config: { model: 'gpt-4o' },
  secrets: {},
};

const nonAiConnection = {
  id: 7,
  catalog_key: 'wordpress',
  label: 'Site vitrine',
  status: 'active',
  config: { site_url: 'https://mon-site.com' },
  secrets: {},
};

function mockCalls({
  connections = [aiConnection],
  usage = { data: { connection_id: 42, period: '2026-11', used_tokens: 12500, budget_tokens: 100000, remaining_tokens: 87500 } },
  usageFails = false,
}: {
  connections?: unknown[];
  usage?: unknown;
  usageFails?: boolean;
} = {}) {
  mockedApiFetch.mockImplementation(async (path: string) => {
    if (path === '/connectors/catalog') return jsonResponse(aiCatalog);
    if (path === '/connectors/connections') return jsonResponse({ data: { items: connections } });
    if (path === '/connectors/overview') return jsonResponse({ data: { integrations: [] } });
    if (path.endsWith('/usage')) {
      if (usageFails) throw new Error('usage unavailable');
      return jsonResponse(usage);
    }
    throw new Error(`Unexpected apiFetch call: ${path}`);
  });
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Panneau Connecteurs — consommation de tokens IA (#9925)', () => {
  it('affiche la consommation et le budget d’une connexion IA', async () => {
    mockCalls();

    render(<ConnectorsPanel />);

    expect(await screen.findByText('Assistant IA')).toBeInTheDocument();

    // Le bloc de consommation apparaît avec les chiffres réels.
    await waitFor(() => {
      expect(screen.getByText(/Consommation de tokens/)).toBeInTheDocument();
    });
    expect(screen.getByText(/12\s?500 tokens consommés/)).toBeInTheDocument();
    expect(screen.getByText(/sur 100\s?000/)).toBeInTheDocument();
  });

  it('affiche « budget illimité » quand aucun budget n’est défini', async () => {
    mockCalls({
      usage: { data: { connection_id: 42, period: '2026-11', used_tokens: 500, budget_tokens: null, remaining_tokens: null } },
    });

    render(<ConnectorsPanel />);

    await waitFor(() => {
      expect(screen.getByText(/Consommation de tokens/)).toBeInTheDocument();
    });
    expect(screen.getByText(/500 tokens consommés/)).toBeInTheDocument();
    expect(screen.getByText(/budget illimité/)).toBeInTheDocument();
  });

  it('n’affiche aucun bloc de consommation pour une connexion non-IA', async () => {
    mockCalls({ connections: [nonAiConnection] });

    render(<ConnectorsPanel />);

    expect(await screen.findByText('Site vitrine')).toBeInTheDocument();
    expect(screen.queryByText(/Consommation de tokens/)).not.toBeInTheDocument();
  });

  it('reste facultatif : un échec de l’endpoint usage ne casse pas le panneau', async () => {
    mockCalls({ usageFails: true });

    render(<ConnectorsPanel />);

    // La connexion reste affichée, sans bloc de consommation.
    expect(await screen.findByText('Assistant IA')).toBeInTheDocument();
    expect(screen.queryByText(/Consommation de tokens/)).not.toBeInTheDocument();
  });
});