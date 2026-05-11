# 09 — ONBOARDING, WORKFLOWS & ABONNEMENTS

**Objectif :** Systematiser l'onboarding client, les workflows d'approbation, et le billing/abonnements.

---

## 1. Onboarding client (amelioration)

### Existant

- `OnboardingChecklistController` — checklist basique
- `CompanyRequestController` — demandes entrantes
- `UserInvitation` — invitations par email

### Ameliorations

#### Checklist dynamique par plan

```
OnboardingStep       # Etape d'onboarding
  - id, company_id
  - step_key (string: company_info, first_employee, first_attendance, etc.)
  - title (string, i18n)
  - description (text, i18n)
  - status (enum: pending, in_progress, completed, skipped)
  - completed_at (timestamp, nullable)
  - completed_by (user_id, nullable)
  - order (integer)
  - required (boolean)
  - metadata (JSON)
  - created_at, updated_at
```

#### Etapes d'onboarding standard

| # | Etape | Auto-detect | Action |
|---|-------|------------|--------|
| 1 | Info entreprise | company.name rempli | Formulaire settings |
| 2 | Premier departement | departments.count > 0 | CRUD departement |
| 3 | Premier employe | employees.count > 0 | Creer employe |
| 4 | Premier pointage | attendance_logs.count > 0 | Faire un check-in |
| 5 | Inviter un manager | users via invitation.count > 0 | Envoyer invitation |
| 6 | Configurer les horaires | schedules.count > 0 | CRUD schedule |
| 7 | Premier rapport | monthly_report generated | Generer rapport mensuel |
| 8 | Configurer la paie | salary_structures.count > 0 | Creer structure (si plan Business+) |
| 9 | Installer un kiosk | kiosks.count > 0 | Enregistrer kiosk (optionnel) |
| 10 | Activer le geofence | company.metadata.geofence set | Configurer geofence |

#### Endpoints

```
GET    /api/v1/onboarding/checklist               # Checklist avec progression (existant, a enrichir)
PATCH  /api/v1/onboarding/checklist/{step}/complete # Marquer complete
PATCH  /api/v1/onboarding/checklist/{step}/skip    # Sauter (si non required)
GET    /api/v1/onboarding/progress                 # Pourcentage global
```

---

## 2. Workflows d'approbation generiques

### Principe

Un systeme de workflow reutilisable pour : absences, notes de frais, prets, avances, achats, etc.

```
ApprovalWorkflow     # Definition de workflow
  - id, company_id
  - name (string)
  - model_type (string: Absence, ExpenseClaim, EmployeeLoan, etc.)
  - levels (JSON: [{level: 1, approver_type: "manager"}, {level: 2, approver_type: "role:rh"}])
  - auto_approve_below (decimal, nullable — montant)
  - escalation_hours (integer, nullable — auto-escalate si pas de reponse)
  - active (boolean)
  - created_at, updated_at

ApprovalRequest      # Instance de demande d'approbation
  - id, company_id
  - workflow_id
  - approvable_type (string), approvable_id (integer) — polymorphic
  - requester_id (employee_id)
  - current_level (integer)
  - status (enum: pending, approved, rejected, escalated, cancelled)
  - created_at, updated_at

ApprovalDecision     # Decision par niveau
  - id, approval_request_id
  - level (integer)
  - approver_id (employee_id)
  - decision (enum: approved, rejected)
  - comment (text, nullable)
  - decided_at (timestamp)
  - created_at
```

### Endpoints

```
GET    /api/v1/approval-workflows                 # Liste workflows (admin)
POST   /api/v1/approval-workflows                 # Creer
PUT    /api/v1/approval-workflows/{id}            # Modifier
DELETE /api/v1/approval-workflows/{id}            # Supprimer

GET    /api/v1/approvals/pending                  # Mes approbations en attente
POST   /api/v1/approvals/{id}/approve             # Approuver
POST   /api/v1/approvals/{id}/reject              # Rejeter
GET    /api/v1/approvals/history                  # Historique de mes decisions
```

### Trait Approvable

```php
// app/Traits/Approvable.php
trait Approvable {
    public function approvalRequest(): MorphOne {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function submitForApproval(): ApprovalRequest {
        $workflow = ApprovalWorkflow::where('model_type', static::class)
            ->where('company_id', $this->company_id)
            ->where('active', true)
            ->firstOrFail();

        return ApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'approvable_type' => static::class,
            'approvable_id' => $this->id,
            'requester_id' => auth()->user()->employee->id,
            'current_level' => 1,
            'status' => 'pending',
        ]);
    }
}
```

---

## 3. Billing & Abonnements

### Existant

- Modele `Company` avec plan
- `PlatformPlanController` — catalogue plans
- `PlatformCompanySubscriptionController` — lecture/modification abonnement

### Ameliorations — Integration PSP (Payment Service Provider)

#### Option 1 : Stripe (international)

```
composer require stripe/stripe-php
```

#### Option 2 : Chargily (Algerie — CIB/EDAHABIA)

```
composer require chargily/chargily-pay-laravel
```

#### Option 3 : Les deux (Stripe pour international, Chargily pour DZ)

### Modeles billing

```
Subscription         # Abonnement (enrichir l'existant)
  - id, company_id, plan_id
  - status (enum: trial, active, past_due, cancelled, expired)
  - trial_ends_at
  - current_period_start, current_period_end
  - cancelled_at (nullable)
  - cancel_reason (text, nullable)
  - payment_method (enum: stripe, chargily, bank_transfer, manual)
  - stripe_subscription_id (nullable)
  - chargily_subscription_id (nullable)
  - created_at, updated_at

Invoice              # Facture
  - id, company_id, subscription_id
  - number (string, auto: LEO-2026-0001)
  - amount (decimal), currency
  - tax_amount (decimal)
  - total (decimal)
  - status (enum: draft, sent, paid, overdue, cancelled)
  - due_date
  - paid_at (nullable)
  - payment_method
  - stripe_invoice_id (nullable)
  - pdf_path (string, nullable)
  - created_at, updated_at

Payment              # Paiement
  - id, invoice_id, company_id
  - amount (decimal), currency
  - method (enum: card, cib, edahabia, bank_transfer, manual)
  - provider_reference (string, nullable)
  - status (enum: pending, completed, failed, refunded)
  - paid_at
  - created_at
```

### Endpoints billing

```
# Subscriptions
GET    /api/v1/platform/subscriptions/{company}   # Detail abonnement
POST   /api/v1/platform/subscriptions/{company}/upgrade # Changer de plan
POST   /api/v1/platform/subscriptions/{company}/cancel  # Annuler
POST   /api/v1/platform/subscriptions/{company}/renew   # Renouveler

# Invoices
GET    /api/v1/platform/invoices                  # Liste factures (super-admin)
GET    /api/v1/billing/invoices                   # Mes factures (tenant)
GET    /api/v1/billing/invoices/{id}/pdf          # PDF facture

# Payments (webhooks)
POST   /api/v1/webhooks/stripe                    # Stripe webhook
POST   /api/v1/webhooks/chargily                  # Chargily webhook
```

### Scheduled jobs billing

```php
$schedule->command('billing:check-trials')->daily();     // Fin de trial -> notifier
$schedule->command('billing:check-overdue')->daily();    // Factures en retard -> notifier
$schedule->command('billing:generate-invoices')->monthly(); // Generer factures du mois
```

---

## 4. Feature Flags avances

### Existant

- Table `features` + `PlatformCompanyFeatureController`

### Ameliorations

Lier les features aux plans et permettre l'activation/desactivation granulaire :

```php
// Usage dans le code
if (Feature::active('payroll', $company)) {
    // Module paie disponible
}

if (Feature::active('ai_chat', $company)) {
    // Chat IA disponible
}
```

### Matrice features/plans

| Feature | Trial | Starter | Business | Enterprise |
|---------|-------|---------|----------|------------|
| employees (max) | 10 | 30 | 100 | Illimite |
| attendance | Oui | Oui | Oui | Oui |
| anomalies | Non | Non | Oui | Oui |
| geofence | Non | Non | Oui | Oui |
| monthly_report | Non | Oui | Oui | Oui |
| absences | Non | Oui | Oui | Oui |
| payroll | Non | Non | Oui | Oui |
| contracts | Non | Non | Oui | Oui |
| recruitment | Non | Non | Non | Oui |
| training | Non | Non | Non | Oui |
| tracking | Non | Non | Oui | Oui |
| ai_chat | Non | Non | Oui | Oui |
| ai_voice | Non | Non | Non | Oui |
| webhooks | Non | Non | Non | Oui |
| api_public | Non | Non | Non | Oui |
| multi_site | Non | Non | Oui | Oui |
| custom_branding | Non | Non | Non | Oui |

---

## 5. Taches

- [ ] **T-OBD-01** : Enrichir la checklist d'onboarding avec auto-detection
- [ ] **T-OBD-02** : Creer le systeme de workflow approbation generique
- [ ] **T-OBD-03** : Implementer le trait Approvable
- [ ] **T-OBD-04** : Integrer Stripe (ou Chargily pour DZ)
- [ ] **T-OBD-05** : Creer les modeles Subscription, Invoice, Payment
- [ ] **T-OBD-06** : Creer les endpoints billing
- [ ] **T-OBD-07** : Implementer les webhooks Stripe/Chargily
- [ ] **T-OBD-08** : Creer les scheduled jobs billing
- [ ] **T-OBD-09** : Implementer la matrice features/plans
- [ ] **T-OBD-10** : Template email facturation (i18n FR/AR/EN)
- [ ] **T-OBD-11** : Generateur PDF factures
- [ ] **T-OBD-12** : Tests Feature workflow approbation
- [ ] **T-OBD-13** : Tests Feature billing/invoices
- [ ] **T-OBD-14** : Tests Feature onboarding checklist
