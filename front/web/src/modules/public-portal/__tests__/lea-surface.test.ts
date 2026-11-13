/**
 * COULOIR 16 — M3-bis · Tests de la surface « Léa » séparée.
 *
 * ## Ce que ces tests protègent
 *
 * 1. **Léa est un groupe distinct** des conversations de commerçants. La spec
 *    impose « Messages (Léa séparée) » : une liste mélangée ferait croire au
 *    client qu'il écrit à un commerçant alors qu'il parle à une IA.
 * 2. **L'écran dit la vérité.** Il n'existe aucun point d'entrée public pour
 *    l'assistant (la passerelle IA exige un tenant). L'écran l'explique au lieu
 *    d'afficher un champ de saisie qui échouerait.
 * 3. **Les 4 locales existent**, arabe compris.
 */

import { getPublicLeaCopy } from '../data/lea-copy';

const LOCALES = ['fr', 'en', 'tr', 'ar'] as const;

describe('M3-bis — copie Léa, 4 langues', () => {
  it.each(LOCALES)('fournit titre, sous-titre et état indisponible en %s', (locale) => {
    const copy = getPublicLeaCopy(locale);

    expect(copy.title).toBeTruthy();
    expect(copy.subtitle).toBeTruthy();
    expect(copy.unavailableTitle).toBeTruthy();
    expect(copy.unavailableBody).toBeTruthy();
    expect(copy.merchantsTitle).toBeTruthy();
  });

  it.each(LOCALES)('distingue le groupe Léa du groupe commerçants en %s', (locale) => {
    const copy = getPublicLeaCopy(locale);

    // Deux libellés accessibles DIFFÉRENTS : un lecteur d'écran doit pouvoir
    // annoncer « Assistante Léa » puis « Conversations avec les professionnels »
    // comme deux régions distinctes.
    expect(copy.leaGroupLabel).not.toBe(copy.merchantsGroupLabel);
  });

  it('replie sur le français pour une locale inconnue', () => {
    expect(getPublicLeaCopy('de').title).toBe(getPublicLeaCopy('fr').title);
  });

  it('sert réellement l’arabe', () => {
    const ar = getPublicLeaCopy('ar');

    expect(ar.title).toMatch(/[\u0600-\u06FF]/);
    expect(ar.unavailableBody).toMatch(/[\u0600-\u06FF]/);
  });
});

describe('M3-bis — l’écran ne promet pas un assistant inexistant', () => {
  it.each(LOCALES)('explique l’indisponibilité au lieu de la masquer en %s', (locale) => {
    const copy = getPublicLeaCopy(locale);

    // Le corps doit être assez long pour être une explication, pas un
    // « bientôt » laconique qui laisserait le client attendre indéfiniment.
    expect(copy.unavailableBody.length).toBeGreaterThan(60);
  });

  it('ne prétend pas que Léa répond (aucune formulation de disponibilité)', () => {
    const copy = getPublicLeaCopy('fr');

    // Le titre d'état doit dire l'indisponibilité, pas la disponibilité.
    expect(copy.unavailableTitle.toLowerCase()).toContain('pas encore');
  });
});
