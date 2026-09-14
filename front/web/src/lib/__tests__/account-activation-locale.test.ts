import { getCopy } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';

/**
 * #7363 — Garde de non-régression : l'écran d'activation de compte a été servi
 * dans la mauvaise langue pour TROIS locales sur quatre.
 *
 * Les quatre blocs `accountActivation` du catalogue (`src/lib/i18n.ts`) étaient
 * décalés d'un cran : l'objet `en` portait le turc, `tr` portait l'arabe et
 * `ar` portait l'anglais. Un utilisateur anglophone — la locale de repli — lisait
 * donc l'écran d'activation en turc. Le défaut est invisible à la lecture d'une
 * seule locale : il faut comparer les quatre.
 *
 * Chaque locale est identifiée par un repère propre à sa langue. Les repères
 * sont volontairement des mots entiers et stables (pas des accents, pas des
 * fragments d'une lettre) pour ne pas casser à la moindre reformulation.
 */
const MARKERS: Record<AppLocale, { title: RegExp; passwordLabel: RegExp }> = {
  fr: { title: /activez votre compte/i, passwordLabel: /mot de passe/i },
  en: { title: /activate your account/i, passwordLabel: /^password$/i },
  tr: { title: /hesab[ıi]n[ıi]z[ıi] etkinle[şs]tirin/i, passwordLabel: /^[şs]ifre$/i },
  // L'arabe se reconnaît à son écriture — aucune ambiguïté avec les 3 autres.
  ar: { title: /[\u0600-\u06FF]/, passwordLabel: /[\u0600-\u06FF]/ },
};

describe('accountActivation — chaque locale est servie dans SA langue (#7363)', () => {
  for (const locale of Object.keys(MARKERS) as AppLocale[]) {
    it(`${locale} : libellés dans la bonne langue`, () => {
      const copy = getCopy(locale).accountActivation;

      expect(copy.title).toMatch(MARKERS[locale].title);
      expect(copy.passwordLabel).toMatch(MARKERS[locale].passwordLabel);
      // Une phrase, pas un mot isolé : un libellé vide ou tronqué passerait
      // inaperçu si l'on ne vérifiait que le titre.
      expect(copy.subtitle.length).toBeGreaterThan(10);
    });
  }

  it('aucune locale ne porte la langue d’une autre (non-latin)', () => {
    // Garde explicite sur les deux langues non latines : un décalage les
    // remet immédiatement dans une locale latine.
    expect(getCopy('ar').accountActivation.title).toMatch(/[\u0600-\u06FF]/);
    expect(getCopy('tr').accountActivation.title).not.toMatch(/[\u0600-\u06FF]/);
    expect(getCopy('en').accountActivation.title).not.toMatch(/[\u0600-\u06FF]/);
    expect(getCopy('en').accountActivation.title).not.toMatch(/[şğıİŞĞ]/);
  });
});
