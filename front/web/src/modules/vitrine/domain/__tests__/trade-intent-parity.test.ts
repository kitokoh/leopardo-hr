import { TRADE_PROFILES, TRADE_PROFILES_BY_MODE } from '../trade-intent';

/**
 * BOS-O-01 / #8625 — garde de PARITÉ entre le web et l'API.
 *
 * L'intention de marché existe en deux exemplaires : l'allowlist du navigateur
 * (`trade-intent.ts`, qui décide ce que le visiteur peut choisir) et celle du
 * serveur (`Company::MARKET_TYPES` / `TRADE_PROFILES_BY_MARKET`, qui décide ce
 * qui peut être persisté). Une divergence produit deux bugs silencieux :
 *
 *  - une valeur acceptée par le web mais refusée par l'API → 422 incompréhensible
 *    en fin de tunnel d'inscription ;
 *  - une valeur acceptée par l'API mais absente du web → un parcours que
 *    personne ne peut activer.
 *
 * Le test lit la SOURCE PHP plutôt qu'une copie : si l'allowlist serveur bouge
 * sans que le web suive, la CI web casse immédiatement.
 */

const COMPANY_MODEL = '../'.repeat(6) + 'api/app/Core/Tenant/Domain/Models/Company.php';

const fs = require('node:fs');
const path = require('node:path');

function readCompanyModel(): string {
  const candidates = [
    path.resolve(process.cwd(), COMPANY_MODEL),
    path.resolve(process.cwd(), '../../api/app/Core/Tenant/Domain/Models/Company.php'),
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) return fs.readFileSync(candidate, 'utf8') as string;
  }

  throw new Error('Company.php introuvable : la garde de parité ne peut pas s’exécuter.');
}

/** Extrait les littéraux d'un tableau PHP `[...]` par nom de constante. */
function phpStringList(source: string, constant: string): string[] {
  const match = new RegExp(`const\\s+${constant}\\s*=\\s*\\[([^\\]]*)\\]`).exec(source);
  if (!match) throw new Error(`Constante PHP ${constant} introuvable.`);

  return Array.from(match[1].matchAll(/'([^']+)'/g)).map((m) => m[1]);
}

describe('market intent — parité web ↔ API', () => {
  const model = readCompanyModel();

  it('exposes the exact same trade profiles as the backend', () => {
    expect([...TRADE_PROFILES].sort()).toEqual(phpStringList(model, 'TRADE_PROFILES').sort());
  });

  it('pairs each market with the same profiles as the backend', () => {
    // Bloc PHP : `self::MARKET_INTERNATIONAL => ['importer', 'exporter', 'both'],`
    const pairs: Array<[string, string[]]> = [];
    const block = /const TRADE_PROFILES_BY_MARKET = \[([\s\S]*?)\n    \];/.exec(model);

    expect(block).not.toBeNull();

    for (const line of (block?.[1] ?? '').split('\n')) {
      const market = /MARKET_(INTERNATIONAL|DOMESTIC)\s*=>\s*\[([^\]]*)\]/.exec(line);
      if (!market) continue;

      pairs.push([
        market[1].toLowerCase(),
        Array.from(market[2].matchAll(/'([^']+)'/g)).map((m) => m[1]),
      ]);
    }

    expect(pairs.length).toBe(2);

    for (const [market, profiles] of pairs) {
      expect([...TRADE_PROFILES_BY_MODE[market as keyof typeof TRADE_PROFILES_BY_MODE]].sort()).toEqual(
        [...profiles].sort(),
      );
    }
  });
});
