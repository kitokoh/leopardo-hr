DROP TABLE IF EXISTS public.user_invitations CASCADE;
DROP TABLE IF EXISTS public.super_admins CASCADE;
DROP TABLE IF EXISTS public.user_lookups CASCADE;
DROP TABLE IF EXISTS public.personal_access_tokens CASCADE;
DROP TABLE IF EXISTS public.languages CASCADE;
DROP TABLE IF EXISTS public.companies CASCADE;
DROP TABLE IF EXISTS public.plans CASCADE;
DROP TABLE IF EXISTS shared_tenants.evaluations CASCADE;
DROP TABLE IF EXISTS shared_tenants.features CASCADE;
DROP TABLE IF EXISTS shared_tenants.payrolls CASCADE;
DROP TABLE IF EXISTS shared_tenants.projects CASCADE;
DROP TABLE IF EXISTS shared_tenants.tasks CASCADE;
DROP TABLE IF EXISTS shared_tenants.notifications CASCADE;
DROP TABLE IF EXISTS shared_tenants.departments CASCADE;
DROP TABLE IF EXISTS shared_tenants.positions CASCADE;
DROP TABLE IF EXISTS shared_tenants.sites CASCADE;
DROP TABLE IF EXISTS shared_tenants.salary_advances CASCADE;
DROP TABLE IF EXISTS shared_tenants.leave_balance_logs CASCADE;
DROP TABLE IF EXISTS shared_tenants.absences CASCADE;
DROP TABLE IF EXISTS shared_tenants.absence_types CASCADE;
DROP TABLE IF EXISTS shared_tenants.camera_access_logs CASCADE;
DROP TABLE IF EXISTS shared_tenants.camera_permissions CASCADE;
DROP TABLE IF EXISTS shared_tenants.camera_access_tokens CASCADE;
DROP TABLE IF EXISTS shared_tenants.cameras CASCADE;
DROP TABLE IF EXISTS shared_tenants.attendance_kiosks CASCADE;
DROP TABLE IF EXISTS shared_tenants.biometric_enrollment_requests CASCADE;
DROP TABLE IF EXISTS shared_tenants.attendance_correction_requests CASCADE;
DROP TABLE IF EXISTS shared_tenants.attendance_logs CASCADE;
DROP TABLE IF EXISTS shared_tenants.employees CASCADE;
DROP TABLE IF EXISTS shared_tenants.schedules CASCADE;

CREATE TABLE public.plans (
    id serial PRIMARY KEY,
    name varchar(50) NOT NULL,
    price_monthly numeric(10, 2) NOT NULL DEFAULT 0,
    price_yearly numeric(10, 2) NOT NULL DEFAULT 0,
    max_employees integer NULL,
    features jsonb NULL DEFAULT '{}'::jsonb,
    trial_days smallint NOT NULL DEFAULT 14,
    is_active boolean NOT NULL DEFAULT true
);

CREATE TABLE public.companies (
    id uuid PRIMARY KEY,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    sector varchar(255) NOT NULL,
    country char(2) NOT NULL,
    city varchar(255) NOT NULL,
    address varchar(255) NULL,
    email varchar(255) NOT NULL,
    phone varchar(255) NULL,
    plan_id integer NULL,
    schema_name varchar(63) NOT NULL,
    tenancy_type varchar(20) NOT NULL DEFAULT 'shared',
    status varchar(20) NOT NULL DEFAULT 'active',
    subscription_start date NULL,
    subscription_end date NULL,
    language char(2) NOT NULL DEFAULT 'fr',
    timezone varchar(50) NOT NULL DEFAULT 'Africa/Algiers',
    currency char(3) NOT NULL DEFAULT 'DZD',
    notes text NULL,
    features jsonb NOT NULL DEFAULT '{}'::jsonb,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX companies_slug_unique ON public.companies (slug);
CREATE UNIQUE INDEX companies_email_unique ON public.companies (email);

CREATE TABLE public.languages (
    code char(2) PRIMARY KEY,
    name_fr varchar(50) NOT NULL,
    name_native varchar(50) NOT NULL,
    is_rtl boolean NOT NULL DEFAULT false,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamptz NULL,
    updated_at timestamptz NULL
);

CREATE TABLE shared_tenants.schedules (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    name varchar(100) NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    break_minutes smallint NOT NULL DEFAULT 60,
    break_rules jsonb NULL,
    work_days jsonb NULL,
    rest_days jsonb NULL,
    leave_rules jsonb NULL,
    assignment_notes text NULL,
    late_tolerance_minutes smallint NOT NULL DEFAULT 15,
    overtime_threshold_daily numeric(4, 2) NOT NULL DEFAULT 8.00,
    overtime_threshold_weekly numeric(5, 2) NOT NULL DEFAULT 40.00,
    is_default boolean NOT NULL DEFAULT false,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX schedules_company_id_index ON shared_tenants.schedules (company_id);

CREATE TABLE shared_tenants.employees (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    schedule_id integer NULL,
    department_id integer NULL,
    position_id integer NULL,
    site_id integer NULL,
    matricule varchar(20) NULL,
    zkteco_id varchar(50) NULL,
    first_name varchar(100) NOT NULL DEFAULT '',
    middle_name varchar(100) NULL,
    last_name varchar(100) NOT NULL DEFAULT '',
    preferred_name varchar(100) NULL,
    email varchar(150) NOT NULL,
    personal_email varchar(150) NULL,
    recovery_email varchar(150) NULL,
    phone varchar(30) NULL,
    personal_phone varchar(30) NULL,
    password_hash varchar(255) NOT NULL,
    date_of_birth date NULL,
    place_of_birth varchar(120) NULL,
    gender char(1) NULL,
    nationality char(2) NULL,
    marital_status varchar(30) NULL,
    address_line varchar(255) NULL,
    postal_code varchar(20) NULL,
    contract_type varchar(20) NOT NULL DEFAULT 'CDI',
    contract_start date NOT NULL DEFAULT CURRENT_DATE,
    contract_end date NULL,
    salary_type varchar(20) NOT NULL DEFAULT 'fixed',
    salary_base numeric(10, 2) NOT NULL DEFAULT 0,
    hourly_rate numeric(10, 2) NULL,
    payment_method varchar(50) NOT NULL DEFAULT 'bank_transfer',
    role varchar(20) NOT NULL DEFAULT 'employee',
    manager_role varchar(30) NULL,
    manager_id integer NULL,
    leave_balance numeric(6, 2) NOT NULL DEFAULT 0,
    status varchar(20) NOT NULL DEFAULT 'active',
    archived_at timestamptz NULL,
    preferred_language char(2) NULL,
    photo_path varchar(255) NULL,
    iban varchar(255) NULL,
    bank_account varchar(255) NULL,
    national_id varchar(255) NULL,
    biometric_face_enabled boolean NOT NULL DEFAULT false,
    biometric_fingerprint_enabled boolean NOT NULL DEFAULT false,
    biometric_face_reference_path varchar(255) NULL,
    biometric_fingerprint_reference_path varchar(255) NULL,
    biometric_consent_at timestamptz NULL,
    invitation_accepted_at timestamptz NULL,
    emergency_contact_name varchar(150) NULL,
    emergency_contact_phone varchar(30) NULL,
    emergency_contact_relation varchar(60) NULL,
    failed_login_attempts smallint NOT NULL DEFAULT 0,
    locked_until timestamptz NULL,
    last_login_at timestamptz NULL,
    email_verified_at timestamptz NULL,
    extra_data jsonb NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX employees_email_unique ON shared_tenants.employees (email);
CREATE UNIQUE INDEX employees_company_id_matricule_unique ON shared_tenants.employees (company_id, matricule);

CREATE TABLE shared_tenants.attendance_logs (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    employee_id integer NOT NULL,
    schedule_id integer NULL,
    date date NOT NULL,
    session_number smallint NOT NULL DEFAULT 1,
    check_in timestamptz NULL,
    check_out timestamptz NULL,
    method varchar(20) NOT NULL DEFAULT 'mobile',
    source_device_code varchar(40) NULL,
    external_event_id varchar(100) NULL,
    biometric_type varchar(20) NULL,
    synced_from_offline boolean NOT NULL DEFAULT false,
    work_type varchar(30) NOT NULL DEFAULT 'normal',
    punch_note text NULL,
    punch_meta jsonb NULL,
    status varchar(20) NOT NULL DEFAULT 'incomplete',
    hours_worked numeric(5, 2) NULL,
    overtime_hours numeric(5, 2) NOT NULL DEFAULT 0,
    late_minutes smallint NOT NULL DEFAULT 0,
    gps_lat numeric(10, 8) NULL,
    gps_lng numeric(11, 8) NULL,
    corrected_by integer NULL,
    correction_note text NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX attendance_logs_external_event_id_unique
    ON shared_tenants.attendance_logs (external_event_id);
CREATE UNIQUE INDEX attendance_logs_employee_id_date_session_number_unique
    ON shared_tenants.attendance_logs (employee_id, date, session_number);
CREATE INDEX attendance_logs_employee_id_date_index
    ON shared_tenants.attendance_logs (employee_id, date);
CREATE INDEX attendance_logs_company_id_index
    ON shared_tenants.attendance_logs (company_id);

CREATE TABLE shared_tenants.attendance_correction_requests (
    id bigserial PRIMARY KEY,
    company_id uuid NOT NULL,
    employee_id integer NOT NULL,
    attendance_log_id bigint NULL,
    date date NOT NULL,
    requested_check_in timestamptz NOT NULL,
    requested_check_out timestamptz NULL,
    reason text NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    reviewed_by integer NULL,
    reviewed_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX attendance_correction_requests_company_id_index
    ON shared_tenants.attendance_correction_requests (company_id);
CREATE INDEX attendance_correction_requests_employee_id_index
    ON shared_tenants.attendance_correction_requests (employee_id);
CREATE INDEX attendance_correction_requests_status_index
    ON shared_tenants.attendance_correction_requests (status);
CREATE INDEX attendance_correction_requests_date_index
    ON shared_tenants.attendance_correction_requests (date);

CREATE TABLE shared_tenants.biometric_enrollment_requests (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    employee_id integer NOT NULL,
    approver_employee_id integer NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    requested_face_enabled boolean NOT NULL DEFAULT false,
    requested_fingerprint_enabled boolean NOT NULL DEFAULT false,
    requested_face_reference_path varchar(255) NULL,
    requested_fingerprint_reference_path varchar(255) NULL,
    requested_fingerprint_device_id varchar(100) NULL,
    request_source varchar(30) NOT NULL DEFAULT 'mobile',
    employee_note text NULL,
    manager_note text NULL,
    submitted_at timestamptz NULL,
    approved_at timestamptz NULL,
    rejected_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX biometric_enrollment_requests_company_id_index
    ON shared_tenants.biometric_enrollment_requests (company_id);
CREATE INDEX biometric_enrollment_requests_employee_id_index
    ON shared_tenants.biometric_enrollment_requests (employee_id);
CREATE INDEX biometric_enrollment_requests_approver_employee_id_index
    ON shared_tenants.biometric_enrollment_requests (approver_employee_id);

CREATE TABLE shared_tenants.attendance_kiosks (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    name varchar(100) NOT NULL,
    location_label varchar(120) NULL,
    device_code varchar(40) NOT NULL,
    sync_token_hash varchar(255) NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    biometric_mode varchar(30) NOT NULL DEFAULT 'fingerprint',
    trusted_device_label varchar(120) NULL,
    last_seen_at timestamptz NULL,
    last_sync_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX attendance_kiosks_device_code_unique
    ON shared_tenants.attendance_kiosks (device_code);
CREATE INDEX attendance_kiosks_company_id_index
    ON shared_tenants.attendance_kiosks (company_id);

CREATE TABLE shared_tenants.cameras (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    name varchar(100) NOT NULL,
    rtsp_url text NOT NULL,
    location varchar(200) NULL,
    is_active boolean NOT NULL DEFAULT true,
    thumbnail_path varchar(255) NULL,
    sort_order smallint NOT NULL DEFAULT 0,
    created_by integer NOT NULL,
    stream_path_override varchar(100) NULL,
    metadata jsonb NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    deleted_at timestamp NULL
);

CREATE TABLE shared_tenants.camera_access_tokens (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    camera_id integer NOT NULL,
    token varchar(64) NOT NULL,
    label varchar(150) NULL,
    granted_to_email varchar(150) NULL,
    granted_to_name varchar(100) NULL,
    granted_by integer NOT NULL,
    permissions jsonb NULL,
    expires_at timestamptz NOT NULL,
    last_used_at timestamptz NULL,
    use_count integer NOT NULL DEFAULT 0,
    is_revoked boolean NOT NULL DEFAULT false,
    ip_whitelist jsonb NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX camera_access_tokens_token_unique
    ON shared_tenants.camera_access_tokens (token);

CREATE TABLE shared_tenants.camera_permissions (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    camera_id integer NOT NULL,
    employee_id integer NOT NULL,
    can_view boolean NOT NULL DEFAULT true,
    can_share boolean NOT NULL DEFAULT false,
    can_manage boolean NOT NULL DEFAULT false,
    granted_by integer NOT NULL,
    granted_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX camera_permissions_camera_id_employee_id_unique
    ON shared_tenants.camera_permissions (camera_id, employee_id);

CREATE TABLE shared_tenants.camera_access_logs (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    camera_id integer NOT NULL,
    employee_id integer NULL,
    access_token_id integer NULL,
    actor_type varchar(20) NOT NULL,
    action varchar(40) NOT NULL,
    reason varchar(60) NULL,
    ip_address varchar(45) NULL,
    user_agent varchar(255) NULL,
    metadata jsonb NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE shared_tenants.audit_logs (
    id bigserial PRIMARY KEY,
    company_id uuid NULL,
    user_id integer NULL,
    action varchar(100) NOT NULL,
    auditable_type varchar(100) NULL,
    auditable_id bigint NULL,
    old_values jsonb NULL,
    new_values jsonb NULL,
    ip_address varchar(45) NULL,
    user_agent varchar(255) NULL,
    metadata jsonb NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX audit_logs_company_id_created_at_index
    ON shared_tenants.audit_logs (company_id, created_at);
CREATE INDEX audit_logs_auditable_type_auditable_id_index
    ON shared_tenants.audit_logs (auditable_type, auditable_id);

CREATE TABLE shared_tenants.absence_types (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    name varchar(100) NOT NULL,
    code varchar(30) NOT NULL,
    is_paid boolean NOT NULL DEFAULT true,
    deducts_leave boolean NOT NULL DEFAULT true,
    requires_proof boolean NOT NULL DEFAULT false,
    max_days_once integer NULL,
    created_at timestamptz NULL
);

CREATE TABLE shared_tenants.absences (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    employee_id integer NOT NULL,
    absence_type_id integer NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    days_count smallint NOT NULL DEFAULT 1,
    status varchar(20) NOT NULL DEFAULT 'pending',
    reason text NULL,
    proof_path varchar(255) NULL,
    approved_by integer NULL,
    rejected_reason text NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE TABLE shared_tenants.leave_balance_logs (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    employee_id integer NOT NULL,
    delta numeric(8, 2) NOT NULL,
    reason varchar(60) NOT NULL,
    reference_id integer NOT NULL DEFAULT 0,
    balance_after numeric(8, 2) NOT NULL,
    created_at timestamptz NULL
);

CREATE TABLE shared_tenants.salary_advances (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    employee_id integer NOT NULL,
    amount numeric(12, 2) NOT NULL,
    reason text NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    approved_by integer NULL,
    manager_approved_at timestamptz NULL,
    manager_approved_by bigint NULL,
    payment_declared_at timestamptz NULL,
    payment_declared_by bigint NULL,
    payment_reference varchar(255) NULL,
    payment_note text NULL,
    employee_confirmed_at timestamptz NULL,
    validation_status varchar(32) NOT NULL DEFAULT 'pending',
    decision_comment text NULL,
    repayment_months smallint NOT NULL DEFAULT 1,
    monthly_deduction numeric(12, 2) NULL,
    amount_remaining numeric(12, 2) NOT NULL DEFAULT 0,
    repayment_plan jsonb NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE TABLE shared_tenants.evaluations (
    id serial PRIMARY KEY,
    company_id uuid NOT NULL,
    employee_id integer NOT NULL,
    evaluator_id integer NOT NULL,
    period varchar(20) NOT NULL,
    score numeric(4, 2) NULL,
    criteria jsonb NOT NULL DEFAULT '[]'::jsonb,
    strengths text NULL,
    improvements text NULL,
    overall_comment text NULL,
    status varchar(20) NOT NULL DEFAULT 'draft',
    acknowledged_at timestamptz NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX evaluations_employee_id_period_evaluator_id_unique
    ON shared_tenants.evaluations (employee_id, period, evaluator_id);

CREATE TABLE shared_tenants.features (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    key varchar(100) NOT NULL,
    title varchar(200) NOT NULL,
    description text NOT NULL,
    endpoint varchar(500) NOT NULL,
    http_methods jsonb NOT NULL,
    parameters jsonb NOT NULL,
    response_schema jsonb NOT NULL,
    permissions jsonb NOT NULL,
    mobile_version_min varchar(20) NOT NULL,
    mobile_version_max varchar(20) NULL,
    api_version varchar(20) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    metadata jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX features_key_unique ON shared_tenants.features (key);
CREATE UNIQUE INDEX features_company_id_key_unique
    ON shared_tenants.features (company_id, key);
CREATE INDEX features_company_id_status_index
    ON shared_tenants.features (company_id, status);
CREATE INDEX features_status_index ON shared_tenants.features (status);
CREATE INDEX features_api_version_index ON shared_tenants.features (api_version);
CREATE INDEX features_mobile_version_min_index
    ON shared_tenants.features (mobile_version_min);
CREATE INDEX features_status_api_version_index
    ON shared_tenants.features (status, api_version);

CREATE TABLE shared_tenants.payrolls (
    id bigserial PRIMARY KEY,
    company_id uuid NULL,
    employee_id integer NOT NULL,
    period_month smallint NOT NULL,
    period_year smallint NOT NULL,
    gross_salary numeric(12, 2) NOT NULL DEFAULT 0,
    net_salary numeric(12, 2) NOT NULL DEFAULT 0,
    status varchar(20) NOT NULL DEFAULT 'draft',
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX payrolls_company_id_index ON shared_tenants.payrolls (company_id);

CREATE TABLE shared_tenants.projects (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    name varchar(150) NOT NULL,
    created_by integer NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX projects_company_id_index ON shared_tenants.projects (company_id);

CREATE TABLE shared_tenants.tasks (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    title varchar(200) NOT NULL,
    created_by integer NOT NULL,
    assigned_to jsonb NOT NULL DEFAULT '[]'::jsonb,
    priority varchar(20) NOT NULL DEFAULT 'normal',
    status varchar(20) NOT NULL DEFAULT 'todo',
    category varchar(100) NULL,
    checklist jsonb NULL,
    visibility varchar(20) NOT NULL DEFAULT 'visible',
    description text NULL,
    due_date timestamptz NOT NULL,
    estimated_minutes integer NULL,
    completed_minutes integer NULL,
    completed_at timestamptz NULL,
    completion_note text NULL,
    performance_score numeric(5, 2) NULL,
    recurrence_rule varchar(120) NULL,
    template_key varchar(100) NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX tasks_company_id_index ON shared_tenants.tasks (company_id);

CREATE TABLE shared_tenants.notifications (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    employee_id integer NOT NULL,
    type varchar(100) NOT NULL,
    title varchar(200) NOT NULL,
    body text NOT NULL,
    data jsonb NULL,
    is_read boolean NOT NULL DEFAULT false,
    read_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX notifications_company_id_index ON shared_tenants.notifications (company_id);
CREATE INDEX notifications_employee_id_is_read_index
    ON shared_tenants.notifications (employee_id, is_read);
CREATE INDEX notifications_created_at_index ON shared_tenants.notifications (created_at);

CREATE TABLE shared_tenants.departments (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    name varchar(150) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX departments_company_id_index ON shared_tenants.departments (company_id);

CREATE TABLE shared_tenants.positions (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    name varchar(150) NOT NULL,
    department_id integer NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX positions_company_id_index ON shared_tenants.positions (company_id);

CREATE TABLE shared_tenants.sites (
    id serial PRIMARY KEY,
    company_id uuid NULL,
    name varchar(150) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE INDEX sites_company_id_index ON shared_tenants.sites (company_id);

CREATE TABLE public.personal_access_tokens (
    id bigserial PRIMARY KEY,
    tokenable_type varchar(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name varchar(255) NOT NULL,
    token varchar(64) NOT NULL,
    abilities text NULL,
    last_used_at timestamp NULL,
    expires_at timestamp NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL
);

CREATE UNIQUE INDEX personal_access_tokens_token_unique
    ON public.personal_access_tokens (token);

CREATE TABLE public.user_lookups (
    email varchar(150) PRIMARY KEY,
    company_id uuid NOT NULL,
    schema_name varchar(63) NOT NULL,
    employee_id integer NOT NULL,
    role varchar(20) NOT NULL
);

CREATE TABLE public.super_admins (
    id serial PRIMARY KEY,
    name varchar(100) NOT NULL,
    email varchar(150) NOT NULL,
    password_hash varchar(255) NOT NULL,
    two_fa_secret varchar(32) NULL,
    last_login_at timestamptz NULL,
    created_at timestamptz NULL
);

CREATE UNIQUE INDEX super_admins_email_unique ON public.super_admins (email);

CREATE TABLE public.user_invitations (
    id uuid PRIMARY KEY,
    company_id uuid NOT NULL,
    schema_name varchar(63) NOT NULL,
    employee_id integer NOT NULL,
    email varchar(150) NOT NULL,
    role varchar(20) NOT NULL,
    manager_role varchar(30) NULL,
    invited_by_type varchar(20) NOT NULL,
    invited_by_email varchar(150) NOT NULL,
    token_hash varchar(64) NOT NULL,
    expires_at timestamptz NOT NULL,
    accepted_at timestamptz NULL,
    last_sent_at timestamptz NULL,
    metadata jsonb NULL,
    created_at timestamptz NULL,
    updated_at timestamptz NULL
);

CREATE UNIQUE INDEX user_invitations_token_hash_unique
    ON public.user_invitations (token_hash);

-- SSO configs (public, FK to companies)
CREATE TABLE IF NOT EXISTS public.company_sso_configs (
    id bigserial PRIMARY KEY,
    company_id uuid NOT NULL,
    provider varchar(20) NOT NULL,
    config jsonb NOT NULL DEFAULT '{}',
    is_active boolean NOT NULL DEFAULT false,
    created_at timestamptz NULL,
    updated_at timestamptz NULL,
    UNIQUE (company_id)
);

-- Device tokens (shared_tenants)
CREATE TABLE IF NOT EXISTS shared_tenants.device_tokens (
    id bigserial PRIMARY KEY,
    employee_id bigint NOT NULL,
    company_id uuid NOT NULL,
    token varchar(512) NOT NULL,
    platform varchar(20) NOT NULL,
    device_name varchar(120) NULL,
    is_active boolean NOT NULL DEFAULT true,
    last_used_at timestamptz NULL,
    created_at timestamptz NULL,
    updated_at timestamptz NULL,
    UNIQUE (employee_id, token)
);

-- Calendar connections (shared_tenants)
CREATE TABLE IF NOT EXISTS shared_tenants.calendar_connections (
    id bigserial PRIMARY KEY,
    employee_id bigint NOT NULL,
    provider varchar(20) NOT NULL,
    access_token text NULL,
    refresh_token text NULL,
    calendar_id varchar(255) NULL,
    token_expires_at timestamptz NULL,
    sync_leaves boolean NOT NULL DEFAULT true,
    sync_training boolean NOT NULL DEFAULT true,
    is_active boolean NOT NULL DEFAULT true,
    last_synced_at timestamptz NULL,
    created_at timestamptz NULL,
    updated_at timestamptz NULL,
    UNIQUE (employee_id, provider)
);

-- Calendar events (shared_tenants)
CREATE TABLE IF NOT EXISTS shared_tenants.calendar_events (
    id bigserial PRIMARY KEY,
    employee_id bigint NOT NULL,
    external_event_id varchar(255) NULL,
    provider varchar(20) NOT NULL DEFAULT 'google',
    title varchar(255) NOT NULL,
    description text NULL,
    starts_at timestamptz NOT NULL,
    ends_at timestamptz NOT NULL,
    all_day boolean NOT NULL DEFAULT false,
    source_type varchar(30) NOT NULL,
    source_id bigint NOT NULL,
    sync_status varchar(20) NOT NULL DEFAULT 'pending',
    created_at timestamptz NULL,
    updated_at timestamptz NULL
);

-- ZKTeco devices (shared_tenants)
CREATE TABLE IF NOT EXISTS shared_tenants.zkteco_devices (
    id bigserial PRIMARY KEY,
    company_id uuid NOT NULL,
    serial_number varchar(100) NOT NULL UNIQUE,
    name varchar(100) NOT NULL,
    ip_address varchar(45) NULL,
    port smallint NOT NULL DEFAULT 4370,
    protocol varchar(20) NOT NULL DEFAULT 'tcp',
    status varchar(20) NOT NULL DEFAULT 'offline',
    last_heartbeat_at timestamptz NULL,
    last_sync_at timestamptz NULL,
    created_at timestamptz NULL,
    updated_at timestamptz NULL
);
