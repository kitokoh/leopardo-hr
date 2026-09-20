import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { normalizeComments, PostInteractions } from '@/modules/marketing/components/PostInteractions';

/**
 * #7755 (backend #7754) — panneau Interactions d'un post publié :
 * lecture des commentaires, réponse manuelle, suggestion IA pré-remplissant
 * le champ (l'humain valide TOUJOURS avant l'envoi).
 */
jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown): Response {
  return {
    ok: true,
    status: 200,
    json: async () => payload,
  } as unknown as Response;
}

const commentsPayload = {
  data: {
    social_post_id: 7,
    provider_post_ref: 'ayr-1',
    comments: {
      linkedin: [{ comment: 'Great offer!', userName: 'Jane' }],
      twitter: [{ comment: 'Is it available in Turkey?' }],
    },
  },
};

beforeEach(() => {
  jest.clearAllMocks();
});

describe('normalizeComments', () => {
  it('flattens the per-platform aggregator payload', () => {
    expect(normalizeComments(commentsPayload.data.comments)).toEqual([
      { platform: 'linkedin', comment: 'Great offer!', userName: 'Jane', created: undefined },
      { platform: 'twitter', comment: 'Is it available in Turkey?', userName: undefined, created: undefined },
    ]);
  });

  it('returns an empty list for malformed payloads', () => {
    expect(normalizeComments(null)).toEqual([]);
    expect(normalizeComments('status')).toEqual([]);
    expect(normalizeComments({ linkedin: 'nope' })).toEqual([]);
  });
});

describe('PostInteractions', () => {
  it('loads and renders the comments of the post', async () => {
    mockedApiFetch.mockResolvedValue(jsonResponse(commentsPayload));

    render(<PostInteractions postId={7} />);

    await waitFor(() => expect(screen.getByTestId('post-interactions-list')).toBeInTheDocument());
    expect(screen.getByText('Great offer!')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/marketing/social-posts/7/comments');
  });

  it('shows the empty state when the post has no comments', async () => {
    mockedApiFetch.mockResolvedValue(jsonResponse({ data: { comments: {} } }));

    render(<PostInteractions postId={7} />);

    await waitFor(() => expect(screen.getByTestId('post-interactions-empty')).toBeInTheDocument());
  });

  it('pre-fills the reply field with the AI suggestion (human validates before sending)', async () => {
    const user = userEvent.setup();
    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      if (endpoint.endsWith('/comments')) {
        return jsonResponse(commentsPayload);
      }
      if (endpoint.endsWith('/suggest-reply')) {
        return jsonResponse({ data: { suggestion: 'Merci Jane, ravi que cela vous plaise !' } });
      }
      throw new Error(`unexpected endpoint: ${endpoint}`);
    });

    render(<PostInteractions postId={7} />);
    await waitFor(() => expect(screen.getByTestId('post-interactions-list')).toBeInTheDocument());

    await user.click(screen.getByTestId('post-interactions-suggest-0'));

    await waitFor(() => (
      expect(screen.getByTestId('post-interactions-reply')).toHaveValue('Merci Jane, ravi que cela vous plaise !')
    ));

    // Suggestion only: no reply endpoint call happened.
    const replyCalls = mockedApiFetch.mock.calls.filter(([endpoint]) => String(endpoint).endsWith('/comments/reply'));
    expect(replyCalls).toHaveLength(0);
  });

  it('sends the manual reply and shows the confirmation', async () => {
    const user = userEvent.setup();
    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      if (endpoint.endsWith('/comments/reply')) {
        return jsonResponse({ data: { status: 'success' } });
      }
      return jsonResponse(commentsPayload);
    });

    render(<PostInteractions postId={7} />);
    await waitFor(() => expect(screen.getByTestId('post-interactions-list')).toBeInTheDocument());

    await user.type(screen.getByTestId('post-interactions-reply'), 'Merci pour votre retour !');
    await user.click(screen.getByTestId('post-interactions-send'));

    await waitFor(() => expect(screen.getByTestId('post-interactions-sent')).toBeInTheDocument());
    expect(mockedApiFetch).toHaveBeenCalledWith('/marketing/social-posts/7/comments/reply', {
      method: 'POST',
      body: JSON.stringify({ comment: 'Merci pour votre retour !' }),
    });
    expect(screen.getByTestId('post-interactions-reply')).toHaveValue('');
  });
});
