# Mini-spec — Issue #3742

## Intention
Éliminer les 403 horaires de `branch-protection-guard.yml` causés par l’usage implicite de `GITHUB_TOKEN` sur l’API GitHub de protection de branche.

## Décision
Le workflow n’est plus planifié toutes les heures. La vérification réelle est conservée sur `workflow_dispatch` et sur les pull requests qui modifient la garde ou son canonique, mais elle ne s’exécute que lorsque le secret `BRANCH_PROTECTION_TOKEN` est configuré. Ce secret doit être un PAT finement scoped ou un token GitHub App doté de `administration:read` sur le dépôt.

Lorsque le secret est absent, un job de notice explique la configuration nécessaire au lieu d’émettre un faux échec 403. Aucun token privilégié n’est ajouté au dépôt.

## Critères d’acceptation

| Critère | Résultat |
|---|---|
| Aucun 403 horaire automatique | Le trigger `schedule` est supprimé |
| Vérification réelle conservée | `check` utilise `BRANCH_PROTECTION_TOKEN` |
| Secret absent explicite | `explain-missing-token` émet une notice actionnable |
| Sécurité | Aucun PAT n’est committé ; le token reste un secret GitHub |
| Auditabilité | Le workflow reste lançable manuellement |
