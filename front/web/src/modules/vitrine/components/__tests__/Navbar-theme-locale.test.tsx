import { render, screen, waitFor, within } from '@testing-library/react';

import { Navbar } from '../Navbar';

/**
 * Refonte vitrine #8461 — deux retours propriétaire verrouillés ici :
 *
 *  1. Le SÉLECTEUR DE LANGUE perd son libellé texte (« Français ») au profit
 *     de la seule icône/drapeau — mais garde son nom accessible (`aria-label`)
 *     et son infobulle (`title`), sans quoi retirer le texte visible casserait
 *     l'accessibilité.
 *  2. La BASCULE JOUR/NUIT disparaît complètement de l'interface (thème unique).
 *
 * Le `<select>` natif reste le vrai contrôle (clavier, lecteur d'écran) : il
 * est visuellement masqué mais couvre la pastille. On vérifie donc que le
 * nœud existe et porte un nom accessible, et qu'AUCUN élément non-`<option>`
 * n'affiche le libellé de locale.
 */

const mockFetch = jest.fn();

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
});

describe('Navbar — sélecteur de langue en icône seule (#8461)', () => {
  it('ne rend AUCUN libellé de locale en texte visible (hors <option>)', async () => {
    // 401 : visiteur anonyme — état déterministe.
    mockFetch.mockResolvedValue({ ok: false, status: 401 });
    render(<Navbar />);
    await waitFor(() => expect(mockFetch).toHaveBeenCalled());

    // Le mot « Français » ne doit apparaître que dans les <option> du
    // contrôle natif (nécessaires au menu du système et aux lecteurs
    // d'écran) — jamais comme texte visible à côté de l'icône.
    const visibleLabels = screen
      .queryAllByText(/Français|English|Türkçe|العربية/i)
      .filter((el) => el.tagName !== 'OPTION');
    expect(visibleLabels).toHaveLength(0);
  });

  it('conserve un nom accessible et une infobulle sur le contrôle de langue', async () => {
    mockFetch.mockResolvedValue({ ok: false, status: 401 });
    render(<Navbar />);
    await waitFor(() => expect(mockFetch).toHaveBeenCalled());

    const select = screen.getAllByRole('combobox')[0];
    expect(select).toBeInTheDocument();
    expect((select.getAttribute('aria-label') ?? '').trim().length).toBeGreaterThan(0);
    expect((select.getAttribute('title') ?? '').trim().length).toBeGreaterThan(0);
  });
});

describe('Navbar — aucun bouton de bascule de thème (#8461)', () => {
  it('n’expose plus de contrôle jour/nuit', async () => {
    mockFetch.mockResolvedValue({ ok: false, status: 401 });
    const { container } = render(<Navbar />);
    await waitFor(() => expect(mockFetch).toHaveBeenCalled());

    const toggle = Array.from(container.querySelectorAll('button')).filter((btn) => {
      const name = `${btn.getAttribute('aria-label') ?? ''} ${btn.getAttribute('title') ?? ''}`;
      return /theme|th[eè]me|dark|sombre|mode/i.test(name);
    });
    expect(toggle).toHaveLength(0);
  });

  it('ne pose jamais la classe .dark (thème unique clair)', async () => {
    mockFetch.mockResolvedValue({ ok: false, status: 401 });
    const { container } = render(<Navbar />);
    await waitFor(() => expect(mockFetch).toHaveBeenCalled());

    const nav = container.querySelector('nav') ?? container;
    expect(within(nav as HTMLElement).queryByText(/dark/i)).not.toBeTruthy();
  });
});
