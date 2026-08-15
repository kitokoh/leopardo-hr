import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { PROTECTED_PREFIXES, VITRINE_LANG_PREFIXES } from '@/lib/protected-prefixes';
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

    it('le matcher middleware ne déclare que des préfixes connus (sources uniques #3377/#4004)', () => {
    const known = [...PROTECTED_PREFIXES, ...VITRINE_LANG_PREFIXES];
    const matcherEntries = [...middlewareSrc.matchAll(/'(\/[a-z-]+)\/:path\*'/g)].map((m) => m[1]);
    for (const entry of matcherEntries) {
      expect(known).toContain(entry);
    }
  });

  it.each(VITRINE_LANG_PREFIXES)('%s est déclaré dans le matcher middleware (normalisation ?lang= #4004)', (prefix) => {
    expect(middlewareSrc).toContain(`'${prefix}/:path*'`);
  });

  it.each(PROTECTED_PREFIXES)('sw.js ne met pas en cache le préfixe protégé %s (issue #3729)', (prefix) => {
    const swSrc = readFileSync(join(__dirname, '../../../public/sw.js'), 'utf8');
    expect(swSrc).toContain(prefix);
  });

  it('sw.js déclare exactement les préfixes protégés (source unique #3377/#3729)', () => {
    const swSrc = readFileSync(join(__dirname, '../../../public/sw.js'), 'utf8');
    const block = swSrc.match(/const PROTECTED_PREFIXES = \[([^\]]*)\]/);
    expect(block).not.toBeNull();
    const declared = [...(block?.[1]?.matchAll(/'([^']+)'/g) ?? [])].map((m) => m[1]);
    expect(declared.sort()).toEqual([...PROTECTED_PREFIXES].sort());
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
