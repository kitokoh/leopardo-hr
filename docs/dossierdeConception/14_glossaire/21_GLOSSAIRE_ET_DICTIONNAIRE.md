# GLOSSAIRE ET DICTIONNAIRE TECHNIQUE — LEOPARDO RH
# Version 1.1 | Mars 2026

---

## A — TERMES MÉTIER

**Absence**
Période pendant laquelle un employé n'est pas en poste, déclarée et enregistrée dans le système.
Peut être payée (congé payé) ou non payée selon le type d'absence. Voir `absence_types`.

**Acquisition de congés (Accrual)**
Mécanisme par lequel un employé gagne des jours de congé au fur et à mesure du temps travaillé.
Taux configurable par pays dans `hr_model_templates.leave_rules.accrual_rate_monthly`.
Exemple Algérie : 2.5 jours/mois travaillé.

**Avance sur salaire (Salary Advance)**
Montant versé à l'employé avant la fin du mois, à rembourser par déductions futures sur la paie.
Cycle de vie : `pending → approved → active → repaid` (ou `rejected`).
`active` = remboursement en cours sur les bulletins de paie.

**Audit Log**
Trace immuable de toute action sensible dans le système (qui, quoi, quand, ancienne/nouvelle valeur).
Stocké en base dans `audit_logs`. Rétention 24 mois. Non modifiable.

---

## B — TERMES TECHNIQUES

**Bearer Token**
Format d'authentification HTTP : `Authorization: Bearer {token}`.
Leopardo RH utilise des tokens Sanctum opaques (pas JWT) — stockés hashés en base.

**Brut Imposable (Gross Taxable)**
Salaire brut total après déduction des cotisations salariales, avant application de l'IR.
`gross_taxable = gross_total - cotisations_salariales_total`

---

## C — TERMES TECHNIQUES

**Check-in / Check-out**
Pointage d'arrivée (check-in) et de départ (check-out) d'un employé.
Méthodes supportées : `mobile` (app Flutter), `qrcode`, `biometric` (ZKTeco), `manual` (gestionnaire).

**Company Scope (Global Scope Laravel)**
Mécanisme Laravel qui injecte automatiquement `WHERE company_id = ?` sur toutes les requêtes
des modèles Tenant en mode `shared`. Défini dans `app/Scopes/CompanyScope.php`.
En mode `schema`, ce scope est désactivé — l'isolation est physique (PostgreSQL).

**Cotisations sociales**
Prélèvements obligatoires sur le salaire au profit des organismes sociaux.
Deux types : **salariales** (à la charge de l'employé, déduites du brut) et
**patronales** (à la charge de l'employeur, non déduites du bulletin employé).
Taux définis par pays dans `hr_model_templates.cotisations`.

---

## D — TERMES TECHNIQUES

**Dépendance circulaire (Circular Dependency)**
Problème résolu en v2.0 de l'ERD : `departments.manager_id → employees.id` ET
`employees.department_id → departments.id` créaient un cercle impossible à satisfaire
à l'insertion. Solution : `departments.manager_id` est nullable et défini APRÈS
la création des employés.

---

## E — TERMES MÉTIER

**Employé (Employee)**
Toute personne enregistrée dans le système d'une entreprise cliente, quel que soit son rôle
(employé de base, manager, RH, comptable, superviseur). Tous stockés dans `employees`.
Les Super Admins (éditeur SaaS) sont dans `super_admins` (schéma public).

**Enterprise (Plan)**
Plan tarifaire haut de gamme (199 EUR/mois). Seul plan bénéficiant de l'isolation physique
PostgreSQL (`tenancy_type = 'schema'`). Employés illimités.

---

## F — TERMES TECHNIQUES

**FCM Token (Firebase Cloud Messaging)**
Identifiant unique d'un appareil mobile pour recevoir les notifications push.
Stocké dans `employee_devices.fcm_token`. Mis à jour à chaque connexion Flutter.
Purgé automatiquement si non vu depuis 90 jours.

**FormRequest (Laravel)**
Classe de validation Laravel. Une FormRequest par action de l'API.
Avantage : sépare la validation de la logique Controller. Retourne automatiquement
une erreur 422 avec les détails si la validation échoue.

---

## G — TERMES MÉTIER

**Global Scope** → Voir *Company Scope*

**GPS Validation**
Vérification que l'employé se trouve à moins de `gps_radius_m` mètres du site de travail
lors du pointage mobile. Configurable : `attendance.gps_enabled` dans `company_settings`.

---

## H — TERMES MÉTIER

**Heures supplémentaires (Overtime / HS)**
Heures travaillées au-delà du seuil configuré (`overtime_threshold_daily` ou `_weekly`).
Calculées par `AttendanceService`. Incluses dans la paie via `PayrollService`.

**HR Model Template**
Modèle de configuration RH par pays stocké dans `hr_model_templates`.
Contient : taux de cotisations, tranches IR, règles de congé, calendrier des jours fériés.
7 pays disponibles en Phase 1 : DZ, MA, TN, FR, TR, SN, CI.

---

## I — TERMES TECHNIQUES

**Inertia.js**
Bibliothèque reliant Laravel (backend) et Vue.js (frontend) sans nécessiter une API REST dédiée
pour les pages web. Les contrôleurs Laravel retournent des "Inertia responses" au lieu de JSON.
Utilisé pour le frontend web de Leopardo RH (pas pour le mobile Flutter).

**IR (Impôt sur le Revenu)**
Taxe prélevée sur le salaire selon un barème progressif par tranches.
Barème défini par pays dans `hr_model_templates.ir_brackets`.
Calculé sur le `gross_taxable` (après cotisations salariales).

**Isolation physique** → Voir *Mode Schema*

**Isolation logique** → Voir *Mode Shared*

---

## J — TERMES TECHNIQUES

**Job / Queue Worker**
Tâche asynchrone exécutée en arrière-plan par Laravel Horizon (Redis + Supervisor).
Exemples : envoi d'emails, push FCM, génération PDF, calcul de paie mensuel.

---

## L — TERMES MÉTIER

**Leave Balance**
Solde de congés disponible d'un employé, en jours décimaux.
Stocké dans `employees.leave_balance`. Tracé ligne par ligne dans `leave_balance_logs`.

**Lookup Table** → Voir *user_lookups*

---

## M — TERMES TECHNIQUES

**Manager Role**
Sous-rôle du rôle `manager` stocké dans `employees.manager_role`.
Valeurs : `principal` | `rh` | `dept` | `comptable` | `superviseur`.
Détermine le périmètre des permissions dans la matrice RBAC.

**Mock JSON**
Fichiers JSON statiques simulant les réponses de l'API, utilisés par Flutter en mode développement.
Stockés dans `mobile/assets/mock/`. Permettent de développer le mobile sans API réelle.
Bascule via une variable d'environnement Flutter (`USE_MOCK=true`).

**Mode Schema (Enterprise)**
Stratégie multitenancy où chaque entreprise a son propre schéma PostgreSQL dédié
(`company_{uuid_court}`). Isolation physique — aucune donnée d'autres clients dans ce schéma.
Activé uniquement pour le plan Enterprise. Déclenché par `TenantMiddleware` via `SET search_path`.

**Mode Shared (Trial / Starter / Business)**
Stratégie multitenancy où toutes les entreprises partagent le même schéma `shared_tenants`.
L'isolation est logique : colonne `company_id` sur chaque table + Global Scope automatique.

**Monorepo**
Structure où le code backend (`api/`), mobile (`mobile/`) et la documentation (`docs/`)
coexistent dans un seul dépôt Git. Facilite la cohérence pour les agents IA qui voient
l'ensemble du projet en même temps.

---

## N — TERMES TECHNIQUES

**Net à Payer (Net Payable)**
Montant final versé à l'employé après toutes les déductions.
`net_payable = gross_total - cotisations_salariales - IR - avances - absences - pénalités`

---

## O — TERMES MÉTIER

**Onboarding**
Processus guidé en 4 étapes que suit un nouveau client après inscription Trial.
Étapes : 1. Ajouter employés → 2. Configurer planning → 3. Télécharger app → 4. Premier pointage.
Suivi via `company_settings.onboarding_completed` (boolean).

---

## P — TERMES TECHNIQUES

**Pest PHP**
Framework de test PHP moderne (syntaxe fonctionnelle) utilisé pour les tests backend.
Remplace PHPUnit dans l'écriture, mais tourne sur PHPUnit en interne.
Exemple : `it('creates employee', fn() => ...)` au lieu de `public function test_creates_employee()`.

**Plan Limit Middleware**
Middleware Laravel (`PlanLimitMiddleware`) qui vérifie les limites du plan avant toute création
de ressource. Exemple : bloque la création d'un 21ème employé pour un plan Starter (max 20).
Voir spec : `07_securite_rbac/11_PLAN_LIMIT_MIDDLEWARE.md`.

**Pointage** → Voir *Check-in / Check-out*

---

## R — TERMES TECHNIQUES

**RBAC (Role-Based Access Control)**
Contrôle d'accès basé sur les rôles. Leopardo RH définit 7 rôles : Super Admin, Manager Principal,
Manager RH, Manager Département, Manager Comptable, Superviseur, Employé.
Matrice complète : `07_securite_rbac/10_RBAC_COMPLET.md`.

**Resource (Laravel API Resource)**
Classe de transformation JSON qui contrôle exactement quels champs sont exposés dans les réponses API.
Une Resource par entité (EmployeeResource, PayrollResource, etc.).

**Riverpod**
Bibliothèque de gestion d'état pour Flutter. Choisie pour sa testabilité et son typage fort.
Alternative à Provider, BLoC, GetX.

**RTL (Right-To-Left)**
Direction de lecture de droite à gauche, utilisée pour l'arabe.
Flutter gère le RTL automatiquement avec `Locale('ar')`. Vue.js nécessite `dir="rtl"` sur `<html>`.

---

## S — TERMES TECHNIQUES

**Sanctum (Laravel Sanctum)**
Package d'authentification Laravel. Leopardo RH utilise les **tokens opaques** (pas SPA cookies
pour le mobile) — chaînes aléatoires stockées hashées en base dans `personal_access_tokens`.
Durée : 90 jours mobile, 8h SPA web, 4h Super Admin.

**Schema Name**
Nom du schéma PostgreSQL dédié à une entreprise Enterprise.
Format : `company_{8 premiers caractères de l'UUID}`. Exemple : `company_7c9e6679`.
Stocké dans `companies.schema_name`.

**Search Path (PostgreSQL)**
Instruction PostgreSQL `SET search_path TO {schema}, public` qui dirige toutes les requêtes
vers le bon schéma. Exécutée par `TenantMiddleware` à chaque requête authentifiée.

**Seeder**
Script PHP Laravel qui insère des données de référence en base au démarrage.
Seeders : `PlanSeeder`, `LanguageSeeder`, `HRModelSeeder`.
Voir : `18_schemas_sql/05_SEEDERS_ET_DONNEES_INITIALES.md`.

**SSE (Server-Sent Events)**
Protocole HTTP pour pousser des données en temps réel du serveur vers le navigateur.
Utilisé pour les notifications temps réel sur le frontend web Vue.js.
Endpoint : `GET /api/v1/notifications/stream` (Content-Type: text/event-stream).

---

## T — TERMES TECHNIQUES

**Tenant**
Entreprise cliente utilisant la plateforme SaaS. Chaque tenant a ses propres données
isolées (physiquement en mode schema, logiquement en mode shared).

**TenantMiddleware**
Middleware central de Leopardo RH. Responsable de : trouver l'entreprise de l'utilisateur
(via `user_lookups`), vérifier le statut (actif/suspendu), configurer le `search_path`
PostgreSQL, injecter la company dans le contexte de la requête.

**Token ZKTeco**
Token d'authentification permanent assigné à un lecteur biométrique ZKTeco.
Stocké hashé dans `devices.token`. Différent des tokens Sanctum (employés/admins).
Rotation possible via `POST /devices/{id}/rotate-token`.

**Trial**
Période d'essai gratuite de 14 jours (30 jours pour Enterprise).
Entreprise en statut `trial`, plan partagé (`tenancy_type = 'shared'`).
Convertie en payant ou expirée (`status = 'expired'`) à terme.

---

## U — TERMES TECHNIQUES

**user_lookups**
Table critique dans le schéma public PostgreSQL.
Permet de résoudre `email → company_id + employee_id + role` en une seule requête,
sans scanner tous les schémas tenant. Utilisée par le `TenantMiddleware` à chaque login.
Doit être synchronisée avec `employees` via des transactions (voir ERD v2.0 — politique sync).

---

## V — TERMES TECHNIQUES

**Validation (FormRequest)**
→ Voir *FormRequest*

---

## W — TERMES MÉTIER

**Work Days**
Jours travaillés dans la semaine, configurés par planning (`schedules.work_days`).
Format JSONB : `[1,2,3,4,5]` (1=Lundi, 7=Dimanche).
Utilisés pour calculer les jours ouvrables dans les absences et la paie.

---

## Z — TERMES TECHNIQUES

**ZKTeco**
Marque de lecteurs biométriques (empreinte digitale, reconnaissance faciale) très répandue
en Algérie, Maroc et Tunisie. Leopardo RH supporte le protocole Push/Pull ZKTeco via
`ZKTecoService`. Le `zkteco_id` de l'employé est son identifiant dans le lecteur.
