import { getFaqItems } from '../faq';

/**
 * #8076 — la FAQ de la landing lève les objections d'un produit open-source /
 * self-host vendu à des PME non techniques, et affiche l'intention paie
 * locale / mobile money SANS promettre ce qui n'existe pas.
 */
const LOCALES = ['fr', 'en', 'tr', 'ar'] as const;

describe('FAQ landing (#8076)', () => {
  it('parité des 4 locales : même nombre de questions partout', () => {
    const count = getFaqItems('fr').length;
    for (const locale of LOCALES) {
      expect(getFaqItems(locale)).toHaveLength(count);
    }
  });

  it('porte les 6 questions de réassurance exigées, en FR et EN', () => {
    const fr = getFaqItems('fr').map((item) => item.question);
    const en = getFaqItems('en').map((item) => item.question);

    const expectedFr = [
      /équipe IT pour installer/i,
      /Cloud ou mon propre serveur/i,
      /en sécurité \? Où sont-elles hébergées/i,
      /depuis Excel ou Sage/i,
      /si j'arrête/i,
      /coûte vraiment/i,
    ];
    const expectedEn = [
      /IT team to install/i,
      /Cloud or my own server/i,
      /secure\? Where is it hosted/i,
      /from Excel or Sage/i,
      /if I cancel/i,
      /really cost/i,
    ];

    for (const pattern of expectedFr) {
      expect(fr.some((question) => pattern.test(question))).toBe(true);
    }
    for (const pattern of expectedEn) {
      expect(en.some((question) => pattern.test(question))).toBe(true);
    }
  });

  it('honnêteté mobile money : intention affichée, jamais présentée comme disponible', () => {
    for (const locale of LOCALES) {
      const mobileMoney = getFaqItems(locale).find((item) =>
        /mobile money|mobil para|الأموال عبر الجوال/i.test(item.question),
      );
      expect(mobileMoney).toBeDefined();
      // Nomme les services (l'intention est assumée)…
      expect(mobileMoney!.answer).toMatch(/Orange Money/);
      expect(mobileMoney!.answer).toMatch(/Wave/);
      expect(mobileMoney!.answer).toMatch(/MTN MoMo/);
      // …mais toujours avec un marqueur explicite de non-disponibilité.
      expect(mobileMoney!.answer).toMatch(
        /pas encore disponibles|not available yet|henuz mevcut degil|غير متاحة بعد/i,
      );
    }
  });

  it('le catalogue de paie est cité avec son volume réel (21 pays, registre CountryDefaults)', () => {
    for (const locale of LOCALES) {
      const payroll = getFaqItems(locale).find((item) => /21 pays|21 countries|21 ulke|21 دولة/i.test(item.answer));
      expect(payroll).toBeDefined();
    }
  });

  it('aucune réponse vide dans aucune locale', () => {
    for (const locale of LOCALES) {
      for (const item of getFaqItems(locale)) {
        expect(item.question.trim().length).toBeGreaterThan(0);
        expect(item.answer.trim().length).toBeGreaterThan(0);
      }
    }
  });
});
