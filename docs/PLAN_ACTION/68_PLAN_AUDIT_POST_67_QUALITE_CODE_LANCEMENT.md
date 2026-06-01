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

### Statut

**Livre.** Le garde `dev-hub/tools/validate-code-quality-governance.ps1` bloque les references canoniques obsoletes (`openapi/v1.yaml`) et verifie la presence des artefacts post-67. Le rapport `docs/validation/CODE_QUALITY_GOVERNANCE_REPORT_2026_06_01.md` documente les corrections livrees et les zones backend a ne refactorer que par lots fonctionnels.

## Lot 68.4 - Audit production ops

### Objectif

Verifier que les operations essentielles sont documentees et testables : deploy, rollback, backup, queues, Redis, notifications, Firebase distribution, staging smoke.

### Livrables attendus

- Rapport operations post-67.
- Mise a jour runbooks si une procedure manque.
- Liste des secrets/envs requis pour lancement.

### Statut

**Livre.** Le garde `dev-hub/tools/validate-production-ops-readiness.ps1` relie deploy, rollback, queues, Redis, scheduler, notifications, Firebase Distribution et backup/restore. `DEPLOYMENT_GUIDE.md` documente les secrets CI/CD/mobile critiques. Rapport : `docs/validation/PRODUCTION_OPS_READINESS_REPORT_2026_06_01.md`.

## Lot 68.5 - Nouveau plan produit

### Objectif

Produire le prochain plan d'action base sur l'audit, pas sur une accumulation brute de demandes.

### Livrables attendus

- Regrouper les demandes restantes par theme coherent.
- Prioriser selon impact lancement, risque securite, impact business et effort.
- Definir les lots de la prochaine execution.

### Statut

**Livre.** Le prochain cycle est formalise dans `docs/PLAN_ACTION/69_PLAN_EXECUTION_LANCEMENT_MOBILE_FIRST_COMPANY_OS.md`. Le rapport `docs/validation/NEXT_PRODUCT_PLAN_2026_06_01.md` explique l'ordre d'execution et cloture le Plan 68.

## Critere de cloture Plan 68

- Depot distant propre.
- Branche locale historique documentee ou traitee.
- Readiness 23/23 toujours verte.
- Contrats API/fronts verifies.
- Nouveau plan produit redige a partir d'un audit concret.

## Statut final

**Cloture le 2026-06-01.** Les lots 68.1 a 68.5 sont livres. La suite canonique est le Plan 69.
