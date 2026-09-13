/**
 * #AI-SEO — garde-fous de `robots.txt`.
 *
 * Vérifie que la politique de crawl reste explicite et non contradictoire :
 * - les crawlers IA (moteurs de réponse) sont déclarés dans un groupe dédié ;
 * - ce groupe hérite des mêmes exclusions que le groupe `*` (zones privées) ;
 * - les aspirateurs SEO restent bloqués ;
 * - le sitemap reste déclaré.
 */
import robots from '../robots';
import { SITE_URL } from '@/lib/site-url';
import { PROTECTED_PREFIXES } from '@/lib/protected-prefixes';

type Rule = { userAgent?: string | string[]; allow?: string | string[]; disallow?: string | string[] };

const asArray = (value: string | string[] | undefined): string[] =>
  value === undefined ? [] : Array.isArray(value) ? value : [value];

describe('robots.txt — groupes et exclusions', () => {
  const result = robots();
  const rules = result.rules as Rule[];

  const agentsOf = (rule: Rule) => asArray(rule.userAgent);

  it('autorise le crawl public par défaut', () => {
    const wildcard = rules.find((rule) => agentsOf(rule).includes('*'));
    expect(wildcard).toBeDefined();
    expect(asArray(wildcard?.allow)).toContain('/');
  });

  it('exclut les zones protégées pour tous les bots autorisés à crawler', () => {
    // Les règles « aspirateurs SEO » (MJ12bot/AhrefsBot/SemrushBot) bloquent
    // tout le site (`Disallow: /`) — elles ne portent pas la liste détaillée.
    const allowingRules = rules.filter((rule) => asArray(rule.allow).includes('/'));
    expect(allowingRules.length).toBeGreaterThan(0);

    for (const rule of allowingRules) {
      const disallow = asArray(rule.disallow);
      for (const prefix of PROTECTED_PREFIXES) {
        expect(disallow).toContain(prefix);
      }
      expect(disallow).toContain('/api');
      expect(disallow).toContain('/auth');
    }
  });

  it('#AI-SEO : déclare un groupe dédié aux crawlers IA', () => {
    const aiRule = rules.find((rule) => agentsOf(rule).includes('GPTBot'));
    expect(aiRule).toBeDefined();

    const agents = agentsOf(aiRule as Rule);
    for (const bot of [
      'GPTBot',
      'OAI-SearchBot',
      'ChatGPT-User',
      'ClaudeBot',
      'Claude-User',
      'PerplexityBot',
      'Google-Extended',
      'Applebot-Extended',
    ]) {
      expect(agents).toContain(bot);
    }
  });

  it('#AI-SEO : les crawlers IA ne peuvent pas atteindre les zones privées', () => {
    const aiRule = rules.find((rule) => agentsOf(rule).includes('GPTBot')) as Rule;
    expect(asArray(aiRule.allow)).toContain('/');
    for (const prefix of PROTECTED_PREFIXES) {
      expect(asArray(aiRule.disallow)).toContain(prefix);
    }
  });

  it('conserve le blocage des aspirateurs SEO', () => {
    for (const bot of ['MJ12bot', 'AhrefsBot', 'SemrushBot']) {
      const rule = rules.find((r) => agentsOf(r).includes(bot));
      expect(rule).toBeDefined();
      expect(asArray(rule?.disallow)).toContain('/');
    }
  });

  it('déclare le sitemap canonique', () => {
    expect(result.sitemap).toBe(`${SITE_URL}/sitemap.xml`);
  });
});
