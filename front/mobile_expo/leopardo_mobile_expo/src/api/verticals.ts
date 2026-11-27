import { api, unwrap, unwrapList } from './client';

/**
 * API des VERTICALES metier.
 *
 * Chaque fonction appelle le prefixe reel du module cote API Laravel
 * (`routes/modules/*.php`). Les prefixes sont ceux du web :
 *
 *   crm            → /crm/*
 *   accounting     → /accounting/*
 *   marketing      → /marketing/*
 *   restaurant     → /restaurant/*
 *   travel         → /travel/*
 *   retail         → /retail/*          (module « Commerce »)
 *   health         → /health-manager/*  (module « Sante »)
 *   pharmacy       → /pharmacy/*
 *   trade          → /trade/*
 *   hospitality    → /hospitality/*
 *   vtc            → /vtc/*
 *   fuel           → /fuel-station/*
 *   edu_manager    → /edu-manager/*
 *   showcase       → /showcase/*
 *   library        → /library/*
 *   fundraising    → /fundraising/*
 *   communication  → /communication/*
 *   cameras        → /cameras/*
 *   fleet          → /fleet/*
 *
 * Toutes ces routes sont tenant-scoped et derriere le feature flag du module
 * (fail-closed) : un module non active repond 403, ce qui est traite comme
 * « module indisponible » par l'ecran generique.
 */

// ─────────────────────────────────────────────────────────────────────────────
// CRM
// ─────────────────────────────────────────────────────────────────────────────

export type CrmAccount = {
  id: number;
  name?: string;
  industry?: string | null;
  website?: string | null;
  phone?: string | null;
  status?: string | null;
  owner_name?: string | null;
  created_at?: string | null;
};

export type CrmLead = {
  id: number;
  name?: string;
  company_name?: string | null;
  email?: string | null;
  phone?: string | null;
  status?: string | null;
  source?: string | null;
  score?: number | null;
  created_at?: string | null;
};

export type CrmOpportunity = {
  id: number;
  name?: string;
  account_name?: string | null;
  stage?: string | null;
  amount?: number | null;
  currency?: string | null;
  expected_close_date?: string | null;
};

export async function fetchCrmAccounts(): Promise<CrmAccount[]> {
  const { data } = await api.get('/crm/accounts', { params: { per_page: 50 } });
  return unwrapList<CrmAccount>(data);
}

export async function fetchCrmLeads(): Promise<CrmLead[]> {
  const { data } = await api.get('/crm/leads', { params: { per_page: 50 } });
  return unwrapList<CrmLead>(data);
}

export async function fetchCrmOpportunities(): Promise<CrmOpportunity[]> {
  const { data } = await api.get('/crm/opportunities', { params: { per_page: 50 } });
  return unwrapList<CrmOpportunity>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMPTABILITE
// ─────────────────────────────────────────────────────────────────────────────

export type AccountingDocument = {
  id: number;
  number?: string | null;
  type?: string | null;
  status?: string | null;
  total?: number | null;
  currency?: string | null;
  issued_at?: string | null;
  due_at?: string | null;
  contact_name?: string | null;
};

export type AccountingBalance = {
  total_debit?: number;
  total_credit?: number;
  balance?: number;
  currency?: string;
};

export async function fetchAccountingDocuments(): Promise<AccountingDocument[]> {
  const { data } = await api.get('/accounting/documents', { params: { per_page: 50 } });
  return unwrapList<AccountingDocument>(data);
}

export async function fetchAccountingBalance(): Promise<AccountingBalance> {
  const { data } = await api.get('/accounting/balance');
  return unwrap<AccountingBalance>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// MARKETING
// ─────────────────────────────────────────────────────────────────────────────

export type SocialPost = {
  id: number;
  content?: string | null;
  status?: string | null;
  network?: string | null;
  scheduled_at?: string | null;
  published_at?: string | null;
  created_at?: string | null;
};

export async function fetchSocialPosts(): Promise<SocialPost[]> {
  const { data } = await api.get('/marketing/social-posts', { params: { per_page: 50 } });
  return unwrapList<SocialPost>(data);
}

export async function fetchMarketingWeeklyReport(): Promise<Record<string, unknown>> {
  const { data } = await api.get('/marketing/reports/weekly');
  return unwrap<Record<string, unknown>>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// RESTAURANT
// ─────────────────────────────────────────────────────────────────────────────

export type RestaurantBranch = {
  id: number;
  name?: string;
  city?: string | null;
  address?: string | null;
  is_active?: boolean;
};

export type RestaurantOrder = {
  id: number;
  reference?: string | null;
  table_name?: string | null;
  status?: string | null;
  total?: number | null;
  currency?: string | null;
  created_at?: string | null;
};

export type RestaurantKpis = {
  revenue_today?: number;
  orders_today?: number;
  average_ticket?: number;
  currency?: string;
  [key: string]: unknown;
};

export async function fetchRestaurantBranches(): Promise<RestaurantBranch[]> {
  const { data } = await api.get('/restaurant/branches', { params: { per_page: 50 } });
  return unwrapList<RestaurantBranch>(data);
}

export async function fetchRestaurantServerOrders(): Promise<RestaurantOrder[]> {
  const { data } = await api.get('/restaurant/mobile/server/orders');
  return unwrapList<RestaurantOrder>(data);
}

export async function fetchRestaurantManagerKpis(): Promise<RestaurantKpis> {
  const { data } = await api.get('/restaurant/mobile/manager/kpis');
  return unwrap<RestaurantKpis>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// AGENCE DE VOYAGE
// ─────────────────────────────────────────────────────────────────────────────

export type TravelTrip = {
  id: number;
  reference?: string | null;
  origin?: string | null;
  destination?: string | null;
  departure_at?: string | null;
  arrival_at?: string | null;
  status?: string | null;
  seats_available?: number | null;
  price?: number | null;
  currency?: string | null;
};

export type TravelBooking = {
  id: number;
  reference?: string | null;
  customer_name?: string | null;
  trip_reference?: string | null;
  status?: string | null;
  total?: number | null;
  currency?: string | null;
  created_at?: string | null;
};

export async function fetchTravelTrips(): Promise<TravelTrip[]> {
  const { data } = await api.get('/travel/trips', { params: { per_page: 50 } });
  return unwrapList<TravelTrip>(data);
}

export async function fetchTravelBookings(): Promise<TravelBooking[]> {
  const { data } = await api.get('/travel/bookings', { params: { per_page: 50 } });
  return unwrapList<TravelBooking>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMMERCE (retail)
// ─────────────────────────────────────────────────────────────────────────────

export type RetailProduct = {
  id: number;
  name?: string;
  sku?: string | null;
  price?: number | null;
  currency?: string | null;
  stock_quantity?: number | null;
  is_published?: boolean;
  category_name?: string | null;
};

export type RetailStockAlert = {
  id: number;
  product_name?: string | null;
  quantity?: number | null;
  threshold?: number | null;
  location_name?: string | null;
};

export type RetailSalesReport = {
  revenue?: number;
  orders?: number;
  average_ticket?: number;
  currency?: string;
  [key: string]: unknown;
};

export async function fetchRetailProducts(): Promise<RetailProduct[]> {
  const { data } = await api.get('/retail/products', { params: { per_page: 50 } });
  return unwrapList<RetailProduct>(data);
}

export async function fetchRetailStockAlerts(): Promise<RetailStockAlert[]> {
  const { data } = await api.get('/retail/stock/alerts');
  return unwrapList<RetailStockAlert>(data);
}

export async function fetchRetailSalesReport(): Promise<RetailSalesReport> {
  const { data } = await api.get('/retail/reports/sales');
  return unwrap<RetailSalesReport>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// SANTE (health-manager)
// ─────────────────────────────────────────────────────────────────────────────

export type HealthDashboard = {
  appointments_today?: number;
  patients_count?: number;
  admissions_active?: number;
  month_revenue?: number;
  currency?: string;
  occupancy?: {
    total_beds?: number;
    occupied_beds?: number;
    free_beds?: number;
    occupancy_rate?: number;
  };
  recent_patients?: HealthPatient[];
};

export type HealthPatient = {
  id: number;
  mrn?: string | null;
  full_name?: string | null;
  sex?: string | null;
  status?: string | null;
  created_at?: string | null;
};

export type HealthAppointment = {
  id: number;
  patient_name?: string | null;
  practitioner_name?: string | null;
  scheduled_at?: string | null;
  status?: string | null;
  reason?: string | null;
};

export async function fetchHealthDashboard(): Promise<HealthDashboard> {
  const { data } = await api.get('/health-manager/dashboard');
  return unwrap<HealthDashboard>(data);
}

export async function fetchHealthPatients(): Promise<HealthPatient[]> {
  const { data } = await api.get('/health-manager/patients', { params: { per_page: 50 } });
  return unwrapList<HealthPatient>(data);
}

export async function fetchHealthAppointments(): Promise<HealthAppointment[]> {
  const { data } = await api.get('/health-manager/appointments', { params: { per_page: 50 } });
  return unwrapList<HealthAppointment>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// PHARMACIE
// ─────────────────────────────────────────────────────────────────────────────

export type PharmacyDashboard = {
  products_count?: number;
  low_stock_count?: number;
  expiring_count?: number;
  sales_today?: number;
  currency?: string;
  [key: string]: unknown;
};

export type PharmacyProduct = {
  id: number;
  name?: string;
  form?: string | null;
  dosage?: string | null;
  price?: number | null;
  stock_quantity?: number | null;
  is_controlled?: boolean;
};

export async function fetchPharmacyDashboard(): Promise<PharmacyDashboard> {
  const { data } = await api.get('/pharmacy/dashboard');
  return unwrap<PharmacyDashboard>(data);
}

export async function fetchPharmacyProducts(): Promise<PharmacyProduct[]> {
  const { data } = await api.get('/pharmacy/products', { params: { per_page: 50 } });
  return unwrapList<PharmacyProduct>(data);
}

export async function fetchPharmacyAlerts(): Promise<Record<string, unknown>[]> {
  const { data } = await api.get('/pharmacy/alerts');
  return unwrapList<Record<string, unknown>>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMMERCE INTERNATIONAL (trade)
// ─────────────────────────────────────────────────────────────────────────────

export type TradeRfq = {
  id: number;
  reference?: string | null;
  title?: string | null;
  status?: string | null;
  counterparty_name?: string | null;
  created_at?: string | null;
};

export type TradeOrder = {
  id: number;
  reference?: string | null;
  status?: string | null;
  counterparty_name?: string | null;
  total?: number | null;
  currency?: string | null;
  incoterm?: string | null;
};

export type TradeQuote = {
  id: number;
  reference?: string | null;
  status?: string | null;
  total?: number | null;
  currency?: string | null;
  valid_until?: string | null;
};

export async function fetchTradeRfqs(): Promise<TradeRfq[]> {
  const { data } = await api.get('/trade/rfqs', { params: { per_page: 50 } });
  return unwrapList<TradeRfq>(data);
}

export async function fetchTradeOrders(): Promise<TradeOrder[]> {
  const { data } = await api.get('/trade/orders', { params: { per_page: 50 } });
  return unwrapList<TradeOrder>(data);
}

export async function fetchTradeQuotes(): Promise<TradeQuote[]> {
  const { data } = await api.get('/trade/quotes', { params: { per_page: 50 } });
  return unwrapList<TradeQuote>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// HOSPITALITY
// ─────────────────────────────────────────────────────────────────────────────

export type HospitalityKpis = {
  date?: string;
  occupancy?: {
    operational_units?: number;
    occupied_units?: number;
    rate?: number;
  };
  arrivals_today?: number;
  departures_today?: number;
  pending_online?: number;
};

export type HospitalityProperty = {
  id: number;
  name?: string;
  city?: string | null;
  type?: string | null;
  is_published?: boolean;
  units_count?: number | null;
};

export type HospitalityReservation = {
  id: number;
  reference?: string | null;
  guest_name?: string | null;
  property_name?: string | null;
  status?: string | null;
  check_in?: string | null;
  check_out?: string | null;
  total?: number | null;
  currency?: string | null;
};

export async function fetchHospitalityKpis(): Promise<HospitalityKpis> {
  const { data } = await api.get('/hospitality/dashboard/kpis');
  return unwrap<HospitalityKpis>(data);
}

export async function fetchHospitalityProperties(): Promise<HospitalityProperty[]> {
  const { data } = await api.get('/hospitality/properties', { params: { per_page: 50 } });
  return unwrapList<HospitalityProperty>(data);
}

export async function fetchHospitalityReservations(): Promise<HospitalityReservation[]> {
  const { data } = await api.get('/hospitality/reservations', { params: { per_page: 50 } });
  return unwrapList<HospitalityReservation>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// VTC & TAXI
// ─────────────────────────────────────────────────────────────────────────────

export type VtcRide = {
  id: number;
  reference?: string | null;
  status?: string | null;
  pickup_address?: string | null;
  dropoff_address?: string | null;
  fare?: number | null;
  currency?: string | null;
  requested_at?: string | null;
};

export type VtcDriver = {
  id: number;
  name?: string | null;
  status?: string | null;
  vehicle_plate?: string | null;
  rating?: number | null;
};

export async function fetchVtcDispatchRides(): Promise<VtcRide[]> {
  const { data } = await api.get('/vtc/dispatch/rides');
  return unwrapList<VtcRide>(data);
}

export async function fetchVtcDispatchDrivers(): Promise<VtcDriver[]> {
  const { data } = await api.get('/vtc/dispatch/drivers');
  return unwrapList<VtcDriver>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// STATION-SERVICE
// ─────────────────────────────────────────────────────────────────────────────

export type FuelSale = {
  id: number;
  reference?: string | null;
  pump_name?: string | null;
  fuel_type?: string | null;
  litres?: number | null;
  amount?: number | null;
  currency?: string | null;
  sold_at?: string | null;
};

export type FuelShift = {
  id: number;
  name?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  status?: string | null;
};

export async function fetchFuelSales(): Promise<FuelSale[]> {
  const { data } = await api.get('/fuel-station/sales', { params: { per_page: 50 } });
  return unwrapList<FuelSale>(data);
}

export async function fetchFuelShifts(): Promise<FuelShift[]> {
  const { data } = await api.get('/fuel-station/shifts', { params: { per_page: 50 } });
  return unwrapList<FuelShift>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// SCOLARITE (edu-manager)
// ─────────────────────────────────────────────────────────────────────────────

export type EduStudent = {
  id: number;
  full_name?: string | null;
  matricule?: string | null;
  class_name?: string | null;
  status?: string | null;
  guardian_name?: string | null;
};

export type EduClass = {
  id: number;
  name?: string | null;
  level?: string | null;
  students_count?: number | null;
  campus_name?: string | null;
};

export async function fetchEduStudents(): Promise<EduStudent[]> {
  const { data } = await api.get('/edu-manager/students', { params: { per_page: 50 } });
  return unwrapList<EduStudent>(data);
}

export async function fetchEduClasses(): Promise<EduClass[]> {
  const { data } = await api.get('/edu-manager/classes', { params: { per_page: 50 } });
  return unwrapList<EduClass>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// SITE VITRINE
// ─────────────────────────────────────────────────────────────────────────────

export type ShowcaseSite = {
  id?: number;
  slug?: string | null;
  title?: string | null;
  is_published?: boolean;
  published_at?: string | null;
  sections_count?: number | null;
};

export async function fetchShowcaseSite(): Promise<ShowcaseSite> {
  const { data } = await api.get('/showcase');
  return unwrap<ShowcaseSite>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// BIBLIOTHEQUE
// ─────────────────────────────────────────────────────────────────────────────

export type LibraryResource = {
  id: number;
  title?: string | null;
  type?: string | null;
  status?: string | null;
  author?: string | null;
  published_at?: string | null;
};

export type LibraryStats = {
  resources_count?: number;
  published_count?: number;
  downloads?: number;
  [key: string]: unknown;
};

export async function fetchLibraryResources(): Promise<LibraryResource[]> {
  const { data } = await api.get('/library/resources', { params: { per_page: 50 } });
  return unwrapList<LibraryResource>(data);
}

export async function fetchLibraryStats(): Promise<LibraryStats> {
  const { data } = await api.get('/library/stats');
  return unwrap<LibraryStats>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// CAGNOTTES
// ─────────────────────────────────────────────────────────────────────────────

export type Fundraiser = {
  id: number;
  title?: string | null;
  slug?: string | null;
  status?: string | null;
  goal_amount?: number | null;
  collected_amount?: number | null;
  currency?: string | null;
  ends_at?: string | null;
};

export async function fetchFundraisers(): Promise<Fundraiser[]> {
  const { data } = await api.get('/fundraising/fundraisers', { params: { per_page: 50 } });
  return unwrapList<Fundraiser>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// COMMUNICATION
// ─────────────────────────────────────────────────────────────────────────────

export type CommunicationThread = {
  id: number;
  subject?: string | null;
  from_name?: string | null;
  from_email?: string | null;
  snippet?: string | null;
  unread?: boolean;
  last_message_at?: string | null;
};

export async function fetchCommunicationThreads(): Promise<CommunicationThread[]> {
  const { data } = await api.get('/communication/threads', { params: { per_page: 50 } });
  return unwrapList<CommunicationThread>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// CAMERAS
// ─────────────────────────────────────────────────────────────────────────────

export type Camera = {
  id: number;
  name?: string | null;
  location?: string | null;
  status?: string | null;
  is_online?: boolean;
};

export type CameraAlert = {
  id: number;
  camera_name?: string | null;
  type?: string | null;
  severity?: string | null;
  created_at?: string | null;
  acknowledged?: boolean;
};

export async function fetchCameras(): Promise<Camera[]> {
  const { data } = await api.get('/cameras');
  return unwrapList<Camera>(data);
}

export async function fetchCameraAlerts(): Promise<CameraAlert[]> {
  const { data } = await api.get('/cameras/alerts');
  return unwrapList<CameraAlert>(data);
}

// ─────────────────────────────────────────────────────────────────────────────
// FLOTTE
// ─────────────────────────────────────────────────────────────────────────────

export type FleetOverview = {
  vehicles_count?: number;
  active_count?: number;
  alerts_count?: number;
  [key: string]: unknown;
};

export type FleetVehicle = {
  id: number;
  plate?: string | null;
  model?: string | null;
  status?: string | null;
  driver_name?: string | null;
  mileage?: number | null;
};

export async function fetchFleetOverview(): Promise<FleetOverview> {
  const { data } = await api.get('/fleet/overview');
  return unwrap<FleetOverview>(data);
}

export async function fetchMyVehicles(): Promise<FleetVehicle[]> {
  const { data } = await api.get('/me/vehicles');
  return unwrapList<FleetVehicle>(data);
}
