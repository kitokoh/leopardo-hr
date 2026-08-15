import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { PROTECTED_PREFIXES } from '@/lib/protected-prefixes';
import robots from '@/app/robots';

/**
 * Issue #3377 — la liste des préfixes protégés doit rester une source unique.
 * Next.js exige des littéraux dans `config.matcher` du middleware : ce test
 * est la garde anti-dérive entre les deux fichiers.
 */
describe('protected prefixes (source unique #3377)', () => {
  const middlewareSrc = readFileSync(join(__dirname, '../../middleware.ts'), 'utf8');

  it.each(PROTECTED_PREFIXES)('%s est déclaré dans le matcher middleware', (prefix) => {
    expect(middlewareSrc).toContain(`'${prefix}/:path*'`);
  });

  it('le matcher middleware ne protège rien hors de la source unique', () => {
    const matcherEntries = [...middlewareSrc.matchAll(/'(\/[a-z-]+)\/:path\*'/g)].map((m) => m[1]);
    for (const entry of matcherEntries) {
      expect(PROTECTED_PREFIXES).toContain(entry);
    }
  });

  it('robots.txt interdit chaque préfixe protégé à TOUS les bots (Googlebot/Bingbot inclus)', () => {
    const result = robots();
    const rules = Array.isArray(result.rules) ? result.rules : [result.rules];
    for (const agent of ['*', 'Googlebot', 'Bingbot']) {
      const rule = rules.find((r) => r.userAgent === agent);
      expect(rule).toBeDefined();
      const disallow = Array.isArray(rule?.disallow) ? rule?.disallow : [rule?.disallow];
      for (const prefix of PROTECTED_PREFIXES) {
        expect(disallow).toContain(prefix);
      }
    }
  });
});
