# Dossier de refactoring architectural

Ce dossier transforme l’analyse de complexité cumulative du monorepo Leopardo HR en plan de travail exécutable.

| Document | Rôle |
|---|---|
| [`../PLAN_REFACTORING_COMPLEXITE_CUMULATIVE.md`](../PLAN_REFACTORING_COMPLEXITE_CUMULATIVE.md) | Cartographie des hotspots et diagnostic architectural |
| [`ADR-REFACTORING-FONDATIONS.md`](ADR-REFACTORING-FONDATIONS.md) | Décisions et principes qui encadrent les extractions |
| [`ROADMAP-BACKLOG-REFACTORING.md`](ROADMAP-BACKLOG-REFACTORING.md) | Lots, backlog, dépendances et calendrier indicatif |
| [`TESTS-RISQUES-GOUVERNANCE.md`](TESTS-RISQUES-GOUVERNANCE.md) | Risques, stratégie de tests, rollback et Definition of Done |

## Ordre de lecture

La lecture commence par le diagnostic, se poursuit par les décisions, puis par la roadmap et enfin par les garde-fous de livraison. Une équipe peut utiliser la roadmap comme backlog de haut niveau et ouvrir une issue dédiée pour chaque item `R0.x` à `R6.x`.

## Règle de démarrage

Aucun grand refactoring ne doit commencer par une extraction mécanique. Le lot doit d’abord identifier le contrat préservé, les tests de référence, le propriétaire, la métrique de succès et la stratégie de rollback. Les premiers lots recommandés sont la frontière tenant des exports/notifications, puis un sous-pipeline Payroll dans un pays pilote.

## État

Les documents sont proposés pour validation d’équipe. Après approbation, l’ADR doit être marqué accepté et les items R0.1 à R0.3 peuvent être transformés en issues de travail. Le dossier ne remplace pas les politiques de sécurité ni les procédures de release existantes ; il les relie au chantier de simplification architecturale.
