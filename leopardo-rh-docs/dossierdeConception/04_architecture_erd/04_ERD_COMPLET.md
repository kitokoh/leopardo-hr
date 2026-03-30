# ERD COMPLET — LEOPARDO RH
# Version 2.0 | Mars 2026
# CORRIGÉ : dépendance circulaire departments ↔ employees résolue
# CORRIGÉ : company_id ajouté sur toutes les tables shared_tenants

---

## LÉGENDE
```
PK  = Primary Key
FK  = Foreign Key
UQ  = Unique
NN  = Not Null
DEF = Default value
[S] = Présent uniquement en mode SHARED (schéma shared_tenants)
[E] = Absent en mode SCHEMA Enterprise (isolation physique)
```

---

## SCHÉMA PUBLIC (toujours présent)

### plans
```
id              SERIAL      PK
name            VARCHAR(50) NN UQ     "Starter" | "Business" | "Enterprise"
price_monthly   DECIMAL(10,2) NN DEF 0
price_yearly    DECIMAL(10,2) NN DEF 0
max_employees   INT         NULL      NULL = illimité
features        JSONB       NN DEF {} Voir 03_MODELE_ECONOMIQUE.md
trial_days      INT         NN DEF 14
is_active       BOOL        NN DEF true
created_at      TIMESTAMPTZ NN DEF NOW()
```

### companies
```
id                  UUID        PK DEF gen_random_uuid()
name                VARCHAR(100) NN
slug                VARCHAR(100) UQ
sector              VARCHAR(100) NN
country             CHAR(2)     NN
city                VARCHAR(100) NN
address             TEXT        NULL
email               VARCHAR(150) NN UQ
phone               VARCHAR(30)  NULL
logo_path           VARCHAR(255) NULL
plan_id             INT         FK → plans.id NN
schema_name         VARCHAR(60)  NN UQ   "company_7c9e6679" (utilisé seulement si schema)
tenancy_type        VARCHAR(20)  NN DEF 'shared'   CHECK IN ('schema','shared')
status              VARCHAR(20)  NN DEF 'trial'    CHECK IN ('active','trial','suspended','expired')
subscription_start  DATE        NN
subscription_end    DATE        NN
language            CHAR(2)     NN DEF 'fr'
timezone            VARCHAR(50)  NN DEF 'Africa/Algiers'
currency            VARCHAR(3)   NN DEF 'DZD'
notes               TEXT        NULL
created_at          TIMESTAMPTZ NN DEF NOW()
updated_at          TIMESTAMPTZ NN DEF NOW()

INDEX: (status), (plan_id), (subscription_end), (tenancy_type)
```

### user_lookups  ← TABLE CRITIQUE pour la performance auth
```
id              SERIAL      PK
email           VARCHAR(150) NN UQ
company_id      UUID        FK → companies.id ON DELETE CASCADE
employee_id     INT         NN     (id dans le schéma du tenant)
role            VARCHAR(20)  NN
created_at      TIMESTAMPTZ NN DEF NOW()

INDEX: (email) UNIQUE
```

### super_admins
```
id              SERIAL      PK
name            VARCHAR(100) NN
email           VARCHAR(150) NN UQ
password_hash   VARCHAR(255) NN
two_fa_secret   VARCHAR(32)  NULL
last_login_at   TIMESTAMPTZ  NULL
created_at      TIMESTAMPTZ  NN DEF NOW()
```

### invoices
```
id              SERIAL      PK
company_id      UUID        FK → companies.id ON DELETE CASCADE
amount          DECIMAL(10,2) NN
currency        VARCHAR(3)   NN
period          VARCHAR(20)  NN    "2026-04"
status          VARCHAR(20)  NN DEF 'draft'
pdf_path        VARCHAR(255) NULL
due_date        DATE        NN
paid_at         TIMESTAMPTZ  NULL
payment_method  VARCHAR(50)  NULL
notes           TEXT        NULL
created_at      TIMESTAMPTZ  NN DEF NOW()

INDEX: (company_id), (status), (due_date)
```

### languages
```
id          SERIAL      PK
code        CHAR(2)     NN UQ
name        VARCHAR(50)  NN
direction   VARCHAR(3)   NN DEF 'ltr'
is_active   BOOL        NN DEF true
```

### hr_model_templates
```
id                INT     PK
country_code      CHAR(2) NN UQ
name              VARCHAR(100) NN
cotisations       JSONB   NN
ir_brackets       JSONB   NN
leave_rules       JSONB   NN
holiday_calendar  JSONB   NN
```

---

## SCHÉMA TENANT (shared_tenants OU company_{uuid})

> **Note :** En mode `shared`, toutes les tables ci-dessous ont une colonne
> `company_id UUID NOT NULL` avec un INDEX. En mode `schema`, cette colonne
> est absente (inutile). Le SQL de migration gère les deux cas via une variable.

---

### ORDRE DE MIGRATION (critique — résout la dépendance circulaire)

```
Migration 01 : departments     (sans manager_id)
Migration 02 : positions       (FK → departments)
Migration 03 : schedules
Migration 04 : sites
Migration 05 : employees       (FK → departments, positions, schedules, sites)
Migration 06 : ALTER TABLE departments ADD COLUMN manager_id  ← ICI, après employees
Migration 07 : devices
Migration 08 : attendance_logs (FK → employees)
Migration 09 : absence_types
Migration 10 : absences        (FK → employees, absence_types)
Migration 11 : leave_balance_logs
Migration 12 : salary_advances
Migration 13 : projects
Migration 14 : tasks           (FK → employees, projects)
Migration 15 : task_comments
Migration 16 : evaluations
Migration 17 : payrolls
Migration 18 : payroll_export_batches
Migration 19 : company_settings
Migration 20 : audit_logs
Migration 21 : notifications
```

---

### departments
```
id          INT         PK AUTO
[S]company_id UUID      FK → companies.id NN
name        VARCHAR(100) NN
manager_id  INT         FK → employees.id NULL  ← Ajouté en Migration 06
created_at  TIMESTAMPTZ NN DEF NOW()
```

### positions
```
id              INT     PK AUTO
[S]company_id   UUID    FK NN
name            VARCHAR(100) NN
department_id   INT     FK → departments.id NN
created_at      TIMESTAMPTZ NN DEF NOW()
```

### schedules
```
id                          INT         PK AUTO
[S]company_id               UUID        FK NN
name                        VARCHAR(100) NN
start_time                  TIME        NN
end_time                    TIME        NN
break_minutes               INT         NN DEF 60
work_days                   JSONB       NN DEF '[1,2,3,4,5]'
late_tolerance_minutes      INT         NN DEF 15
overtime_threshold_daily    DECIMAL(4,2) NN DEF 8.0
overtime_threshold_weekly   DECIMAL(5,2) NN DEF 40.0
is_default                  BOOL        NN DEF false
created_at                  TIMESTAMPTZ NN DEF NOW()
```

### sites
```
id              INT     PK AUTO
[S]company_id   UUID    FK NN
name            VARCHAR(100) NN
address         TEXT    NULL
gps_lat         DECIMAL(10,7) NULL
gps_lng         DECIMAL(10,7) NULL
gps_radius_m    INT     NULL DEF 100
created_at      TIMESTAMPTZ NN DEF NOW()
```

### employees
```
id                  INT         PK AUTO
[S]company_id       UUID        FK NN
matricule           VARCHAR(20)  NN UQ (unique dans le scope company)
first_name          VARCHAR(100) NN
last_name           VARCHAR(100) NN
email               VARCHAR(150) NN UQ (unique dans le scope company)
phone               VARCHAR(30)  NULL
password_hash       VARCHAR(255) NN
role                VARCHAR(20)  NN DEF 'employee'   CHECK IN ('employee','manager')
manager_role        VARCHAR(20)  NULL     CHECK IN ('principal','rh','dept','comptable','superviseur')
department_id       INT         FK → departments.id NULL
position_id         INT         FK → positions.id NULL
schedule_id         INT         FK → schedules.id NULL
site_id             INT         FK → sites.id NULL
supervisor_id       INT         FK → employees.id NULL  (pour role superviseur)
salary_base         DECIMAL(12,2) NN DEF 0
contract_type       VARCHAR(20)  NN DEF 'cdi'  CHECK IN ('cdi','cdd','freelance','stage')
hire_date           DATE        NN
contract_end_date   DATE        NULL
leave_balance       DECIMAL(5,1) NN DEF 0
iban                TEXT        NULL  (chiffré AES-256)
bank_account        TEXT        NULL  (chiffré AES-256)
national_id         VARCHAR(50)  NULL (chiffré AES-256 — RGPD)
photo_path          VARCHAR(255) NULL
fcm_tokens          JSONB       NN DEF '[]'
is_active           BOOL        NN DEF true
last_login_at       TIMESTAMPTZ NULL
created_at          TIMESTAMPTZ NN DEF NOW()
updated_at          TIMESTAMPTZ NN DEF NOW()

INDEX: (email), (matricule), (department_id), (role)
```

### attendance_logs
```
id              INT         PK AUTO
[S]company_id   UUID        FK NN
employee_id     INT         FK → employees.id NN
date            DATE        NN
check_in        TIMESTAMPTZ NULL
check_out       TIMESTAMPTZ NULL
method          VARCHAR(20)  NN DEF 'mobile'  CHECK IN ('mobile','qr','biometric','manual')
status          VARCHAR(20)  NN DEF 'incomplete'
                             CHECK IN ('ontime','late','absent','leave','holiday','incomplete')
hours_worked    DECIMAL(5,2) NULL
overtime_hours  DECIMAL(5,2) NULL DEF 0
late_minutes    INT         NULL DEF 0
gps_check_in    POINT       NULL
gps_check_out   POINT       NULL
photo_check_in  VARCHAR(255) NULL
corrected_by    INT         FK → employees.id NULL
correction_note TEXT        NULL
created_at      TIMESTAMPTZ NN DEF NOW()
updated_at      TIMESTAMPTZ NN DEF NOW()

UNIQUE: (employee_id, date)  ← UNE SEULE LIGNE PAR EMPLOYÉ PAR JOUR
INDEX: (employee_id, date), (date, status)
```

### absence_types
```
id              INT     PK AUTO
[S]company_id   UUID    FK NN
name            VARCHAR(100) NN
code            VARCHAR(20)  NN UQ (scope company)
is_paid         BOOL    NN DEF true
deducts_leave   BOOL    NN DEF true
requires_proof  BOOL    NN DEF false
max_days_once   INT     NULL
created_at      TIMESTAMPTZ NN DEF NOW()
```

### absences
```
id              INT     PK AUTO
[S]company_id   UUID    FK NN
employee_id     INT     FK → employees.id NN
absence_type_id INT     FK → absence_types.id NN
start_date      DATE    NN
end_date        DATE    NN
days_count      INT     NN
status          VARCHAR(20) NN DEF 'pending'
                CHECK IN ('pending','approved','rejected','cancelled')
reason          TEXT    NULL
proof_path      VARCHAR(255) NULL
approved_by     INT     FK → employees.id NULL
rejected_reason TEXT    NULL
created_at      TIMESTAMPTZ NN DEF NOW()
updated_at      TIMESTAMPTZ NN DEF NOW()

INDEX: (employee_id, status), (start_date, end_date)
```

### salary_advances
```
id                  INT     PK AUTO
[S]company_id       UUID    FK NN
employee_id         INT     FK → employees.id NN
amount              DECIMAL(12,2) NN
status              VARCHAR(20) NN DEF 'pending'
                    CHECK IN ('pending','approved','rejected','active','repaid')
reason              TEXT    NULL
approved_by         INT     FK → employees.id NULL
repayment_months    INT     NN DEF 1
monthly_deduction   DECIMAL(12,2) NULL
repaid_amount       DECIMAL(12,2) NN DEF 0
repayment_plan      JSONB   NULL
created_at          TIMESTAMPTZ NN DEF NOW()
updated_at          TIMESTAMPTZ NN DEF NOW()
```

### tasks
```
id              INT     PK AUTO
[S]company_id   UUID    FK NN
project_id      INT     FK → projects.id NULL
title           VARCHAR(200) NN
description     TEXT    NULL
assigned_to     INT     FK → employees.id NN
assigned_by     INT     FK → employees.id NN
priority        VARCHAR(20) NN DEF 'medium' CHECK IN ('low','medium','high','urgent')
status          VARCHAR(20) NN DEF 'todo' CHECK IN ('todo','in_progress','review','done','rejected')
due_date        DATE    NULL
completed_at    TIMESTAMPTZ NULL
checklist       JSONB   NULL
created_at      TIMESTAMPTZ NN DEF NOW()
updated_at      TIMESTAMPTZ NN DEF NOW()

INDEX: (assigned_to, status), (due_date)
```

### payrolls
```
id                      INT     PK AUTO
[S]company_id           UUID    FK NN
employee_id             INT     FK → employees.id NN
period_year             INT     NN
period_month            INT     NN  CHECK IN (1..12)
salary_base             DECIMAL(12,2) NN
overtime_hours          DECIMAL(5,2) NN DEF 0
overtime_amount         DECIMAL(12,2) NN DEF 0
bonuses                 JSONB   NN DEF '[]'
bonuses_total           DECIMAL(12,2) NN DEF 0
gross_total             DECIMAL(12,2) NN
cotisations_salariales  JSONB   NN DEF '[]'
cotisations_total       DECIMAL(12,2) NN DEF 0
gross_taxable           DECIMAL(12,2) NN
ir_amount               DECIMAL(12,2) NN DEF 0
net_before_deductions   DECIMAL(12,2) NN
advance_deduction       DECIMAL(12,2) NN DEF 0
absence_deduction       DECIMAL(12,2) NN DEF 0
penalty_deduction       DECIMAL(12,2) NN DEF 0
other_deductions        JSONB   NN DEF '[]'
deductions_total        DECIMAL(12,2) NN DEF 0
net_payable             DECIMAL(12,2) NN
pdf_path                VARCHAR(255) NULL
status                  VARCHAR(20) NN DEF 'draft' CHECK IN ('draft','validated','paid')
validated_by            INT     FK → employees.id NULL
validated_at            TIMESTAMPTZ NULL
created_at              TIMESTAMPTZ NN DEF NOW()
updated_at              TIMESTAMPTZ NN DEF NOW()

UNIQUE: (employee_id, period_year, period_month)
```

### evaluations
```
id              INT     PK AUTO
[S]company_id   UUID    FK NN
employee_id     INT     FK → employees.id NN
evaluator_id    INT     FK → employees.id NN
period          VARCHAR(20) NN  "2026-Q1"
scores          JSONB   NN DEF '{}'
global_score    DECIMAL(3,1) NULL
comments        TEXT    NULL
self_evaluation JSONB   NULL
status          VARCHAR(20) NN DEF 'pending' CHECK IN ('pending','self_done','manager_done','completed')
created_at      TIMESTAMPTZ NN DEF NOW()
updated_at      TIMESTAMPTZ NN DEF NOW()
```

### notifications
```
id          INT     PK AUTO
[S]company_id UUID  FK NN
employee_id INT     FK → employees.id NN
type        VARCHAR(50) NN   "absence.approved", "task.assigned", etc.
title       VARCHAR(200) NN
body        TEXT    NN
data        JSONB   NN DEF '{}'
is_read     BOOL    NN DEF false
read_at     TIMESTAMPTZ NULL
created_at  TIMESTAMPTZ NN DEF NOW()

INDEX: (employee_id, is_read), (created_at)
```

### company_settings
```
id          INT     PK AUTO
[S]company_id UUID  FK NN
key         VARCHAR(100) NN
value       TEXT    NN
created_at  TIMESTAMPTZ NN DEF NOW()
updated_at  TIMESTAMPTZ NN DEF NOW()

UNIQUE: (company_id, key)  -- En mode schema : UNIQUE(key) seulement
```

### audit_logs
```
id          BIGINT  PK AUTO
[S]company_id UUID  FK NN
user_id     INT     FK → employees.id NN
action      VARCHAR(100) NN
table_name  VARCHAR(100) NN
record_id   INT     NULL
old_values  JSONB   NULL
new_values  JSONB   NULL
ip          INET    NULL
user_agent  TEXT    NULL
created_at  TIMESTAMPTZ NN DEF NOW()

INDEX: (user_id, created_at), (table_name, record_id)
```
