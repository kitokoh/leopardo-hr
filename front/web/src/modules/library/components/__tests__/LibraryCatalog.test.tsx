import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { LibraryCatalog, type CatalogItem } from '../LibraryCatalog';

/**
 * Tests du catalogue de la bibliothèque — recherche, filtres, état vide.
 */
const items: CatalogItem[] = [
  {
    slug: 'pride-and-prejudice',
    title: 'pride-and-prejudice',
    author: 'Jane Austen',
    category: 'litterature',
    categoryLabel: 'Littérature',
    language: 'en',
    accessModel: 'free',
    accessLabel: 'Gratuit',
    accessTone: 'free',
    documentType: 'book',
    keywords: ['roman', 'austen'],
  },
  {
    slug: 'introduction-au-machine-learning',
    title: 'introduction-au-machine-learning',
    author: 'Leopardo Library',
    category: 'informatique-ia',
    categoryLabel: 'Informatique et IA',
    language: 'fr',
    accessModel: 'purchase',
    accessLabel: 'Achat à l’unité',
    accessTone: 'paid',
    documentType: 'guide',
    keywords: ['ia', 'machine-learning'],
  },
];

const categories = [
  { id: 'litterature', label: 'Littérature' },
  { id: 'informatique-ia', label: 'Informatique et IA' },
];
const languages = [
  { id: 'en', label: 'EN' },
  { id: 'fr', label: 'FR' },
];
const accessOptions = [
  { id: 'free', label: 'Gratuit' },
  { id: 'purchase', label: 'Achat à l’unité' },
];
const typeOptions = [
  { id: 'book', label: 'Livre' },
  { id: 'guide', label: 'Guide' },
];

const copy = {
  searchPlaceholder: 'Rechercher…',
  noResults: 'Aucune ressource ne correspond à votre recherche.',
  allCategories: 'Toutes les catégories',
  allLanguages: 'Toutes les langues',
  allAccess: 'Tous les accès',
  allTypes: 'Tous les types',
  freeOnly: 'Gratuit uniquement',
  openLabel: 'Lire en ligne',
  resourcesCount: 'ressources disponibles',
};

function renderCatalog() {
  return render(
    <LibraryCatalog
      items={items}
      categories={categories}
      languages={languages}
      accessOptions={accessOptions}
      typeOptions={typeOptions}
      copy={copy}
    />,
  );
}

describe('LibraryCatalog', () => {
  it('affiche toutes les ressources et le compteur', () => {
    renderCatalog();
    expect(screen.getByText('pride-and-prejudice')).toBeInTheDocument();
    expect(screen.getByText('introduction-au-machine-learning')).toBeInTheDocument();
    expect(screen.getByText(/2 ressources disponibles/)).toBeInTheDocument();
  });

  it('filtre par recherche sur l’auteur', () => {
    renderCatalog();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'austen' } });
    expect(screen.getByText('pride-and-prejudice')).toBeInTheDocument();
    expect(screen.queryByText('introduction-au-machine-learning')).not.toBeInTheDocument();
  });

  it('filtre par catégorie', () => {
    renderCatalog();
    fireEvent.change(screen.getByLabelText('Toutes les catégories'), { target: { value: 'informatique-ia' } });
    expect(screen.getByText('introduction-au-machine-learning')).toBeInTheDocument();
    expect(screen.queryByText('pride-and-prejudice')).not.toBeInTheDocument();
  });

  it('filtre par langue', () => {
    renderCatalog();
    fireEvent.change(screen.getByLabelText('Toutes les langues'), { target: { value: 'fr' } });
    expect(screen.getByText('introduction-au-machine-learning')).toBeInTheDocument();
    expect(screen.queryByText('pride-and-prejudice')).not.toBeInTheDocument();
  });

  it('filtre « gratuit uniquement »', () => {
    renderCatalog();
    fireEvent.click(screen.getByLabelText('Gratuit uniquement'));
    expect(screen.getByText('pride-and-prejudice')).toBeInTheDocument();
    expect(screen.queryByText('introduction-au-machine-learning')).not.toBeInTheDocument();
  });

  it('affiche l’état vide quand rien ne correspond', () => {
    renderCatalog();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzzzz' } });
    expect(screen.getByText('Aucune ressource ne correspond à votre recherche.')).toBeInTheDocument();
  });
});
