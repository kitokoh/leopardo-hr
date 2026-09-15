import { render, screen, waitFor } from '@testing-library/react';

import { Navbar } from '../Navbar';

/**
 * #7492 — Session active : la Navbar vitrine remplace les CTA
 * « Connexion / Créer un compte » par un CTA unique « Mon espace ».
 *
 * Le cookie de session est httpOnly (illisible en JS) : la Navbar valide la
 * session auprès de `/api/v1/auth/me`. Les assertions portent sur les HREF
 * (indépendantes de la locale — jsdom rend en `en` par défaut) :
 * session active → lien `/dashboard`, jamais `/auth/login` ni `/signup`.
 */

const mockFetch = jest.fn();

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
});

function ctaHrefs(): Array<string | null> {
  return screen
    .getAllByRole('link')
    .map((a) => a.getAttribute('href'))
    .filter((href) => href === '/dashboard' || href === '/auth/login' || href === '/signup');
}

describe('Navbar — CTA selon la session (#7492)', () => {
  it('affiche « Mon espace » (→ /dashboard) quand la session est active', async () => {
    // Stub minimal : jsdom ne fournit pas `Response` (fetch natif absent).
    mockFetch.mockResolvedValue({ ok: true, status: 200 });

    render(<Navbar isDark={false} onToggleDark={() => undefined} />);

    await waitFor(() => {
      expect(ctaHrefs()).toContain('/dashboard');
    });

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/v1/auth/me',
      expect.objectContaining({ headers: { Accept: 'application/json' } }),
    );
    // Les CTA d'acquisition ont disparu.
    expect(ctaHrefs()).not.toContain('/auth/login');
    expect(ctaHrefs()).not.toContain('/signup');
  });

  it('conserve Connexion / Créer un compte pour un visiteur anonyme (401)', async () => {
    mockFetch.mockResolvedValue({ ok: false, status: 401 });

    render(<Navbar isDark={false} onToggleDark={() => undefined} />);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalled();
    });

    expect(ctaHrefs()).not.toContain('/dashboard');
    expect(ctaHrefs()).toContain('/auth/login');
    expect(ctaHrefs()).toContain('/signup');
  });

  it('reste en mode anonyme si la vérification échoue (réseau)', async () => {
    mockFetch.mockRejectedValue(new Error('network down'));

    render(<Navbar isDark={false} onToggleDark={() => undefined} />);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalled();
    });

    expect(ctaHrefs()).not.toContain('/dashboard');
    expect(ctaHrefs()).toContain('/auth/login');
  });
});
