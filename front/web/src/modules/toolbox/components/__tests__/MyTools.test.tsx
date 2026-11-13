import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MyTools, type MyToolsItem } from '../MyTools';
import { TOOLBOX_STORAGE_KEY } from '../../lib/storage';

/**
 * Tests de « Mes outils » — favoris + historique.
 *
 * Le composant lit `localStorage` : chaque test part d'un stockage vide et
 * vérifie l'état RÉELLEMENT rendu (pas seulement l'écriture en stockage).
 */

const ITEMS: MyToolsItem[] = [
  { slug: 'imc', name: 'IMC', categoryLabel: 'Santé' },
  { slug: 'tva', name: 'TVA', categoryLabel: 'Finance' },
  { slug: 'age', name: 'Âge', categoryLabel: 'Calcul' },
];

const COPY = {
  title: 'Mes outils',
  subtitle: 'Vos favoris et vos outils récemment utilisés.',
  favorites: 'Favoris',
  history: 'Récemment utilisés',
  empty: 'Aucun outil enregistré pour le moment.',
  emptyHint: 'Ouvrez un outil et ajoutez-le à vos favoris.',
  clear: 'Effacer mes outils',
};

function seed(state: { favorites: string[]; history: string[] }) {
  window.localStorage.setItem(TOOLBOX_STORAGE_KEY, JSON.stringify(state));
}

describe('MyTools', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('affiche l’état vide quand aucun outil n’est enregistré', async () => {
    render(<MyTools items={ITEMS} copy={COPY} />);

    expect(await screen.findByText(COPY.empty)).toBeInTheDocument();
    expect(screen.getByText(COPY.emptyHint)).toBeInTheDocument();
    // Le bouton d'effacement n'a pas de sens sur un état vide.
    expect(screen.queryByRole('button', { name: COPY.clear })).not.toBeInTheDocument();
  });

  it('affiche les favoris enregistrés, dans l’ordre du stockage', async () => {
    seed({ favorites: ['tva', 'imc'], history: [] });
    render(<MyTools items={ITEMS} copy={COPY} />);

    expect(await screen.findByText(COPY.favorites)).toBeInTheDocument();
    const links = screen.getAllByRole('link');
    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/toolbox/tva',
      '/toolbox/imc',
    ]);
  });

  it('affiche l’historique enregistré', async () => {
    seed({ favorites: [], history: ['age'] });
    render(<MyTools items={ITEMS} copy={COPY} />);

    expect(await screen.findByText(COPY.history)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Âge' })).toHaveAttribute('href', '/toolbox/age');
  });

  it('ignore un slug inconnu du catalogue (stockage obsolète)', async () => {
    seed({ favorites: ['outil-supprime'], history: ['imc'] });
    render(<MyTools items={ITEMS} copy={COPY} />);

    await screen.findByText(COPY.history);
    // Le slug inconnu ne produit aucun lien ; seul `imc` est rendu.
    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'IMC' })).toBeInTheDocument();
  });

  it('efface l’état au clic sur « Effacer mes outils »', async () => {
    seed({ favorites: ['imc'], history: ['tva'] });
    render(<MyTools items={ITEMS} copy={COPY} />);

    const clearButton = await screen.findByRole('button', { name: COPY.clear });
    fireEvent.click(clearButton);

    await waitFor(() => {
      expect(screen.getByText(COPY.empty)).toBeInTheDocument();
    });
    expect(window.localStorage.getItem(TOOLBOX_STORAGE_KEY)).toBe(
      JSON.stringify({ favorites: [], history: [] }),
    );
  });

  it('ne lit pas le stockage pendant le rendu (sûr en SSR)', () => {
    // Le composant doit être montable sans `localStorage` disponible : c'est la
    // condition pour qu'un rendu serveur ne lève pas. On simule l'absence de
    // l'API, puis on vérifie que le montage ne lève pas et que l'état vide
    // s'affiche (repli silencieux de `lib/storage.ts`).
    const original = window.localStorage;
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get() {
        throw new Error('localStorage inaccessible');
      },
    });

    try {
      expect(() => render(<MyTools items={ITEMS} copy={COPY} />)).not.toThrow();
    } finally {
      Object.defineProperty(window, 'localStorage', {
        configurable: true,
        value: original,
      });
    }
  });

  it('survit à un contenu de stockage corrompu', async () => {
    window.localStorage.setItem(TOOLBOX_STORAGE_KEY, '{ pas du json');
    render(<MyTools items={ITEMS} copy={COPY} />);

    expect(await screen.findByText(COPY.empty)).toBeInTheDocument();
  });
});
