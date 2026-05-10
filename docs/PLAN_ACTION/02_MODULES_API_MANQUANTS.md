# 02 — MODULES API MANQUANTS

**Objectif :** Implementer tous les modules RH manquants pour etre au niveau ERPNext/Frappe HR. Chaque module est decrit avec ses endpoints, modeles, migrations et policies.

---

## Module A — Conges Avances (Leave Management)

L'existant a un module Absence basique (CRUD + approve/reject). Il manque les politiques de conges, soldes, accrual, et workflows multi-niveaux.

### Modeles a creer

```
LeavePolicy          # Politique de conges par type et par entreprise
  - id, company_id, absence_type_id, name
  - accrual_type (enum: monthly, yearly, manual)
  - accrual_amount (decimal)
  - max_balance (decimal)
  - carry_forward (boolean)
  - carry_forward_max (decimal, nullable)
  - carry_forward_expiry_days (integer, nullable)
  - requires_approval (boolean)
  - approval_levels (integer, default 1)
  - min_notice_days (integer, default 0)
  - max_consecutive_days (integer, nullable)
  - applicable_roles (JSON)
  - active (boolean)
  - created_at, updated_at

LeaveAccrual         # Log d'accumulation de conges
  - id, company_id, employee_id, leave_policy_id
  - amount (decimal)
  - type (enum: accrual, carry_forward, manual_adjustment, deduction)
  - description
  - effective_date
  - created_by (user_id)
  - created_at

LeaveBalance         # Solde courant (vue materialisee ou calculee)
  - id, company_id, employee_id, absence_type_id
  - balance (decimal)
  - used (decimal)
  - pending (decimal)
  - year (integer)
  - updated_at

AbsenceApproval      # Workflow approbation multi-niveaux
  - id, absence_id, level (integer)
  - approver_id (employee_id)
  - status (enum: pending, approved, rejected)
  - comment (text, nullable)
  - decided_at (timestamp, nullable)
  - created_at
```

### Endpoints a creer

```
GET    /api/v1/leave-policies                     # Liste des politiques
POST   /api/v1/leave-policies                     # Creer une politique
GET    /api/v1/leave-policies/{id}                # Detail
PUT    /api/v1/leave-policies/{id}                # Modifier
DELETE /api/v1/leave-policies/{id}                # Supprimer

GET    /api/v1/leave-balances                     # Soldes de tous les employes (manager)
GET    /api/v1/leave-balances/{employee_id}       # Soldes d'un employe
POST   /api/v1/leave-balances/recalculate         # Recalculer les soldes (admin)

GET    /api/v1/me/leave-balances                  # Mes soldes (self-service)

GET    /api/v1/leave-accruals                     # Historique accruals (manager)
POST   /api/v1/leave-accruals                     # Ajustement manuel (admin)

# Les routes absences existantes sont conservees, on ajoute :
GET    /api/v1/absences/{id}/approvals            # Historique approbations
POST   /api/v1/absences/{id}/approvals            # Soumettre approbation niveau N
```

### Scheduled job

```php
// app/Console/Commands/AccrueLeaveBalances.php
// Execute quotidiennement via scheduler
// Pour chaque politique active avec accrual_type=monthly :
//   Si today est le 1er du mois, creer un LeaveAccrual pour chaque employe concerne
```

### Tests requis

- [ ] Test Feature : CRUD leave-policies (manager only)
- [ ] Test Feature : Leave balance recalculation
- [ ] Test Feature : Self-service /me/leave-balances
- [ ] Test Feature : Workflow approbation multi-niveaux (2 niveaux)
- [ ] Test Feature : Carry forward et expiration
- [ ] Test Unit : Accrual calculation logic

---

## Module B — Contrats de Travail (Employment Contracts)

### Modeles a creer

```
Contract             # Contrat de travail
  - id, company_id, employee_id
  - contract_type (enum: cdi, cdd, stage, freelance, interim)
  - reference (string, auto-generated)
  - start_date, end_date (nullable for CDI)
  - job_title, department_id, position_id
  - base_salary (decimal), currency (default: DZD)
  - salary_frequency (enum: monthly, hourly, daily)
  - work_hours_per_week (decimal)
  - probation_end_date (nullable)
  - benefits (JSON: transport, meals, housing, etc.)
  - clauses (JSON: non_compete, confidentiality, etc.)
  - status (enum: draft, active, suspended, expired, terminated)
  - signed_at (timestamp, nullable)
  - signed_document_path (string, nullable)
  - termination_reason (text, nullable)
  - terminated_at (timestamp, nullable)
  - created_by (user_id)
  - created_at, updated_at

ContractAmendment    # Avenant au contrat
  - id, contract_id, company_id
  - amendment_type (enum: salary_change, position_change, hours_change, renewal, other)
  - changes (JSON: {field: {old, new}})
  - effective_date
  - reason (text)
  - approved_by (user_id, nullable)
  - document_path (string, nullable)
  - created_at
```

### Endpoints a creer

```
GET    /api/v1/contracts                          # Liste (filtre par employee, status, type)
POST   /api/v1/contracts                          # Creer
GET    /api/v1/contracts/{id}                     # Detail
PUT    /api/v1/contracts/{id}                     # Modifier (draft only)
POST   /api/v1/contracts/{id}/activate            # Activer
POST   /api/v1/contracts/{id}/suspend             # Suspendre
POST   /api/v1/contracts/{id}/terminate           # Resilier
POST   /api/v1/contracts/{id}/renew               # Renouveler (cree un nouveau contrat)
GET    /api/v1/contracts/{id}/amendments          # Liste des avenants
POST   /api/v1/contracts/{id}/amendments          # Creer un avenant
GET    /api/v1/contracts/{id}/generate-pdf        # Generer le PDF du contrat

GET    /api/v1/contracts/expiring                 # Contrats expirant dans N jours (alerte)

GET    /api/v1/me/contracts                       # Mes contrats (self-service)
```

### Scheduled job

```php
// app/Console/Commands/AlertExpiringContracts.php
// Execute quotidiennement
// Notifie le manager et le RH quand un contrat expire dans 30, 15 et 7 jours
```

### Tests requis

- [ ] Test Feature : CRUD complet contrats
- [ ] Test Feature : Workflow activate/suspend/terminate
- [ ] Test Feature : Renewal cree un nouveau contrat
- [ ] Test Feature : Alertes expiration
- [ ] Test Feature : PDF generation
- [ ] Test Feature : Self-service /me/contracts

---

## Module C — Recrutement / ATS (Applicant Tracking)

### Modeles a creer

```
JobPosting           # Offre d'emploi
  - id, company_id
  - title, description (richtext)
  - department_id, position_id
  - location (string), remote_policy (enum: onsite, hybrid, remote)
  - contract_type (enum: cdi, cdd, stage, freelance)
  - salary_range_min, salary_range_max (decimal, nullable)
  - currency
  - skills_required (JSON)
  - status (enum: draft, published, closed, archived)
  - published_at, closes_at
  - created_by, created_at, updated_at

Applicant            # Candidat
  - id, company_id, job_posting_id
  - first_name, last_name, email, phone
  - resume_path (string, nullable)
  - cover_letter (text, nullable)
  - source (enum: website, referral, linkedin, agency, other)
  - status (enum: new, screening, interview, offer, hired, rejected, withdrawn)
  - rating (integer 1-5, nullable)
  - notes (text, nullable)
  - applied_at, created_at, updated_at

Interview            # Entretien
  - id, applicant_id, company_id
  - interviewer_id (employee_id)
  - type (enum: phone, video, onsite, technical)
  - scheduled_at, duration_minutes
  - status (enum: scheduled, completed, cancelled, no_show)
  - feedback (text, nullable)
  - rating (integer 1-5, nullable)
  - created_at, updated_at
```

### Endpoints a creer

```
# Job Postings
GET    /api/v1/job-postings                       # Liste
POST   /api/v1/job-postings                       # Creer
GET    /api/v1/job-postings/{id}                  # Detail
PUT    /api/v1/job-postings/{id}                  # Modifier
POST   /api/v1/job-postings/{id}/publish          # Publier
POST   /api/v1/job-postings/{id}/close            # Fermer
DELETE /api/v1/job-postings/{id}                  # Supprimer (draft only)

# Applicants
GET    /api/v1/job-postings/{id}/applicants       # Candidats pour une offre
POST   /api/v1/job-postings/{id}/applicants       # Nouvelle candidature
GET    /api/v1/applicants/{id}                    # Detail candidat
PUT    /api/v1/applicants/{id}                    # Modifier
PATCH  /api/v1/applicants/{id}/status             # Changer statut (pipeline)
DELETE /api/v1/applicants/{id}                    # Supprimer

# Interviews
GET    /api/v1/applicants/{id}/interviews         # Entretiens d'un candidat
POST   /api/v1/applicants/{id}/interviews         # Planifier entretien
PUT    /api/v1/interviews/{id}                    # Modifier
PATCH  /api/v1/interviews/{id}/feedback           # Ajouter feedback
DELETE /api/v1/interviews/{id}                    # Annuler
```

### Tests requis

- [ ] Test Feature : CRUD job postings + publish/close
- [ ] Test Feature : CRUD applicants + pipeline status changes
- [ ] Test Feature : CRUD interviews + feedback
- [ ] Test Feature : Filtres et pagination

---

## Module D — Formation / LMS (Learning & Development)

### Modeles a creer

```
TrainingCourse       # Cours/Formation
  - id, company_id
  - title, description
  - category (string)
  - type (enum: internal, external, online, certification)
  - provider (string, nullable)
  - duration_hours (decimal)
  - max_participants (integer, nullable)
  - cost_per_participant (decimal, nullable)
  - currency
  - materials_path (string, nullable)
  - active (boolean)
  - created_at, updated_at

TrainingSession      # Session de formation
  - id, training_course_id, company_id
  - trainer_id (employee_id, nullable)
  - external_trainer (string, nullable)
  - start_date, end_date
  - location (string, nullable)
  - status (enum: planned, in_progress, completed, cancelled)
  - notes (text, nullable)
  - created_at, updated_at

TrainingEnrollment   # Inscription employe
  - id, training_session_id, employee_id, company_id
  - status (enum: enrolled, attended, completed, no_show, cancelled)
  - score (decimal, nullable)
  - certificate_path (string, nullable)
  - feedback (text, nullable)
  - completed_at (timestamp, nullable)
  - created_at, updated_at
```

### Endpoints a creer

```
# Courses
GET    /api/v1/training-courses                   # Liste
POST   /api/v1/training-courses                   # Creer
GET    /api/v1/training-courses/{id}              # Detail
PUT    /api/v1/training-courses/{id}              # Modifier
DELETE /api/v1/training-courses/{id}              # Supprimer

# Sessions
GET    /api/v1/training-sessions                  # Liste
POST   /api/v1/training-sessions                  # Creer
GET    /api/v1/training-sessions/{id}             # Detail
PUT    /api/v1/training-sessions/{id}             # Modifier
DELETE /api/v1/training-sessions/{id}             # Supprimer

# Enrollments
GET    /api/v1/training-sessions/{id}/enrollments # Inscrits
POST   /api/v1/training-sessions/{id}/enrollments # Inscrire
PATCH  /api/v1/training-enrollments/{id}/status   # Changer statut
DELETE /api/v1/training-enrollments/{id}          # Desinscrire

# Self-service
GET    /api/v1/me/trainings                       # Mes formations
POST   /api/v1/me/trainings/{sessionId}/enroll    # M'inscrire
```

### Tests requis

- [ ] Test Feature : CRUD courses, sessions, enrollments
- [ ] Test Feature : Self-service enrollment
- [ ] Test Feature : Status transitions (enrolled -> attended -> completed)

---

## Module E — Prets Employes (Employee Loans)

### Modeles a creer

```
EmployeeLoan         # Pret employe
  - id, company_id, employee_id
  - loan_type (enum: personal, housing, vehicle, education, emergency)
  - amount (decimal), currency
  - interest_rate (decimal, default 0)
  - installments (integer)
  - installment_amount (decimal)
  - start_date
  - status (enum: draft, pending_approval, approved, disbursed, repaying, closed, defaulted)
  - approved_by (user_id, nullable)
  - disbursed_at (timestamp, nullable)
  - notes (text, nullable)
  - created_at, updated_at

LoanRepayment        # Echeance de remboursement
  - id, employee_loan_id, company_id
  - due_date
  - amount (decimal)
  - principal (decimal)
  - interest (decimal)
  - status (enum: pending, paid, overdue, waived)
  - paid_at (timestamp, nullable)
  - payroll_id (nullable — deduction automatique)
  - created_at, updated_at
```

### Endpoints a creer

```
GET    /api/v1/employee-loans                     # Liste
POST   /api/v1/employee-loans                     # Creer
GET    /api/v1/employee-loans/{id}                # Detail
PUT    /api/v1/employee-loans/{id}                # Modifier (draft)
POST   /api/v1/employee-loans/{id}/approve        # Approuver
POST   /api/v1/employee-loans/{id}/disburse       # Debloquer
POST   /api/v1/employee-loans/{id}/close          # Clore

GET    /api/v1/employee-loans/{id}/repayments     # Echeancier
POST   /api/v1/employee-loans/{id}/repayments     # Enregistrer paiement

GET    /api/v1/me/loans                           # Mes prets (self-service)
```

### Tests requis

- [ ] Test Feature : CRUD prets + workflow approbation/deblocage
- [ ] Test Feature : Generation echeancier automatique
- [ ] Test Feature : Integration payroll (deduction automatique)

---

## Module F — Notes de Frais (Expense Claims)

### Modeles a creer

```
ExpenseClaim         # Note de frais
  - id, company_id, employee_id
  - title, description
  - total_amount (decimal), currency
  - status (enum: draft, submitted, approved, rejected, paid)
  - submitted_at, approved_at, paid_at
  - approved_by (user_id, nullable)
  - payment_reference (string, nullable)
  - created_at, updated_at

ExpenseItem          # Ligne de frais
  - id, expense_claim_id
  - category (enum: transport, meals, accommodation, office, communication, other)
  - description (string)
  - amount (decimal)
  - date
  - receipt_path (string, nullable)
  - created_at
```

### Endpoints a creer

```
GET    /api/v1/expense-claims                     # Liste (manager: equipe, admin: all)
POST   /api/v1/expense-claims                     # Creer
GET    /api/v1/expense-claims/{id}                # Detail
PUT    /api/v1/expense-claims/{id}                # Modifier (draft)
POST   /api/v1/expense-claims/{id}/submit         # Soumettre
POST   /api/v1/expense-claims/{id}/approve        # Approuver
POST   /api/v1/expense-claims/{id}/reject         # Rejeter
POST   /api/v1/expense-claims/{id}/mark-paid      # Marquer comme paye

GET    /api/v1/expense-claims/{id}/items          # Lignes de frais
POST   /api/v1/expense-claims/{id}/items          # Ajouter une ligne
PUT    /api/v1/expense-items/{id}                 # Modifier une ligne
DELETE /api/v1/expense-items/{id}                 # Supprimer une ligne

GET    /api/v1/me/expense-claims                  # Mes notes de frais (self-service)
POST   /api/v1/me/expense-claims                  # Creer (self-service)
```

### Tests requis

- [ ] Test Feature : CRUD claims + items
- [ ] Test Feature : Workflow draft -> submitted -> approved -> paid
- [ ] Test Feature : Upload justificatifs
- [ ] Test Feature : Self-service

---

## Module G — Organigramme & Hierarchie

### Modeles a modifier/creer

```
# Modifier Employee existant — ajouter :
  - manager_id (self-referencing, nullable)
  - level (integer, computed)

OrgNode              # Noeud d'organigramme (optionnel, si besoin hors Employee)
  - id, company_id
  - parent_id (self-referencing, nullable)
  - type (enum: company, division, department, team, position)
  - name
  - head_employee_id (nullable)
  - order (integer)
  - created_at, updated_at
```

### Endpoints a creer

```
GET    /api/v1/org-chart                          # Arbre complet de la hierarchie
GET    /api/v1/org-chart/{employee_id}/team       # Equipe directe d'un manager
GET    /api/v1/org-chart/{employee_id}/chain       # Chaine hierarchique (employee -> CEO)

GET    /api/v1/me/team                            # Mon equipe (self-service manager)
GET    /api/v1/me/manager                         # Mon manager (self-service)
```

### Tests requis

- [ ] Test Feature : Org chart tree generation
- [ ] Test Feature : Team listing pour un manager
- [ ] Test Feature : Chain hierarchique

---

## Module H — Rapports RH Avances

### Endpoints a creer

```
GET    /api/v1/reports/headcount                  # Effectifs par departement/site/statut
GET    /api/v1/reports/turnover                   # Taux de turnover par periode
GET    /api/v1/reports/absenteeism                # Taux d'absenteisme par departement
GET    /api/v1/reports/payroll-summary             # Masse salariale par departement/periode
GET    /api/v1/reports/overtime                    # Heures supplementaires par employe/equipe
GET    /api/v1/reports/leave-balances              # Soldes conges globaux
GET    /api/v1/reports/contract-status             # Contrats par statut/type/expiration
GET    /api/v1/reports/training-completion          # Taux de completion formations
GET    /api/v1/reports/attendance-summary           # Resume pointage par periode

# Chaque endpoint accepte : ?format=json|csv|pdf&period=2026-05&department_id=X
```

### Taches

- [ ] **T-MOD-H1** : Creer `ReportController` avec les actions ci-dessus
- [ ] **T-MOD-H2** : Creer `app/Services/ReportService.php` avec la logique d'aggregation
- [ ] **T-MOD-H3** : Creer les exports CSV (Laravel Export) et PDF (DomPDF)
- [ ] **T-MOD-H4** : Tests Feature pour chaque rapport

---

## Module I — Webhooks & API Publique

### Modeles a creer

```
WebhookEndpoint      # Endpoint webhook d'un tenant
  - id, company_id
  - url (string)
  - events (JSON: ["employee.created", "attendance.checked_in", ...])
  - secret (string, encrypted)
  - active (boolean)
  - last_triggered_at (timestamp, nullable)
  - failure_count (integer, default 0)
  - created_at, updated_at

WebhookDelivery      # Log de livraison
  - id, webhook_endpoint_id
  - event (string)
  - payload (JSON)
  - response_code (integer, nullable)
  - response_body (text, nullable)
  - delivered_at (timestamp)
  - duration_ms (integer)
```

### Endpoints a creer

```
GET    /api/v1/webhooks                           # Liste endpoints
POST   /api/v1/webhooks                           # Creer
GET    /api/v1/webhooks/{id}                      # Detail
PUT    /api/v1/webhooks/{id}                      # Modifier
DELETE /api/v1/webhooks/{id}                      # Supprimer
POST   /api/v1/webhooks/{id}/test                 # Envoyer un test

GET    /api/v1/webhooks/{id}/deliveries           # Historique livraisons
POST   /api/v1/webhooks/{id}/deliveries/{did}/retry # Retenter
```

### Implementation

Le `WebhookDispatcher` listener (voir 01_ARCHITECTURE) ecoute les domain events et poste en async (queue `webhooks`) vers chaque endpoint concerne. Le payload inclut un header `X-Leopardo-Signature` (HMAC-SHA256 du body avec le secret).

### Tests requis

- [ ] Test Feature : CRUD webhook endpoints
- [ ] Test Feature : Webhook delivery + signature verification
- [ ] Test Unit : HMAC signature generation

---

## Module J — Audit Trail Complet

### Modeles a creer

```
AuditLog             # Log d'audit
  - id, company_id, user_id
  - action (enum: created, updated, deleted, viewed, exported, approved, rejected)
  - auditable_type (string: Employee, Absence, Payroll, etc.)
  - auditable_id (integer)
  - old_values (JSON, nullable)
  - new_values (JSON, nullable)
  - ip_address (string)
  - user_agent (string)
  - metadata (JSON, nullable)
  - created_at
```

### Endpoints a creer

```
GET    /api/v1/audit-logs                         # Liste (admin/manager)
GET    /api/v1/audit-logs/{auditable_type}/{id}   # Logs d'un objet specifique
GET    /api/v1/audit-logs/export                  # Export CSV
```

### Implementation

Utiliser un Observer Laravel automatique sur tous les modeles :

```php
// app/Observers/AuditObserver.php
class AuditObserver {
    public function created(Model $model) { $this->log('created', $model); }
    public function updated(Model $model) { $this->log('updated', $model, $model->getOriginal()); }
    public function deleted(Model $model) { $this->log('deleted', $model); }
}
```

Enregistrer l'observer sur chaque modele dans le ServiceProvider.

### Tests requis

- [ ] Test Feature : Audit log creation automatique on CRUD
- [ ] Test Feature : Listing et filtrage des logs
- [ ] Test Feature : Export CSV
