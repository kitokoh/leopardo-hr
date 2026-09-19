# HEALTHMANAGER_SOLUTION — Spécification de la verticale « Hôpitaux & cliniques privées » (BC-30 HEALTH)

> **Statut :** Pilote — tranche 1 livrée par lots (issues `HC-*` : #7785, #7786, #7787)
> **Modèle :** verticale EduManager (BC-16) — manifest, feature flag scope solution fail-closed,
> catalogue, trait de garde, RBAC par accès, isolation tenant absolue.
> **Périmètre tranche 1 :** fondations de la solution (HC-001), référentiel de structure clinique
> (HC-002 : services médicaux, salles, lits, praticiens & spécialités), registre patients
> (HC-003 : dossier administratif, données de santé, RBAC strict).
> **Hors périmètre tranche 1 :** dossier médical (consultations, prescriptions), rendez-vous,
> hospitalisations/séjours, facturation des actes, portail patient.

---

## 1. Contexte & objectifs

Le tenant cible est un **fondateur / directeur de clinique ou d'hôpital privé**. Il utilise déjà
les modules transversaux Leopardo (RH, Documents, Notifications) et active la solution
**HealthManager** pour configurer sa structure de A à Z : services médicaux (cardiologie,
maternité, urgences…), salles et lits, praticiens rattachés aux employés RH, spécialités —
puis tenir le registre administratif de ses patients.

**Sensibilité maximale** : les données manipulées sont des données de santé (art. 9 RGPD).
Règles non négociables : fail-closed partout, deny-by-default sur les policies, isolation tenant
absolue (`company_id` sur toutes les tables + 404 cross-tenant), archivage au lieu de suppression
physique pour les patients, aucun partage avec le CRM commercial.

## 2. Architecture (HC-001, #7785)

- **Manifest** : `App\Modules\HealthManager\Domain\Solution\HealthManagerManifest`
  (`SolutionManifest`) — code `healthmanager`, maturité `pilot`, modules requis
  `rh, documents, notifications`, modules optionnels `accounting, crm, payroll, attendance,
  planning`, données sensibles déclarées (`sensitiveData()`), permissions
  `health.admin | health.practitioner | health.reception | health.billing`.
- **3 points d'enregistrement obligatoires** (leçons #7220/#7235) :
  1. `SolutionCatalogue` via `HealthManagerServiceProvider` (`$this->app->resolving`) ;
  2. `api/config/feature-flags.php` — `healthmanager` : scope `solution`, défaut `false`,
     `killable: true` ;
  3. `Company::KNOWN_MODULES`.
- **Routes** : `api/routes/modules/health_manager.php`, incluses dans le groupe v1 de
  `api/routes/api.php`, stack `throttle:api, auth:sanctum, token.refresh, tenant,
  throttle:api-plan`, ids `whereNumber`.
- **Garde fail-closed** : trait `ChecksHealthSolution` — `assertSolutionActive()` → 403
  `HEALTH_SOLUTION_INACTIVE` tant que le flag tenant est inactif ; `assertSameTenant()` → 404
  (aucune fuite cross-tenant).
- **Activation** : chemin canonique `SolutionActivator` (idempotent, dépendances vérifiées,
  audit `solution.activated`). À l'activation, le module écoute `SolutionActivated` et seed le
  référentiel de spécialités standards (HC-002), idempotent.
- **Registre BC** : `BC-30 HEALTH` dans `dev-hub/governance/bounded-context-registry.json`
  (chemin `api/app/Modules/HealthManager`, CODEOWNERS dédié).

## 3. Modèle de données (tenant, `company_id` partout)

| Table | Rôle | Invariants portés par le schéma |
|---|---|---|
| `health_departments` | Services médicaux | UNIQUE(company_id, code) ; CHECK status active/inactive/archived ; UNIQUE(id, company_id) pour FK composites |
| `health_rooms` | Salles | FK composite (department_id, company_id) → health_departments ; UNIQUE(company_id, code) |
| `health_beds` | Lits | FK composite (room_id, company_id) → health_rooms ; UNIQUE(company_id, room_id, code) ; CHECK status free/occupied/maintenance |
| `health_specialties` | Spécialités | UNIQUE(company_id, code) ; seedées à l'activation |
| `health_practitioners` | Praticiens | `employee_id` lien RH découplé (pattern edu_teachers, UNIQUE(company_id, employee_id)) ; CHECK status |
| `health_practitioner_specialty` | n-n praticien ↔ spécialité | FK composites des deux côtés ; UNIQUE(company_id, practitioner_id, specialty_id) |
| `health_patients` | Registre patients (HC-003) | UNIQUE(company_id, mrn) ; MRN serveur `PAT-YYYY-NNNN` ; CHECK status active/deceased/archived ; SOFT DELETE (jamais de suppression physique) ; allergies/antécédents/n° d'assuré chiffrés au repos (casts `encrypted`) |

Hiérarchie : un lit appartient à une salle, une salle à un service — garanti par FK composites
(une référence cross-tenant est une violation FK en base).

## 4. API v1 (`/api/v1/health-manager/*`)

CRUD complet, FormRequests validées, `whereNumber` sur les ids, pagination `data`/`meta` :

- `/health-manager/departments` (HC-002)
- `/health-manager/rooms` (HC-002)
- `/health-manager/beds` (HC-002 — statut libre/occupé/maintenance)
- `/health-manager/specialties` (HC-002)
- `/health-manager/practitioners` (HC-002 — `specialty_ids` n-n)
- `/health-manager/patients` (HC-003 — recherche `q` nom/MRN/téléphone paginée,
  DELETE = archivage)

Contrat documenté dans `api/openapi.yaml` (tag `HealthManager`).

## 5. RBAC (deny-by-default)

Mapping V0 des permissions du manifest sur les rôles tenant existants
(`HealthAccess`, pattern `EduAccess`) :

| Permission | Qui | Droits tranche 1 |
|---|---|---|
| `health.admin` | manager `principal`/`rh` ou propriétaire (manager sans sous-rôle) | Gestion complète (structure + patients) |
| `health.reception` | manager `superviseur`/`dept` (accueil / admissions) | Gestion du registre patients ; lecture structure |
| `health.practitioner` | employé référencé ACTIF dans `health_practitioners` | Lecture registre patients + structure |
| `health.billing` | manager `comptable` | Lecture registre patients (couverture assurance) |
| Employé lambda | — | **403 sur tout** |

## 6. Tests & Definition of Done

- Manifest enregistré + activation idempotente + dépendances + audit (`HealthSolutionManifestTest`).
- Fail-closed : flag défaut false, toute route health → 403 `HEALTH_SOLUTION_INACTIVE` sans le flag.
- Matrice 401 / 403 / happy path par ressource ; isolation cross-tenant (404).
- HC-002 : hiérarchie service→salle→lit, statut lit, seed spécialités idempotent.
- HC-003 : MRN unique par tenant généré côté serveur, archivage au lieu de suppression,
  praticien lecture seule, lambda 403.
- PHPStan strict niveau 8, une table = une migration (#7452), OpenAPI couvert.

## 7. Références

- `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`
- Verticale modèle : `api/app/Modules/EduManager` (BC-16)
- Issues : #7785 (HC-001), #7786 (HC-002), #7787 (HC-003)
