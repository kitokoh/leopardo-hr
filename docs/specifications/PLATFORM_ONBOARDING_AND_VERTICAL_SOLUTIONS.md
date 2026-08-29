# Leopardo Platform — Provisioning, onboarding et solutions verticales

**Statut :** Proposition technique prête pour implémentation par lots
**Base :** dernière tête de `main` vérifiée le 28 août 2026
**Périmètre :** plateforme centrale, onboarding tenant, FuelStation et EduManager
**CRM commercial :** reste dans l’admin plateforme Leopardo
**CRM client :** reste dans les espaces clients et sous l’API tenant
**Migrations :** Laravel et `php artisan leopardo:migrate` exclusivement

## 1. Décision d’architecture

Leopardo doit être conçu comme une **plateforme centrale modulaire** sur laquelle peuvent être activées des solutions opérationnelles sectorielles. La plateforme fournit les capacités communes : identité, tenants, rôles, permissions, RH, présence, paie, CRM client, marketing, comptabilité, documents, notifications, API, audit et intégrations.

Les solutions verticales ajoutent des workflows propres à un secteur. Elles ne remplacent pas les modules transversaux et ne recopient pas leurs règles métier. Une station-service utilise RH, présence, paie, CRM et marketing ; elle ajoute ses propres stations, pompes, shifts, caisses, ventes et incidents. Un établissement scolaire utilise RH, CRM et marketing ; il ajoute élèves, responsables légaux, inscriptions, classes, évaluations, notes et bulletins.

Le CRM commercial Leopardo reste dans `Platform`/`Marketing` et dans l’admin plateforme. Le CRM interne client reste dans `CRM`, dans l’espace client et avec `company_id` obligatoire. L’orchestrateur de provisioning ne fusionne jamais ces deux contextes.

```text
Leopardo Platform
├── Platform Core
│   ├── Identity
│   ├── Tenant
│   ├── Authorization
│   ├── Feature Flags
│   ├── Audit
│   └── Integration Runtime
├── Shared Business Modules
│   ├── HR
│   ├── Attendance
│   ├── Payroll
│   ├── CRM client
│   ├── Marketing client
│   ├── Accounting
│   ├── Documents
│   └── Notifications
└── Operational Solutions
    ├── FuelStation
    ├── EduManager
    ├── Retail POS
    └── FieldService (futur)
```

## 2. Tenant Provisioning Orchestrator

### 2.1 Responsabilité

`TenantProvisioningOrchestrator` est le composant applicatif responsable de transformer une intention d’onboarding confirmée en configuration tenant cohérente. Il ne contient pas les règles métier de Payroll, CRM ou d’une verticale. Il coordonne des étapes idempotentes appartenant à leurs modules.

Le provisioning doit être :

| Propriété | Exigence |
|---|---|
| Idempotent | Une même commande ou clé ne crée jamais deux tenants, rôles, activations ou seeds. |
| Rejouable | Une étape échouée peut reprendre depuis son dernier état durable. |
| Audité | Chaque décision, activation, erreur et compensation est tracée sans exposer de secrets. |
| Fail-closed | Une permission, un module ou une connexion non validée reste désactivée. |
| Tenant-safe | Le tenant cible est résolu côté serveur et vérifié à chaque étape. |
| Observable | Durée, statut, retries, lag et erreur sont mesurables par correlation ID. |
| Réversible | Une activation incomplète peut être suspendue ou compensée sans suppression aveugle. |

### 2.2 Frontières

L’orchestrateur peut créer ou configurer :

- une entreprise tenant après validation de la plateforme ;
- les memberships, rôles et permissions par défaut ;
- les modules et solutions activés par feature flag ;
- les paramètres régionaux, devise, langue, timezone et fiscalité déclarée ;
- les seeds de configuration non sensibles ;
- les jobs d’initialisation et read models ;
- les connexions d’intégration après consentement et secret validé.

Il ne doit pas :

- calculer de paie ;
- créer un contact CRM client sans décision explicite du client ;
- appeler directement une table interne d’un autre module ;
- recevoir un `company_id` comme preuve d’autorité ;
- stocker un token WhatsApp ou OAuth en clair ;
- activer une campagne marketing ou un canal sans consentement et feature flag ;
- créer une branche Git ou modifier des migrations hors Laravel.

### 2.3 Contrats applicatifs

```php
interface TenantProvisioningOrchestrator
{
    public function start(StartProvisioningCommand $command): ProvisioningRun;
    public function resume(ProvisioningRunId $runId): ProvisioningRun;
    public function pause(ProvisioningRunId $runId, PauseReason $reason): void;
    public function cancel(ProvisioningRunId $runId, CancellationReason $reason): void;
    public function retryStep(ProvisioningRunId $runId, ProvisioningStepName $step): void;
}
```

Les commandes doivent contenir un `idempotency_key`, un `correlation_id`, un acteur authentifié, un plan d’activation, la version de la spec d’onboarding et les valeurs de configuration validées. Les champs inconnus sont rejetés ; les listes de modules et de solutions sont des allowlists serveur.

### 2.4 États du provisioning

```text
DRAFT
  → REVIEW_REQUIRED
  → CONFIRMED
  → RUNNING
  → WAITING_EXTERNAL_INPUT
  → PAUSED
  → COMPLETED

RUNNING → FAILED_RETRYABLE → RUNNING
RUNNING → FAILED_PERMANENT → COMPENSATION_REQUIRED
RUNNING → CANCEL_REQUESTED → COMPENSATING → CANCELLED
```

Une transition est réalisée par une commande autorisée et crée un événement audité. Aucun contrôleur ne doit modifier directement le statut par assignment arbitraire.

### 2.5 Étapes standard

| Code | Étape | Propriétaire | Rejouable |
|---|---|---|---:|
| `validate_request` | Valider payload, plan, secteur et capacités disponibles. | Platform | Oui |
| `create_tenant` | Créer le tenant et son contexte de schéma. | Tenant | Avec clé unique |
| `apply_tenant_schema` | Exécuter les migrations Laravel tenant. | Tenant/DB | Oui |
| `create_owner` | Créer l’utilisateur propriétaire ou envoyer une invitation. | Identity | Oui |
| `seed_roles` | Installer rôles et permissions minimaux. | Authorization | Oui |
| `activate_modules` | Activer RH, Attendance, Payroll, CRM, Marketing, Accounting. | Platform | Oui |
| `install_solution` | Installer la configuration FuelStation/EduManager. | Vertical | Oui |
| `seed_configuration` | Créer sites, pipelines, statuts et paramètres bornés. | Modules | Oui |
| `schedule_read_models` | Lancer les recalculs nécessaires. | Reporting | Oui |
| `configure_integrations` | Préparer des connexions sans persister de secrets en clair. | Integration | Oui |
| `send_welcome` | Envoyer une notification contrôlée. | Notification | Oui |
| `finalize` | Vérifier invariants et publier le résultat. | Platform | Oui |

Chaque étape possède une clé `(provisioning_run_id, step_code, version)`, un statut, un nombre de tentatives, un résultat non sensible, une erreur normalisée et des timestamps.

### 2.6 Persistance Laravel

Les tables de contrôle sont globales au niveau `public` ou `shared_tenants` selon les conventions réelles du dépôt ; les tables de données client restent tenant-scoped. Les migrations doivent suivre les conventions existantes et ne doivent pas être ajoutées comme scripts Flyway séparés.

```text
provisioning_runs
- id
- idempotency_key UNIQUE
- requested_by
- target_company_id NULL avant création
- sector_code
- plan_version
- status
- current_step
- correlation_id
- requested_at / started_at / completed_at
- last_error_code
- metadata_redacted JSONB borné

provisioning_steps
- id
- provisioning_run_id
- step_code
- step_version
- status
- attempt_count
- idempotency_key
- started_at / completed_at
- output_redacted JSONB borné
- error_code / error_message_safe
- UNIQUE(run_id, step_code, step_version)

provisioning_activations
- id
- company_id
- module_code
- solution_code NULL
- activation_state
- source_run_id
- activated_at / deactivated_at
- UNIQUE(company_id, module_code, solution_code)
```

Les JSON de métadonnées ne remplacent pas les colonnes d’invariants. Ils sont bornés en taille, filtrés, non utilisés pour l’autorisation et ne contiennent ni tokens ni PII inutile.

### 2.7 Exécution asynchrone

La requête HTTP crée le run et retourne `202 Accepted` avec `run_id`. Un job `RunProvisioningStep` reprend chaque étape avec `TenantManager::withinTenant()` lorsque l’étape cible des données tenant. Un verrou distribué ou verrou SQL empêche deux workers de traiter simultanément le même run.

Les jobs tenant doivent implémenter le contrat `TenantScopedJob` et fournir `tenantCompanyId()`. Le worker doit restaurer `search_path` et `current_company` dans un bloc `finally`. Un job sans tenant explicite est refusé avant toute requête CRM ou verticale.

### 2.8 Échecs et compensation

Une erreur transitoire est rejouée avec backoff borné. Une erreur de validation devient permanente et demande une correction utilisateur. Une étape déjà réussie est reconnue par sa clé d’idempotence. Une étape suivante ne s’exécute pas si sa dépendance n’est pas `COMPLETED`.

La compensation ne supprime pas aveuglément le tenant. Elle peut désactiver un module, révoquer une invitation, annuler une activation ou marquer un run pour intervention. Les données créées doivent suivre leur politique de rétention et d’audit.

## 3. API de provisioning

Les routes suivantes sont réservées à l’admin plateforme ou à l’utilisateur propriétaire dans les limites autorisées. Elles ne réutilisent pas les routes du CRM client.

| Méthode | Route | Autorité |
|---|---|---|
| `POST` | `/api/v1/platform/provisioning/runs` | Platform admin ou onboarding autorisé |
| `GET` | `/api/v1/platform/provisioning/runs/{run}` | Actor autorisé, projection filtrée |
| `POST` | `/api/v1/platform/provisioning/runs/{run}/resume` | Platform operator |
| `POST` | `/api/v1/platform/provisioning/runs/{run}/pause` | Platform operator |
| `POST` | `/api/v1/platform/provisioning/runs/{run}/retry-step` | Platform operator |
| `POST` | `/api/v1/platform/provisioning/runs/{run}/cancel` | Platform operator |
| `GET` | `/api/v1/platform/catalog/sectors` | Public/auth selon exposition |
| `GET` | `/api/v1/platform/catalog/solutions` | Public/auth selon exposition |
| `GET` | `/api/v1/tenant/configuration` | Tenant member autorisé |
| `PATCH` | `/api/v1/tenant/configuration/modules/{module}` | Tenant owner/admin |

Les routes `/api/v1/crm/*` restent réservées au CRM client tenant. Les routes du CRM commercial existant sous Platform/Marketing restent inchangées.

## 4. Parcours onboarding UI/UX

### 4.1 Principes d’expérience

L’onboarding doit être guidé par le secteur et les objectifs, pas par une liste de tables ou de modules techniques. Le client choisit d’abord son activité, décrit son organisation, voit les solutions recommandées, confirme les modules et configure les premiers utilisateurs.

Le parcours doit afficher la progression, permettre de revenir en arrière avant confirmation, conserver un brouillon, expliquer chaque activation et ne jamais activer silencieusement une intégration sensible.

### 4.2 Étapes visibles

```text
Bienvenue
  → Secteur
  → Organisation
  → Solution recommandée
  → Modules
  → Sites et paramètres régionaux
  → Utilisateurs et rôles
  → Intégrations
  → Résumé et confirmation
  → Provisioning en cours
  → Espace prêt
```

#### Écran 1 — Bienvenue

L’écran explique la valeur de Leopardo et propose `Configurer mon espace`, `Voir une démonstration` et `Reprendre une configuration`. Il indique que les choix peuvent être ajustés plus tard et que les modules sensibles nécessitent une confirmation.

#### Écran 2 — Secteur

L’utilisateur choisit une carte de secteur : station-service, commerce, éducation, maintenance, sécurité, nettoyage, distribution ou autre. Une recherche et une option `Mon secteur n’est pas listé` évitent de bloquer l’inscription.

#### Écran 3 — Organisation

Les champs demandés sont : nombre de sites, nombre approximatif d’employés, équipes mobiles, pays, devise, timezone, type de clients et processus prioritaires. Les valeurs servent à recommander une configuration ; elles ne déclenchent pas de facturation ou de droit non confirmé.

#### Écran 4 — Solution

L’interface affiche une solution principale et ses bénéfices. Pour une station-service : `FuelStation`. Pour une école : `EduManager`. Chaque carte indique les modules requis, optionnels, dépendances, maturité et données sensibles.

#### Écran 5 — Modules

Les modules sont présentés en deux groupes :

| Groupe | Exemples |
|---|---|
| Recommandés | RH, Attendance, CRM, Documents, Notifications. |
| Optionnels | Payroll, Marketing, Accounting, WhatsApp, Fleet, POS. |

Les modules requis par une solution sont signalés mais restent expliqués. Le client peut différer les intégrations sensibles. Le CRM commercial Leopardo n’apparaît jamais dans cette liste : il appartient à l’admin plateforme.

#### Écran 6 — Sites et paramètres

L’utilisateur ajoute au moins un site, sa timezone, son adresse contrôlée, ses horaires et ses règles opérationnelles. Les champs géographiques sont validés et les données sensibles minimisées.

#### Écran 7 — Utilisateurs et rôles

Le propriétaire invite les premiers utilisateurs. Les rôles sont proposés selon la solution, mais les permissions effectives restent serveur-side. Une matrice montre ce que chaque rôle peut lire, créer, approuver, exporter ou administrer.

#### Écran 8 — Intégrations

L’utilisateur peut choisir `Configurer plus tard`. Pour WhatsApp, l’écran explique Business API/BSP officiel, numéro, consentement, templates, fenêtre de service, coûts éventuels et traitement des webhooks. Aucun token n’est affiché ou conservé côté frontend.

#### Écran 9 — Résumé

Le résumé présente secteur, solution, modules, sites, rôles, intégrations, données traitées et éléments différés. L’utilisateur doit confirmer explicitement l’activation et accepter les politiques correspondantes.

#### Écran 10 — Provisioning

L’écran affiche les étapes avec statut `En attente`, `En cours`, `Terminé`, `À corriger` ou `En pause`. Il expose un message actionnable, un bouton `Réessayer` lorsque permis et un lien support. Il ne montre jamais de stack trace, token ou payload provider.

#### Écran 11 — Espace prêt

L’écran propose les prochaines actions : ajouter un compte CRM, créer un employé, configurer un site, inviter un manager, importer des données ou ouvrir la documentation. Il affiche les modules actifs et les tâches restantes.

### 4.3 Responsive et accessibilité

Le parcours doit fonctionner desktop et mobile web, avec clavier, focus visible, labels explicites, erreurs associées aux champs, contraste conforme, états de chargement et mode hors ligne non autorisé pour la confirmation finale. Les étapes doivent être accessibles par lien direct uniquement si le brouillon appartient à l’utilisateur.

## 5. Solution sectorielle FuelStation

### 5.1 Positionnement

`FuelStation` est une solution opérationnelle pour stations-service et réseaux de stations. Elle relie personnel, shifts, pompes, cuves, ventes, caisses, incidents, maintenance, clients professionnels et reporting. Elle utilise les capacités communes sans les dupliquer.

### 5.2 Modules requis et optionnels

| Capacité | Statut |
|---|---|
| Tenant, Identity, RBAC | Requis |
| HR et employés | Requis |
| Attendance et shifts | Requis |
| Documents et notifications | Requis |
| CRM client | Recommandé |
| Accounting | Recommandé |
| Payroll | Selon pays et activation |
| Marketing/fidélité | Optionnel |
| POS | Optionnel, périmètre propre |
| Fleet/maintenance | Optionnel |
| Connecteurs matériel | Après étude provider |

### 5.3 Modèle métier vertical

```text
fuel_stations
- company_id
- code
- name
- address
- timezone
- status

fuel_pumps
- company_id
- station_id
- code
- product_types
- status

fuel_tanks
- company_id
- station_id
- code
- product_type
- capacity_minor
- current_level_minor
- status

fuel_shifts
- company_id
- station_id
- starts_at
- ends_at
- supervisor_id
- status

fuel_shift_assignments
- company_id
- shift_id
- employee_id
- role_code
- status

fuel_cash_sessions
- company_id
- station_id
- shift_id
- operator_id
- opening_amount_minor
- closing_amount_minor
- variance_minor
- status

fuel_sales
- company_id
- station_id
- pump_id NULL
- cash_session_id NULL
- product_code
- quantity_minor
- unit_price_minor
- total_amount_minor
- currency
- occurred_at
- external_id NULL

fuel_incidents
- company_id
- station_id
- category
- severity
- description_redacted
- status
- reported_by
```

Chaque table possède `company_id` non nullable et des clés/foreign keys composites lorsqu’une relation tenant est possible. Les ventes et événements externes sont idempotents par `(company_id, provider, external_id)`.

### 5.4 Flux opérationnels

Le manager crée une station, définit les équipements, publie les shifts et affecte les employés. Attendance enregistre la présence. Le responsable clôture le shift et la caisse. Les écarts créent une tâche et un événement d’audit. Accounting reçoit uniquement un contrat de synthèse validé ; le CRM reçoit les comptes professionnels et activités commerciales, pas les détails inutiles de caisse.

### 5.5 Applications

La première interface est web responsive pour le responsable et le propriétaire. Une app mobile ciblée peut être prévue pour le pompiste : shift actif, présence, consignes, incident et statut de tâche. Un POS ou kiosk desktop ne doit être livré qu’après validation du matériel, de l’offline, des paiements et de la fiscalité.

## 6. Solution sectorielle EduManager

### 6.1 Positionnement

`EduManager` est une solution pour écoles privées, centres de formation et établissements multi-sites. Elle couvre administration scolaire, inscriptions, communication, personnel, présence et évaluations sans devenir immédiatement un système académique universel.

Les notes et données d’élèves étant sensibles, les permissions et la rétention sont plus strictes que pour un contact CRM ordinaire. Les responsables légaux ne doivent voir que les élèves qui leur sont autorisés.

### 6.2 Modules requis et optionnels

| Capacité | Statut |
|---|---|
| Tenant, Identity, RBAC | Requis |
| HR et personnel | Requis |
| Documents et notifications | Requis |
| CRM client pour prospects d’inscription | Recommandé |
| Marketing admissions | Recommandé avec consentement |
| Classes et inscriptions | Requis EduManager |
| Évaluations et notes | Requis EduManager après fondations |
| Paiements/frais | Optionnel via Accounting |
| Portail parent | V1/V2 |
| WhatsApp | Optionnel et officiellement connecté |

### 6.3 Modèle métier vertical

```text
edu_campuses
- company_id
- code
- name
- address
- timezone
- status

edu_students
- company_id
- student_number
- display_name
- birth_date_encrypted
- status

edu_guardians
- company_id
- contact_reference
- relationship_code
- verified_at

edu_student_guardians
- company_id
- student_id
- guardian_id
- relationship_code
- can_view_grades
- can_receive_notifications

edu_academic_years
- company_id
- code
- starts_on
- ends_on
- status

edu_classes
- company_id
- campus_id
- academic_year_id
- code
- name
- homeroom_teacher_id

edu_enrollments
- company_id
- student_id
- class_id
- enrolled_on
- left_on NULL
- status

edu_subjects
- company_id
- code
- name
- coefficient

edu_assessments
- company_id
- class_id
- subject_id
- title
- assessment_date
- status

edu_grades
- company_id
- assessment_id
- student_id
- value_minor
- scale_code
- comment_redacted
- published_at NULL

edu_report_cards
- company_id
- student_id
- academic_year_id
- period_code
- status
- published_at NULL
```

Les notes doivent être immuables après publication ou corrigées par une opération contrôlée avec justification, audit et version. Les valeurs et échelles sont validées côté serveur. Aucun commentaire libre ne doit accepter un JSON non borné ou des informations médicales sans politique spécifique.

### 6.4 Flux inscription → élève

Le CRM client peut enregistrer un prospect d’inscription et une activité de relance. Une conversion confirmée crée ou rattache un responsable légal et déclenche une commande EduManager. La création d’un élève et d’une inscription est distincte de la conversion CRM et doit être idempotente.

```text
Lead CRM
  → qualification
  → account/contact responsable légal
  → dossier d’inscription
  → élève
  → inscription à une classe
  → communication autorisée
```

Le CRM commercial Leopardo ne voit jamais les prospects scolaires du tenant. Marketing peut consommer des événements d’admission uniquement après consentement et selon la politique du tenant.

### 6.5 Applications

Le portail web cible l’administration, les enseignants et les responsables autorisés. Une app mobile enseignant peut saisir une présence ou une évaluation temporaire, avec synchronisation contrôlée. Un portail parent peut être ajouté plus tard ; il doit utiliser des liens d’accès tenant-scoped et ne jamais exposer une liste globale d’élèves.

## 7. Intégrations CRM, marketing et verticales

Les intégrations passent par des contrats versionnés et des événements :

| Événement | Producteur | Consommateurs autorisés |
|---|---|---|
| `tenant.provisioned.v1` | Platform/Tenant | Modules activés et observabilité |
| `solution.activated.v1` | Provisioning | Vertical, UI, support |
| `crm.lead.qualified.v1` | CRM client | Marketing tenant, reporting |
| `crm.opportunity.won.v1` | CRM client | Accounting/vertical si contrat |
| `fuel.shift.closed.v1` | FuelStation | Attendance, Payroll, Accounting |
| `fuel.cash.closed.v1` | FuelStation | Accounting, audit |
| `edu.enrollment.created.v1` | EduManager | Notifications, Marketing avec consentement |
| `edu.grade.published.v1` | EduManager | Notifications autorisées uniquement |

Un consommateur doit vérifier version, tenant, correlation ID, idempotency key et permissions de la donnée. Les événements sont publiés par outbox après commit ; les effets externes sont rejouables.

## 8. Tâches d’implémentation

### Foundation / provisioning

| ID | Tâche | Dépendances |
|---|---|---|
| `PLAT-001` | Registre des modules, solutions, owners et maturité. | Aucune |
| `PLAT-002` | Contrat `TenantProvisioningOrchestrator` et états. | `PLAT-001` |
| `PLAT-003` | Tables Laravel runs/steps/activations et index. | `PLAT-002` |
| `PLAT-004` | Jobs tenant-scoped et verrouillage de run. | `PLAT-002`, TenantManager |
| `PLAT-005` | Catalogue secteurs/solutions et validation allowlist. | `PLAT-001` |
| `PLAT-006` | Feature flags et kill switch par tenant/solution. | `PLAT-003` |
| `PLAT-007` | Compensation, pause, retry et runbook. | `PLAT-004` |
| `PLAT-008` | API platform provisioning et OpenAPI. | `PLAT-002` |
| `PLAT-009` | Harness deux tenants et tests d’isolation provisioning. | `PLAT-003` |
| `PLAT-010` | UI onboarding brouillon → confirmation. | `PLAT-005`, `PLAT-008` |
| `PLAT-011` | UI progression, erreurs et reprise. | `PLAT-004`, `PLAT-010` |
| `PLAT-012` | Observabilité, alertes et métriques provisioning. | `PLAT-007` |

### FuelStation

| ID | Tâche | Dépendances |
|---|---|---|
| `FUEL-001` | Module et solution manifest FuelStation. | `PLAT-001` |
| `FUEL-002` | Migrations Laravel stations, pompes, cuves et statuts. | `PLAT-003` |
| `FUEL-003` | Shifts, affectations et contrat Attendance. | `FUEL-002` |
| `FUEL-004` | Sessions de caisse et clôture idempotente. | `FUEL-002`, Accounting contract |
| `FUEL-005` | Incidents et tâches opérationnelles. | `FUEL-002` |
| `FUEL-006` | API manager FuelStation et Policies. | `FUEL-002`, `PLAT-008` |
| `FUEL-007` | Dashboard station et états de shift. | `FUEL-003`, `FUEL-004` |
| `FUEL-008` | App mobile pompiste ciblée. | `FUEL-003`, mobile core |
| `FUEL-009` | Étude POS/hardware/offline. | `FUEL-004` |
| `FUEL-010` | Pilote une station, charge et rollback. | `FUEL-006` à `FUEL-009` |

### EduManager

| ID | Tâche | Dépendances |
|---|---|---|
| `EDU-001` | Module et solution manifest EduManager. | `PLAT-001` |
| `EDU-002` | Migrations Laravel campus, élèves et responsables. | `PLAT-003`, RGPD |
| `EDU-003` | Années, matières, classes et inscriptions. | `EDU-002` |
| `EDU-004` | Évaluations, notes versionnées et publication. | `EDU-003` |
| `EDU-005` | Policies enseignant, administration et responsable légal. | `EDU-002` |
| `EDU-006` | API inscriptions et classements bornés. | `EDU-003`, `PLAT-008` |
| `EDU-007` | UI administration scolaire. | `EDU-006` |
| `EDU-008` | Portail enseignant pour présence/notes. | `EDU-004`, mobile core |
| `EDU-009` | Notifications parents avec consentement. | `EDU-005`, Marketing |
| `EDU-010` | Pilote établissement, confidentialité et rollback. | `EDU-006` à `EDU-009` |

## 9. Definition of Done commune

Une tâche est terminée uniquement lorsque le code est dans le module propriétaire, les migrations Laravel sont exécutables par `leopardo:migrate`, les index et contraintes sont testés, les Requests valident strictement les entrées, les Policies vérifient le tenant, les erreurs sont sûres, les événements sont versionnés et les tests négatifs sont présents.

Les routes doivent être documentées dans OpenAPI. Les jobs doivent être tenant-scoped et idempotents. Les exports, caches, read models, webhooks et notifications doivent porter le contexte tenant. Les logs ne doivent pas exposer de PII inutile, secrets ou payloads provider.

Chaque PR doit passer les guards de structure, migrations, PHPStan strict, Pint, tests ciblés, tests cross-tenant, contrat OpenAPI et contrôles de sécurité concernés. Les changements doivent rester fusionnables sur `main`; aucune verticale ne peut contourner les checks en raison d’un besoin produit.

## 10. Découpage des dépôts, branches et releases

Il ne faut pas maintenir une branche `platform` et une branche `solutions` pendant des mois. Le dépôt reste un monorepo et les branches représentent des changements courts :

```text
main
├── feature/plat-002-provisioning-contract
├── feature/plat-010-onboarding-ui
├── feature/fuel-002-station-schema
└── feature/edu-002-student-schema
```

Les releases peuvent ensuite être composées de la plateforme et de solution packs activés par configuration :

```text
Leopardo Platform release
+ FuelStation solution pack
+ RH / Attendance / Payroll
+ Customer CRM / Marketing
```

ou :

```text
Leopardo Platform release
+ EduManager solution pack
+ RH / Documents / Customer CRM
+ Marketing admissions
```

Le versionnement des solutions doit rester compatible avec celui de la plateforme. Une solution ne peut exiger une API non supportée ; un manifest déclare ses dépendances, migrations, permissions, événements, feature flags et version minimale.

## 11. Gates avant activation client

| Gate | Vérification |
|---|---|
| Tenant | Aucun accès cross-tenant dans HTTP, jobs, cache, exports ou événements. |
| Migration | Fresh, re-run, provisioning et rollback Laravel validés. |
| Permissions | Matrice allow/deny testée par rôle et solution. |
| Données | PII classifiée, rétention et anonymisation documentées. |
| Integration | Signatures, idempotence, secrets et retries validés. |
| Performance | p95/p99, N+1, queue lag et exports mesurés. |
| Pilot | Feature flag, kill switch, support et rollback disponibles. |
| CRM dual | CRM commercial plateforme inchangé et tests de non-régression verts. |

## 12. Première trajectoire recommandée

La première livraison doit se limiter à l’orchestrateur, à l’onboarding et à une verticale pilote. Je recommande de commencer par **FuelStation** si un partenaire opérationnel réel est disponible, car la valeur de la jonction personnel/shift/présence/caisse/client est forte. EduManager doit commencer par administration, inscriptions et permissions avant d’activer les notes et bulletins.

L’ordre global est :

```text
PLAT-001/002/003/004/005
        ↓
PLAT-006/007/008/009
        ↓
PLAT-010/011/012
        ↓
FUEL-001/002/003 ou EDU-001/002/003
        ↓
API + UI + tests + pilote
```

Cette stratégie permet à Leopardo de devenir une plateforme adaptable sans transformer le monorepo en ensemble de branches divergentes. Elle conserve la stabilité des modules existants, protège le CRM commercial de Leopardo, place le CRM client dans les espaces clients et donne un cadre clair pour ajouter des solutions sectorielles sans dupliquer le cœur métier.

## 13. Exigence critique FuelStation — relevés de compteur par pompe

### 13.1 Cas d’usage obligatoire

Un pompiste doit pouvoir déclarer précisément qu’à une heure donnée, une pompe déterminée affichait une valeur déterminée sur un compteur déterminé. Il doit ensuite pouvoir déclarer une nouvelle valeur pour cette même pompe et ce même compteur à une autre heure. Le système doit conserver les deux relevés, identifier l’opérateur, enregistrer le site, la pompe, le compteur, la date et l’heure, puis calculer la différence entre les deux valeurs lorsque la séquence est cohérente.

Exemple métier :

```text
Station : Station ABC
Pompe : P-04
Compteur : C-04-A
Pompiste : opérateur-123

08:00 → relevé 125 430,20 litres
16:00 → relevé 125 612,80 litres

Volume mesuré sur la période : 182,60 litres
```

Le nombre affiché par le compteur est un relevé cumulatif et non une vente saisie manuellement. Le volume de période est donc la différence entre le relevé courant et le relevé précédent, avec conservation de la précision native du compteur. Le système doit distinguer compteur mécanique, électronique, totalisateur principal, totalisateur secondaire et compteur de test si le matériel le nécessite.

### 13.2 Modèle de données complémentaire

```text
fuel_meter_registers
- id
- company_id
- station_id
- pump_id
- meter_code
- meter_type
- product_code
- unit_code
- precision_scale
- rollover_limit NULL
- installed_at
- retired_at NULL
- status
- UNIQUE(company_id, pump_id, meter_code, status_active_guard)

fuel_meter_readings
- id
- company_id
- station_id
- pump_id
- meter_id
- reading_value_minor
- reading_unit
- captured_at_utc
- captured_at_station_local
- timezone
- captured_by_employee_id
- shift_id NULL
- source_code              # operator, import, device, correction
- device_reference NULL
- idempotency_key
- status                   # submitted, accepted, rejected, corrected
- correction_reason NULL
- created_at
- UNIQUE(company_id, idempotency_key)
- INDEX(company_id, meter_id, captured_at_utc DESC)
- INDEX(company_id, pump_id, captured_at_utc DESC)

fuel_meter_intervals
- id
- company_id
- meter_id
- previous_reading_id
- current_reading_id
- previous_value_minor
- current_value_minor
- delta_minor
- interval_seconds
- calculated_at
- calculation_status       # valid, rollover, anomaly, pending_review
- UNIQUE(company_id, previous_reading_id, current_reading_id)
```

`company_id` est obligatoire sur chaque table. Les foreign keys composites doivent empêcher de relier une lecture à une pompe ou un meter d’un autre tenant. Les valeurs monétaires et volumétriques sont stockées en unités mineures entières ou en `numeric` contrôlé selon la précision du compteur ; les flottants binaires sont interdits pour le calcul métier.

### 13.3 Règles de cohérence

Une lecture est refusée si la pompe, le compteur, la station ou l’employé n’appartiennent pas au tenant courant. L’heure reçue est normalisée en UTC, mais l’heure locale de la station est conservée pour l’affichage et l’audit. Une lecture future au-delà d’une dérive configurée est rejetée ou soumise à revue. Deux relevés identiques peuvent être acceptés uniquement s’ils ont des clés d’idempotence distinctes et un motif opérationnel compatible.

Lorsqu’un relevé courant est inférieur au relevé précédent, le système ne doit pas accepter silencieusement la différence négative. Il doit classer l’intervalle comme `anomaly`, puis proposer une revue pour remise à zéro, remplacement du compteur, rollover déclaré ou correction autorisée. Une correction ne supprime jamais le relevé original ; elle crée une nouvelle version, exige un motif et laisse une trace d’audit.

Le calcul d’intervalle doit vérifier :

| Contrôle | Résultat |
|---|---|
| Même tenant, station, pompe et compteur | Sinon rejet cross-tenant ou incohérence. |
| `current.captured_at > previous.captured_at` | Sinon statut `anomaly`. |
| Valeur courante supérieure ou rollover documenté | Sinon revue obligatoire. |
| Shift et opérateur cohérents | Avertissement ou blocage selon politique station. |
| Delta sous le seuil de capacité configuré | Sinon anomalie opérationnelle. |
| Idempotency key déjà traitée | Retour du résultat existant, sans doublon. |

### 13.4 API métier

```text
POST /api/v1/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/readings
GET  /api/v1/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/readings
GET  /api/v1/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/intervals
POST /api/v1/fuel-station/meter-readings/{reading}/corrections
POST /api/v1/fuel-station/meter-intervals/{interval}/review
```

Le POST exige `reading_value`, `unit`, `captured_at`, `idempotency_key` et éventuellement `shift_id`/`device_reference`. Le serveur résout station, pompe et meter dans le contexte tenant authentifié ; il n’accepte pas un `company_id` fourni par le client comme autorité. Les Requests Laravel appliquent une allowlist stricte des unités, formats de date, bornes numériques et motifs de correction.

La réponse doit indiquer `accepted`, `pending_review` ou `rejected`, le relevé précédent trouvé, le delta lorsqu’il est calculable, la raison d’une anomalie et le `correlation_id`. Elle ne doit pas exposer de stack trace ou de données d’un autre tenant.

### 13.5 Écran pompiste

L’écran mobile ou kiosk doit être utilisable en quelques secondes :

```text
[Station ABC] [Shift 08:00–16:00]

Pompe : [ P-04 ▼ ]
Compteur : [ C-04-A ▼ ]
Valeur affichée : [ 125430.20 ]
Heure du relevé : [ maintenant ▼ ]

[Prendre une photo facultative selon politique]
[Enregistrer le relevé]
```

Après enregistrement, l’interface affiche immédiatement la dernière valeur, la valeur précédente, le delta calculé, l’heure et l’identité de l’opérateur. Si le compteur est incohérent, l’application doit dire clairement `Relevé enregistré pour vérification` et ne jamais modifier la valeur silencieusement.

L’interface manager affiche la grille station/pompe/compteur, les trous temporels, les anomalies, les deltas, les relevés corrigés et les écarts avec les ventes ou les clôtures de caisse. Les corrections sont séparées des saisies normales et nécessitent une permission dédiée.

### 13.6 Offline et synchronisation

Si l’application pompiste fonctionne hors ligne, elle stocke localement uniquement les relevés nécessaires, chiffrés, avec expiration et identifiant d’appareil. La synchronisation utilise la même `idempotency_key`, conserve l’heure de capture et distingue heure de capture de l’heure de réception serveur. Un conflit ne remplace jamais le relevé serveur ; il crée un état de revue.

Les événements `fuel.meter_reading.accepted.v1`, `fuel.meter_interval.calculated.v1` et `fuel.meter_anomaly.detected.v1` sont publiés par outbox après commit. Attendance peut fournir le contexte de shift, Payroll peut utiliser les heures de présence, Accounting peut consommer des agrégats validés, mais aucun de ces modules ne peut modifier directement un relevé de compteur.

### 13.7 Critères d’acceptation obligatoires

- Un pompiste peut choisir une station, une pompe et un compteur autorisés par son tenant.
- Il peut enregistrer deux relevés à deux heures différentes avec son identité et son shift.
- Le système calcule la différence uniquement lorsque les relevés sont cohérents.
- Une valeur décroissante devient une anomalie ou un rollover explicitement déclaré, jamais une différence négative silencieuse.
- Une requête cross-tenant échoue et est couverte par un test négatif.
- Une répétition de webhook, de synchronisation ou de POST ne crée pas de double relevé.
- Une correction conserve l’original, exige un motif et produit un audit.
- Les index permettent la lecture du dernier relevé et de l’historique par meter sans scan global.
- Les données sont affichées dans la timezone de la station et stockées avec une référence UTC.
- Le parcours mobile reste utilisable lorsque le réseau est instable et rejoue sans doublon.

Cette exigence devient une partie de `FUEL-002`, `FUEL-003`, `FUEL-006`, `FUEL-008` et `FUEL-010`. Une version de FuelStation ne peut être considérée fonctionnelle si elle gère les shifts et les caisses mais ne permet pas de tracer les compteurs par pompe, par opérateur et par heure.

## 14. Flux opérationnel prioritaire — session pompiste de bout en bout

### 14.1 Modèle métier corrigé

Une station-service appartient à un tenant et un tenant peut posséder une ou plusieurs stations. Une station possède plusieurs pompes. Une pompe peut être affectée à un ou plusieurs pompistes successivement au cours de la même journée, mais une même pompe ne peut pas avoir deux affectations actives qui se chevauchent sauf si une permission de supervision autorise explicitement le chevauchement.

Le concept central n’est pas simplement le shift : c’est la `fuel_attendant_session`, c’est-à-dire la période pendant laquelle un pompiste est responsable d’une ou plusieurs pompes déterminées. Une session peut couvrir une pompe ou plusieurs pompes, avec une heure de début et une heure de fin, un shift de référence et un responsable de validation.

```text
Tenant
 └── Station A
      ├── Pompe 01 ─┬─ Session pompiste P1 : 06:00–12:00
      │             └─ Session pompiste P2 : 12:00–18:00
      ├── Pompe 02 ─┬─ Session pompiste P1 : 06:00–12:00
      │             └─ Session pompiste P3 : 12:00–18:00
      └── Pompe 03 ─── Session pompiste P4 : 18:00–06:00
```

Un pompiste peut donc avoir plusieurs pompes dans sa session. Une pompe peut changer de pompiste à midi sans créer une nouvelle station ni perdre l’historique. Les périodes doivent être comparables aux relevés de compteur et aux dépôts financiers.

### 14.2 Parcours du pompiste — application mobile uniquement

Il n’y a pas d’application web pour le pompiste. Le pompiste utilise une application mobile Flutter dédiée, par exemple `leopardo_pump_operator`, qui réutilise les primitives d’authentification, de synchronisation et de design system de `leopardo_core`, mais possède un parcours extrêmement court et adapté au terrain.

#### Début de session

Le pompiste se connecte ou utilise une session appareil sécurisée. L’application obtient depuis l’API les affectations actives autorisées pour la station et l’intervalle courant. Il sélectionne `Commencer ma session`, vérifie la liste des pompes qui lui sont attribuées et photographie l’écran de chaque compteur.

Pour chaque pompe, l’application conserve :

```text
station_id
pump_id
meter_id
attendant_session_id
captured_at_device
captured_at_utc après synchronisation
photo_reference chiffrée et à durée de vie limitée
ocr_raw_result non exposé au pompiste
reading_value extrait
reading_unit
ocr_confidence
operator_confirmation
```

L’OCR est une aide à la saisie, pas une autorité aveugle. L’image doit être cadrée, lisible et associée à la pompe sélectionnée. Si la confiance est inférieure au seuil configuré, l’application demande une nouvelle photo ou une confirmation manuelle contrôlée. Le serveur revalide les bornes, le compteur, la séquence et la permission ; il ne fait jamais confiance à `company_id`, `pump_id` ou au résultat OCR envoyé par le client sans résolution serveur.

#### Fin de session

Le pompiste sélectionne `Terminer ma session`. L’application lui présente uniquement les pompes de sa session encore ouvertes. Il photographie l’écran de chaque compteur, récupère les valeurs finales et affiche pour chaque pompe :

```text
Valeur début
Valeur fin
Litres vendus calculés
Prix applicable si configuré
Montant théorique attendu
Montant réellement déposé
Écart
Motif obligatoire si écart
```

Le pompiste saisit le montant réellement déposé, ou le confirme à partir d’une photo/reçu si le mode de caisse le permet. Le système ne considère pas le bilan comme clôturé tant que chaque pompe et le dépôt financier de la session ne sont pas traités ou explicitement soumis à revue.

### 14.3 Modèle de données spécifique

Les tables Laravel doivent au minimum couvrir :

```text
fuel_attendant_sessions
- id
- company_id
- station_id
- attendant_employee_id
- shift_id NULL
- opened_at_utc
- opened_at_local
- closed_at_utc NULL
- closed_at_local NULL
- timezone
- status: draft/open/submitted/under_review/approved/rejected/closed
- opened_by_device_id
- closed_by_device_id NULL
- closing_reviewed_by NULL
- version
- idempotency_key UNIQUE(company_id, idempotency_key)

fuel_attendant_session_pumps
- id
- company_id
- session_id
- pump_id
- meter_id
- assignment_started_at_utc
- assignment_ended_at_utc NULL
- opening_reading_id NULL
- closing_reading_id NULL
- expected_litres NULL
- expected_amount_minor NULL
- actual_amount_minor NULL
- variance_amount_minor NULL
- variance_reason_code NULL
- status
- UNIQUE(company_id, session_id, pump_id)
- INDEX(company_id, pump_id, assignment_started_at_utc)

fuel_meter_readings
- id
- company_id
- station_id
- pump_id
- meter_id
- session_id
- reading_kind: opening/closing/intermediate/correction
- reading_value_minor
- reading_unit
- captured_at_device
- captured_at_utc
- station_local_date
- captured_by_employee_id
- photo_asset_id NULL
- ocr_attempt_id NULL
- ocr_confidence NULL
- operator_confirmed_at NULL
- validation_status
- idempotency_key
- UNIQUE(company_id, idempotency_key)
- INDEX(company_id, meter_id, captured_at_utc DESC)

fuel_ocr_attempts
- id
- company_id
- reading_id
- provider_code
- provider_request_id
- status
- extracted_value_minor NULL
- extracted_unit NULL
- confidence NULL
- bounding_boxes_redacted JSONB NULL
- error_code NULL
- requested_at / completed_at
- retention_expires_at
- UNIQUE(company_id, provider_code, provider_request_id)

fuel_session_cash_deposits
- id
- company_id
- session_id
- expected_amount_minor
- actual_amount_minor NULL
- currency
- deposit_method
- receipt_asset_id NULL
- declared_at_utc NULL
- declared_by_employee_id NULL
- verified_at_utc NULL
- verified_by NULL
- status: pending/declared/under_review/accepted/rejected
- variance_amount_minor NULL
- variance_reason_code NULL

fuel_session_settlements
- id
- company_id
- session_id
- station_business_date
- total_litres
- expected_amount_minor
- actual_amount_minor
- variance_amount_minor
- pumps_count
- anomalies_count
- status: draft/submitted/manager_reviewed/approved/closed
- UNIQUE(company_id, session_id)
- INDEX(company_id, station_business_date, status)
```

Toutes les relations vers `company_id`, station, pompe, meter, employé, shift, session et reading doivent être vérifiées par clés composites ou par Policies et requêtes tenant-scoped. Une session clôturée est immuable ; une correction crée une version ou un ajustement audité.

### 14.4 Calcul du volume et du montant attendu

Pour chaque pompe de la session :

```text
litres = closing_meter_value - opening_meter_value
expected_amount = litres × applicable_unit_price
```

Le système doit prendre en compte le produit distribué, l’unité et la précision du compteur. Si le prix a changé pendant la session, le calcul doit être segmenté par période de prix ou soumis à une règle de clôture validée ; il ne faut pas appliquer silencieusement le dernier prix à toute la période.

Un compteur décroissant, une photo illisible, une valeur hors capacité, un trou temporel, un prix manquant ou une affectation qui chevauche une autre session produit une anomalie. L’anomalie empêche la clôture automatique mais ne détruit pas la saisie. Le manager peut demander une correction ou approuver une exception avec motif.

Le montant attendu est le montant théorique calculé à partir des litres validés et des prix applicables. Le montant réellement déposé est une déclaration de caisse distincte. L’écart est :

```text
variance = actual_amount - expected_amount
```

Il doit être conservé en unités monétaires mineures, avec devise, méthode de dépôt, preuve éventuelle et motif obligatoire au-delà d’un seuil configuré.

### 14.5 Bilan de journée manager

Le manager ne reçoit pas un message libre non vérifiable : il reçoit un bilan généré depuis les sessions et settlements persistés. Pour une station ou pour toutes les stations de son tenant autorisées, il peut consulter :

- les pompistes présents et leurs horaires ;
- les pompes attribuées à chacun ;
- les relevés d’ouverture et de fermeture ;
- les litres par pompe, pompiste, station et période ;
- le montant attendu ;
- le montant réellement déposé ;
- les écarts et leurs motifs ;
- les sessions non clôturées ;
- les anomalies OCR ou compteur ;
- les corrections et validations ;
- l’état de synchronisation des appareils.

Le bilan de journée est un read model calculé par un job idempotent après soumission de session et après clôture de la journée commerciale. Une notification est envoyée au manager uniquement si le canal est configuré et autorisé. Elle contient un résumé minimal et un lien authentifié vers l’espace client ; elle ne doit pas exposer les données financières détaillées dans un WhatsApp non sécurisé.

```text
Manager mobile
  → sélectionne tenant/station/date autorisés
  → consulte le bilan calculé
  → demande revue ou approuve
  → reçoit alertes de session manquante/anomalie
  → valide la clôture de station
```

Un manager ayant plusieurs stations ne voit que les stations qui lui sont attribuées par Policy. Les totaux multi-stations ne doivent jamais être accessibles par simple modification d’un identifiant dans l’URL.

### 14.6 API mobile pompiste

```text
GET  /api/v1/fuel-station/my/assignments?at={time}
POST /api/v1/fuel-station/attendant-sessions
GET  /api/v1/fuel-station/attendant-sessions/{session}
POST /api/v1/fuel-station/attendant-sessions/{session}/pumps/{pump}/opening-photo
POST /api/v1/fuel-station/attendant-sessions/{session}/pumps/{pump}/closing-photo
POST /api/v1/fuel-station/attendant-sessions/{session}/cash-deposit
POST /api/v1/fuel-station/attendant-sessions/{session}/submit
POST /api/v1/fuel-station/attendant-sessions/{session}/cancel
```

Les endpoints photo utilisent upload signé à durée de vie courte ou endpoint multipart contrôlé, type MIME allowlisté, limite de taille, antivirus/scan, suppression EXIF non nécessaire, chiffrement au repos et rétention courte. L’API retourne un `202 Accepted` pour l’OCR asynchrone, puis l’application consulte un statut ou reçoit une notification sécurisée. Une capture échouée peut être rejouée avec la même clé d’idempotence sans créer une seconde lecture.

### 14.7 API manager mobile

```text
GET  /api/v1/fuel-station/manager/stations
GET  /api/v1/fuel-station/manager/stations/{station}/daily-settlements?date={date}
GET  /api/v1/fuel-station/manager/sessions/{session}
POST /api/v1/fuel-station/manager/sessions/{session}/request-review
POST /api/v1/fuel-station/manager/sessions/{session}/approve
POST /api/v1/fuel-station/manager/sessions/{session}/reject
GET  /api/v1/fuel-station/manager/anomalies
```

Les routes manager utilisent les Policies serveur, filtrent par stations autorisées et renvoient des Resources explicitement allowlistées. Les totaux sont calculés côté serveur depuis un read model validé, jamais depuis des valeurs agrégées envoyées par le mobile.

### 14.8 États de session

```text
DRAFT
  → OPEN
  → SUBMITTED
  → UNDER_REVIEW
  → APPROVED
  → CLOSED

OPEN → CANCELLED
SUBMITTED → REJECTED → OPEN
UNDER_REVIEW → REJECTED → OPEN
```

Une session ne peut devenir `CLOSED` que si toutes les pompes affectées ont un relevé de début et de fin accepté, le dépôt est déclaré ou explicitement marqué manquant, les anomalies sont résolues ou approuvées et le manager autorisé a validé le bilan.

### 14.9 Critères de recette métier obligatoires

- Un tenant peut gérer plusieurs stations sans mélange de données.
- Une station peut affecter plusieurs pompes à un pompiste pour une période donnée.
- Plusieurs pompistes peuvent se succéder sur la même pompe pendant la même journée.
- Un pompiste peut gérer plusieurs pompes simultanément.
- Le pompiste utilise uniquement l’application mobile dédiée ; aucun écran web pompiste n’est requis.
- Le pompiste photographie le compteur au début et à la fin de sa session.
- L’OCR extrait une valeur, expose sa confiance et demande confirmation ou nouvelle photo si nécessaire.
- Le serveur calcule les litres par pompe à partir des relevés validés.
- Le serveur calcule le montant attendu avec le prix applicable et conserve le montant réellement déposé séparément.
- Un écart impose un motif ou une revue selon le seuil configuré.
- Le manager voit le bilan de sa ou ses stations dans son application mobile manager.
- Une session, une photo, un OCR, une synchronisation ou une soumission rejouée ne crée pas de doublon.
- Une requête forgée vers une autre station, pompe, session ou tenant échoue.
- Une session clôturée et un relevé corrigé restent entièrement auditables.
- Le bilan de journée est recalculable et identique après reprise du job.

Cette section rend obsolète toute formulation qui traiterait le relevé de compteur comme une simple saisie isolée. La capacité de référence de FuelStation est désormais : **session pompiste → affectations de pompes → photos compteur ouverture/fermeture → OCR contrôlé → litres → montant attendu → dépôt réel → écart → validation manager → bilan de journée multi-stations**.

## 15. EduManager — clarification de priorité

EduManager reste une solution verticale indépendante et n’est pas modifiée par le flux FuelStation. Ses spécifications élèves, responsables légaux, classes, notes, bulletins, présence, permissions et portail guardian restent valides. Les deux verticales partagent Platform Core, Tenant, Identity, Notifications, CRM client, Marketing et Accounting par contrats, mais ne partagent pas leurs tables métier.
