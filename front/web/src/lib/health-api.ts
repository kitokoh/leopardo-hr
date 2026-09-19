/**
 * health-api.ts — client typé de la verticale HealthManager (BC-30, #7792).
 *
 * Consomme l'API tenant `/health-manager/*` (contrat :
 * `api/routes/modules/health_manager.php`, spec
 * `docs/specifications/HEALTHMANAGER_SOLUTION.md`) via `apiFetch`
 * (proxy same-origin `/api/v1`, cookie httpOnly — même pattern que
 * travel/restaurant/edu-manager). Le feature flag tenant `healthmanager`
 * est fail-closed côté serveur : solution inactive → 403.
 */

import { apiFetch } from '@/lib/api-client';

const BASE = '/health-manager';

// ─────────────────────────────────────────────────────────────────────────
// Types (miroir du modèle de données HC-002…HC-007)
// ─────────────────────────────────────────────────────────────────────────

export type HealthStatus = 'active' | 'inactive';

export type Department = {
  id: number;
  name: string;
  code: string;
  description?: string | null;
  status: HealthStatus;
};

export type RoomType = 'consultation' | 'hospitalization' | 'operating' | 'emergency' | 'other';

export type Room = {
  id: number;
  department_id: number;
  name: string;
  code: string;
  type: RoomType;
  status: HealthStatus;
  department?: Department | null;
};

export type BedStatus = 'free' | 'occupied' | 'maintenance';

export type Bed = {
  id: number;
  room_id: number;
  code: string;
  status: BedStatus;
  room?: Room | null;
};

export type Specialty = {
  id: number;
  name: string;
  code: string;
};

export type PractitionerTitle = 'dr' | 'pr' | 'midwife' | 'nurse' | 'other';

export type Practitioner = {
  id: number;
  employee_id: number;
  department_id?: number | null;
  title: PractitionerTitle;
  license_number?: string | null;
  status: HealthStatus;
  full_name?: string | null;
  department?: Department | null;
  specialties?: Specialty[];
};

export type StaffRole = {
  id: number;
  employee_id: number;
  role: 'reception' | 'billing';
  employee_name?: string | null;
};

export type PatientSex = 'male' | 'female' | 'other';
export type PatientStatus = 'active' | 'deceased' | 'archived';

export type Patient = {
  id: number;
  mrn: string;
  full_name: string;
  sex: PatientSex;
  birth_date?: string | null;
  phone?: string | null;
  email?: string | null;
  blood_group?: string | null;
  insurance_provider?: string | null;
  insurance_number?: string | null;
  status: PatientStatus;
};

export type AppointmentStatus =
  | 'scheduled'
  | 'confirmed'
  | 'checked_in'
  | 'completed'
  | 'cancelled'
  | 'no_show';

export type Appointment = {
  id: number;
  patient_id: number;
  practitioner_id: number;
  department_id?: number | null;
  starts_at: string;
  ends_at: string;
  reason?: string | null;
  status: AppointmentStatus;
  notes?: string | null;
  patient?: Patient | null;
  practitioner?: Practitioner | null;
};

export type AdmissionStatus = 'admitted' | 'transferred' | 'discharged';

export type Admission = {
  id: number;
  patient_id: number;
  practitioner_id: number;
  department_id: number;
  bed_id: number;
  reason?: string | null;
  admitted_at: string;
  expected_discharge_at?: string | null;
  discharged_at?: string | null;
  status: AdmissionStatus;
  discharge_notes?: string | null;
  patient?: Patient | null;
  practitioner?: Practitioner | null;
  department?: Department | null;
  bed?: Bed | null;
};

export type CareActCategory = 'consultation' | 'exam' | 'surgery' | 'hospitalization' | 'other';

export type CareAct = {
  id: number;
  code: string;
  label: string;
  category: CareActCategory;
  price: number | string;
  currency: string;
  active: boolean;
};

export type InvoiceStatus = 'draft' | 'issued' | 'paid' | 'partially_paid' | 'cancelled';

export type InvoiceItem = {
  id?: number;
  care_act_id?: number | null;
  label: string;
  unit_price: number | string;
  quantity: number;
  line_total?: number | string;
};

export type Invoice = {
  id: number;
  number: string;
  patient_id: number;
  status: InvoiceStatus;
  currency: string;
  subtotal: number | string;
  discount: number | string;
  total: number | string;
  amount_paid: number | string;
  issued_at?: string | null;
  items?: InvoiceItem[];
  patient?: Patient | null;
};

export type PaymentMethod = 'cash' | 'card' | 'transfer' | 'mobile' | 'insurance' | 'other';

export type BedOccupancy = {
  total_beds?: number;
  occupied_beds?: number;
  free_beds?: number;
  maintenance_beds?: number;
  occupancy_rate?: number;
  by_department?: { department_id: number; department_name?: string; total?: number; occupied?: number }[];
};

export type InvoiceStats = {
  draft_count?: number;
  issued_count?: number;
  paid_count?: number;
  month_revenue?: number | string;
  outstanding?: number | string;
  currency?: string;
};

export type HealthDashboard = {
  appointments_today?: number;
  patients_count?: number;
  admissions_active?: number;
  occupancy?: BedOccupancy | null;
  month_revenue?: number | string;
  currency?: string;
  recent_patients?: Patient[];
};

export type Paginated<T> = {
  data: T[];
  meta?: { current_page?: number; last_page?: number; per_page?: number; total?: number };
};

// ─────────────────────────────────────────────────────────────────────────
// Helpers bas niveau
// ─────────────────────────────────────────────────────────────────────────

async function request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const res = await apiFetch(`${BASE}${endpoint}`, options);
  if (!res.ok) {
    const payload = (await res.json().catch(() => ({}))) as { message?: string };
    throw new Error(payload.message || `HTTP ${res.status}`);
  }
  if (res.status === 204) {
    return undefined as T;
  }
  return (await res.json()) as T;
}

function query(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') {
      search.set(key, String(value));
    }
  }
  const qs = search.toString();
  return qs ? `?${qs}` : '';
}

function unwrap<T>(payload: { data?: T } | T): T {
  if (payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)) {
    return (payload as { data: T }).data;
  }
  return payload as T;
}

// ─────────────────────────────────────────────────────────────────────────
// Tableau de bord (HC-008)
// ─────────────────────────────────────────────────────────────────────────

export async function getHealthDashboard(): Promise<HealthDashboard> {
  return unwrap(await request<{ data?: HealthDashboard }>('/dashboard'));
}

// ─────────────────────────────────────────────────────────────────────────
// Référentiel structure (HC-002)
// ─────────────────────────────────────────────────────────────────────────

export async function listDepartments(): Promise<Department[]> {
  return unwrap(await request<{ data?: Department[] }>('/departments?per_page=200')) ?? [];
}

export async function listRooms(): Promise<Room[]> {
  return unwrap(await request<{ data?: Room[] }>('/rooms?per_page=200')) ?? [];
}

export async function listBeds(params: { status?: BedStatus } = {}): Promise<Bed[]> {
  return unwrap(await request<{ data?: Bed[] }>(`/beds${query({ per_page: 200, ...params })}`)) ?? [];
}

export async function listSpecialties(): Promise<Specialty[]> {
  return unwrap(await request<{ data?: Specialty[] }>('/specialties?per_page=200')) ?? [];
}

export async function listPractitioners(): Promise<Practitioner[]> {
  return unwrap(await request<{ data?: Practitioner[] }>('/practitioners?per_page=200')) ?? [];
}

// ─────────────────────────────────────────────────────────────────────────
// Registre patients (HC-003)
// ─────────────────────────────────────────────────────────────────────────

export type PatientInput = {
  full_name: string;
  sex: PatientSex;
  birth_date?: string;
  phone?: string;
  blood_group?: string;
  insurance_provider?: string;
  insurance_number?: string;
};

export async function listPatients(
  params: { search?: string; page?: number; per_page?: number } = {},
): Promise<Paginated<Patient>> {
  const payload = await request<Paginated<Patient> | Patient[]>(
    `/patients${query({ per_page: 25, ...params })}`,
  );
  return Array.isArray(payload) ? { data: payload } : payload;
}

export async function createPatient(input: PatientInput): Promise<Patient> {
  return unwrap(
    await request<{ data?: Patient }>('/patients', { method: 'POST', body: JSON.stringify(input) }),
  );
}

export async function updatePatient(id: number, input: Partial<PatientInput>): Promise<Patient> {
  return unwrap(
    await request<{ data?: Patient }>(`/patients/${id}`, {
      method: 'PUT',
      body: JSON.stringify(input),
    }),
  );
}

/** Archivage logique — jamais de suppression physique (données de santé). */
export async function archivePatient(id: number): Promise<void> {
  await request<unknown>(`/patients/${id}/archive`, { method: 'POST' });
}

// ─────────────────────────────────────────────────────────────────────────
// Rendez-vous & agenda (HC-004)
// ─────────────────────────────────────────────────────────────────────────

export type AppointmentInput = {
  patient_id: number;
  practitioner_id: number;
  department_id?: number;
  starts_at: string;
  ends_at: string;
  reason?: string;
};

export async function listAppointments(
  params: { date?: string; practitioner_id?: number; status?: AppointmentStatus; page?: number } = {},
): Promise<Paginated<Appointment>> {
  const payload = await request<Paginated<Appointment> | Appointment[]>(
    `/appointments${query({ per_page: 100, ...params })}`,
  );
  return Array.isArray(payload) ? { data: payload } : payload;
}

export async function createAppointment(input: AppointmentInput): Promise<Appointment> {
  return unwrap(
    await request<{ data?: Appointment }>('/appointments', {
      method: 'POST',
      body: JSON.stringify(input),
    }),
  );
}

/**
 * Transitions valides (spec §4) : scheduled→confirmed|cancelled ;
 * confirmed→checked_in|cancelled|no_show ; checked_in→completed.
 */
export async function transitionAppointment(
  id: number,
  status: AppointmentStatus,
): Promise<Appointment> {
  return unwrap(
    await request<{ data?: Appointment }>(`/appointments/${id}/status`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    }),
  );
}

export const APPOINTMENT_TRANSITIONS: Record<AppointmentStatus, AppointmentStatus[]> = {
  scheduled: ['confirmed', 'cancelled'],
  confirmed: ['checked_in', 'cancelled', 'no_show'],
  checked_in: ['completed'],
  completed: [],
  cancelled: [],
  no_show: [],
};

// ─────────────────────────────────────────────────────────────────────────
// Hospitalisations & lits (HC-006)
// ─────────────────────────────────────────────────────────────────────────

export type AdmissionInput = {
  patient_id: number;
  practitioner_id: number;
  department_id: number;
  bed_id: number;
  reason?: string;
  expected_discharge_at?: string;
};

export async function listAdmissions(
  params: { status?: AdmissionStatus; page?: number } = {},
): Promise<Paginated<Admission>> {
  const payload = await request<Paginated<Admission> | Admission[]>(
    `/admissions${query({ per_page: 100, ...params })}`,
  );
  return Array.isArray(payload) ? { data: payload } : payload;
}

export async function createAdmission(input: AdmissionInput): Promise<Admission> {
  return unwrap(
    await request<{ data?: Admission }>('/admissions', {
      method: 'POST',
      body: JSON.stringify(input),
    }),
  );
}

export async function transferAdmission(id: number, bedId: number): Promise<Admission> {
  return unwrap(
    await request<{ data?: Admission }>(`/admissions/${id}/transfer`, {
      method: 'POST',
      body: JSON.stringify({ bed_id: bedId }),
    }),
  );
}

export async function dischargeAdmission(id: number, notes?: string): Promise<Admission> {
  return unwrap(
    await request<{ data?: Admission }>(`/admissions/${id}/discharge`, {
      method: 'POST',
      body: JSON.stringify(notes ? { discharge_notes: notes } : {}),
    }),
  );
}

export async function getOccupancy(): Promise<BedOccupancy> {
  return unwrap(await request<{ data?: BedOccupancy }>('/admissions/occupancy')) ?? {};
}

// ─────────────────────────────────────────────────────────────────────────
// Actes & facturation des soins (HC-007)
// ─────────────────────────────────────────────────────────────────────────

export async function listCareActs(): Promise<CareAct[]> {
  return unwrap(await request<{ data?: CareAct[] }>('/care-acts?per_page=200')) ?? [];
}

export type InvoiceInput = {
  patient_id: number;
  currency?: string;
  discount?: number;
  items: { care_act_id?: number; label: string; unit_price: number; quantity: number }[];
};

export async function listInvoices(
  params: { status?: InvoiceStatus; page?: number } = {},
): Promise<Paginated<Invoice>> {
  const payload = await request<Paginated<Invoice> | Invoice[]>(
    `/invoices${query({ per_page: 50, ...params })}`,
  );
  return Array.isArray(payload) ? { data: payload } : payload;
}

export async function createInvoice(input: InvoiceInput): Promise<Invoice> {
  return unwrap(
    await request<{ data?: Invoice }>('/invoices', { method: 'POST', body: JSON.stringify(input) }),
  );
}

export async function issueInvoice(id: number): Promise<Invoice> {
  return unwrap(await request<{ data?: Invoice }>(`/invoices/${id}/issue`, { method: 'POST' }));
}

export async function payInvoice(
  id: number,
  payment: { amount: number; method: PaymentMethod; reference?: string },
): Promise<Invoice> {
  return unwrap(
    await request<{ data?: Invoice }>(`/invoices/${id}/payments`, {
      method: 'POST',
      body: JSON.stringify(payment),
    }),
  );
}

export async function cancelInvoice(id: number): Promise<Invoice> {
  return unwrap(await request<{ data?: Invoice }>(`/invoices/${id}/cancel`, { method: 'POST' }));
}

export async function getInvoiceStats(): Promise<InvoiceStats> {
  return unwrap(await request<{ data?: InvoiceStats }>('/invoices/stats')) ?? {};
}
