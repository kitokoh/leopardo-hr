# Garde d'architecture inter-contextes (BC Dependencies)

> **Issue :** [MAT-002 #5860](https://github.com/kitokoh/leopardo-hr/issues/5860)
> **Garde CI :** `dev-hub/tools/check-bc-dependencies.sh` (exécuté dans `architecture-check.yml`, job *Module Structure Validator* — check requis)
> **Dépendance :** [MAT-001 #5859](https://github.com/kitokoh/leopardo-hr/issues/5859) (registre `dev-hub/tools/bc-registry.json`)
> **Tests :** `dev-hub/tools/check-bc-dependencies-test.sh` (4 scénarios)

## Objectif

Interdire les **imports PHP directs entre bounded contexts** hors des contrats
autorisés. C'est la matérialisation exécutable de la règle « pas d'import
direct inter-contexte sans contrat » du registre des bounded contexts.

## Les dépendances autorisées sont versionnées (2 sources)

1. **`allowed_dependencies`** dans `dev-hub/tools/bc-registry.json` — la
   matrice contractuelle (source de vérité, évolue par PR de coordination) ;
2. **`bc-dependencies-allowlist.txt`** — la **dette existante gelée** (58
   paires constatées sur main le 2026-08-28, 219 imports). Le fichier est
   immuable : toute nouvelle paire fait échouer la CI.

Les **noms de famille transverses** sont des contrats partagés, toujours
autorisés : `App\Events`, `App\Shared`, `App\Contracts`, `App\Enums`,
`App\Exceptions`, `App\Support`, `App\Attributes`, `App\Traits`,
`App\Notifications`, `App\Mail`.

## Règles

- Chaque fichier PHP sous `api/app/**` est affecté au BC du **plus long préfixe
  de chemin** déclaré dans le registre.
- Tout import vers un BC différent, non listé dans `allowed_dependencies` du BC
  source et absent de l'allowlist → `::error::` + exit 1, avec le détail des
  fichiers fautifs et la liste des dépendances autorisées du BC source.
- Les fichiers hors périmètre BC (infra partagée non affectée) ne sont pas
  contrôlés en sortie.

## Réduire la dette au lieu d'agrandir l'allowlist

Le message d'erreur du garde liste les alternatives sanctionnées : événements
partagés, contrats (interfaces dans `App\Contracts`), injection de dépendance.
Une PR qui ajoute une paire à l'allowlist sans discussion architecturale
documentée sera refusée en revue.

## Exécution locale

```bash
bash dev-hub/tools/check-bc-dependencies.sh api              # dépôt réel
bash dev-hub/tools/check-bc-dependencies-test.sh             # 4 scénarios
```

## Rollback

- Revert du commit du garde ou de l'allowlist ; le garde est un script bash
  autonome sans état (aucune migration, aucun schéma).
- Si le garde bloque une PR : corriger l'import fautif dans la PR (événements
  partagés/contrats), ne pas désactiver le garde.
