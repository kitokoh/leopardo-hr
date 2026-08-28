# Registre officiel des bounded contexts et plan d’exécution agents

**Projet :** Leopardo Platform
**Statut :** Proposition de gouvernance prête à validation
**Objectif :** permettre d’affecter simplement un agent à un bounded context numéroté, sans ambiguïté de périmètre, de données, de responsabilités ou de dépendances.

> **Instruction simple :** « Agent 01 prend le BC-01 », « Agent 15 prend le BC-15 ». L’agent travaille dans son périmètre, respecte les contrats partagés et ne modifie pas le contexte d’un autre agent sans issue et accord explicite.

## 1. Règle générale

Un bounded context est une frontière métier et technique. Il possède son vocabulaire, ses modèles, ses migrations Laravel, ses routes, ses Policies, ses événements, ses tests et son propriétaire. Il ne s’agit pas nécessairement d’un microservice ou d’un dépôt séparé. Dans Leopardo, le choix reste un **modular monolith Laravel dans un monorepo**, avec des applications frontend spécialisées.

Les agents ne doivent pas créer de branches permanentes par bounded context. Chaque agent utilise des branches courtes et des PRs limitées, régulièrement rébasées sur `main`. Le numéro du bounded context sert à attribuer la responsabilité, pas à créer une divergence durable du code.

## 2. Registre numéroté

| N° | Code | Bounded context | Responsabilité | Emplacement principal | Priorité agent |
|---:|---|---|---|---|---:|
| 01 | BC-PLATFORM | Platform Core | Catalogue, modules, provisioning, configuration globale et feature flags. | `api/app/Modules/Platform`, `api/app/Modules/Onboarding` | P0 |
| 02 | BC-TENANT | Tenant & Isolation | Entreprises, contexte actif, `TenantManager`, search path, scopes et isolation. | `api/app/Core/Tenant`, migrations public/shared/tenant | P0 |
| 03 | BC-IDENTITY | Identity & Access | Utilisateurs, memberships, rôles, permissions, invitations, sessions et Policies de base. | `api/app/Core/Auth`, `api/app/Policies` | P0 |
| 04 | BC-HR | Human Resources | Employés, contrats, départements, profils, documents RH et cycle de vie employé. | `api/app/Modules/HR` | P1 |
| 05 | BC-WORKFORCE | Attendance, Planning & Workforce | Présence, shifts, planning, géolocalisation, modes de pointage et affectations. | `api/app/Modules/Attendance`, `Planning` | P1 |
| 06 | BC-LEAVE | Leave & Absence | Congés, absences, justificatifs, soldes, validations et calendrier d’absence. | `api/app/Modules/Absence` | P1 |
| 07 | BC-PAYROLL | Payroll | Périodes, règles, calculs, snapshots, bulletins, validations et exports paie. | `api/app/Modules/Payroll` | P0 |
| 08 | BC-ACCOUNTING | Accounting & Finance | Plan comptable, journaux, écritures, exercices, lettrage, FEC et états financiers. | `api/app/Modules/Accounting` | P0 |
| 09 | BC-EXPENSE | Expenses & Benefits | Dépenses, avances, prêts, remboursements et avantages, avec contrat Finance. | `api/app/Modules/Expense` | P2 |
| 10 | BC-RECRUITMENT | Recruitment | Candidatures, postes, candidats, entretiens, décisions et onboarding d’embauche. | `api/app/Modules/Recruitment` | P2 |
| 11 | BC-CRM | Customer CRM | Accounts, contacts, leads, opportunités, activités, tâches et pipelines du tenant. | `api/app/Modules/CRM` à créer/compléter | P1 |
| 12 | BC-GROWTH | Marketing & Growth | Segments, campagnes, consentements, templates et marketing client. | `api/app/Modules/Marketing`, `Growth` | P1 |
| 13 | BC-COMMS | Notifications & Communications | Email, SMS, WhatsApp, préférences, templates, inbox/outbox et délivrabilité. | `api/app/Modules/Notification`, `api/app/Contracts/Communication` | P0 |
| 14 | BC-INTEGRATION | Integration Runtime | Webhooks, queues, inbox durable, outbox transactionnelle, adaptateurs et replay. | `api/app/Jobs`, `Contracts/Queue`, `EdgeSync` | P0 |
| 15 | BC-FUEL | FuelStation | Stations, pompes, compteurs, sessions pompistes, photos/OCR, ventes, dépôts et bilans. | `api/app/Solutions/FuelStation` à créer | P0 pilote |
| 16 | BC-EDU | EduManager | Campus, élèves, guardians, classes, présences, notes, bulletins et admissions. | `api/app/Solutions/EduManager` à créer | P1 pilote |
| 17 | BC-RETAIL | Retail & POS | Produits, magasins, caisse, tickets, stocks et synchronisation POS. | `api/app/Solutions/Retail` futur | P3 |
| 18 | BC-FIELD | Field Service & Fleet | Véhicules, interventions, équipements, maintenance et opérations terrain génériques. | `api/app/Modules/Fleet`, futur FieldService | P2 |
| 19 | BC-DEVICE | Devices, Cameras & Edge | Appareils, kiosques, biométrie, synchronisation edge et politiques device. | `api/app/Modules/Cameras`, `EdgeSync` | P1 |
| 20 | BC-DOCUMENTS | Documents & Evidence | Fichiers, pièces justificatives, scans, retention, antivirus, signatures et exports protégés. | `api/app/Modules/Documents` à formaliser, storage | P1 |
| 21 | BC-BILLING | Billing & Subscription | Plans, activation commerciale, facturation de plateforme et entitlements. | `api/app/Modules/Billing` | P2 |
| 22 | BC-ANALYTICS | Reporting & Analytics | Read models, indicateurs, exports asynchrones, audit reporting et data quality. | `api/app/Modules/Reporting` à formaliser | P1 |
| 23 | BC-AI | AI Assistive Services | OCR, suggestions et assistants bornés, registry, consentement et traçabilité. | `api/app/AI` | P2 |

## 3. Distinction CRM commercial / CRM client

Le **CRM commercial Leopardo** reste dans `BC-PLATFORM` avec les composants existants de `Platform` et `Marketing`. Il appartient à l’administration de la plateforme et sert à acquérir, qualifier et convertir les entreprises clientes.

Le **CRM client** appartient à `BC-CRM`. Il est accessible uniquement dans l’espace client et sous l’API tenant. Il gère les propres clients et opportunités de chaque entreprise. `BC-CRM` ne lit pas les tables du CRM commercial et ne peut pas créer ou modifier un tenant directement.

La seule communication autorisée passe par des contrats explicites : `TenantActivated`, `CustomerWorkspaceReady`, `CustomerCrmLeadConverted` ou des commandes versionnées. Les noms de tables, Policies, routes et menus doivent conserver cette distinction.

## 4. Agents et responsabilités

Un agent principal est responsable de la cohérence de son bounded context. Un agent secondaire peut intervenir sur une tâche ciblée, mais le propriétaire du contexte valide la PR. Les agents de plateforme et d’intégration ont une responsabilité de coordination, pas le droit de modifier les règles métier des autres contextes.

| Agent | Prend en charge | Ne doit pas modifier directement |
|---|---|---|
| Agent 01 | BC-PLATFORM | Payroll, CRM client ou données verticales. |
| Agent 02 | BC-TENANT | Les modèles métier des modules. |
| Agent 03 | BC-IDENTITY | Les règles de paie ou les Policies métier sans contrat. |
| Agent 04 | BC-HR | Les calculs Payroll ou les contacts CRM. |
| Agent 05 | BC-WORKFORCE | Les snapshots Payroll ou les comptes Accounting. |
| Agent 06 | BC-LEAVE | TenantManager et Identity sans issue dédiée. |
| Agent 07 | BC-PAYROLL | CRM, Marketing ou Accounting interne sans contrat. |
| Agent 08 | BC-ACCOUNTING | Les données source RH/CRM ; seulement les contrats d’écriture. |
| Agent 09 | BC-EXPENSE | Le ledger directement sans contrat Finance. |
| Agent 10 | BC-RECRUITMENT | Le CRM commercial plateforme. |
| Agent 11 | BC-CRM | `MarketingLead` commercial, `Company` plateforme et Payroll. |
| Agent 12 | BC-GROWTH | Les contacts sans consentement ou les règles de paie. |
| Agent 13 | BC-COMMS | Les secrets provider ou les tables métier verticales. |
| Agent 14 | BC-INTEGRATION | Le traitement métier spécifique d’un module. |
| Agent 15 | BC-FUEL | Le cœur Tenant/HR/Payroll ; il les consomme par contrat. |
| Agent 16 | BC-EDU | Les données FuelStation, Payroll ou CRM commercial. |
| Agent 17 | BC-RETAIL | Les règles génériques de caisse dans CRM. |
| Agent 18 | BC-FIELD | Le calcul de paie et l’identité. |
| Agent 19 | BC-DEVICE | Les données métier hors contrats device. |
| Agent 20 | BC-DOCUMENTS | Les Policies métier d’un autre contexte. |
| Agent 21 | BC-BILLING | L’activation métier sans entitlement officiel. |
| Agent 22 | BC-ANALYTICS | Les tables transactionnelles comme source de dashboard direct. |
| Agent 23 | BC-AI | Les décisions métier automatiques sans confirmation humaine. |

## 5. Statut des travaux

### Déjà présent ou partiellement implémenté

La plateforme possède déjà des briques pour Platform, Tenant, Identity, HR, Attendance, Absence, Payroll, Accounting, Expense, Recruitment, Marketing, Notification, Billing, Fleet, Cameras et EdgeSync. Ces contextes ne sont pas vierges : l’agent doit commencer par une cartographie, des tests et une réduction du couplage avant d’ajouter une nouvelle fonctionnalité.

Le CRM commercial plateforme existe déjà. Le CRM client et les solutions FuelStation/EduManager sont des extensions à construire proprement, pas des remplacements du CRM commercial.

### Déjà spécifié ou publié en issues

Les 27 issues CRM client V0/V1, les 12 issues PRE et les 44 issues FuelStation/EduManager couvrent déjà une grande partie des fonctionnalités. Elles restent soumises aux dépendances de BC-PLATFORM, BC-TENANT, BC-IDENTITY, BC-COMMS et BC-INTEGRATION.

La conception onboarding et verticales existe dans `PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`. Les tâches FuelStation et EduManager sont publiées dans la série `FUEL-*` et `EDU-*`.

### Tâches de maturité à ajouter

Les tâches suivantes sont nécessaires avant de considérer le projet comme mature :

| Code | Tâche | Risque corrigé |
|---|---|---|
| MAT-001 | Registre automatisé des bounded contexts, propriétaires, chemins et dépendances. | Contexte introuvable ou responsabilité ambiguë. |
| MAT-002 | Guard PHP/architecture interdisant les imports hors contrat entre contextes. | Couplage cumulatif. |
| MAT-003 | Guard routes/Policies détectant platform route exposée dans tenant ou inversement. | Fuite d’autorité et mélange des CRM. |
| MAT-004 | Test contractuel de `TenantManager` dans HTTP, jobs, events, cache et exports. | Fuite cross-tenant. |
| MAT-005 | Conventions et validation de toutes les migrations Laravel par bounded context. | Collisions et déploiements incohérents. |
| MAT-006 | Catalogue des événements versionnés et compatibilité consommateurs. | Régressions inter-modules. |
| MAT-007 | Tests de non-régression des invariants Payroll/Accounting. | Erreurs financières silencieuses. |
| MAT-008 | Outbox/inbox/queue standard avec idempotence, retries, DLQ et replay contrôlé. | Perte ou doublon de messages. |
| MAT-009 | Observabilité commune : correlation ID, métriques, traces, alertes et dashboards. | Incidents invisibles. |
| MAT-010 | Feature flags par tenant, solution et provider avec kill switch. | Activation incontrôlée. |
| MAT-011 | Data classification, rétention, anonymisation et droit d’export par contexte. | Risques RGPD et PII. |
| MAT-012 | Seed de pilote par vertical et données synthétiques non sensibles. | Tests non reproductibles. |
| MAT-013 | Golden journeys end-to-end par solution. | Fonctionnalités isolées mais parcours cassé. |
| MAT-014 | Budgets de performance et guards N+1/index/queue lag. | Requêtes lentes et saturation. |
| MAT-015 | Runbooks de backup, restauration, rollback et incident par contexte. | Récupération incertaine. |
| MAT-016 | Release train et matrice de compatibilité des applications mobiles. | Clients mobiles divergents. |
| MAT-017 | Revue de sécurité des uploads/OCR/WhatsApp/POS et devices. | Surface d’attaque accrue. |
| MAT-018 | Pilote métier signé et critères go/no-go pour FuelStation/EduManager. | Mise en production prématurée. |

## 6. Ordre de lancement des agents

### Vague A — gardiens de la plateforme

Les agents 02, 03, 01 et 14 commencent par stabiliser TenantManager, Identity, le provisioning, les queues et les contrats. Ils livrent des PRs courtes avec tests cross-tenant, idempotence et observabilité.

### Vague B — contrats métier

Les agents 04, 05, 06, 07, 08, 11 et 13 vérifient les frontières entre HR, Workforce, Leave, Payroll, Accounting, CRM et Communications. Aucun nouveau CRUD vertical ne doit démarrer avant cette vague.

### Vague C — première solution opérationnelle

L’agent 15 commence FuelStation par `station → pompe → compteur → session pompiste → photo ouverture/fermeture → OCR → litres → dépôt → écart → validation manager`. L’agent 22 prépare les read models et l’agent 19 les devices/mobile nécessaires, sans absorber la responsabilité de FuelStation.

### Vague D — deuxième solution opérationnelle

L’agent 16 commence EduManager après validation des contrats de plateforme. Il couvre établissement, classes, élèves, guardians, présence, notes, bulletins et rôles, avec le même niveau d’exigence tenant et RGPD.

### Vague E — durcissement et pilotes

Les agents 20, 21, 22 et 23 renforcent documents, billing, analytics et IA assistive. Les agents 15 et 16 passent en pilote seulement après MAT-007 à MAT-018 et une recette métier signée.

## 7. Règles de collaboration inter-agents

Un agent ne modifie jamais les migrations, contrats, Policies ou routes d’un autre bounded context comme effet secondaire. Si un changement est nécessaire, il ouvre une tâche de contrat ou demande une PR de coordination.

Chaque PR doit indiquer : `BC propriétaire`, `agent`, `issues`, `paths allowed`, dépendances, migrations Laravel, impacts API, tests cross-tenant, tests de sécurité, métriques, rollback et statut des checks.

Les agents doivent utiliser les commandes Laravel existantes, notamment `php artisan leopardo:migrate`, et ne doivent pas introduire une deuxième chaîne Flyway/Prisma/Knex pour le backend Laravel.

Une PR est refusée si elle :

- mélange CRM commercial plateforme et CRM client tenant ;
- introduit une requête directe inter-contexte sans contrat ;
- accepte `company_id` comme preuve d’autorité ;
- crée une donnée tenant sans `TenantManager` ;
- ajoute une migration non Laravel ;
- stocke un token provider en clair ;
- ajoute un cache sans clé tenant ;
- modifie un relevé FuelStation ou une note EduManager sans audit/version ;
- fusionne un changement sensible avec une CI requise rouge.

## 8. Definition of Done par bounded context

Un contexte est considéré comme mature seulement si ses invariants métier, migrations Laravel, routes, Policies, événements, tests, observabilité, documentation, rollback et owner sont présents. Le nombre de fichiers ou d’endpoints ne suffit pas.

La validation minimale est :

```text
architecture guard
  + migration fresh/re-run/rollback
  + tests domaine
  + tests API
  + tests cross-tenant
  + tests sécurité/permissions
  + tests contrats événements
  + performance mesurée
  + audit et observabilité
  + documentation OpenAPI
  + runbook et rollback
  + CI verte
```

## 9. Commandes d’affectation simples

```text
Agent 01 → BC-PLATFORM
Agent 02 → BC-TENANT
Agent 03 → BC-IDENTITY
Agent 04 → BC-HR
Agent 05 → BC-WORKFORCE
Agent 06 → BC-LEAVE
Agent 07 → BC-PAYROLL
Agent 08 → BC-ACCOUNTING
Agent 09 → BC-EXPENSE
Agent 10 → BC-RECRUITMENT
Agent 11 → BC-CRM
Agent 12 → BC-GROWTH
Agent 13 → BC-COMMS
Agent 14 → BC-INTEGRATION
Agent 15 → BC-FUEL
Agent 16 → BC-EDU
Agent 17 → BC-RETAIL
Agent 18 → BC-FIELD
Agent 19 → BC-DEVICE
Agent 20 → BC-DOCUMENTS
Agent 21 → BC-BILLING
Agent 22 → BC-ANALYTICS
Agent 23 → BC-AI
```

Le coordinateur de programme ne remplace pas les propriétaires de contexte. Il contrôle l’ordre, les dépendances, la CI, les conflits et les releases ; il ne fusionne pas directement les responsabilités métier dans un service partagé.

## 10. Décision recommandée

Cette numérotation doit devenir la référence unique dans les issues, les branches, les PRs, les CODEOWNERS, les plans agents et les documents d’architecture. Elle permet de conserver une seule `main`, de travailler en parallèle lorsque les contrats sont stables et d’éviter que les solutions verticales ne contaminent la plateforme ou que le CRM client ne remplace le CRM commercial Leopardo.
