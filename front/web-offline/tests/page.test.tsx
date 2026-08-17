// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import HomePage from '../src/app/page';
import { checkEdgeHealth } from '../src/lib/edge-health';

vi.mock('../src/lib/edge-health', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../src/lib/edge-health')>();

  return {
    ...actual,
    checkEdgeHealth: vi.fn(),
  };
});

const mockedCheck = vi.mocked(checkEdgeHealth);

/**
 * Issue #3971 — le rendu des états de santé de la PWA Edge (contrat #3719/#3772)
 * n'avait aucune couverture.
 */

beforeEach(() => {
  mockedCheck.mockReset();
  // #4806 : la copie d'interface est détectée depuis navigator.language.
  // jsdom définit en-US par défaut → on fige fr-FR pour conserver les
  // assertions FR historiques de cette suite.
  Object.defineProperty(window.navigator, 'language', {
    configurable: true,
    value: 'fr-FR',
  });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('HomePage (PWA Edge)', () => {
  it('shows the checking state before the first health response', () => {
    mockedCheck.mockReturnValue(new Promise(() => undefined)); // never resolves

    render(<HomePage />);

    // Le label de statut ET le bouton Actualiser affichent « Vérification… ».
    expect(screen.getAllByText('Vérification…').length).toBeGreaterThanOrEqual(1);
  });

  it('shows online state with health fields when the node responds', async () => {
    mockedCheck.mockResolvedValue({
      status: 'online',
      health: { status: 'healthy', node_id: 'edge-42', pending_sync: 2 },
    });

    render(<HomePage />);

    expect(await screen.findByText('Edge en ligne')).toBeInTheDocument();
    expect(screen.getByText('edge-42')).toBeInTheDocument();
    expect(screen.getByText('2 enregistrement(s)')).toBeInTheDocument();
    expect(screen.getByText('healthy')).toBeInTheDocument();
  });

  it('renders the dash placeholder for missing optional health fields', async () => {
    mockedCheck.mockResolvedValue({
      status: 'online',
      health: { status: 'healthy' },
    });

    render(<HomePage />);

    expect(await screen.findByText('Edge en ligne')).toBeInTheDocument();
    // node_id absent → '—', pending_sync absent → '—'
    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(2);
  });

  it('shows offline notice when the node is unreachable', async () => {
    mockedCheck.mockResolvedValue({ status: 'offline', health: null });

    render(<HomePage />);

    expect(await screen.findByText('Hors ligne')).toBeInTheDocument();
    expect(
      screen.getByText(/le node Edge local n'?est pas joignable/i)
    ).toBeInTheDocument();
  });

  it('shows error state (node reachable but failing) without the offline notice', async () => {
    mockedCheck.mockResolvedValue({ status: 'error', health: null });

    render(<HomePage />);

    expect(await screen.findByText('Erreur de connexion')).toBeInTheDocument();
    expect(screen.queryByText(/le node Edge local n'?est pas joignable/i)).not.toBeInTheDocument();
  });

  it('re-runs the health check when the user clicks Actualiser', async () => {
    mockedCheck
      .mockResolvedValueOnce({ status: 'offline', health: null })
      .mockResolvedValueOnce({ status: 'online', health: { status: 'healthy' } });

    render(<HomePage />);

    expect(await screen.findByText('Hors ligne')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Actualiser' }));

    await waitFor(() => {
      expect(screen.getByText('Edge en ligne')).toBeInTheDocument();
    });
    expect(mockedCheck).toHaveBeenCalledTimes(2);
  });

  it('localizes the interface from navigator.language (issue #4806)', async () => {
    mockedCheck.mockResolvedValue({
      status: 'online',
      health: { status: 'healthy', node_id: 'edge-42', pending_sync: 2 },
    });

    Object.defineProperty(window.navigator, 'language', {
      configurable: true,
      value: 'tr-TR',
    });

    render(<HomePage />);

    expect(await screen.findByText('Edge çevrimiçi')).toBeInTheDocument();
    expect(screen.getByText('Bekleyen senkronizasyon')).toBeInTheDocument();
    expect(screen.getByText('2 kayıt')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Yenile' })).toBeInTheDocument();
    // Chaînes techniques non traduites : la marque et le libellé Statut restent
    // inchangés quelle que soit la locale.
    expect(screen.getByText('Leopardo Edge')).toBeInTheDocument();
    expect(screen.getByText('Statut')).toBeInTheDocument();
  });
});
