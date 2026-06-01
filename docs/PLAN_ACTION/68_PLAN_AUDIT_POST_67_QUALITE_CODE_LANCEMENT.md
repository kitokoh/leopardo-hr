# Plan 68 - Audit post-67 qualite code et lancement

## Objectif

Cloturer proprement le cycle des plans 01-67 et repartir sur un audit operationnel verifiable avant lancement marche.

Le Plan 68 ne remplace pas les plans historiques. Il sert a verifier que le depot, les contrats API, les apps mobiles, la vitrine, le kiosk, les workflows CI/CD et les preuves de readiness restent coherents apres les merges successifs.

## Lot 68.1 - Hygiene depot et branches

### Probleme

Le projet a recu beaucoup de branches et de contributions successives. Avant de lancer une nouvelle phase produit, le depot doit rester lisible : `origin/main` comme seule source de verite, branches mergées supprimees, branches locales historiques identifiees sans suppression aveugle.

### Actions

- Verifier `git fetch --prune origin`.
- Lister les branches distantes mergees dans `origin/main`.
- Lister les branches distantes non mergees.
- Lister les branches locales mergees et non mergees.
- Ajouter un garde reproductible pour refaire ce controle.
- Documenter les branches restantes a traiter manuellement.

### Statut

**Livre.** Le script `dev-hub/tools/repository-hygiene-report.ps1` produit un rapport machine lisible. Le rapport `docs/validation/REPOSITORY_HYGIENE_REPORT_2026_06_01.md` confirme que le distant ne suit plus que `main`; la seule branche locale non mergee observee est `codex/plan57-api-docs-ecosystem`, a conserver tant que son contenu/stash n'est pas audite.

## Lot 68.2 - Audit contrats API/fronts

### Objectif

Verifier que les surfaces mobiles employee, manager, platform admin, vitrine, kiosk et admin-dashboard consomment des routes documentees ou explicitement matricees.

### Livrables attendus

- Relancer les gardes `mobile-workflow-contracts`, OpenAPI et frontend/API matrix.
- Produire un rapport des endpoints orphelins ou non documentes.
- Corriger uniquement les divergences a fort risque.

### Statut

**Livre.** Le garde `dev-hub/tools/validate-frontend-api-contract-governance.ps1` relie maintenant la matrice frontend/API, `FrontendApiContractTest`, `mobile-workflow-contracts.json`, `mobile-apps-ci.yml`, `openapi-ci.yml` et les routes critiques de lancement. Rapport : `docs/validation/FRONTEND_API_CONTRACT_GOVERNANCE_REPORT_2026_06_01.md`.

## Lot 68.3 - Audit code quality pragmatique

### Objectif

Identifier les zones de dette qui menacent le lancement sans lancer de refonte large.

### Livrables attendus

- Rapport top risques par surface : backend, mobile core, employee, manager, platform admin, web, kiosk.
- Liste de quick wins exploitables par PR courtes.
- Garde anti-regression si un risque peut etre automatise.

## Lot 68.4 - Audit production ops

### Objectif

Verifier que les operations essentielles sont documentees et testables : deploy, rollback, backup, queues, Redis, notifications, Firebase distribution, staging smoke.

### Livrables attendus

- Rapport operations post-67.
- Mise a jour runbooks si une procedure manque.
- Liste des secrets/envs requis pour lancement.

## Lot 68.5 - Nouveau plan produit

### Objectif

Produire le prochain plan d'action base sur l'audit, pas sur une accumulation brute de demandes.

### Livrables attendus

- Regrouper les demandes restantes par theme coherent.
- Prioriser selon impact lancement, risque securite, impact business et effort.
- Definir les lots de la prochaine execution.

## Critere de cloture Plan 68

- Depot distant propre.
- Branche locale historique documentee ou traitee.
- Readiness 23/23 toujours verte.
- Contrats API/fronts verifies.
- Nouveau plan produit redige a partir d'un audit concret.
