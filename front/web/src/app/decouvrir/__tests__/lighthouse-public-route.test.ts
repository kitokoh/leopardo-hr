import { readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Couloir 16 — la surface publique doit rester auditée par Lighthouse.
 *
 * ## Ce que ce test protège
 *
 * `docs/design/PUBLIC_APP_BUDGETS.md` (§3, §4) désigne **`/decouvrir`** comme la
 * route PWA de la surface publique et **Lighthouse mobile** comme sa méthode de
 * mesure. Or `front/web/lighthouserc.json` auditait neuf URLs — toutes des pages
 * du produit RH (`/employes`, `/comptabilite`, `/pricing`…) — et **aucune route
 * publique**. Le budget de la surface publique était donc écrit mais **jamais
 * mesuré** : un budget non mesuré ne ferme rien.
 *
 * Ce test verrouille la présence de `/decouvrir` dans la liste auditée. Il ne
 * vérifie **pas** de seuil chiffré : les valeurs de `PUBLIC_APP_BUDGETS.md` sont
 * des propositions non calibrées (§7), et un test qui les figerait fermerait des
 * lots sur des chiffres inventés. Il vérifie seulement que la route est
 * **mesurée**, ce qui est la condition pour pouvoir la calibrer un jour.
 *
 * ## Pourquoi un test et pas seulement la modification
 *
 * La liste d'URLs est un tableau JSON sans garde : la retirer par mégarde lors
 * d'un refactor de `lighthouserc.json` ne casserait rien de visible. Le test
 * transforme cette omission silencieuse en échec explicite.
 */

const LIGHTHOUSERC = join(__dirname, '..', '..', '..', '..', 'lighthouserc.json');

interface LighthouseConfig {
  ci: { collect: { url: string[] } };
}

function loadConfig(): LighthouseConfig {
  return JSON.parse(readFileSync(LIGHTHOUSERC, 'utf8')) as LighthouseConfig;
}

describe('Lighthouse — la surface publique est auditée', () => {
  it('audite la route publique /decouvrir', () => {
    const urls = loadConfig().ci.collect.url;
    const publicRoute = urls.find((u) => u.endsWith('/decouvrir'));

    expect(publicRoute).toBeDefined();
  });

  it('audite toujours les pages du produit RH (non-régression)', () => {
    const urls = loadConfig().ci.collect.url;

    // La correction la plus tentante aurait été de remplacer une URL RH par
    // `/decouvrir`. Le budget public s'ajoute, il ne se substitue pas.
    expect(urls.some((u) => u.endsWith('/employes'))).toBe(true);
    expect(urls.some((u) => u.endsWith('/pricing'))).toBe(true);
  });

  it('ne contient aucune URL dupliquée', () => {
    const urls = loadConfig().ci.collect.url;

    expect(new Set(urls).size).toBe(urls.length);
  });
});
