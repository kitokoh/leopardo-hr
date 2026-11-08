import {
  getResource,
  getAllResourceSlugs,
  getPublishedResources,
  getResourcesByCategory,
  getFeaturedResources,
  getNewResources,
  getFreeResources,
  getRelatedResources,
  getAvailableLanguages,
  searchResources,
  isPubliclyVisible,
} from '../lib/registry';
import { LIBRARY_RESOURCES } from '../data/catalog';

/**
 * Tests du registre — visibilité, résolution, recherche et filtres.
 */
describe('registry — visibilité', () => {
  it('n’expose que les ressources publiées', () => {
    const published = getPublishedResources();
    expect(published.length).toBeGreaterThan(0);
    expect(published.every((resource) => resource.status === 'published')).toBe(true);
  });

  it('exclut les brouillons du catalogue public', () => {
    const draft = LIBRARY_RESOURCES.find((resource) => resource.status === 'draft');
    expect(draft).toBeDefined();
    expect(getResource(draft!.slug)).toBeNull();
    expect(getAllResourceSlugs()).not.toContain(draft!.slug);
  });

  it('isPubliclyVisible reflète le statut', () => {
    const draft = LIBRARY_RESOURCES.find((resource) => resource.status === 'draft')!;
    const published = getPublishedResources()[0];
    expect(isPubliclyVisible(draft)).toBe(false);
    expect(isPubliclyVisible(published)).toBe(true);
  });
});

describe('registry — résolution', () => {
  it('retourne une ressource publiée par slug', () => {
    const resource = getResource('pride-and-prejudice');
    expect(resource).not.toBeNull();
    expect(resource!.author).toBe('Jane Austen');
  });

  it('retourne null pour un slug inconnu', () => {
    expect(getResource('slug-inexistant')).toBeNull();
  });

  it('liste les ressources d’une catégorie', () => {
    const literature = getResourcesByCategory('litterature');
    expect(literature.length).toBeGreaterThan(0);
    expect(literature.every((resource) => resource.category === 'litterature')).toBe(true);
  });

  it('retourne des ressources similaires de la même catégorie', () => {
    const related = getRelatedResources('pride-and-prejudice');
    expect(related.every((resource) => resource.category === 'litterature')).toBe(true);
    expect(related.map((resource) => resource.slug)).not.toContain('pride-and-prejudice');
  });

  it('liste les langues disponibles', () => {
    const languages = getAvailableLanguages();
    expect(languages).toContain('en');
    expect(languages).toContain('fr');
  });
});

describe('registry — sélections', () => {
  it('retourne des mises en avant', () => {
    expect(getFeaturedResources().length).toBeGreaterThan(0);
  });

  it('retourne des nouveautés', () => {
    expect(getNewResources().length).toBeGreaterThan(0);
  });

  it('retourne des ressources gratuites (free ou read_only)', () => {
    const free = getFreeResources();
    expect(free.length).toBeGreaterThan(0);
    expect(
      free.every((resource) => resource.accessModel === 'free' || resource.accessModel === 'read_only'),
    ).toBe(true);
  });
});

describe('registry — recherche et filtres', () => {
  it('recherche par auteur', () => {
    const results = searchResources({ query: 'austen' });
    expect(results.map((resource) => resource.slug)).toContain('pride-and-prejudice');
  });

  it('recherche insensible à la casse et aux accents', () => {
    const results = searchResources({ query: 'LITTÉRATURE' });
    // « litterature » est une catégorie, pas un mot-clé : la recherche porte sur
    // les mots-clés ; on vérifie plutôt un mot-clé accentué.
    const accented = searchResources({ query: 'STRATÉGIE' });
    expect(accented.map((resource) => resource.slug)).toContain('the-art-of-war');
    expect(Array.isArray(results)).toBe(true);
  });

  it('filtre par catégorie', () => {
    const results = searchResources({ category: 'litterature' });
    expect(results.every((resource) => resource.category === 'litterature')).toBe(true);
  });

  it('filtre par langue', () => {
    const results = searchResources({ language: 'fr' });
    expect(results.every((resource) => resource.language === 'fr')).toBe(true);
  });

  it('filtre par modèle d’accès', () => {
    const results = searchResources({ accessModel: 'purchase' });
    expect(results.every((resource) => resource.accessModel === 'purchase')).toBe(true);
  });

  it('filtre par type de document', () => {
    const results = searchResources({ documentType: 'guide' });
    expect(results.every((resource) => resource.documentType === 'guide')).toBe(true);
  });

  it('filtre « gratuit uniquement »', () => {
    const results = searchResources({ freeOnly: true });
    expect(
      results.every((resource) => resource.accessModel === 'free' || resource.accessModel === 'read_only'),
    ).toBe(true);
  });

  it('combine recherche et filtres', () => {
    const results = searchResources({ query: 'guide', category: 'guides-professionnels' });
    expect(results.every((resource) => resource.category === 'guides-professionnels')).toBe(true);
  });

  it('retourne tout le catalogue publié sans filtre', () => {
    expect(searchResources({}).length).toBe(getPublishedResources().length);
  });

  it('retourne un tableau vide quand rien ne correspond', () => {
    expect(searchResources({ query: 'zzzzzzzz' })).toEqual([]);
  });
});
