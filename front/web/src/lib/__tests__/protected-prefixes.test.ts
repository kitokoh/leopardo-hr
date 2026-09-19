import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

import { PROTECTED_PREFIXES, VITRINE_LANG_PREFIXES } from '@/lib/protected-prefixes';
import robots from '@/app/robots';

/**
 * Issue #3377 — la liste des préfixes protégés doit rester une source unique.
 * Next.js exige des littéraux dans `config.matcher` du proxy (ex-middleware, #7305) : ce test
 * est la garde anti-dérive entre les deux fichiers.
 */
describe('protected prefixes (source unique #3377)', () => {
  const proxySrc = readFileSync(join(__dirname, '../../proxy.ts'), 'utf8');

  it.each(PROTECTED_PREFIXES)('%s est déclaré dans le matcher du proxy', (prefix) => {
    expect(proxySrc).toContain(`'${prefix}/:path*'`);
  });

  /**
   * #7663 — le gate de session du proxy repose sur la liste littérale
   * DASHBOARD_PREFIXES (startsWith), distincte du matcher : /crm, /accounting,
   * /edu-manager et /fuel étaient absents des DEUX. Chaque préfixe protégé
   * doit être couvert par un préfixe du gate, pas seulement par le matcher.
   */
  it.each(PROTECTED_PREFIXES)('%s est couvert par DASHBOARD_PREFIXES (gate session #7663)', (prefix) => {
    const block = proxySrc.match(/const DASHBOARD_PREFIXES = \[([^\]]*)\]/);
    expect(block).not.toBeNull();
    const declared = [...(block?.[1]?.matchAll(/^\s*'([^']+)',\s*$/gm) ?? [])].map((m) => m[1]);
    const covered = declared.some((p) => prefix === p || prefix.startsWith(`${p}/`));
    expect(covered).toBe(true);
  });

    it('le matcher du proxy ne déclare que des préfixes connus (sources uniques #3377/#4004)', () => {
    const known = [...PROTECTED_PREFIXES, ...VITRINE_LANG_PREFIXES];
    const matcherEntries = [...proxySrc.matchAll(/'(\/[a-z-]+)\/:path\*'/g)].map((m) => m[1]);
    for (const entry of matcherEntries) {
      expect(known).toContain(entry);
    }
  });

  it.each(VITRINE_LANG_PREFIXES)('%s est déclaré dans le matcher du proxy (normalisation ?lang= #4004)', (prefix) => {
    expect(proxySrc).toContain(`'${prefix}/:path*'`);
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

  /**
   * #7663 — anti-oubli : les zones applicatives /crm, /accounting,
   * /edu-manager et /fuel ont été servies pendant des mois à des visiteurs
   * anonymes (200 + HTML applicatif, crawlable) parce que leurs répertoires
   * `(dashboard)` n'ont jamais été reportés dans PROTECTED_PREFIXES. Ce test
   * force la décision : tout nouveau répertoire de la zone dashboard doit
   * être protégé OU allowlisté ici avec une justification écrite.
   */
  it('chaque route de src/app/(dashboard) correspond à un préfixe protégé (anti-oubli #7663)', () => {
    // Exceptions assumées — justifier chaque entrée :
    // - /travel : partage son préfixe avec la route publique /travel/contact
    //   (vitrine agence de voyages) ; le gate exige un split de routes
    //   (précédent /restaurant → /restaurateur), suivi hors #7663.
    const allowlisted = ['/travel'];
    const entries = readdirSync(join(__dirname, '../../app/(dashboard)'), {
      withFileTypes: true,
    });
    const routes = entries
      .filter((e) => e.isDirectory() && !e.name.startsWith('(') && !e.name.startsWith('__'))
      .map((e) => `/${e.name}`);
    for (const route of routes) {
      const covered =
        allowlisted.includes(route) ||
        PROTECTED_PREFIXES.some((p) => route === p || route.startsWith(`${p}/`));
      if (!covered) {
        throw new Error(
          `${route} (src/app/(dashboard)) n'est ni dans PROTECTED_PREFIXES ni allowlisté — décider et documenter (#7663)`,
        );
      }
    }
  });
});
