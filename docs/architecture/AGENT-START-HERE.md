# Agent Start Here — Leopardo Platform

Ce document est le point d’entrée obligatoire pour tout agent intervenant sur Leopardo.

## Instruction d’affectation

Le coordinateur donne à l’agent un numéro de bounded context :

```text
Agent 01 → BC-01 Platform
Agent 02 → BC-02 Tenant
Agent 03 → BC-03 Identity
Agent 04 → BC-04 HR
Agent 05 → BC-05 Workforce
Agent 06 → BC-06 Leave
Agent 07 → BC-07 Payroll
Agent 08 → BC-08 Accounting
Agent 09 → BC-09 Expense
Agent 10 → BC-10 Recruitment
Agent 11 → BC-11 CRM client
Agent 12 → BC-12 Growth/Marketing client
Agent 13 → BC-13 Communications
Agent 14 → BC-14 Integration Runtime
Agent 15 → BC-15 FuelStation
Agent 16 → BC-16 EduManager
Agent 17 → BC-17 Retail/POS
Agent 18 → BC-18 Field/Fleet
Agent 19 → BC-19 Devices/Edge
Agent 20 → BC-20 Documents
Agent 21 → BC-21 Billing
Agent 22 → BC-22 Analytics
Agent 23 → BC-23 AI
```

L’agent doit ensuite découvrir les issues qui portent son code `DEP-BCxx`, les issues fonctionnelles de son contexte et les tâches de maturité transverses nécessaires. Il ne reçoit pas nécessairement un numéro d’issue unique : il traite son contexte de manière séquentielle jusqu’à sa Definition of Done.

## Séquence obligatoire

1. Lire `AGENTS.md`, `.specify/constitution.md`, ce document, le registre et le backlog de profondeur.
2. Vérifier la tête de `main`, l’état des PRs, les issues déjà closes et les fichiers réellement présents.
3. Produire une cartographie de l’existant : code, routes, migrations Laravel, modèles, jobs, événements, tests et dépendances.
4. Construire l’ordre des issues par dépendance ; ne pas choisir uniquement par numéro.
5. Créer une branche courte avec le code du contexte dans le nom.
6. Traiter une issue vérifiable à la fois. Une issue peut produire plusieurs PRs si le périmètre est trop grand.
7. Utiliser exclusivement les migrations Laravel et le runner existant `php artisan leopardo:migrate`.
8. Ajouter validation stricte, Policies, tests de permission et tests cross-tenant lorsque le contexte est concerné.
9. Ajouter tests d’idempotence, concurrence, rollback, performance, jobs et intégrations lorsque le contexte le demande.
10. Ouvrir une PR courte avec preuves de tests, risques, rollback et fichiers touchés.
11. Attendre les checks requis verts avant de passer à l’issue suivante.
12. Mettre à jour le registre de maturité et produire un rapport de fin de contexte.

## Interdictions absolues

L’agent ne doit pas mélanger le CRM commercial Leopardo, situé dans l’administration plateforme, avec le CRM client tenant-scoped situé dans les espaces clients. Il ne doit pas accepter `company_id` fourni par le client comme preuve d’autorité, accéder directement à la base d’un autre contexte, ajouter une migration Flyway/Prisma/Knex parallèle, écrire un secret dans PostgreSQL ou les logs, désactiver un test de sécurité, ou fusionner une PR avec des checks requis rouges.

Une modification du contrat partagé `TenantManager`, des événements, de l’outbox, de l’identité, des Policies, des migrations communes ou de l’OpenAPI doit faire l’objet d’une tâche de coordination et d’une validation par le propriétaire du contexte concerné.

## Definition of Done d’un contexte

Un contexte est terminé uniquement si les douze dimensions du backlog de profondeur sont prouvées : domaine, données, tenant, API, autorisation, transactions, asynchronisme, sécurité, frontend, performance, exploitation et produit.

La preuve minimale comprend une cartographie, des migrations Laravel reproductibles, des routes et Requests versionnées, des Policies, des tests unitaires et intégration, des tests négatifs cross-tenant, des événements/j jobs idempotents, des métriques, un runbook, un rollback, un golden journey et une CI verte.

## Références obligatoires

- `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md`
- `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md`
- `docs/specifications/PROGRAMME-CRM-INTERNE-CLIENT-COMPLET.md`
- `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`
- les issues GitHub `DEP-BCxx`, `MAT-*`, `CRM-*`, `FUEL-*` et `EDU-*`

## Rapport attendu en fin de contexte

Le rapport doit indiquer les issues traitées, les PRs fusionnées, les fichiers modifiés, les migrations exécutées, les tests et checks, les métriques, les risques restants, les dépendances non résolues et la prochaine action recommandée. Un contexte ne doit jamais être déclaré « terminé » uniquement parce que ses issues fonctionnelles sont fermées.
