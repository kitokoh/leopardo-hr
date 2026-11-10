import fs from 'fs';
import path from 'path';
import { PUBLIC_PORTAL_LOCALES } from '../data/copy';
import { getPublicHomeCopy } from '../data/home-copy';

/**
 * Garde de la page d'accueil du portail grand public (#9357).
 *
 * ## Ce que ce test protège
 *
 * 1. **La copie est complète dans les 4 langues.** Une clé manquante en `ar`
 *    ou `tr` ne casse pas le build (le type est satisfait par le repli FR) mais
 *    affiche du français à un visiteur arabophone. On vérifie donc que chaque
 *    locale a du texte réel, et que les locales ne sont pas des copies
 *    littérales les unes des autres.
 * 2. **Les liens critiques existent.** `/business` est le SEUL chemin vers
 *    l'offre professionnelle : s'il disparaît de la home, la vitrine devient
 *    inatteignable depuis la racine.
 * 3. **Aucun `<main>` imbriqué.** Le layout racine en fournit un ; un second
 *    est invalide en HTML et casse la navigation par repères.
 * 4. **Le thème est appliqué avant le paint.** Sans le script inline, un
 *    visiteur en mode sombre voit un flash blanc à chaque navigation.
 */

const moduleRoot = path.resolve(__dirname, '..');
const appRoot = path.resolve(__dirname, '../../../app');

/**
 * Retire les commentaires avant toute assertion structurelle : un `<main>`
 * cité dans un docblock n'est pas un `<main>` rendu, et une route citée en
 * commentaire n'est pas un lien. Sans ce nettoyage, les gardes passeraient au
 * vert sur du code qui ne fait rien — ou échoueraient sur du code correct.
 */
function stripComments(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/.*$/gm, '');
}

const homePage = stripComments(fs.readFileSync(path.join(appRoot, '(public)', 'page.tsx'), 'utf8'));
const homeLayout = stripComments(fs.readFileSync(path.join(appRoot, '(public)', 'layout.tsx'), 'utf8'));
const nav = stripComments(fs.readFileSync(path.join(moduleRoot, 'components', 'PublicPortalNav.tsx'), 'utf8'));
const layout = stripComments(fs.readFileSync(path.join(moduleRoot, 'components', 'PublicPortalLayout.tsx'), 'utf8'));
const themeToggle = fs.readFileSync(path.join(moduleRoot, 'components', 'ThemeToggle.tsx'), 'utf8');

describe('public-portal — page d’accueil grand public (#9357)', () => {
  it('sert la racine depuis le groupe (public), sans segment d’URL', () => {
    expect(fs.existsSync(path.join(appRoot, '(public)', 'page.tsx'))).toBe(true);
    // Un page.tsx à la racine entrerait en collision avec le groupe.
    expect(fs.existsSync(path.join(appRoot, 'page.tsx'))).toBe(false);
  });

  it('n’imbrique pas de <main> (le layout racine en fournit un)', () => {
    expect(homePage).not.toMatch(/<main[\s>]/);
    expect(layout).not.toMatch(/<main[\s>]/);
  });

  it('expose le bouton « Leopardo Business » vers /business, dans l’en-tête ET le hero', () => {
    expect(nav).toContain('href="/business"');
    expect(homePage).toContain('href="/business"');
    // Le CTA business du hero vient de la copie, pas d'une chaîne en dur.
    expect(homePage).toContain('copy.hero.businessCta');
  });

  it('relie le portail de découverte et les 5 annuaires', () => {
    for (const href of ['/decouvrir', '/restaurants', '/boutiques', '/operateurs', '/entreprises', '/stay']) {
      // `href="/x"` (JSX) ou `href: '/x'` (tableau VERTICALS) : les deux formes
      // sont des liens réels, la garde ne doit pas dépendre de la syntaxe.
      expect(homePage).toMatch(new RegExp(`href[=:]\\s*["']${href}["']`));
    }
  });

  it('applique le thème avant le premier paint (script inline dans le layout)', () => {
    expect(layout).toContain('THEME_SCRIPT');
    expect(themeToggle).toContain('prefers-color-scheme: dark');
    expect(themeToggle).toContain('localStorage');
  });

  it('déclare le manifeste de l’app grand public sur la racine', () => {
    expect(homeLayout).toContain('/decouvrir/manifest.webmanifest');
  });

  it('déclare les alternates hreflang des 4 locales', () => {
    // `fr` est la forme canonique (sans paramètre) ; en/tr/ar portent `?lang=`.
    expect(homePage).toContain('canonical: SITE_URL');
    for (const locale of ['en', 'tr', 'ar'] as const) {
      expect(homePage).toContain(`?lang=${locale}`);
    }
    expect(homePage).toContain("'x-default'");
    expect(PUBLIC_PORTAL_LOCALES).toHaveLength(4);
  });

  describe('copie i18n', () => {
    it.each(PUBLIC_PORTAL_LOCALES)('%s fournit une copie complète et non vide', (locale) => {
      const copy = getPublicHomeCopy(locale);
      expect(copy.meta.title.trim().length).toBeGreaterThan(10);
      expect(copy.meta.description.trim().length).toBeGreaterThan(40);
      expect(copy.hero.title.trim().length).toBeGreaterThan(0);
      expect(copy.hero.titleAccent.trim().length).toBeGreaterThan(0);
      expect(copy.hero.subtitle.trim().length).toBeGreaterThan(40);
      expect(copy.hero.searchCta.trim().length).toBeGreaterThan(0);
      expect(copy.hero.businessCta.trim().length).toBeGreaterThan(0);
      expect(copy.trust.items).toHaveLength(3);
      expect(copy.how.steps).toHaveLength(3);
      expect(copy.business.cta.trim().length).toBeGreaterThan(0);
      expect(copy.theme.light.trim().length).toBeGreaterThan(0);
    });

    it('ne sert pas du français aux visiteurs en/tr/ar', () => {
      const fr = getPublicHomeCopy('fr');
      for (const locale of ['en', 'tr', 'ar'] as const) {
        const copy = getPublicHomeCopy(locale);
        expect(copy.hero.subtitle).not.toBe(fr.hero.subtitle);
        expect(copy.meta.description).not.toBe(fr.meta.description);
      }
    });

    it('replie sur le français pour une locale inconnue', () => {
      expect(getPublicHomeCopy('de').hero.title).toBe(getPublicHomeCopy('fr').hero.title);
    });
  });
});
