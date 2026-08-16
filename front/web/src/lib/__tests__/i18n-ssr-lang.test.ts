/**
 * Unit tests — resolveSsrVitrineLang (issue #4393).
 *
 * La vitrine servait des metadata (title/description) FR en dur quand la
 * locale du visiteur venait d'Accept-Language (sans `?lang=`) : le middleware
 * ne copiait que `?lang=` vers `x-vitrine-lang`, et les layouts landing
 * retombaient sur la metadata FR par défaut. `resolveSsrVitrineLang` est la
 * source unique de la décision : `?lang=` prime (comportement #4173),
 * sinon Accept-Language normalisé (défaut fr, comme le root layout #2657).
 */

import {
  resolveSsrVitrineLang,
  normalizeLocale,
  isSupportedLocale,
} from '@/lib/i18n';

describe('resolveSsrVitrineLang (#4393)', () => {
  it('privilégie ?lang= sur Accept-Language (#4173)', () => {
    expect(resolveSsrVitrineLang('en', 'fr-FR,fr;q=0.9')).toBe('en');
    expect(resolveSsrVitrineLang('ar', 'en-US,en;q=0.9')).toBe('ar');
  });

  it('normalise Accept-Language quand ?lang= est absent', () => {
    expect(resolveSsrVitrineLang(null, 'en-US,en;q=0.9')).toBe('en');
    expect(resolveSsrVitrineLang(undefined, 'tr-TR,tr;q=0.9')).toBe('tr');
    expect(resolveSsrVitrineLang(null, 'ar')).toBe('ar');
  });

  it('retombe sur fr pour un header absent, vide ou non supporté', () => {
    expect(resolveSsrVitrineLang(null, null)).toBe('fr');
    expect(resolveSsrVitrineLang(undefined, undefined)).toBe('fr');
    expect(resolveSsrVitrineLang(null, '')).toBe('fr');
    expect(resolveSsrVitrineLang(null, 'de-DE,de;q=0.8')).toBe('fr');
  });

  it('ignore un ?lang= invalide et utilise Accept-Language', () => {
    expect(resolveSsrVitrineLang('zz', 'en-US,en;q=0.9')).toBe('en');
    expect(resolveSsrVitrineLang('fr', null)).toBe('fr');
  });
});

describe('normalizeLocale / isSupportedLocale (pré-requis #4393)', () => {
  it('extrait les 2 premières lettres, minuscules', () => {
    expect(normalizeLocale('EN-us,en;q=0.9')).toBe('en');
    expect(normalizeLocale('AR-EG')).toBe('ar');
  });

  it('isSupportedLocale accepte fr/en/tr/ar uniquement', () => {
    expect(isSupportedLocale('fr')).toBe(true);
    expect(isSupportedLocale('de')).toBe(false);
  });
});
