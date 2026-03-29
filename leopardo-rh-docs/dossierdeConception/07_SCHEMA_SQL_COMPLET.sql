-- ============================================================
-- LEOPARDO RH — SCHÉMA SQL POSTGRESQL COMPLET
-- Version 1.1 (bugs corrigés)
-- À exécuter dans l'ordre : schéma public d'abord,
-- puis schéma tenant via TenantService lors de la création
-- d'une entreprise.
-- ============================================================

-- ============================================================
-- EXTENSIONS (une seule fois sur la base leopardo_db)
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";  -- gen_random_uuid()

-- ============================================================
-- SCHÉMA PUBLIC — Tables partagées toute la plateforme
-- ============================================================

SET search_path TO public;

-- ------------------------------------------------------------
-- plans
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
    id              SERIAL          PRIMARY KEY,
    name            VARCHAR(50)     NOT NULL UNIQUE,
    price_monthly   DECIMAL(10,2)   NOT NULL DEFAULT 0,
    price_yearly    DECIMAL(10,2)   NOT NULL DEFAULT 0,
    max_employees   INT             NULL,           -- NULL = illimité
    features        JSONB           NOT NULL DEFAULT '{}',
    trial_days      INT             NOT NULL DEFAULT 14,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

COMMENT ON COLUMN plans.max_employees IS 'NULL = employés illimités (plan Enterprise)';
COMMENT ON COLUMN plans.features      IS 'JSON : {"biometric":bool,"tasks":bool,"bank_export":bool,"multi_managers":bool,"photo_attendance":bool,"api_public":bool}';

-- ------------------------------------------------------------
-- companies
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS companies (
    id                  UUID            PRIMARY KEY DEFAULT gen_random_uuid(),
    name                VARCHAR(100)    NOT NULL,
    slug                VARCHAR(100)    NOT NULL UNIQUE,
    sector              VARCHAR(100)    NOT NULL,
    country             CHAR(2)         NOT NULL,   -- ISO 3166-1 alpha-2
    city                VARCHAR(100)    NOT NULL,
    address             TEXT            NULL,
    email               VARCHAR(150)    NOT NULL UNIQUE,
    phone               VARCHAR(30)     NULL,
    logo_path           VARCHAR(255)    NULL,
    plan_id             INT             NOT NULL REFERENCES plans(id),
    schema_name         VARCHAR(60)     NOT NULL UNIQUE, -- ex: "company_7c9e6679"
    tenancy_type        VARCHAR(20)     NOT NULL DEFAULT 'schema'
                            CHECK (tenancy_type IN ('schema','shared')),
    status              VARCHAR(20)     NOT NULL DEFAULT 'trial'
                            CHECK (status IN ('active','trial','suspended','expired')),
    subscription_start  DATE            NOT NULL,
    subscription_end    DATE            NOT NULL,
    language            CHAR(2)         NOT NULL DEFAULT 'fr',
    timezone            VARCHAR(50)     NOT NULL DEFAULT 'Africa/Algiers',
    currency            VARCHAR(3)      NOT NULL DEFAULT 'DZD',
    notes               TEXT            NULL,        -- visible Super Admin uniquement
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_companies_status  ON companies(status);
CREATE INDEX idx_companies_plan_id ON companies(plan_id);
CREATE INDEX idx_companies_sub_end ON companies(subscription_end);

-- ------------------------------------------------------------
-- super_admins
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS super_admins (
    id              SERIAL          PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    two_fa_secret   VARCHAR(32)     NULL,
    last_login_at   TIMESTAMPTZ     NULL,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- invoices
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id              SERIAL          PRIMARY KEY,
    company_id      UUID            NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    amount          DECIMAL(10,2)   NOT NULL,
    currency        VARCHAR(3)      NOT NULL DEFAULT 'EUR',
    period          VARCHAR(20)     NOT NULL,    -- ex: "2026-04"
    status          VARCHAR(20)     NOT NULL DEFAULT 'draft'
                        CHECK (status IN ('draft','sent','paid','overdue','cancelled')),
    pdf_path        VARCHAR(255)    NULL,
    due_date        DATE            NOT NULL,
    paid_at         TIMESTAMPTZ     NULL,
    payment_method  VARCHAR(50)     NULL,
    notes           TEXT            NULL,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_invoices_company_id ON invoices(company_id);
CREATE INDEX idx_invoices_status     ON invoices(status);
CREATE INDEX idx_invoices_due_date   ON invoices(due_date);

-- ------------------------------------------------------------
-- billing_transactions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS billing_transactions (
    id              SERIAL          PRIMARY KEY,
    invoice_id      INT             NOT NULL REFERENCES invoices(id),
    amount          DECIMAL(10,2)   NOT NULL,
    currency        VARCHAR(3)      NOT NULL,
    gateway         VARCHAR(50)     NULL,        -- 'stripe','cmi','paydunya','manual'
    gateway_ref     VARCHAR(100)    NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','success','failed','refunded')),
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_btrans_invoice_id ON billing_transactions(invoice_id);

-- ------------------------------------------------------------
-- languages
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
    id          SERIAL          PRIMARY KEY,
    code        CHAR(5)         NOT NULL UNIQUE,  -- 'fr','ar','tr','en'
    name        VARCHAR(50)     NOT NULL,
    direction   VARCHAR(3)      NOT NULL DEFAULT 'ltr' CHECK (direction IN ('ltr','rtl')),
    is_active   BOOLEAN         NOT NULL DEFAULT TRUE
);

-- ------------------------------------------------------------
-- hr_model_templates  (modèles RH pré-remplis par pays)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hr_model_templates (
    id                  SERIAL          PRIMARY KEY,
    country_code        CHAR(2)         NOT NULL UNIQUE,
    name                VARCHAR(100)    NOT NULL,
    cotisations         JSONB           NOT NULL DEFAULT '{}',
    ir_brackets         JSONB           NOT NULL DEFAULT '[]',
    leave_rules         JSONB           NOT NULL DEFAULT '{}',
    holiday_calendar    JSONB           NOT NULL DEFAULT '[]'
);

-- ------------------------------------------------------------
-- user_lookups (Table de correspondance pour le login multi-tenant)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_lookups (
    email           VARCHAR(150)    PRIMARY KEY,
    company_id      UUID            NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    schema_name     VARCHAR(60)     NOT NULL,
    user_id         INT             NOT NULL, -- ID de l'employé dans son schéma
    role            VARCHAR(20)     NOT NULL
);

CREATE INDEX idx_user_lookups_company ON user_lookups(company_id);

COMMENT ON COLUMN hr_model_templates.cotisations      IS 'Structure: {salariales:[{name,rate,base,ceiling,multiplier}], patronales:[...]}';
COMMENT ON COLUMN hr_model_templates.ir_brackets      IS 'Structure: [{min,max,rate,deduction}]';
COMMENT ON COLUMN hr_model_templates.leave_rules      IS 'Structure: {accrual_rate_monthly,initial_balance,carry_over,max_balance,min_notice_days}';
COMMENT ON COLUMN hr_model_templates.holiday_calendar IS 'Structure: [{date,name,is_recurring}]';


-- ============================================================
-- FONCTION utilitaire : crée le schéma tenant + toutes les tables
-- Appelée par Laravel TenantService via DB::statement()
-- ============================================================

CREATE OR REPLACE FUNCTION create_tenant_schema(p_schema_name VARCHAR)
RETURNS VOID AS $$
BEGIN
    -- Créer le schéma
    EXECUTE format('CREATE SCHEMA IF NOT EXISTS %I', p_schema_name);
    EXECUTE format('SET search_path TO %I', p_schema_name);

    -- --------------------------------------------------------
    -- departments
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.departments (
        id          SERIAL          PRIMARY KEY,
        company_id  UUID            NULL,       -- Utilisé uniquement en mode tenancy_type=''shared''
        name        VARCHAR(100)    NOT NULL,
        manager_id  INT             NULL,       -- FK ajoutée après employees
        created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name);

    EXECUTE format('CREATE INDEX IF NOT EXISTS idx_dept_company ON %I.departments(company_id) WHERE company_id IS NOT NULL', p_schema_name);

    -- --------------------------------------------------------
    -- positions
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.positions (
        id              SERIAL          PRIMARY KEY,
        company_id      UUID            NULL,
        name            VARCHAR(100)    NOT NULL,
        department_id   INT             NOT NULL REFERENCES %I.departments(id) ON DELETE CASCADE,
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name);

    -- --------------------------------------------------------
    -- schedules (plannings de travail)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.schedules (
        id                          SERIAL          PRIMARY KEY,
        company_id                  UUID            NULL,
        name                        VARCHAR(100)    NOT NULL,
        start_time                  TIME            NOT NULL DEFAULT ''08:00:00'',
        end_time                    TIME            NOT NULL DEFAULT ''17:00:00'',
        break_minutes               INT             NOT NULL DEFAULT 60,
        work_days                   JSONB           NOT NULL DEFAULT ''[1,2,3,4,5]'',
        late_tolerance_minutes      INT             NOT NULL DEFAULT 15,
        overtime_threshold_daily    DECIMAL(4,2)    NOT NULL DEFAULT 8.0,
        overtime_threshold_weekly   DECIMAL(5,2)    NOT NULL DEFAULT 40.0,
        is_default                  BOOLEAN         NOT NULL DEFAULT FALSE
    )', p_schema_name);

    -- --------------------------------------------------------
    -- sites (lieux de travail)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.sites (
        id              SERIAL          PRIMARY KEY,
        company_id      UUID            NULL,
        name            VARCHAR(100)    NOT NULL,
        address         TEXT            NULL,
        gps_lat         DECIMAL(10,8)   NULL,
        gps_lng         DECIMAL(11,8)   NULL,
        gps_radius_m    INT             NOT NULL DEFAULT 100
    )', p_schema_name);

    -- --------------------------------------------------------
    -- employees (table centrale)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.employees (
        id                  SERIAL          PRIMARY KEY,
        company_id          UUID            NULL,       -- Utilisé uniquement en mode tenancy_type=''shared''
        matricule           VARCHAR(20)     NOT NULL UNIQUE,
        zkteco_id           VARCHAR(50)     NULL,       -- ID unique dans le lecteur biométrique
        first_name          VARCHAR(100)    NOT NULL,
        last_name           VARCHAR(100)    NOT NULL,
        email               VARCHAR(150)    NOT NULL UNIQUE,
        phone               VARCHAR(30)     NULL,
        password_hash       VARCHAR(255)    NOT NULL,
        role                VARCHAR(20)     NOT NULL DEFAULT ''employee''
                                CHECK (role IN (''manager'',''employee'')),
        manager_role        VARCHAR(20)     NULL
                                CHECK (manager_role IN (''principal'',''rh'',''dept'',''comptable'',''superviseur'')),
        department_id       INT             NULL REFERENCES %I.departments(id),
        position_id         INT             NULL REFERENCES %I.positions(id),
        schedule_id         INT             NULL REFERENCES %I.schedules(id),
        manager_id          INT             NULL REFERENCES %I.employees(id),
        site_id             INT             NULL REFERENCES %I.sites(id),
        date_of_birth       DATE            NULL,
        gender              CHAR(1)         NULL CHECK (gender IN (''M'',''F'')),
        nationality         CHAR(2)         NULL,
        national_id         VARCHAR(50)     NULL,
        address             TEXT            NULL,
        personal_email      VARCHAR(150)    NULL,
        emergency_contact   JSONB           NULL,
        contract_type       VARCHAR(20)     NOT NULL DEFAULT ''CDI''
                                CHECK (contract_type IN (''CDI'',''CDD'',''Stage'',''Interim'',''Consultant'')),
        contract_start      DATE            NOT NULL,
        contract_end        DATE            NULL,
        salary_base         DECIMAL(12,2)   NOT NULL DEFAULT 0,
        salary_type         VARCHAR(20)     NOT NULL DEFAULT ''fixed''
                                CHECK (salary_type IN (''fixed'',''hourly'',''daily'')),
        hourly_rate         DECIMAL(10,2)   NULL,
        payment_method      VARCHAR(20)     NOT NULL DEFAULT ''bank_transfer''
                                CHECK (payment_method IN (''bank_transfer'',''cash'',''cheque'')),
        iban                TEXT            NULL,   -- chiffré via Laravel Crypt
        bank_account        TEXT            NULL,   -- chiffré via Laravel Crypt
        leave_balance       DECIMAL(6,2)    NOT NULL DEFAULT 0,
        status              VARCHAR(20)     NOT NULL DEFAULT ''active''
                                CHECK (status IN (''active'',''suspended'',''archived'')),
        photo_path          VARCHAR(255)    NULL,
        extra_data          JSONB           NULL,
        email_verified_at   TIMESTAMPTZ     NULL,
        created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
        updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name, p_schema_name, p_schema_name, p_schema_name, p_schema_name);

    -- Index employees
    EXECUTE format('CREATE INDEX idx_emp_dept   ON %I.employees(department_id)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_emp_status ON %I.employees(status)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_emp_mgr    ON %I.employees(manager_id, status)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_emp_cend   ON %I.employees(contract_end) WHERE contract_end IS NOT NULL', p_schema_name);

    -- FK departments.manager_id → employees (ajoutée après la création d'employees)
    EXECUTE format('
        ALTER TABLE %I.departments
        ADD CONSTRAINT fk_dept_manager
        FOREIGN KEY (manager_id) REFERENCES %I.employees(id) ON DELETE SET NULL
    ', p_schema_name, p_schema_name);

    -- --------------------------------------------------------
    -- employee_devices (tokens FCM pour les push notifications)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.employee_devices (
        id          SERIAL          PRIMARY KEY,
        company_id  UUID            NULL,
        employee_id INT             NOT NULL REFERENCES %I.employees(id) ON DELETE CASCADE,
        fcm_token   TEXT            NOT NULL UNIQUE,
        platform    VARCHAR(10)     NOT NULL CHECK (platform IN (''android'',''ios'')),
        device_name VARCHAR(150)    NULL,
        last_seen   TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
        created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_devs_employee ON %I.employee_devices(employee_id)', p_schema_name);

    -- --------------------------------------------------------
    -- devices (lecteurs biométriques ZKTeco, terminaux QR)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.devices (
        id              SERIAL          PRIMARY KEY,
        company_id      UUID            NULL,
        name            VARCHAR(100)    NOT NULL,
        model           VARCHAR(100)    NOT NULL,
        serial_number   VARCHAR(100)    NULL UNIQUE,
        type            VARCHAR(20)     NOT NULL CHECK (type IN (''zkteco'',''qrcode_terminal'',''tablet'')),
        site_id         INT             NULL REFERENCES %I.sites(id),
        token           VARCHAR(255)    NOT NULL UNIQUE,   -- haché Hash::make()
        last_sync_at    TIMESTAMPTZ     NULL,
        status          VARCHAR(20)     NOT NULL DEFAULT ''active''
                            CHECK (status IN (''active'',''inactive'',''error'')),
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name);

    -- --------------------------------------------------------
    -- attendance_logs
    -- ⚠️  RÈGLE CRITIQUE : check-out = UPDATE sur la ligne du jour
    --                       check-in  = INSERT (une seule ligne par employé par jour)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.attendance_logs (
        id                  BIGSERIAL       PRIMARY KEY,
        company_id          UUID            NULL,
        employee_id         INT             NOT NULL REFERENCES %I.employees(id),
        date                DATE            NOT NULL,
        check_in            TIMESTAMPTZ     NULL,   -- horodatage serveur UTC
        check_out           TIMESTAMPTZ     NULL,   -- horodatage serveur UTC — UPDATE, jamais INSERT
        method              VARCHAR(20)     NOT NULL DEFAULT ''mobile''
                                CHECK (method IN (''mobile'',''qrcode'',''biometric'',''manual'')),
        gps_lat             DECIMAL(10,8)   NULL,
        gps_lng             DECIMAL(11,8)   NULL,
        gps_valid           BOOLEAN         NULL,
        photo_path          VARCHAR(255)    NULL,
        hours_worked        DECIMAL(5,2)    NULL,   -- calculé après check_out
        overtime_hours      DECIMAL(5,2)    NULL DEFAULT 0,
        status              VARCHAR(20)     NOT NULL DEFAULT ''incomplete''
                                CHECK (status IN (''ontime'',''late'',''absent'',''leave'',''holiday'',''incomplete'')),
        is_manual_edit      BOOLEAN         NOT NULL DEFAULT FALSE,
        edited_by           INT             NULL REFERENCES %I.employees(id),
        edit_reason         TEXT            NULL,
        created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_att_date_status ON %I.attendance_logs(date, status)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_att_emp_date    ON %I.attendance_logs(employee_id, date DESC)', p_schema_name);

    -- --------------------------------------------------------
    -- absence_types
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.absence_types (
        id                      SERIAL          PRIMARY KEY,
        company_id              UUID            NULL,
        label                   VARCHAR(100)    NOT NULL,
        is_paid                 BOOLEAN         NOT NULL DEFAULT TRUE,
        pay_rate                DECIMAL(5,2)    NULL,   -- % si paiement partiel
        deducts_leave           BOOLEAN         NOT NULL DEFAULT TRUE,
        requires_justification  BOOLEAN         NOT NULL DEFAULT FALSE,
        min_notice_days         INT             NOT NULL DEFAULT 0,
        max_days                INT             NULL,   -- NULL = illimité
        who_submits             VARCHAR(20)     NOT NULL DEFAULT ''employee''
                                    CHECK (who_submits IN (''employee'',''manager'')),
        color                   VARCHAR(7)      NOT NULL DEFAULT ''#4CAF50''
    )', p_schema_name);

    -- --------------------------------------------------------
    -- absences
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.absences (
        id                  SERIAL          PRIMARY KEY,
        company_id          UUID            NULL,
        employee_id         INT             NOT NULL REFERENCES %I.employees(id),
        type_id             INT             NOT NULL REFERENCES %I.absence_types(id),
        start_date          DATE            NOT NULL,
        end_date            DATE            NOT NULL,
        days_count          INT             NOT NULL,   -- calculé auto (hors WE et fériés)
        status              VARCHAR(20)     NOT NULL DEFAULT ''pending''
                                CHECK (status IN (''pending'',''approved'',''rejected'',''cancelled'')),
        comment             TEXT            NULL,
        attachment_path     VARCHAR(255)    NULL,
        decided_by          INT             NULL REFERENCES %I.employees(id),
        decision_comment    TEXT            NULL,
        created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
        deleted_at          TIMESTAMPTZ     NULL        -- soft delete
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_abs_emp_status ON %I.absences(employee_id, status)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_abs_dates      ON %I.absences(start_date, end_date)', p_schema_name);

    -- --------------------------------------------------------
    -- leave_balance_logs
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.leave_balance_logs (
        id              BIGSERIAL       PRIMARY KEY,
        company_id      UUID            NULL,
        employee_id     INT             NOT NULL REFERENCES %I.employees(id),
        type            VARCHAR(20)     NOT NULL
                            CHECK (type IN (''accrual'',''consumption'',''adjustment'',''reset'',''carry_over'')),
        days            DECIMAL(6,2)    NOT NULL,   -- positif = ajout, négatif = déduction
        balance_after   DECIMAL(6,2)    NOT NULL,
        reference_id    INT             NULL,       -- absence_id si consumption
        created_by      INT             NULL REFERENCES %I.employees(id),
        note            TEXT            NULL,
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_lbl_emp ON %I.leave_balance_logs(employee_id, created_at DESC)', p_schema_name);

    -- --------------------------------------------------------
    -- salary_advances
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.salary_advances (
        id                  SERIAL          PRIMARY KEY,
        company_id          UUID            NULL,
        employee_id         INT             NOT NULL REFERENCES %I.employees(id),
        amount              DECIMAL(12,2)   NOT NULL,
        reason              TEXT            NULL,
        status              VARCHAR(20)     NOT NULL DEFAULT ''pending''
                                CHECK (status IN (''pending'',''approved'',''rejected'',''repaid'')),
        repayment_plan      JSONB           NULL,
        -- Structure: [{"month":"2026-05","amount":5000,"paid":false}, ...]
        -- "paid" est mis à true par PayrollService lors du calcul mensuel
        amount_remaining    DECIMAL(12,2)   NOT NULL DEFAULT 0,
        approved_by         INT             NULL REFERENCES %I.employees(id),
        decision_comment    TEXT            NULL,
        created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
        updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_adv_emp_status ON %I.salary_advances(employee_id, status)', p_schema_name);

    -- --------------------------------------------------------
    -- projects
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.projects (
        id          SERIAL          PRIMARY KEY,
        company_id  UUID            NULL,
        name        VARCHAR(150)    NOT NULL,
        description TEXT            NULL,
        start_date  DATE            NULL,
        end_date    DATE            NULL,
        members     JSONB           NOT NULL DEFAULT ''[]'',   -- [employee_ids]
        status      VARCHAR(20)     NOT NULL DEFAULT ''active''
                        CHECK (status IN (''active'',''completed'',''archived'')),
        created_by  INT             NOT NULL REFERENCES %I.employees(id),
        created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name);

    -- --------------------------------------------------------
    -- tasks
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.tasks (
        id              SERIAL          PRIMARY KEY,
        company_id      UUID            NULL,
        title           VARCHAR(200)    NOT NULL,
        description     TEXT            NULL,
        created_by      INT             NOT NULL REFERENCES %I.employees(id),
        assigned_to     JSONB           NOT NULL DEFAULT ''[]'',   -- [employee_ids]
        project_id      INT             NULL REFERENCES %I.projects(id),
        due_date        TIMESTAMPTZ     NOT NULL,
        priority        VARCHAR(10)     NOT NULL DEFAULT ''normal''
                            CHECK (priority IN (''low'',''normal'',''high'',''urgent'')),
        status          VARCHAR(20)     NOT NULL DEFAULT ''todo''
                            CHECK (status IN (''todo'',''inprogress'',''review'',''done'',''rejected'',''cancelled'')),
        category        VARCHAR(100)    NULL,
        checklist       JSONB           NULL,   -- [{label, done}]
        visibility      VARCHAR(10)     NOT NULL DEFAULT ''visible''
                            CHECK (visibility IN (''private'',''visible'')),
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
        updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_tasks_status_due ON %I.tasks(status, due_date)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_tasks_assigned   ON %I.tasks USING GIN(assigned_to)', p_schema_name);

    -- --------------------------------------------------------
    -- task_comments
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.task_comments (
        id              SERIAL          PRIMARY KEY,
        task_id         INT             NOT NULL REFERENCES %I.tasks(id) ON DELETE CASCADE,
        author_id       INT             NOT NULL REFERENCES %I.employees(id),
        content         TEXT            NOT NULL,
        attachment_path VARCHAR(255)    NULL,
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_tc_task ON %I.task_comments(task_id, created_at)', p_schema_name);

    -- --------------------------------------------------------
    -- evaluations
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.evaluations (
        id                  SERIAL          PRIMARY KEY,
        company_id          UUID            NULL,
        employee_id         INT             NOT NULL REFERENCES %I.employees(id),
        evaluator_id        INT             NOT NULL REFERENCES %I.employees(id),
        period              VARCHAR(20)     NOT NULL,   -- ex: "2026-S1", "2026-Q1"
        criteria_scores     JSONB           NOT NULL,   -- {"Qualité du travail":4, "Ponctualité":5}
        global_score        DECIMAL(4,2)    NOT NULL,
        comment             TEXT            NULL,
        objectives          TEXT            NULL,
        self_evaluation     JSONB           NULL,       -- rempli par l''employé si option activée
        created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
        CONSTRAINT uq_eval_period UNIQUE (employee_id, period)
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_eval_emp ON %I.evaluations(employee_id, created_at DESC)', p_schema_name);

    -- --------------------------------------------------------
    -- payrolls
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.payrolls (
        id                  BIGSERIAL       PRIMARY KEY,
        company_id          UUID            NULL,
        employee_id         INT             NOT NULL REFERENCES %I.employees(id),
        period_month        INT             NOT NULL CHECK (period_month BETWEEN 1 AND 12),
        period_year         INT             NOT NULL CHECK (period_year >= 2020),
        gross_salary        DECIMAL(12,2)   NOT NULL DEFAULT 0,
        overtime_amount     DECIMAL(12,2)   NOT NULL DEFAULT 0,
        bonuses             JSONB           NOT NULL DEFAULT ''[]'',   -- [{name, amount}]
        deductions          JSONB           NOT NULL DEFAULT ''[]'',   -- [{name, amount}]
        cotisations         JSONB           NOT NULL DEFAULT ''[]'',   -- [{name, rate, base, amount}]
        ir_amount           DECIMAL(12,2)   NOT NULL DEFAULT 0,
        advance_deduction   DECIMAL(12,2)   NOT NULL DEFAULT 0,
        absence_deduction   DECIMAL(12,2)   NOT NULL DEFAULT 0,
        net_salary          DECIMAL(12,2)   NOT NULL DEFAULT 0,
        pdf_path            VARCHAR(255)    NULL,
        status              VARCHAR(10)     NOT NULL DEFAULT ''draft''
                                CHECK (status IN (''draft'',''validated'')),
        validated_by        INT             NULL REFERENCES %I.employees(id),
        validated_at        TIMESTAMPTZ     NULL,
        CONSTRAINT uq_payroll_period UNIQUE (employee_id, period_month, period_year)
    )', p_schema_name, p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_payroll_period ON %I.payrolls(period_month, period_year, status)', p_schema_name);

    -- --------------------------------------------------------
    -- payroll_export_batches
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.payroll_export_batches (
        id              SERIAL          PRIMARY KEY,
        period_month    INT             NOT NULL,
        period_year     INT             NOT NULL,
        bank_format     VARCHAR(30)     NOT NULL,   -- DZ_GENERIC, MA_CIH, FR_SEPA...
        file_path       VARCHAR(255)    NOT NULL,
        total_amount    DECIMAL(14,2)   NOT NULL,
        employees_count INT             NOT NULL,
        exported_by     INT             NOT NULL REFERENCES %I.employees(id),
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name);

    -- --------------------------------------------------------
    -- payroll_export_items  (pivot batch ↔ payrolls)
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.payroll_export_items (
        id          SERIAL          PRIMARY KEY,
        batch_id    INT             NOT NULL REFERENCES %I.payroll_export_batches(id) ON DELETE CASCADE,
        payroll_id  BIGINT          NOT NULL REFERENCES %I.payrolls(id),
        amount      DECIMAL(12,2)   NOT NULL,   -- snapshot net_salary au moment de l''export
        CONSTRAINT uq_export_item UNIQUE (batch_id, payroll_id)
    )', p_schema_name, p_schema_name, p_schema_name);

    -- --------------------------------------------------------
    -- company_settings
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.company_settings (
        key         VARCHAR(100)    PRIMARY KEY,
        value       TEXT            NOT NULL,
        value_type  VARCHAR(10)     NOT NULL DEFAULT ''string''
                        CHECK (value_type IN (''string'',''integer'',''boolean'',''json'',''decimal'')),
        updated_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name);

    -- --------------------------------------------------------
    -- audit_logs
    -- ⚠️  Pas de FK sur actor_id : le Super Admin est dans le schéma public,
    --     une FK vers employees.id serait invalide pour ses actions.
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.audit_logs (
        id          BIGSERIAL       PRIMARY KEY,
        company_id  UUID            NULL,
        actor_type  VARCHAR(20)     NOT NULL DEFAULT ''employee''
                        CHECK (actor_type IN (''employee'',''super_admin'',''system'')),
        actor_id    INT             NULL,       -- employees.id OU super_admins.id selon actor_type
        actor_name  VARCHAR(200)    NULL,       -- dénormalisé pour l''affichage (pas de JOIN cross-schema)
        action      VARCHAR(100)    NOT NULL,
        table_name  VARCHAR(100)    NOT NULL,
        record_id   VARCHAR(50)     NULL,
        old_values  JSONB           NULL,
        new_values  JSONB           NULL,
        ip          VARCHAR(45)     NULL,
        user_agent  TEXT            NULL,
        created_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name);

    EXECUTE format('CREATE INDEX idx_audit_actor ON %I.audit_logs(actor_type, actor_id, created_at DESC)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_audit_table ON %I.audit_logs(table_name, record_id)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_audit_date  ON %I.audit_logs(created_at)', p_schema_name);

    -- --------------------------------------------------------
    -- notifications
    -- --------------------------------------------------------
    EXECUTE format('
    CREATE TABLE IF NOT EXISTS %I.notifications (
        id              BIGSERIAL       PRIMARY KEY,
        company_id      UUID            NULL,
        recipient_id    INT             NOT NULL REFERENCES %I.employees(id) ON DELETE CASCADE,
        type            VARCHAR(100)    NOT NULL,   -- "absence.approved", "task.assigned"
        title           VARCHAR(200)    NOT NULL,
        body            TEXT            NOT NULL,
        data            JSONB           NULL,       -- données pour le deep link Flutter
        read_at         TIMESTAMPTZ     NULL,
        created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    )', p_schema_name, p_schema_name);

    EXECUTE format('CREATE INDEX idx_notif_recipient ON %I.notifications(recipient_id, read_at)', p_schema_name);
    EXECUTE format('CREATE INDEX idx_notif_date      ON %I.notifications(recipient_id, created_at DESC)', p_schema_name);

END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- EXEMPLE D'UTILISATION (NE PAS EXÉCUTER EN PRODUCTION MANUELLEMENT)
-- Laravel TenantService appelle :
--   DB::statement("SELECT create_tenant_schema('company_7c9e6679')");
-- ============================================================

-- ============================================================
-- PARAMÈTRES COMPANY_SETTINGS PAR DÉFAUT
-- À insérer après la création de chaque tenant via TenantService
-- ============================================================

-- (Exemple pour le schéma company_7c9e6679 — TenantService généralise)
-- INSERT INTO company_7c9e6679.company_settings (key, value, value_type) VALUES
--   ('attendance.gps_enabled',        'false',  'boolean'),
--   ('attendance.gps_radius_m',        '100',    'integer'),
--   ('attendance.photo_enabled',       'false',  'boolean'),
--   ('attendance.qr_code_token',       '',       'string'),
--   ('attendance.biometric_enabled',   'false',  'boolean'),
--   ('advance.enabled',                'false',  'boolean'),
--   ('advance.max_percentage',         '50',     'integer'),
--   ('advance.max_simultaneous',       '1',      'integer'),
--   ('advance.max_repayment_months',   '3',      'integer'),
--   ('advance.min_delay_days',         '30',     'integer'),
--   ('leave.accrual_rate_monthly',     '2.5',    'decimal'),
--   ('leave.carry_over',               'false',  'boolean'),
--   ('leave.carry_over_max_days',      '0',      'integer'),
--   ('leave.max_balance',              '60',     'decimal'),
--   ('leave.validation_levels',        '1',      'integer'),
--   ('leave.min_notice_days',          '3',      'integer'),
--   ('payroll.cotisations',            '[]',     'json'),
--   ('payroll.ir_brackets',            '[]',     'json'),
--   ('payroll.overtime_rate_1',        '1.25',   'decimal'),
--   ('payroll.overtime_rate_2',        '1.50',   'decimal'),
--   ('payroll.bank_export_format',     'GENERIC_CSV', 'string'),
--   ('tasks.enabled',                  'true',   'boolean'),
--   ('evaluations.auto_enabled',       'false',  'boolean'),
--   ('evaluations.self_eval_enabled',  'false',  'boolean');
