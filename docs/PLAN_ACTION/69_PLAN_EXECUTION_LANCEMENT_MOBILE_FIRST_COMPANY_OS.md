# Plan 69 - Execution lancement Mobile-First Company OS

## Objectif

Transformer les preuves et audits des Plans 67-68 en execution produit orientee lancement. Le Plan 69 cible ce qui doit etre verifie ou renforce avant exposition marketing plus large : vrais appareils mobiles, parcours metier critiques, isolation donnees, super-admin, paie/finance et observabilite.

## Principe d'execution

- Travailler par PR courtes, mergees apres checks verts.
- Ne pas relancer de refonte large sans bug ou risque concret.
- Prioriser les preuves exploitables par testeurs et clients.
- Garder `front/mobile_apps/` comme source canonique mobile.
- Garder `api/openapi.yaml`, `FrontendApiContractTest` et `FRONTEND_API_CONTRACT_MATRIX.md` alignes.

## Lot 69.1 - Recette mobile release sur vrais appareils

### Objectif

Prouver que les trois apps `employee`, `manager` et `platform_admin` s'ouvrent, affichent un premier ecran utile, se connectent et sortent correctement sur APK Firebase issus de `main`.

### Actions

- Declencher `mobile-distribute.yml` pour les trois apps.
- Verifier noms APK prefixes par app.
- Tester login demo employee, manager et super-admin.
- Verifier que le splash ne bloque pas et que `StartupGate` affiche un etat explicite si bootstrap lent.
- Documenter version, SHA, appareil, resultat.

### Sortie attendue

Rapport device QA par app avec verdict `Go`, `Go conditionnel` ou `No-go`.

### Statut

**CI/Firebase livre. Device QA a confirmer.** Le workflow `Mobile - Build and Firebase Distribution` run `26750677529` a distribue avec succes les trois APK staging depuis `main` (`employee-manual-20260601`, `manager-manual-20260601`, `platform-admin-manual-20260601`). Rapport : `docs/validation/MOBILE_RELEASE_DEVICE_QA_2026_06_01.md`.

## Lot 69.2 - Parcours employe terrain

### Objectif

Valider le parcours quotidien employe : login, pointage simple, pointages multiples, pause/reprise, taches du jour, demandes absence/avance, notifications, compte durable.

### Actions

- Smoke API employee avec token demo.
- Verifier `attendance/today` avec `sessions` et `summary`.
- Verifier `tasks/today` et completion tache.
- Verifier absence/avance avec contexte complet.
- Verifier compte durable, career et placard numerique.

### Sortie attendue

Rapport parcours employee + corrections ciblees si endpoint ou UI bloque.

### Statut

**Go partiel, correction livree.** Le smoke Render confirme le login employee, les lectures critiques, le pointage multiple (`normal` puis `overtime`) et la creation/annulation d'une avance. Le parcours absence a revele une rupture contrat mobile/API : les apps lisaient `/leave-balances` au lieu de `/me/leave-balances`, et les demos existantes n'avaient pas de `leave_balances`. Correction appliquee dans les apps employee/manager, le contrat mobile et les seeders demo. Rapport : `docs/validation/EMPLOYEE_TERRAIN_API_SMOKE_2026_06_01.md`.

## Lot 69.3 - Parcours manager/RH et isolation donnees

### Objectif

Valider que manager/RH voient uniquement leurs equipes, peuvent agir sur employes, horaires, taches, corrections, absences, avances et branding tenant.

### Actions

- Smoke API manager avec token demo.
- Verifier liste equipe sans spinner infini.
- Verifier create/update employe avec salaire, horaire et QR.
- Verifier corrections pointage, absences, avances et taches.
- Ajouter ou renforcer un test d'isolation si un risque tenant est detecte.

### Sortie attendue

Rapport manager/RH + preuve isolation.

## Lot 69.4 - Super-admin plateforme

### Objectif

Valider l'app platform admin : login obligatoire, compte demo, creation entreprise, fiche entreprise, subscription/features, health et demandes clients.

### Actions

- Smoke API platform avec token super-admin.
- Verifier creation entreprise payload minimal.
- Verifier fiche client et actions modules/abonnement.
- Verifier que l'app ne consomme pas les routes tenant `/device-tokens`.

### Sortie attendue

Rapport platform admin + corrections si creation ou navigation bloque.

## Lot 69.5 - Paie, avances et documents asynchrones

### Objectif

Stabiliser le cycle financier mobile-first : avance double validation, solde employe, paiement masse, documents PDF et confirmation reception.

### Actions

- Verifier endpoints avances `manager-approve`, `mark-paid`, `confirm-received`.
- Verifier `payment-batches` et documents paiement.
- Verifier queues `documents,pdf,payroll,notifications`.
- Ne pas bloquer l'UI sur generation PDF.

### Sortie attendue

Rapport paie/finance + tests backend cibles si un workflow manque.

## Lot 69.6 - Observabilite lancement

### Objectif

Preparer une lecture simple du lancement : health API, queue health, erreurs 5xx, deploy SHA, Firebase releases, backups et incidents.

### Actions

- Consolider les signaux `launch-readiness`, `health`, queue et deploy.
- Ajouter un rapport lancement par SHA.
- Documenter seuils P1/P2 et action de rollback.

### Sortie attendue

Cockpit de lancement documentaire et procedures d'escalade simples.

## Priorite

1. Lot 69.1
2. Lot 69.2
3. Lot 69.3
4. Lot 69.4
5. Lot 69.5
6. Lot 69.6

## Critere de cloture

- Les trois apps mobiles sont testees sur vrais appareils ou Firebase App Distribution.
- Les parcours employee, manager/RH et platform admin ont un rapport de preuve.
- Les gaps bloquants ont une PR corrective ou un no-go explicite.
- Le depot reste propre, `main` distant aligne et release readiness verte.
