import { t } from '@/lib/i18n/locale-catalog';

/**
 * Issue #5233 — portail client documents : parité ×4 des clés
 * `accountingPortal.*` (fr/ar/tr/en), aucune chaîne hardcodée (règle #2755).
 */
describe('accountingPortal i18n ×4 (#5233)', () => {
  const LOCALES = ['fr', 'en', 'tr', 'ar'] as const;
  const KEYS = [
    'accountingPortal.title',
    'accountingPortal.subtitle',
    'accountingPortal.number',
    'accountingPortal.type',
    'accountingPortal.status',
    'accountingPortal.issueDate',
    'accountingPortal.total',
    'accountingPortal.expiresAt',
    'accountingPortal.download',
    'accountingPortal.downloadHint',
    'accountingPortal.downloadError',
    'accountingPortal.notFoundTitle',
    'accountingPortal.notFoundBody',
    'accountingPortal.errorTitle',
    'accountingPortal.errorBody',
    'accountingPortal.retry',
    'accountingPortal.backToSite',
    'accountingPortal.securityNote',
    'accountingPortal.statusDraft',
    'accountingPortal.statusSent',
    'accountingPortal.statusPartiallyPaid',
    'accountingPortal.statusPaid',
    'accountingPortal.statusCancelled',
    'accountingPortal.statusOverdue',
    'accountingPortal.loading',
  ] as const;

  it.each(KEYS)('la clé %s est résolue et non vide ×4 locales', (key) => {
    for (const locale of LOCALES) {
      const value = t(locale, key, '');
      expect(value.trim().length).toBeGreaterThan(0);
      expect(value).not.toContain('accountingPortal.');
    }
  });

  it('en/tr/ar ne retombent pas silencieusement sur le texte FR', () => {
    for (const key of KEYS) {
      const fr = t('fr', key, '');
      for (const locale of ['en', 'tr', 'ar'] as const) {
        const value = t(locale, key, '');
        expect(value).not.toBe(fr);
      }
    }
  });

  it('le placeholder :date est présent et cohérent sur expiresAt', () => {
    for (const locale of LOCALES) {
      expect(t(locale, 'accountingPortal.expiresAt', '')).toContain(':date');
    }
  });
});
