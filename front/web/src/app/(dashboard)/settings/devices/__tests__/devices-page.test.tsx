import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DevicesSettingsPage from '../page';

/**
 * #8499 (I-12) — preuves de l'écran d'enrôlement/révocation :
 *  - la liste des appareils se charge ;
 *  - l'enrôlement affiche le jeton UNE SEULE FOIS avec l'avertissement ;
 *  - la révocation appelle DELETE /edge/{id}.
 */

jest.mock('@/lib/api-client', () => ({ apiFetch: jest.fn() }));
jest.mock('@/lib/i18n', () => ({
  getPreferredLocale: () => 'fr',
}));
jest.mock('@/lib/i18n/locale-catalog', () => ({
  t: (_l: string, key: string) => key,
}));
jest.mock('@/components/module-page-shell', () => ({
  ModulePageShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import { apiFetch } from '@/lib/api-client';

const mockFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(body: unknown, ok = true) {
  return Promise.resolve({ ok, json: () => Promise.resolve(body) } as Response);
}

describe('DevicesSettingsPage (#8499)', () => {
  beforeEach(() => {
    mockFetch.mockReset();
  });

  it('loads and lists enrolled devices', async () => {
    mockFetch.mockReturnValueOnce(
      jsonResponse({ data: [{ id: 'n1', name: 'Caisse 1', is_online: true }] }),
    );
    render(<DevicesSettingsPage />);
    await waitFor(() => expect(screen.getByText('Caisse 1')).toBeInTheDocument());
  });

  it('shows the one-time token warning after enrolment', async () => {
    mockFetch
      .mockReturnValueOnce(jsonResponse({ data: [] })) // initial load
      .mockReturnValueOnce(
        jsonResponse({
          data: { id: 'n2', name: 'KDS 1' },
          edge_token: 'tok_secret',
          install_command: 'curl ... EDGE_TOKEN=tok_secret ...',
        }),
      )
      .mockReturnValueOnce(jsonResponse({ data: [{ id: 'n2', name: 'KDS 1' }] })); // reload

    render(<DevicesSettingsPage />);
    await waitFor(() => expect(mockFetch).toHaveBeenCalled());

    await userEvent.type(screen.getByPlaceholderText('devices.namePlaceholder'), 'KDS 1');
    await userEvent.click(screen.getByText('devices.create'));

    await waitFor(() => expect(screen.getByText('devices.secretWarning')).toBeInTheDocument());
    expect(screen.getByText(/EDGE_TOKEN=tok_secret/)).toBeInTheDocument();
  });

  it('revokes a device via DELETE /edge/{id}', async () => {
    mockFetch
      .mockReturnValueOnce(jsonResponse({ data: [{ id: 'n3', name: 'POS 1' }] }))
      .mockReturnValueOnce(jsonResponse({ status: 'revoked' }))
      .mockReturnValueOnce(jsonResponse({ data: [] }));
    jest.spyOn(window, 'confirm').mockReturnValue(true);

    render(<DevicesSettingsPage />);
    await waitFor(() => expect(screen.getByText('POS 1')).toBeInTheDocument());

    await userEvent.click(screen.getByText('devices.revoke'));

    await waitFor(() =>
      expect(mockFetch).toHaveBeenCalledWith('/edge/n3', expect.objectContaining({ method: 'DELETE' })),
    );
  });
});
