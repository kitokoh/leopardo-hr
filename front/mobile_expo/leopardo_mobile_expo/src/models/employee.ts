export type MobileModule = {
  key: string;
  title: string;
  description: string;
  domain: string;
  route: string | null;
  status: string;
};

export type MobileQuickAction = {
  key: string;
  title: string;
  description: string;
  domain: string;
  icon: string;
  route: string;
};

export type MobileExperience = {
  stage: 'new' | 'regular' | string;
  app?: { id: string; name: string; deep_link_scheme: string };
  modules: MobileModule[];
  quick_actions: MobileQuickAction[];
};

/**
 * Employe authentifie — miroir de EmployeeResource (API Laravel).
 * Seuls les champs consommes par l'app mobile sont typés.
 */
export type Employee = {
  id: number;
  matricule: string | null;
  company_id: string | null;
  first_name: string;
  last_name: string;
  preferred_name?: string | null;
  email: string;
  phone: string | null;
  personal_email?: string | null;
  personal_phone?: string | null;
  schedule_id: number | null;
  schedule?: {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    late_tolerance_minutes: number;
  } | null;
  role: string | null;
  manager_role: string | null;
  status: string;
  work_state: string;
  work_state_label: string;
  photo_url: string | null;
  hire_date: string | null;
  salary_type: string | null;
  salary_base: number | null;
  hourly_rate: number | null;
  currency: string | null;
  department?: string | null;
  job_title?: string | null;
  work_location?: string | null;
  language: string;
  is_rtl: boolean;
  capabilities: string[];
  features: Record<string, boolean>;
  /**
   * Grants de modules delegues par le principal (`/auth/me` → `module_grants`).
   * Exposes uniquement sur SA PROPRE fiche — sert au gating des verticaux.
   */
  module_grants?: string[] | null;
  /** Tenant : type (`company` | `solo`), selection de modules, features. */
  company?: {
    id: string;
    name: string;
    language?: string;
    timezone?: string;
    currency?: string;
    type?: 'company' | 'solo' | string;
    sector?: string | null;
    modules?: Record<string, unknown> | null;
    features?: Record<string, unknown> | null;
  } | null;
  /** Plan commercial : features incluses. */
  plan?: { features?: Record<string, unknown> | null } | null;
  mobile_experience: MobileExperience;
  suggested_home_route?: string | null;
};

export function fullName(employee: Employee | null | undefined): string {
  if (!employee) return '';
  const name = `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim();
  return name || employee.email;
}

export function initials(employee: Employee | null | undefined): string {
  if (!employee) return '? ';
  const first = (employee.first_name ?? '').trim().charAt(0);
  const last = (employee.last_name ?? '').trim().charAt(0);
  return `${first}${last}`.toUpperCase() || '?';
}

/** Capacites utiles pour conditionner l'UI (miroir des capabilityKeys web). */
export function canManageTeam(employee: Employee | null | undefined): boolean {
  if (!employee) return false;
  if (employee.manager_role) return true;
  return employee.capabilities.includes('can_view_employees');
}
