import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

import { surfaceRegistry } from '../registry';

/**
 * Garde : chaque `discoveryType` d'une vitrine doit correspondre à un provider
 * de découverte RÉELLEMENT enregistré côté API (#9592).
 *
 * ## Le trou que cette garde ferme
 *
 * `discoveryType` est décrit dans le noyau comme « le type canonique du contrat
 * public de découverte : c'est lui qui relie la vitrine aux données réelles de
 * la verticale ». Rien ne le vérifiait. Une verticale pouvait donc déclarer
 * `discoveryType: 'travel'` alors qu'aucun `PublicDiscoverableProvider` ne sert
 * ce type : la vitrine aurait compilé, ses tests seraient passés, et elle
 * n'aurait jamais rien affiché en production.
 *
 * C'est exactement le mode de défaillance que la garde de parité de déploiement
 * (#9591) ferme pour les routes. Ici, c'est la même idée appliquée au contrat
 * de données.
 *
 * ## Pourquoi lire le code PHP plutôt qu'une liste en dur
 *
 * Une liste en dur dériverait au premier provider ajouté. On lit les
 * `return '<type>'` des `*DiscoverableProvider.php` : c'est la source de
 * vérité du backend, et elle est stable (le contrat impose cette forme).
 *
 * ## Exception documentée
 *
 * Une verticale peut être servie par un contrat DÉDIÉ plutôt que par la
 * découverte générique — c'est le cas de Billets, dont la recherche de trajets
 * passe par `GET /public/travel/marketplace/trips`. Ces verticales sont
 * déclarées explicitement ci-dessous, avec la raison. Une exception non
 * déclarée fait échouer le test.
 */

/** Verticales servies par un contrat dédié, hors découverte générique. */
const DEDICATED_CONTRACT: Record<string, string> = {
  travel:
    'Recherche de trajets via GET /public/travel/marketplace/trips — un trajet ' +
    "n'est pas une entité découvrable (pas de slug, pas de fiche) : c'est une " +
    'offre datée. La découverte générique ne peut pas la représenter.',
};

const API_MODULES = join(__dirname, '../../../../../../api/app/Modules');

/** Types servis par les providers de découverte du backend. */
function backendDiscoveryTypes(): Set<string> {
  const types = new Set<string>();

  const walk = (dir: string): void => {
    let entries: string[];
    try {
      entries = readdirSync(dir);
    } catch {
      return;
    }

    for (const entry of entries) {
      const full = join(dir, entry);
      if (statSync(full).isDirectory()) {
        walk(full);
        continue;
      }

      if (!entry.endsWith('DiscoverableProvider.php')) continue;

      const source = readFileSync(full, 'utf8');
      // `public function type(): string { return 'restaurant'; }`
      const match = source.match(/function\s+type\s*\(\s*\)\s*:\s*string\s*\{[^}]*return\s+'([a-z_]+)'/);
      if (match) types.add(match[1]);
    }
  };

  walk(API_MODULES);
  return types;
}

describe('discoveryType ↔ providers backend', () => {
  it('trouve les providers du backend (sinon la garde ne prouve rien)', () => {
    const types = backendDiscoveryTypes();

    // Contrôle de vraisemblance : si le chemin ou le motif change, on veut un
    // échec explicite plutôt qu'un test qui passe sur un ensemble vide.
    expect(types.size).toBeGreaterThanOrEqual(5);
    expect(types.has('restaurant')).toBe(true);
    expect(types.has('pharmacy')).toBe(true);
  });

  it('chaque vitrine pointe vers un type réellement servi', () => {
    const backend = backendDiscoveryTypes();
    const orphans: string[] = [];

    for (const identity of surfaceRegistry.all()) {
      if (DEDICATED_CONTRACT[identity.vertical]) continue;

      if (!backend.has(identity.discoveryType)) {
        orphans.push(
          `${identity.vertical} → discoveryType '${identity.discoveryType}' ` +
            `(aucun *DiscoverableProvider ne sert ce type)`,
        );
      }
    }

    expect(orphans).toEqual([]);
  });

  it('une exception de contrat dédié est justifiée et non périmée', () => {
    for (const [vertical, reason] of Object.entries(DEDICATED_CONTRACT)) {
      // La verticale doit exister : une exception pour une verticale supprimée
      // est une ligne morte qui masquerait un futur conflit de nom.
      expect(surfaceRegistry.get(vertical as never)).toBeDefined();
      // La justification doit être substantielle, pas un « TODO ».
      expect(reason.length).toBeGreaterThan(40);
    }
  });
});
