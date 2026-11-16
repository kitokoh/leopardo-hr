import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { ToolboxCatalog, type CatalogItem } from '../ToolboxCatalog';

/**
 * Tests du catalogue — recherche plein-texte, filtre par catégorie, état vide,
 * état persisté dans l'URL et annonce du compteur aux lecteurs d'écran.
 */
const items: CatalogItem[] = [
  {
    slug: 'imc',
    name: 'IMC — Indice de masse corporelle',
    description: 'Calculez votre indice de masse corporelle.',
    category: 'sante',
    categoryLabel: 'Santé',
    keywords: ['imc', 'bmi', 'poids'],
  },
  {
    slug: 'tva',
    name: 'TVA — calcul HT / TTC',
    description: 'Calculez la TVA à partir d’un taux.',
    category: 'finance',
    categoryLabel: 'Finance',
    keywords: ['tva', 'vat', 'taxe'],
  },
];

const categories = [
  { id: 'sante', label: 'Santé' },
  { id: 'finance', label: 'Finance' },
];

const copy = {
  searchPlaceholder: 'Rechercher un outil…',
  noResults: 'Aucun outil ne correspond à votre recherche.',
  allCategories: 'Toutes les catégories',
  openTool: 'Ouvrir l’outil',
  toolsCount: 'outils disponibles',
  clearFilters: 'Effacer les filtres',
};

/** Remet l'URL à `/toolbox` entre deux tests (jsdom partage `location`). */
function resetUrl(search = '') {
  window.history.replaceState(null, '', `/toolbox${search}`);
}

describe('ToolboxCatalog', () => {
  beforeEach(() => resetUrl());

  it('affiche tous les outils et le compteur', () => {
    render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.getByText('TVA — calcul HT / TTC')).toBeInTheDocument();
    expect(screen.getByText(/2 outils disponibles/)).toBeInTheDocument();
  });

  it('filtre par recherche sur les mots-clés (insensible à la casse)', () => {
    render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'POIDS' } });
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.queryByText('TVA — calcul HT / TTC')).not.toBeInTheDocument();
  });

  it('filtre par recherche sur le nom (insensible aux accents)', () => {
    render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'masse' } });
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.queryByText('TVA — calcul HT / TTC')).not.toBeInTheDocument();
  });

  it('filtre par catégorie', () => {
    render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'finance' } });
    expect(screen.getByText('TVA — calcul HT / TTC')).toBeInTheDocument();
    expect(screen.queryByText('IMC — Indice de masse corporelle')).not.toBeInTheDocument();
  });

  it('affiche l’état vide quand rien ne correspond', () => {
    render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzzzz' } });
    expect(screen.getByText('Aucun outil ne correspond à votre recherche.')).toBeInTheDocument();
  });

  describe('état dans l’URL', () => {
    it('n’écrit rien dans l’URL tant que l’utilisateur n’a pas agi', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      expect(window.location.search).toBe('');
    });

    it('écrit la recherche dans l’URL après une saisie', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'tva' } });
      expect(new URLSearchParams(window.location.search).get('q')).toBe('tva');
    });

    it('écrit la catégorie dans l’URL après sélection', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'finance' } });
      expect(new URLSearchParams(window.location.search).get('cat')).toBe('finance');
    });

    it('n’écrit jamais cat=all (URL canonique)', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'finance' } });
      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'all' } });
      expect(new URLSearchParams(window.location.search).get('cat')).toBeNull();
    });

    it('conserve les paramètres étrangers (lang) en écrivant les filtres', () => {
      resetUrl('?lang=tr');
      render(<ToolboxCatalog items={items} categories={categories} locale="tr" copy={copy} />);
      fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'tva' } });
      const params = new URLSearchParams(window.location.search);
      expect(params.get('lang')).toBe('tr');
      expect(params.get('q')).toBe('tva');
    });

    it('restaure les filtres depuis l’URL au montage (lien partagé)', () => {
      resetUrl('?q=tva&cat=finance');
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      expect(screen.getByRole('searchbox')).toHaveValue('tva');
      expect(screen.getByRole('combobox')).toHaveValue('finance');
      expect(screen.getByText('TVA — calcul HT / TTC')).toBeInTheDocument();
      expect(screen.queryByText('IMC — Indice de masse corporelle')).not.toBeInTheDocument();
    });

    it('ignore une catégorie inconnue présente dans l’URL', () => {
      resetUrl('?cat=inexistante');
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      expect(screen.getByRole('combobox')).toHaveValue('all');
      expect(screen.getByText(/2 outils disponibles/)).toBeInTheDocument();
    });

    it('restaure le filtre au retour arrière (popstate)', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'tva' } });
      expect(screen.queryByText('IMC — Indice de masse corporelle')).not.toBeInTheDocument();

      act(() => {
        window.history.replaceState(null, '', '/toolbox');
        window.dispatchEvent(new PopStateEvent('popstate'));
      });

      expect(screen.getByRole('searchbox')).toHaveValue('');
      expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    });
  });

  describe('bouton « effacer les filtres »', () => {
    it('n’apparaît pas sur un catalogue complet', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      expect(screen.queryByRole('button', { name: /effacer les filtres/i })).not.toBeInTheDocument();
    });

    it('apparaît dès qu’un filtre est actif et remet tout à zéro', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'tva' } });
      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'finance' } });

      fireEvent.click(screen.getByRole('button', { name: /effacer les filtres/i }));

      expect(screen.getByRole('searchbox')).toHaveValue('');
      expect(screen.getByRole('combobox')).toHaveValue('all');
      expect(screen.getByText(/2 outils disponibles/)).toBeInTheDocument();
      expect(window.location.search).toBe('');
    });
  });

  describe('accessibilité du compteur', () => {
    it('place le compteur dans une région aria-live polie', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      const counter = screen.getByText(/2 outils disponibles/);
      expect(counter).toHaveAttribute('aria-live', 'polite');
      expect(counter).toHaveAttribute('aria-atomic', 'true');
    });

    it('met le compteur à jour quand la liste se vide', () => {
      render(<ToolboxCatalog items={items} categories={categories} locale="fr" copy={copy} />);
      fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzzzz' } });
      expect(screen.getByText(/0 outils disponibles/)).toHaveAttribute('aria-live', 'polite');
    });
  });
});
