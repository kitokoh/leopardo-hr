# ERD COMPLET — Leopardo RH
# Relations entre toutes les tables
# Version 2.0 | Mars 2026
# CORRIGÉ v3.1.0 : tenancy_type companies, zkteco_id employees, salary_advances status 'active', user_lookups + sync policy

---

## LÉGENDE

```
PK  = Primary Key
FK  = Foreign Key
UQ  = Unique
NN  = Not Null
DEF = Default value
»   = Relation vers
```

---

## SCHÉMA PUBLIC (partagé)

### plans
```
plans
├── id              INT         PK AUTO
├── name            VARCHAR(50) NN UQ     ex: "Starter", "Business", "Enterprise"
├── price_monthly   DECIMAL(10,2) NN
├── price_yearly    DECIMAL(10,2) NN
├── max_employees   INT         NULL      NULL = illimité
├── features        JSONB       NN DEF {} ex: {"biometric":true, "tasks":true, "export_bank":true}
├── trial_days      INT         NN DEF 14
├── is_active       BOOL        NN DEF true
└── created_at      TIMESTAMP

Relations : 1 plan » N companies
```

### companies
```
companies
├── id                  UUID        PK DEFAULT gen_random_uuid()
├── name                VARCHAR(100) NN
├── slug                VARCHAR(100) UQ   ex: "techcorp-spa"
├── sector              VARCHAR(100) NN
├── country             CHAR(2)     NN   Code ISO (DZ, MA, TN, FR, TR, SN, CI)
├── city                VARCHAR(100) NN
├── address             TEXT        NULL
├── email               VARCHAR(150) NN UQ
├── phone               VARCHAR(30)  NULL
├── logo_path           VARCHAR(255) NULL
├── plan_id             INT         FK » plans.id NN
├── schema_name         VARCHAR(63)  NN UQ  ex: "company_7c9e6679"
├── tenancy_type        VARCHAR(20)  NN DEF 'shared'
│                                   CHECK : ['shared'|'schema']
│                                   'shared' = isolation logique (Trial / Starter / Business)
│                                   'schema' = isolation physique PostgreSQL (Enterprise uniquement)
├── status              VARCHAR(20)  NN DEF 'trial'
│                                        [active|trial|suspended|expired]
├── subscription_start  DATE        NN
├── subscription_end    DATE        NN
├── language            CHAR(2)     NN DEF 'fr'  [fr|ar|tr|en]
├── timezone            VARCHAR(50)  NN DEF 'Africa/Algiers'
├── currency            VARCHAR(3)   NN DEF 'DZD'
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

Relations : N companies » 1 plan
```

### super_admins
```
super_admins
├── id              INT         PK AUTO
├── name            VARCHAR(100) NN
├── email           VARCHAR(150) NN UQ
├── password_hash   VARCHAR(255) NN
├── two_fa_secret   VARCHAR(32)  NULL
├── last_login_at   TIMESTAMP   NULL
└── created_at      TIMESTAMP
```

### invoices
```
invoices
├── id              INT         PK AUTO
├── company_id      UUID        FK » companies.id NN
├── amount          DECIMAL(10,2) NN
├── currency        VARCHAR(3)   NN
├── period          VARCHAR(20)  NN  ex: "2026-04"
├── status          VARCHAR(20)  NN DEF 'draft'
│                                   [draft|sent|paid|overdue|cancelled]
├── pdf_path        VARCHAR(255) NULL
├── due_date        DATE        NN
├── paid_at         TIMESTAMP   NULL
├── payment_method  VARCHAR(50)  NULL
├── notes           TEXT        NULL
└── created_at      TIMESTAMP

INDEX : (company_id), (status), (due_date)
```


### user_lookups  ← TABLE CRITIQUE (Performance auth multi-schéma)
```
user_lookups
├── email           VARCHAR(150) PK      (email de l'employé — clé de lookup)
├── company_id      UUID        FK » companies.id ON DELETE CASCADE
├── employee_id     INT         NN       ⚠️  PAS de FK réelle — référence virtuelle vers employees.id
│                                         (impossible entre schémas PostgreSQL)
│                                         Synchronisation obligatoire via EmployeeService
├── role            VARCHAR(20)  NN       Copie du rôle — mise à jour si role change
└── created_at      TIMESTAMPTZ NN DEF NOW()

INDEX : PRIMARY KEY(email) ← seul index nécessaire — auth = lookup par email
```

**Pourquoi cette table existe :**
Le login Flutter envoie un email. Sans user_lookups, Laravel devrait scanner TOUS les schémas
tenant pour trouver à quelle entreprise appartient cet email → O(n) requêtes.
Avec user_lookups : 1 seule requête dans le schéma public → company_id + schema_name récupérés
→ SET search_path TO company_uuid → authentification complète.

**Politique de synchronisation (obligatoire — voir aussi `08_MULTITENANCY_STRATEGY.md`) :**

| Événement | Action sur user_lookups | Tokens Sanctum |
|-----------|------------------------|:--------------:|
| Création employé | INSERT (dans transaction) | — |
| Archivage employé | Conservé — email reste réservé | Révoqués |
| Modification email | UPDATE email (dans transaction) | Révoqués |
| Modification rôle | UPDATE role (dans transaction) | Révoqués |
| Suppression entreprise | CASCADE automatique (FK) | Révoqués |

⚠️  Règle absolue : TOUJOURS dans une `DB::transaction()` qui englobe
l'opération sur le schéma tenant ET la mise à jour de user_lookups.
Si l'une des deux échoue → rollback complet → cohérence garantie.


### languages
```
languages
├── id          INT         PK AUTO
├── code        CHAR(2)     NN UQ  [fr|ar|tr|en|...]
├── name_fr     VARCHAR(50)  NN    ex: "Arabe"
├── name_native VARCHAR(50)  NN    ex: "العربية"
├── is_rtl      BOOL        NN DEF false
└── is_active   BOOL        NN DEF true
```

### hr_model_templates
```
hr_model_templates
├── id                  INT     PK AUTO
├── country_code        CHAR(2) NN UQ
├── name                VARCHAR(100) NN  ex: "Droit du travail algérien"
├── cotisations         JSONB   NN       Taux de cotisations standards
├── ir_brackets         JSONB   NN       Tranches IR
├── leave_rules         JSONB   NN       Règles de congé standards
└── holiday_calendar    JSONB   NN       Jours fériés de l'année
```

---

## SCHÉMA COMPANY_{UUID} (par entreprise)

### departments
```
departments
├── id          INT         PK AUTO
├── name        VARCHAR(100) NN
├── manager_id  INT         FK » employees.id NULL  (ajouté après employees)
└── created_at  TIMESTAMP
```

### positions
```
positions
├── id              INT     PK AUTO
├── name            VARCHAR(100) NN
├── department_id   INT     FK » departments.id NN
└── created_at      TIMESTAMP
```

### schedules
```
schedules
├── id                          INT         PK AUTO
├── name                        VARCHAR(100) NN  ex: "Standard 8h-17h"
├── start_time                  TIME        NN   ex: 08:00:00
├── end_time                    TIME        NN   ex: 17:00:00
├── break_minutes               INT         NN DEF 60
├── work_days                   JSONB       NN   ex: [1,2,3,4,5] (1=Lun, 7=Dim)
├── late_tolerance_minutes      INT         NN DEF 15
├── overtime_threshold_daily    DECIMAL(4,2) NN DEF 8.0   heures/jour
├── overtime_threshold_weekly   DECIMAL(5,2) NN DEF 40.0  heures/semaine
└── is_default                  BOOL        NN DEF false
```

### sites
```
sites
├── id          INT         PK AUTO
├── name        VARCHAR(100) NN  ex: "Siège Alger", "Agence Oran"
├── address     TEXT        NULL
├── gps_lat     DECIMAL(10,8) NULL
├── gps_lng     DECIMAL(11,8) NULL
└── gps_radius_m INT        NN DEF 100
```

### employees ← TABLE CENTRALE
```
employees
├── id                  INT         PK AUTO
├── matricule           VARCHAR(20)  NN UQ   ex: "EMP-0042" (auto-généré)
├── first_name          VARCHAR(100) NN
├── last_name           VARCHAR(100) NN
├── email               VARCHAR(150) NN UQ   (identifiant de connexion mobile)
├── phone               VARCHAR(30)  NULL
├── password_hash       VARCHAR(255) NN
├── role                VARCHAR(20)  NN DEF 'employee'
│                                   [manager|employee]
├── manager_role        VARCHAR(20)  NULL    (si role=manager)
│                                   [principal|rh|dept|comptable|superviseur]
├── department_id       INT         FK » departments.id NULL
├── position_id         INT         FK » positions.id NULL
├── schedule_id         INT         FK » schedules.id NULL
├── manager_id          INT         FK » employees.id NULL   (autoréférentielle)
├── site_id             INT         FK » sites.id NULL
├── date_of_birth       DATE        NULL
├── gender              CHAR(1)     NULL  [M|F]
├── nationality         CHAR(2)     NULL
├── national_id         VARCHAR(50)  NULL  ⚠️  CHIFFRÉ (EncryptedCast) — conformité RGPD/Loi 18-07 DZ/09-08 MA
├── address             TEXT        NULL
├── personal_email      VARCHAR(150) NULL
├── contract_type       VARCHAR(20)  NN DEF 'CDI'
│                                   [CDI|CDD|Stage|Interim|Consultant]
├── contract_start      DATE        NN
├── contract_end        DATE        NULL  (pour CDD/Stage)
├── salary_base         DECIMAL(12,2) NN DEF 0
├── salary_type         VARCHAR(20)  NN DEF 'fixed'  [fixed|hourly|daily]
├── hourly_rate         DECIMAL(10,2) NULL
├── payment_method      VARCHAR(20)  NN DEF 'bank_transfer'
│                                   [bank_transfer|cash|cheque]
├── iban                TEXT        NULL  (chiffré Laravel Crypt)
├── bank_account        TEXT        NULL  (chiffré)
├── leave_balance       DECIMAL(6,2) NN DEF 0
├── status              VARCHAR(20)  NN DEF 'active'
│                                   [active|suspended|archived]
├── photo_path          VARCHAR(255) NULL
├── zkteco_id           VARCHAR(50)  NULL UQ  (identifiant sur les lecteurs biométriques ZKTeco)
│                                             NULL si l'employé n'utilise pas la biométrie
├── emergency_contact   JSONB       NULL  {name, phone, relation}
├── extra_data          JSONB       NULL  (données supplémentaires libres)
├── email_verified_at   TIMESTAMP   NULL
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

INDEX : (email), (department_id), (status), (contract_end)
INDEX COMPOSITE : (manager_id, status)
```

### devices (lecteurs pointage)
```
devices
├── id              INT         PK AUTO
├── name            VARCHAR(100) NN  ex: "Lecteur entrée principale"
├── model           VARCHAR(100) NN  ex: "ZKTeco K40"
├── serial_number   VARCHAR(100) NULL UQ
├── type            VARCHAR(20)  NN  [zkteco|qrcode_terminal|tablet]
├── site_id         INT         FK » sites.id NULL
├── token           VARCHAR(255) NN UQ  (haché bcrypt)
├── last_sync_at    TIMESTAMP   NULL
├── status          VARCHAR(20)  NN DEF 'active'  [active|inactive|error]
└── created_at      TIMESTAMP
```

### attendance_logs ← TABLE CRITIQUE
```
attendance_logs
├── id                  BIGINT      PK AUTO
├── employee_id         INT         FK » employees.id NN
├── schedule_id         INT         FK » schedules.id NULL
│                                    Planning actif AU MOMENT du pointage (snapshot)
├── date                DATE        NN
├── session_number      SMALLINT    NN DEF 1   (1 = session principale, 2 = split-shift matin/soir)
├── check_in            TIMESTAMP   NULL  (horodatage serveur UTC)
├── check_out           TIMESTAMP   NULL  (horodatage serveur UTC)
├── declared_check_in   TIME        NULL  (heure déclarée par l'employé si différente)
├── declared_check_out  TIME        NULL
├── method              VARCHAR(20)  NN   [mobile|qrcode|biometric|manual]
├── gps_lat             DECIMAL(10,8) NULL
├── gps_lng             DECIMAL(11,8) NULL
├── gps_valid           BOOL        NULL
├── photo_path          VARCHAR(255) NULL
├── hours_worked        DECIMAL(5,2) NULL  (calculé après check_out)
├── overtime_hours      DECIMAL(5,2) NULL DEF 0
├── status              VARCHAR(20)  NN DEF 'incomplete'
│                                   [ontime|late|absent|leave|holiday|incomplete]
├── is_manual_edit      BOOL        NN DEF false
├── edited_by           INT         FK » employees.id NULL
├── edit_reason         TEXT        NULL
└── created_at          TIMESTAMP

UNIQUE : (employee_id, date, session_number)   ← session_number permet le split-shift
INDEX : (date, status), (employee_id, date DESC)
```

### absence_types
```
absence_types
├── id                      INT         PK AUTO
├── label                   VARCHAR(100) NN  ex: "Congé payé"
├── is_paid                 BOOL        NN DEF true
├── pay_rate                DECIMAL(5,2) NULL  (% si partiel, ex: 50.00)
├── deducts_leave           BOOL        NN DEF true
├── requires_justification  BOOL        NN DEF false
├── min_notice_days         INT         NN DEF 0
├── max_days                INT         NULL  (NULL = illimité)
├── who_submits             VARCHAR(20)  NN DEF 'employee'  [employee|manager]
└── color                   VARCHAR(7)   NN DEF '#4CAF50'  (hex couleur)
```

### absences
```
absences
├── id                  INT         PK AUTO
├── employee_id         INT         FK » employees.id NN
├── type_id             INT         FK » absence_types.id NN
├── start_date          DATE        NN
├── end_date            DATE        NN
├── days_count          INT         NN  (calculé auto : hors WE et fériés)
├── status              VARCHAR(20)  NN DEF 'pending'
│                                   [pending|approved|rejected|cancelled]
├── comment             TEXT        NULL
├── attachment_path     VARCHAR(255) NULL
├── decided_by          INT         FK » employees.id NULL
├── decision_comment    TEXT        NULL
├── created_at          TIMESTAMP
└── deleted_at          TIMESTAMP   NULL  (soft delete)

INDEX : (employee_id, status), (start_date, end_date), (status, created_at)
```

### leave_balance_logs
```
leave_balance_logs
├── id              BIGINT      PK AUTO
├── employee_id     INT         FK » employees.id NN
├── type            VARCHAR(20)  NN  [accrual|consumption|adjustment|reset|carry_over]
├── days            DECIMAL(6,2) NN  (positif = ajout, négatif = déduction)
├── balance_after   DECIMAL(6,2) NN
├── reference_id    INT         NULL  (absence_id si consommation)
├── created_by      INT         FK » employees.id NULL
├── note            TEXT        NULL
└── created_at      TIMESTAMP

INDEX : (employee_id, created_at DESC)
```

### salary_advances
```
salary_advances
├── id                  INT         PK AUTO
├── employee_id         INT         FK » employees.id NN
├── amount              DECIMAL(12,2) NN
├── reason              TEXT        NULL
├── status              VARCHAR(20)  NN DEF 'pending'
│                                   [pending|approved|active|rejected|repaid]
│                                   ⚠️  'active' = remboursement en cours (ajouté v3.1.0 — était absent, causait crash PayrollService)
├── repayment_plan      JSONB       NULL
│   ex: [{"month":"2026-05","amount":5000,"paid":false}, ...]
├── amount_remaining    DECIMAL(12,2) NN DEF 0
├── approved_by         INT         FK » employees.id NULL
├── decision_comment    TEXT        NULL
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

INDEX : (employee_id, status)
```

### projects
```
projects
├── id          INT         PK AUTO
├── name        VARCHAR(150) NN
├── description TEXT        NULL
├── start_date  DATE        NULL
├── end_date    DATE        NULL
├── members     JSONB       NN DEF '[]'  [employee_ids]
├── status      VARCHAR(20)  NN DEF 'active'  [active|completed|archived]
├── created_by  INT         FK » employees.id NN
└── created_at  TIMESTAMP

INDEX : GIN(members) pour requêtes d'appartenance (`@> '[employee_id]'`)
NOTE  : Phase 1 conserve JSONB ; nettoyage des références membres obligatoire via EmployeeService.archiveEmployee().
```

### tasks
```
tasks
├── id              INT         PK AUTO
├── title           VARCHAR(200) NN
├── description     TEXT        NULL
├── created_by      INT         FK » employees.id NN
├── assigned_to     JSONB       NN DEF '[]'  [employee_ids]
├── project_id      INT         FK » projects.id NULL
├── due_date        TIMESTAMP   NN
├── priority        VARCHAR(10)  NN DEF 'normal'
│                               [low|normal|high|urgent]
├── status          VARCHAR(20)  NN DEF 'todo'
│                               [todo|inprogress|review|done|rejected|cancelled]
├── category        VARCHAR(100) NULL
├── checklist       JSONB       NULL  [{label, done}]
├── visibility      VARCHAR(10)  NN DEF 'visible'  [private|visible]
├── created_at      TIMESTAMP
└── updated_at      TIMESTAMP

INDEX : (status, due_date), GIN index sur assigned_to pour recherche JSON
```

### task_comments
```
task_comments
├── id              INT         PK AUTO
├── task_id         INT         FK » tasks.id NN
├── author_id       INT         FK » employees.id NN
├── content         TEXT        NN
├── attachment_path VARCHAR(255) NULL
└── created_at      TIMESTAMP

INDEX : (task_id, created_at)
```

### evaluations
```
evaluations
├── id                  INT         PK AUTO
├── employee_id         INT         FK » employees.id NN
├── evaluator_id        INT         FK » employees.id NN
├── period              VARCHAR(20)  NN  ex: "2026-S1", "2026-Q1", "2026"
├── criteria_scores     JSONB       NN  {"Qualité du travail":4, "Ponctualité":5, ...}
├── global_score        DECIMAL(4,2) NN  (calculé : moyenne pondérée)
├── comment             TEXT        NULL
├── objectives          TEXT        NULL
├── self_evaluation     JSONB       NULL  (mêmes critères, rempli par l'employé)
└── created_at          TIMESTAMP

UQ : (employee_id, period)
INDEX : (employee_id, created_at DESC)
```

### payrolls
```
payrolls
├── id                  BIGINT      PK AUTO
├── employee_id         INT         FK » employees.id NN
├── period_month        INT         NN  (1-12)
├── period_year         INT         NN  ex: 2026
├── gross_salary        DECIMAL(12,2) NN  ← brut calculé (salary_base + overtime + bonuses)
│                                               ≠ employees.salary_base (salaire contractuel)
├── overtime_amount     DECIMAL(12,2) NN DEF 0
├── bonuses             JSONB       NN DEF '[]'  [{name, amount}]
├── deductions          JSONB       NN DEF '[]'  [{name, amount}]
├── cotisations         JSONB       NN DEF '[]'  [{name, rate, base, amount}]
├── ir_amount           DECIMAL(12,2) NN DEF 0
├── advance_deduction   DECIMAL(12,2) NN DEF 0
├── absence_deduction   DECIMAL(12,2) NN DEF 0
├── penalty_deduction   DECIMAL(12,2) NN DEF 0
├── net_salary          DECIMAL(12,2) NN
├── pdf_path            VARCHAR(255) NULL
├── status              VARCHAR(10)  NN DEF 'draft'  [draft|validated]
├── validated_by        INT         FK » employees.id NULL
└── validated_at        TIMESTAMP   NULL

UNIQUE : (employee_id, period_month, period_year)
INDEX : (period_month, period_year, status)

Formule net (contrat) :
net_salary = gross_salary + overtime_amount + bonuses
           - cotisations - ir_amount
           - advance_deduction - absence_deduction - penalty_deduction
           - autres deductions JSONB
```

### payroll_export_batches
```
payroll_export_batches
├── id              INT         PK AUTO
├── period_month    INT         NN
├── period_year     INT         NN
├── bank_format     VARCHAR(30)  NN  ex: "DZ_GENERIC", "MA_CIH", "FR_SEPA"
├── file_path       VARCHAR(255) NN
├── total_amount    DECIMAL(14,2) NN
├── employees_count INT         NN
├── exported_by     INT         FK » employees.id NN
└── created_at      TIMESTAMP

INDEX : (period_month, period_year)
```

### payroll_export_items *(table pivot batch ↔ payrolls)*
```
payroll_export_items
├── id          INT     PK AUTO
├── batch_id    INT     FK » payroll_export_batches.id NN
├── payroll_id  BIGINT  FK » payrolls.id NN
└── amount      DECIMAL(12,2) NN  (net_salary au moment de l'export — snapshot)

UNIQUE : (batch_id, payroll_id)
```

### employee_devices *(tokens FCM push notifications)*
```
employee_devices
├── id          INT         PK AUTO
├── employee_id INT         FK » employees.id NN
├── fcm_token   TEXT        NN  (token Firebase Cloud Messaging)
├── platform    VARCHAR(10)  NN  [android|ios]
├── device_name VARCHAR(150) NULL  ex: "iPhone 15 de Ahmed"
├── last_seen   TIMESTAMP   NN
└── created_at  TIMESTAMP

UNIQUE : (fcm_token)
INDEX  : (employee_id)
⚠️  À chaque connexion Flutter, upsert sur fcm_token.
    Si le token a changé (app réinstallée) → mettre à jour last_seen et fcm_token.
    Tokens non vus depuis 90 jours → purger automatiquement.
```

### company_settings
```
company_settings
├── key         VARCHAR(100) PK  ex: "payroll.cotisations", "attendance.gps_enabled"
├── value       TEXT        NN   (toujours stocké en TEXT — voir value_type pour parser)
├── value_type  VARCHAR(10)  NN DEF 'string'  [string|integer|boolean|json|decimal]
│   ⚠️  Utiliser value_type pour savoir comment interpréter value :
│      boolean → value IN ('true','false') → cast PHP
│      integer → intval($setting->value)
│      decimal → floatval($setting->value)
│      json    → json_decode($setting->value, true)
│      string  → $setting->value direct
└── updated_at  TIMESTAMP

Exemples de clés standards :
  attendance.gps_enabled          → boolean  → 'false'
  attendance.gps_radius_m         → integer  → '100'
  attendance.photo_enabled         → boolean  → 'false'
  attendance.qr_code_token         → string   → 'LEOPARDO-QR-abc123'
  advance.enabled                  → boolean  → 'false'
  advance.max_percentage           → integer  → '50'
  payroll.cotisations              → json     → '[{"name":"CNAS",...}]'
  payroll.ir_brackets              → json     → '[{"min":0,...}]'
  payroll.bank_export_format       → string   → 'DZ_GENERIC'
  leave.accrual_rate_monthly       → decimal  → '2.5'
  leave.carry_over                 → boolean  → 'true'
```

### audit_logs
```
audit_logs
├── id          BIGINT      PK AUTO
├── actor_type  VARCHAR(20)  NN DEF 'employee'  [employee|super_admin|system]
│   ⚠️  PAS de FK — le Super Admin vit dans le schéma public, pas dans le schéma tenant.
│      Une FK vers employees.id serait invalide pour les actions du Super Admin.
│      actor_type + actor_id permettent de retrouver l'auteur sans cross-schema FK.
├── actor_id    INT         NULL  (employees.id si employee, super_admins.id si super_admin, NULL si system)
├── actor_name  VARCHAR(200) NULL  (dénormalisé : "Ahmed Benali" ou "Super Admin" — pour l'affichage sans JOIN)
├── action      VARCHAR(100) NN  ex: "employee.updated", "payroll.validated"
├── table_name  VARCHAR(100) NN
├── record_id   VARCHAR(50)  NULL
├── old_values  JSONB       NULL
├── new_values  JSONB       NULL
├── ip          VARCHAR(45)  NULL  (IPv4 ou IPv6)
├── user_agent  TEXT        NULL
└── created_at  TIMESTAMP

INDEX : (actor_type, actor_id, created_at DESC), (table_name, record_id), (created_at)
Rétention : 24 mois — purge automatique via commande cron
```

### notifications
```
notifications
├── id              BIGINT      PK AUTO
├── recipient_id    INT         FK » employees.id NN
├── type            VARCHAR(100) NN  ex: "absence.approved", "task.assigned"
├── title           VARCHAR(200) NN
├── body            TEXT        NN
├── data            JSONB       NULL  (données supplémentaires pour le deep link)
├── read_at         TIMESTAMP   NULL
└── created_at      TIMESTAMP

INDEX : (recipient_id, read_at), (recipient_id, created_at DESC)
```

---

## DIAGRAMME DE RELATIONS (texte)

```
SCHÉMA PUBLIC
=============
plans 1──────────N companies
companies 1──────N invoices
companies 1──────N billing_transactions (via invoices)

SCHÉMA TENANT (company_{uuid})
================================
schedules 1──────N employees
departments 1────N employees
departments 1────1 employees (manager_id)
positions 1──────N employees
sites 1──────────N employees
sites 1──────────N devices
employees N──────1 employees (manager_id : autoréférentielle)

employees 1──────N attendance_logs
employees 1──────N absences
employees 1──────N salary_advances
employees N──────N tasks (via assigned_to JSONB)
employees 1──────N task_comments
employees 1──────N evaluations (comme évalué)
employees 1──────N evaluations (comme évaluateur)
employees 1──────N payrolls
employees 1──────N leave_balance_logs
employees 1──────N notifications
employees 1──────N audit_logs

absence_types 1──N absences
tasks 1──────────N task_comments
projects 1───────N tasks

payrolls → payroll_export_batches (batch contient plusieurs payrolls)
```
