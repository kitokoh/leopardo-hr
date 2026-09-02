# Performance — données de charge CRM (séparées des fixtures)

Ce dossier porte les **volumes synthétiques de benchmark** (issue #5738) :

- `CrmBenchmarkDataset.php` — générateur de volumes (accounts/contacts/leads
  par tenant) pour k6 / smoke de charge / comparaisons avant-après.

## Règles strictes

1. **Jamais importé par une suite Feature** : les tests fonctionnels utilisent
   `Tests\Support\CRM\CrmTenantFixture` (données minimales déterministes) ;
   les volumes de charge ne s'exécutent que dans des scripts/benchmarks dédiés.
2. Données 100 % synthétiques et seedées (reproductibles), aucun secret ni PII.
3. L'activation des tables CRM est conditionnée à leur existence (`hasTable`)
   — le générateur démarre quand le socle V0 (#5708/#5709) est présent.
