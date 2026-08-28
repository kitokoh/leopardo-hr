# Registre des bounded contexts (BC Registry)

> **Issue :** [MAT-001 #5859](https://github.com/kitokoh/leopardo-hr/issues/5859)
> **Garde CI :** `dev-hub/tools/check-bc-registry.sh` (exécuté dans `architecture-check.yml`, job *Hygiene Guards*)
> **Registre machine-readable :** `dev-hub/tools/bc-registry.json`
> **Tests :** `dev-hub/tools/check-bc-registry-test.sh` (8 scénarios)

## Objectif

Le registre des bounded contexts est le **manifeste vérifiable** des 23 bounded
contexts Leopardo (BC-01..BC-23) : propriétaire, priorité, statut, chemins
autorisés, routes, migrations, événements et dépendances autorisées. Il rend la
gouvernance exécutable : la CI échoue si un chemin ou un propriétaire manque,
si un BC référencé n'existe pas, ou si `CODEOWNERS` n'est plus cohérent avec le
registre.

La numérotation suit `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md`
(plan de gouvernance ratifié, PR #5900) : un agent affecté à « BC-15 » travaille
dans le périmètre exact décrit ici.

## Fichiers

| Fichier | Rôle |
|---|---|
| `dev-hub/tools/bc-registry.json` | Le manifeste (source de vérité machine) |
| `dev-hub/tools/check-bc-registry.sh` | Le garde CI (`::error::` + exit 1 sur violation) |
| `dev-hub/tools/check-bc-registry-test.sh` | Harness de tests (fixtures positifs/négatifs) |
| `CODEOWNERS` | Sections `# BC-xx` — la cohérence avec le registre est vérifiée |
| `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md` | Registre humain de référence (PR #5900) |

## Schéma d'une entrée

```json
{
  "code": "BC-15",
  "name": "FUEL",
  "label": "FuelStation (verticale)",
  "owner": "kitokoh",
  "priority": "P0",
  "status": "active | partial | planned",
  "paths": ["api/app/Solutions/FuelStation"],
  "routes": ["api/routes/modules/crm.php"],
  "migrations": ["api/database/migrations/tenant/*fuel*"],
  "events": ["App\\Events\\EmployeeCreated"],
  "allowed_dependencies": ["BC-02", "BC-03"]
}
```

Champs :

- `code` — `BC-xx`, obligatoire, unique. Les 23 codes doivent tous exister.
- `name` / `label` — identifiant court et libellé humain.
- `owner` — propriétaire GitHub (doit apparaître dans `CODEOWNERS`).
- `priority` — P0/P1/P2/P3 (ordre d'affectation des agents).
- `status` — `active` (code présent sur main), `partial` (présent mais en cours),
  `planned` (futur : les chemins peuvent ne pas encore exister).
- `paths` — chemins/globs du code du contexte. **Existence vérifiée pour
  `active`/`partial`** ; chaque chemin doit aussi être couvert par la section
  `# BC-xx` de `CODEOWNERS` (le fallback global `*` ne suffit pas).
- `routes` — fichiers de routes (`api/routes/**`). Chaque glob doit avoir ≥ 1
  correspondance pour un BC actif/partiel.
- `migrations` — globs de migrations (`api/database/migrations/**`, convention
  Laravel `leopardo:migrate` uniquement — aucune chaîne Flyway/Prisma/Knex).
  Chaque glob doit avoir ≥ 1 correspondance pour un BC actif/partiel.
- `events` — classes d'événements (`App\Events\...`), vérifiées sur le disque.
- `allowed_dependencies` — **contrats autorisés** (codes BC). C'est la matrice
  consommée par le garde d'architecture inter-contextes (MAT-002 #5860) : un
  import PHP entre BC doit être couvert par cette liste.

## Règles de modification

1. Toujours passer par une **PR de coordination** : ajouter/retirer un chemin,
   un propriétaire ou une dépendance impacte d'autres agents.
2. Un BC `planned` passe à `active` dans la même PR qui livre son code sur main
   (sinon le garde échoue sur les chemins introuvables).
3. `CODEOWNERS` et le registre évoluent ensemble — le garde vérifie la
   cohérence (section présente + chemins couverts).
4. Ne jamais renommer un `code` (`BC-xx`) : c'est l'identifiant de contrat
   utilisé dans les issues, branches et PRs.

## Rollback

- **Garde qui bloque une PR :** corriger le registre ou `CODEOWNERS` dans la
  PR elle-même (le message `::error::` nomme le BC et la règle violée).
- **Garde qui bloque main :** reverter le commit du registre en question ; le
  garde est un script bash autonome sans état, il revient au comportement
  précédent immédiatement.
- Le registre est purement déclaratif : aucun schéma de base, aucune migration.

## Exécution locale

```bash
bash dev-hub/tools/check-bc-registry.sh              # registre réel + CODEOWNERS réel
bash dev-hub/tools/check-bc-registry-test.sh         # 8 scénarios (fixtures)
```
