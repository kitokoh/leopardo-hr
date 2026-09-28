import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, apiFetch } from '@/lib/api-client';
import { AssistantPanel } from '../AssistantPanel';

/**
 * BOS-035 (#8224) — panneau Assistant Leo IA du portail tenant.
 *
 * Ce que ce fichier verrouille :
 *  1. l'envoi d'un message affiche la bulle utilisateur puis la réponse de
 *     l'assistant (POST /ai/chat) et persiste le conversation_id ;
 *  2. les badges `tools_used` sont affichés sous la réponse de l'assistant ;
 *  3. une carte de confirmation → Confirmer → résultat d'exécution affiché ;
 *  4. une carte de confirmation → Rejeter → état rejeté ;
 *  5. un confirm 404 (action expirée — TTL 15 min) → message « expirée » ;
 *  6. un 403 AI_FEATURE_DISABLED → état « assistant désactivé » du panneau ;
 *  7. un 422 AI_CREDITS_EXHAUSTED → bannière dédiée, fil non pollué.
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    code?: string;
    constructor(message: string, status: number, code?: string) {
      super(message);
      this.status = status;
      this.code = code;
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

const pendingConfirmation = {
  status: 'confirmation_required',
  pending_action_id: 'uuid-action-1',
  tool: 'absence.approve',
  summary: 'Approuver la demande d’absence de Karim Benali',
  arguments: { absence_id: 12 },
};

const chatWithConfirmation = {
  conversation_id: 43,
  response: 'Je peux approuver cette absence.',
  tools_used: ['absence.approve'],
  pending_confirmations: [pendingConfirmation],
  tokens: { input: 20, output: 40 },
};

type MockOptions = {
  chatPayload?: unknown;
  chatError?: ApiError;
  confirmError?: ApiError;
};

function mockAssistantApi({ chatPayload, chatError, confirmError }: MockOptions = {}) {
  mockedApiFetch.mockImplementation(async (path: string, options?: RequestInit) => {
    if (path.startsWith('/ai/chat/history')) {
      return jsonResponse({ data: [], meta: { total: 0 } });
    }
    if (path === '/ai/chat' && options?.method === 'POST') {
      if (chatError) {
        throw chatError;
      }
      return jsonResponse({
        data: chatPayload ?? {
          conversation_id: 42,
          response: 'Vous avez 39 employés actifs aujourd’hui.',
          tools_used: ['employees.list'],
          pending_confirmations: [],
          tokens: { input: 12, output: 34 },
        },
      });
    }
    if (path === '/ai/actions/uuid-action-1/confirm') {
      if (confirmError) {
        throw confirmError;
      }
      return jsonResponse({
        data: {
          status: 'executed',
          tool: 'absence.approve',
          result: { absence: { id: 12, status: 'approved' } },
        },
      });
    }
    if (path === '/ai/actions/uuid-action-1/reject') {
      return jsonResponse({ data: { status: 'rejected', tool: 'absence.approve' } });
    }
    throw new Error(`Unexpected apiFetch call: ${path}`);
  });
}

async function sendMessage(text: string) {
  const input = await screen.findByTestId('assistant-input');
  await userEvent.type(input, text);
  await userEvent.click(screen.getByTestId('assistant-send'));
}

beforeAll(() => {
  // jsdom ne connaît pas scrollIntoView (auto-défilement du fil) : stub.
  window.HTMLElement.prototype.scrollIntoView = jest.fn();
});

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.clear();
  window.localStorage.setItem('preferred_locale', 'fr');
});

describe('Panneau Assistant Leo IA (BOS-035, #8224)', () => {
  it('envoie un message et affiche les bulles utilisateur + assistant', async () => {
    mockAssistantApi();

    render(<AssistantPanel />);
    await sendMessage('Combien d’employés actifs ?');

    // Bulle utilisateur puis réponse de l'assistant.
    const userBubble = await screen.findByTestId('assistant-user-bubble');
    expect(userBubble).toHaveTextContent('Combien d’employés actifs ?');
    const assistantBubble = await screen.findByTestId('assistant-assistant-bubble');
    expect(assistantBubble).toHaveTextContent('Vous avez 39 employés actifs');

    // POST /ai/chat avec conversation_id null (nouvelle conversation).
    const chatCall = mockedApiFetch.mock.calls.find(([path]) => path === '/ai/chat');
    expect(chatCall).toBeDefined();
    const body = JSON.parse(String((chatCall?.[1] as RequestInit).body));
    expect(body).toEqual({ message: 'Combien d’employés actifs ?', conversation_id: null });

    // Le conversation_id renvoyé est persisté pour survivre à un rechargement.
    expect(window.localStorage.getItem('leopardo_assistant_current')).toBe('42');
    const cached = window.localStorage.getItem('leopardo_assistant_msgs_42');
    expect(cached).not.toBeNull();
    expect(JSON.parse(String(cached))).toHaveLength(2);
  });

  it('affiche les badges tools_used sous la réponse de l’assistant', async () => {
    mockAssistantApi();

    render(<AssistantPanel />);
    await sendMessage('Pointages du jour ?');

    const tools = await screen.findByTestId('assistant-tools-used');
    expect(tools).toHaveTextContent('employees.list');
  });

  it('carte de confirmation : Confirmer exécute l’action et affiche le résultat', async () => {
    mockAssistantApi({ chatPayload: chatWithConfirmation });

    render(<AssistantPanel />);
    await sendMessage('Approuve l’absence de Karim');

    const card = await screen.findByTestId('assistant-confirmation-card');
    expect(card).toHaveTextContent('absence.approve');
    expect(card).toHaveTextContent('Approuver la demande d’absence de Karim Benali');
    expect(card).toHaveTextContent('absence_id');

    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/ai/actions/uuid-action-1/confirm',
        expect.objectContaining({ method: 'POST' }),
      );
    });

    const executed = await screen.findByTestId('assistant-card-executed');
    expect(executed).toHaveTextContent('"status": "approved"');
    // La carte est one-shot : plus aucun bouton d'action.
    expect(screen.queryByRole('button', { name: 'Confirmer' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Rejeter' })).not.toBeInTheDocument();
  });

  it('carte de confirmation : Rejeter affiche l’état rejeté', async () => {
    mockAssistantApi({ chatPayload: chatWithConfirmation });

    render(<AssistantPanel />);
    await sendMessage('Approuve l’absence de Karim');

    await userEvent.click(await screen.findByRole('button', { name: 'Rejeter' }));

    expect(await screen.findByTestId('assistant-card-rejected')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/ai/actions/uuid-action-1/reject',
      expect.objectContaining({ method: 'POST' }),
    );
    expect(screen.queryByRole('button', { name: 'Confirmer' })).not.toBeInTheDocument();
  });

  it('confirm 404 : la carte affiche le message « action expirée »', async () => {
    mockAssistantApi({
      chatPayload: chatWithConfirmation,
      confirmError: new ApiError('Pending action not found.', 404, 'PENDING_ACTION_NOT_FOUND'),
    });

    render(<AssistantPanel />);
    await sendMessage('Approuve l’absence de Karim');

    await userEvent.click(await screen.findByRole('button', { name: 'Confirmer' }));

    const expired = await screen.findByTestId('assistant-card-expired');
    expect(expired).toHaveTextContent('expiré');
    expect(screen.queryByRole('button', { name: 'Confirmer' })).not.toBeInTheDocument();
  });

  it('403 AI_FEATURE_DISABLED : le panneau affiche l’état désactivé', async () => {
    mockAssistantApi({ chatError: new ApiError('AI feature disabled.', 403, 'AI_FEATURE_DISABLED') });

    render(<AssistantPanel />);
    await sendMessage('Bonjour Leo');

    const disabled = await screen.findByTestId('assistant-disabled');
    expect(disabled).toHaveTextContent('Assistant IA désactivé');
  });

  it('422 AI_CREDITS_EXHAUSTED : bannière crédits épuisés, fil non pollué', async () => {
    mockAssistantApi({ chatError: new ApiError('AI credits exhausted.', 422, 'AI_CREDITS_EXHAUSTED') });

    render(<AssistantPanel />);
    await sendMessage('Bonjour Leo');

    const banner = await screen.findByTestId('assistant-error-credits');
    expect(banner).toHaveTextContent('crédits IA sont épuisés');
    // Le message non traité n'est pas conservé dans le fil.
    expect(screen.queryByTestId('assistant-user-bubble')).not.toBeInTheDocument();
  });
});
