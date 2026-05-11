# PLAN D'ACTION COMPLET — LEOPARDO RH

**Version :** 1.0  
**Date :** 2026-05-10  
**Objectif :** Amener Leopardo RH au niveau enterprise-grade, capable de rivaliser avec ERPNext/Frappe HR sur le domaine RH, tout en conservant l'avantage terrain (pointage, biometrie, geofence, mobile natif).

**Public cible :** Developpeurs (juniors inclus), agents IA, contributeurs open-source.

---

## Index du dossier

| # | Fichier | Contenu |
|---|---------|---------|
| 00 | `00_SOMMAIRE.md` | Ce fichier — index et vision globale |
| 01 | `01_ARCHITECTURE_FONDATIONS.md` | Fondations techniques : DDD, event sourcing, API versioning, scalabilite, conventions code |
| 02 | `02_MODULES_API_MANQUANTS.md` | Tous les modules API manquants : endpoints, modeles, migrations, policies — prets a coder |
| 03 | `03_PAIE_COMPLETE.md` | Paie legale multi-pays (DZ, MA, TN, FR, TR, SN), conformite, exports bancaires, bulletins |
| 04 | `04_COUCHE_IA.md` | Architecture IA parallele : AI Gateway, Orchestrator, Tool Registry, Voice, Memory, phases |
| 05 | `05_TRACKING_VEHICULES.md` | Integration Traccar : architecture, endpoints API, modeles, sync utilisateurs |
| 06 | `06_INTERFACES.md` | Interfaces web (dashboard admin, blog vitrine), mobile Flutter, kiosk — ecrans a creer |
| 07 | `07_MONITORING_LOGGING_OBSERVABILITE.md` | Monitoring, logging, alerting, health checks, APM, error tracking |
| 08 | `08_TESTS_CI_CD.md` | Strategie tests (unit, feature, E2E, Playwright), CI/CD pipelines, deploy automatique |
| 09 | `09_ONBOARDING_WORKFLOWS_ABONNEMENTS.md` | Onboarding clients, workflows approbation, billing, abonnements, feature flags |
| 10 | `10_OPEN_SOURCE_GITHUB.md` | Ameliorer le depot GitHub pour attirer des devs : templates, labels, good first issues, docs |
| 11 | `11_GTM_EXECUTION.md` | Plan marketing execution etape par etape : Maghreb, Afrique francophone, contenu, SEO, vente |
| 12 | `12_PRIORITES_ROADMAP.md` | Ordre d'execution, dependances entre modules, timeline realiste, criteres de validation |

---

## Etat actuel du projet (audit 2026-05-10)

### Ce qui EXISTE et FONCTIONNE

| Module | Endpoints | Modeles | Tests | Etat |
|--------|-----------|---------|-------|------|
| Auth (login/register/SSO/2FA) | 10 | User, SuperAdmin | Oui | Stable |
| Employees (CRUD + archive) | 6 | Employee | Oui | Stable |
| Attendance (check-in/out, anomalies, monthly report) | 7 | AttendanceLog, AttendanceKiosk | Oui | Stable |
| Estimations (daily/quick/receipt) | 3+3 self-service | - | Oui | Stable |
| Absences (CRUD + approve/reject) | 5 | Absence, AbsenceType, LeaveBalanceLog | Oui | Stable |
| Salary Advances (CRUD + approve/reject) | 5 | SalaryAdvance | Oui | Stable |
| Payrolls (CRUD + validate) | 6 | Payroll | Partiel | En cours |
| Departments/Positions/Sites/Schedules | 20 | Department, Position, Site, Schedule | Partiel | Stable |
| Notifications | 4 | Notification | Oui | Stable |
| Projects & Tasks | 12 | Project, Task, TaskComment | Partiel | Stable |
| Evaluations | 5 | Evaluation | Partiel | Stable |
| Cabinet documentaire | 12 | CabinetFolder, CabinetDocument, CabinetShare | Oui | Stable |
| Cameras/Surveillance | 12 | Module DDD complet | Oui | Stable |
| Biometrie/Kiosks | 5 | BiometricEnrollmentRequest | Partiel | Stable |
| Invitations/Onboarding | 5 | UserInvitation | Oui | Stable |
| Feature flags/manifest | 5 | Feature | Oui | Stable |
| Plateforme super-admin | 8 | Company, CompanyRequest, CompanySetting | Oui | Recent |
| i18n enterprise | 2 | Language | Oui | Stable |

**Total existant : ~135 endpoints API, 30 modeles, 75 fichiers de test, 10 workflows CI.**

### Ce qui MANQUE pour rivaliser avec ERPNext HR

1. **Paie complete legale** (bulletins, cotisations, exports bancaires, conformite multi-pays)
2. **Conges avances** (politiques, soldes, accrual, workflows multi-niveaux)
3. **Recrutement/ATS** (offres, candidatures, pipeline, entretiens)
4. **Formation/LMS** (catalogue, inscriptions, suivi, certifications)
5. **Prets employes** (au-dela des avances : echeanciers, remboursements)
6. **Notes de frais** (soumission, workflows approbation, remboursement)
7. **Contrats de travail** (generation, renouvellement, historique, alertes expiration)
8. **Organigramme dynamique** (hierarchie, visualisation, delegation)
9. **Rapports RH avances** (turnover, absenteisme, masse salariale, KPIs)
10. **Export bancaire** (SEPA, virements locaux DZ/MA/TN/FR)
11. **Couche IA** (chat, voice, agents, predictions)
12. **Tracking vehicules** (integration Traccar)
13. **Blog/CMS vitrine** (publication contenu marketing)
14. **Webhooks/API publique** (integrations tierces)
15. **Audit trail complet** (logs modifications, compliance)

---

## Regles pour les developpeurs

1. **API first** — Chaque module commence par les endpoints API. Les interfaces suivent.
2. **Tests obligatoires** — Chaque endpoint a au minimum un test Feature Pest.
3. **Migrations idempotentes** — `Schema::hasTable()` + `try/catch 42P07` pour PostgreSQL/Render.
4. **i18n des le depart** — Messages de validation via `__()`, pas de texte en dur.
5. **RBAC** — Chaque action passe par une Policy Laravel. Utiliser les gates existantes.
6. **Multi-tenant** — Tout utilise le `company_id` Global Scope. Jamais d'acces cross-tenant.
7. **DDD pour nouveaux modules** — Structure : `Domain/`, `Application/`, `Infrastructure/`, `Interfaces/`.
8. **CHANGELOG.md** — Chaque PR met a jour le changelog.
9. **AGENTS.md** — Chaque lecon operationnelle y est ajoutee.
10. **OpenAPI** — Chaque nouvel endpoint est documente dans `openapi/v1.yaml`.
