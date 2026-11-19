/**
 * Couloir 16 — M1 (#9372) : tests des trois blocs ajoutés à l'accueil.
 *
 * Ces tests vérifient la RÈGLE DE VÉRITÉ, pas le rendu pixel : une section
 * dont la donnée est absente doit être ABSENTE, jamais un bloc vide ni une
 * donnée inventée.
 */

import { buildResumeHref, describeResumeQuery } from '../components/PublicResumeSearch';
import { getPublicHomeCopy } from '../data/home-copy';

describe('M1 — « Reprendre où vous étiez » (point F)', () => {
  it('construit l’URL de reprise depuis la requête en cache', () => {
    expect(buildResumeHref({ q: 'boulangerie', city: 'Lyon', type: 'shops', page: 1 })).toBe(
      '/decouvrir?q=boulangerie&city=Lyon&type=shops',
    );
  });

  it('n’ajoute pas `page=1` (URL canonique)', () => {
    expect(buildResumeHref({ q: '', city: '', type: '', page: 1 })).toBe('/decouvrir');
  });

  it('conserve la pagination au-delà de la première page', () => {
    expect(buildResumeHref({ q: 'pizza', city: '', type: '', page: 3 })).toBe(
      '/decouvrir?q=pizza&page=3',
    );
  });

  it('ne reprend JAMAIS de position : `near` n’existe pas dans la requête cachée', () => {
    const href = buildResumeHref({ q: 'café', city: 'Paris', type: '', page: 1 });
    expect(href).not.toContain('near');
  });

  it('résume la requête pour le libellé accessible', () => {
    expect(describeResumeQuery({ q: 'fleuriste', city: 'Lyon', type: '', page: 1 })).toBe(
      'fleuriste · Lyon',
    );
  });

  it('rend un résumé vide quand la requête est vide (le composant retombe sur le sous-titre)', () => {
    expect(describeResumeQuery({ q: '', city: '', type: '', page: 1 })).toBe('');
  });
});

describe('M1 — copie des nouveaux blocs, 4 langues', () => {
  const locales = ['fr', 'en', 'tr', 'ar'] as const;

  it.each(locales)('fournit resume/nearby/tabs en %s', (locale) => {
    const copy = getPublicHomeCopy(locale);

    expect(copy.resume.title.length).toBeGreaterThan(0);
    expect(copy.resume.cta.length).toBeGreaterThan(0);
    expect(copy.nearby.title.length).toBeGreaterThan(0);
    expect(copy.nearby.cta.length).toBeGreaterThan(0);
    expect(copy.tabs.home.length).toBeGreaterThan(0);
    expect(copy.tabs.messages.length).toBeGreaterThan(0);
    expect(copy.tabs.activities.length).toBeGreaterThan(0);
    expect(copy.tabs.profile.length).toBeGreaterThan(0);
  });

  it('ne sert pas le français aux visiteurs en/tr/ar', () => {
    const fr = getPublicHomeCopy('fr');
    for (const locale of ['en', 'tr', 'ar'] as const) {
      const copy = getPublicHomeCopy(locale);
      expect(copy.tabs.activities).not.toBe(fr.tabs.activities);
      expect(copy.nearby.title).not.toBe(fr.nearby.title);
    }
  });

  it('écrit réellement en arabe pour la locale ar (RTL)', () => {
    const ar = getPublicHomeCopy('ar');
    // Plage arabe de base : au moins un caractère hors ASCII latin.
    expect(/[\u0600-\u06FF]/.test(ar.tabs.activities)).toBe(true);
    expect(/[\u0600-\u06FF]/.test(ar.nearby.title)).toBe(true);
  });
});
