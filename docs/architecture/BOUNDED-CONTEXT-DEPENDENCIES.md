# Dépendances inter-contextes — matrice versionnée

> **MAT-002 (issue #5860)** — Guard d'architecture inter-contextes.
> Source de vérité : [`dev-hub/governance/bounded-context-dependencies.json`](../../dev-hub/governance/bounded-context-dependencies.json)
> Garde CI : [`dev-hub/tools/check-bounded-context-dependencies.sh`](../../dev-hub/tools/check-bounded-context-dependencies.sh)

## Principe

**Deny-by-default.** Chaque import PHP inter-contextes (`use App\...` qui
traverse la frontière d'un bounded context, résolue via le registre
MAT-001/#5859) doit correspondre à une arête déclarée dans la matrice.
Une arête absente fait échouer la CI avec un message actionnable :

```
api/app/Modules/HR/Application/XxxService.php : import App\Modules\Payroll\... —
arête BC-04 → BC-07 non déclarée dans bounded-context-dependencies.json
→ ajoute l'arête dans la matrice (avec justification) ou utilise un contrat partagé
```

## Format

```jsonc
{
  "_meta": { "policy": "deny-by-default", "updated": "2026-08-28" },
  "edges": [
    { "from": "BC-04", "to": "BC-07", "baseline": true,  "note": "HR → Payroll (état actuel gelé)" },
    { "from": "BC-15", "to": "BC-02", "baseline": false, "note": "FuelStation → Tenant (nouveau contrat)" }
  ]
}
```

- `baseline: true` — arête constatée sur `main` à l'audit initial (gelée, ne
  régresse pas la CI).
- `baseline: false` — nouveau contrat déclaré explicitement par une PR.

## Règles vérifiées

1. La matrice référence uniquement des codes BC existants dans le registre.
2. Tout import `use App\...` qui traverse une frontière BC (fichier et cible
   résolus via `bounded-context-registry.json`) doit avoir son arête
   `(from → to)` dans la matrice.
3. Les imports vers l'infrastructure partagée (`App\Shared`, `App\Models`,
   `App\Http`, `App\Core\Http`, providers, etc.) ne sont pas des arêtes
   inter-contextes : ignorés.

## Ajouter une dépendance légitime

1. Ajouter l'arête dans `bounded-context-dependencies.json` avec
   `baseline: false` et une note justifiant le contrat utilisé
   (interface partagée, événement, contrat tenant, etc.).
2. Lancer localement :
   ```bash
   bash dev-hub/tools/check-bounded-context-dependencies.sh
   node --test dev-hub/tools/tests/check-bounded-context-dependencies.test.mjs
   ```
3. La PR doit référencer l'issue avec `Closes #N`.

> Complémentaire du guard d'isolation (#5584) : celui-ci bloque tout NOUVEL
> import croisé ; celui-ci impose que chaque dépendance existante ou nouvelle
> soit **déclarée et versionnée** au niveau bounded context.

## Régénération de la baseline (référence)

La baseline v1 (80 arêtes, 660 imports) a été générée depuis l'audit du graphe
d'imports réel de `main` le 2026-08-28. Pour re-auditer le graphe (diagnostic,
pas mise à jour automatique) : scanner `api/app/Modules`, `api/app/Core`,
`api/app/AI` avec la résolution du registre — voir le script de guard (mode
rapport).
