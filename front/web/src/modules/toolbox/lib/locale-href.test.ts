import { withLang, localeSwitchHref, isToolboxLocale, DEFAULT_TOOLBOX_LOCALE } from './locale-href';

/**
 * Tests de la propagation de locale dans les liens de la Toolbox.
 *
 * Cas connus calculés à la main :
 * - `fr` est la locale par défaut → URL canonique, aucun `?lang=fr` (convention
 *   `sitemap.ts` / `alternates.hreflang`).
 * - `en`/`tr`/`ar` → `?lang=<code>`.
 * - La bascule conserve les autres paramètres (filtres, `s=` d'un calcul
 *   partagé) et ne remplace que `lang`.
 */
describe('withLang', () => {
  it('laisse le lien intact pour le français (URL canonique)', () => {
    expect(withLang('/toolbox/imc', 'fr')).toBe('/toolbox/imc');
  });

  it('ajoute ?lang= pour les autres locales', () => {
    expect(withLang('/toolbox/imc', 'en')).toBe('/toolbox/imc?lang=en');
    expect(withLang('/toolbox/imc', 'tr')).toBe('/toolbox/imc?lang=tr');
    expect(withLang('/toolbox/imc', 'ar')).toBe('/toolbox/imc?lang=ar');
  });

  it('conserve une requête existante et y ajoute lang', () => {
    expect(withLang('/toolbox?category=finance', 'tr')).toBe(
      '/toolbox?category=finance&lang=tr',
    );
  });

  it('remplace un lang déjà présent au lieu de le dupliquer', () => {
    expect(withLang('/toolbox/imc?lang=en', 'ar')).toBe('/toolbox/imc?lang=ar');
  });

  it('ne réécrit pas les liens absolus ni les ancres', () => {
    expect(withLang('https://www.impots.gouv.fr/x', 'tr')).toBe(
      'https://www.impots.gouv.fr/x',
    );
    expect(withLang('//cdn.example.com/x', 'tr')).toBe('//cdn.example.com/x');
    expect(withLang('#resultat', 'tr')).toBe('#resultat');
  });

  it('ignore une locale non supportée (défense en profondeur)', () => {
    expect(withLang('/toolbox/imc', 'de' as never)).toBe('/toolbox/imc');
  });
});

describe('localeSwitchHref', () => {
  it('pose lang sur une URL sans requête', () => {
    expect(localeSwitchHref('/toolbox', '', 'en')).toBe('/toolbox?lang=en');
  });

  it('supprime lang quand on revient au français', () => {
    expect(localeSwitchHref('/toolbox', 'lang=tr', 'fr')).toBe('/toolbox');
  });

  it('conserve les filtres du catalogue', () => {
    expect(localeSwitchHref('/toolbox', 'category=finance&q=tva', 'ar')).toBe(
      '/toolbox?category=finance&q=tva&lang=ar',
    );
  });

  it('conserve le paramètre de partage s= d’un calcul', () => {
    // Un lien partagé porte les ENTRÉES du calcul : changer de langue ne doit
    // pas effacer le calcul en cours.
    expect(localeSwitchHref('/toolbox/imc', 's=abc123', 'tr')).toBe(
      '/toolbox/imc?s=abc123&lang=tr',
    );
  });

  it('accepte une requête déjà préfixée par ?', () => {
    expect(localeSwitchHref('/toolbox', '?lang=en', 'tr')).toBe('/toolbox?lang=tr');
  });

  it('retourne le chemin nu quand tout est retiré', () => {
    expect(localeSwitchHref('/toolbox/imc', 'lang=en', 'fr')).toBe('/toolbox/imc');
  });
});

describe('isToolboxLocale', () => {
  it('accepte les 4 locales du module', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar']) {
      expect(isToolboxLocale(locale)).toBe(true);
    }
  });

  it('refuse tout le reste', () => {
    for (const value of ['de', 'FR', '', null, undefined, 42, {}]) {
      expect(isToolboxLocale(value)).toBe(false);
    }
  });
});

describe('DEFAULT_TOOLBOX_LOCALE', () => {
  it('est le français (valeur qui ne s’écrit pas dans l’URL)', () => {
    expect(DEFAULT_TOOLBOX_LOCALE).toBe('fr');
  });
});
