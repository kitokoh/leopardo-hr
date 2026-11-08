import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ToolboxCatalog, type CatalogItem } from '../ToolboxCatalog';

/**
 * Tests du catalogue — recherche plein-texte, filtre par catégorie, état vide.
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
};

describe('ToolboxCatalog', () => {
  it('affiche tous les outils et le compteur', () => {
    render(<ToolboxCatalog items={items} categories={categories} copy={copy} />);
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.getByText('TVA — calcul HT / TTC')).toBeInTheDocument();
    expect(screen.getByText(/2 outils disponibles/)).toBeInTheDocument();
  });

  it('filtre par recherche sur les mots-clés (insensible à la casse)', () => {
    render(<ToolboxCatalog items={items} categories={categories} copy={copy} />);
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'POIDS' } });
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.queryByText('TVA — calcul HT / TTC')).not.toBeInTheDocument();
  });

  it('filtre par recherche sur le nom (insensible aux accents)', () => {
    render(<ToolboxCatalog items={items} categories={categories} copy={copy} />);
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'masse' } });
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.queryByText('TVA — calcul HT / TTC')).not.toBeInTheDocument();
  });

  it('filtre par catégorie', () => {
    render(<ToolboxCatalog items={items} categories={categories} copy={copy} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'finance' } });
    expect(screen.getByText('TVA — calcul HT / TTC')).toBeInTheDocument();
    expect(screen.queryByText('IMC — Indice de masse corporelle')).not.toBeInTheDocument();
  });

  it('affiche l’état vide quand rien ne correspond', () => {
    render(<ToolboxCatalog items={items} categories={categories} copy={copy} />);
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzzzz' } });
    expect(screen.getByText('Aucun outil ne correspond à votre recherche.')).toBeInTheDocument();
  });
});
