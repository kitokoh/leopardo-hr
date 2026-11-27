import type { ClientModuleKey } from './types';
import * as V from '../api/verticals';

/**
 * REGISTRE DES ECRANS DE MODULE — equivalent mobile de `ModulePageShell`.
 *
 * Le web rend chaque module metier avec un bandeau compact + des blocs de
 * donnees. Le mobile fait la meme chose, mais de facon DECLARATIVE : chaque
 * module decrit ses KPI et ses listes, et un seul composant generique
 * (`ModuleScreen`) les rend. Consequence : ajouter un module = ajouter une
 * entree ici, pas un ecran de plus a maintenir.
 *
 * Chaque `load` appelle les endpoints REELS du module (voir `api/verticals.ts`).
 */

export type KpiSpec = {
  label: string;
  /** Chemin dans l'objet charge (ex. `occupancy.rate`). */
  path: string;
  /** `money` formate avec la devise, `percent` multiplie par 100. */
  format?: 'number' | 'money' | 'percent' | 'text';
  accent?: string;
};

export type ListColumn = {
  /** Cle de l'element affiche. */
  key: string;
  label?: string;
  /** `title` = ligne principale, `meta` = sous-titre, `badge` = pastille. */
  role?: 'title' | 'meta' | 'badge' | 'amount';
};

export type ListSpec = {
  title: string;
  /** Fonction de chargement renvoyant un tableau. */
  load: () => Promise<unknown[]>;
  columns: ListColumn[];
  emptyMessage?: string;
};

export type ModuleScreenSpec = {
  /** Titre affiche (par defaut : le label du catalogue). */
  title?: string;
  subtitle?: string;
  /** Chargement des KPI (optionnel). */
  loadKpis?: () => Promise<Record<string, unknown>>;
  kpis?: KpiSpec[];
  lists: ListSpec[];
  /** Note affichee en bas d'ecran (perimetre, limite connue). */
  note?: string;
};

/** Lit une valeur imbriquee (`a.b.c`) dans un objet. */
export function readPath(source: unknown, path: string): unknown {
  return path.split('.').reduce<unknown>((acc, segment) => {
    if (acc && typeof acc === 'object' && segment in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[segment];
    }
    return undefined;
  }, source);
}

/**
 * Registre : une entree par module metier.
 * Les modules absents de ce registre affichent un ecran « module en
 * preparation » — jamais un ecran vide ni une erreur.
 */
export const MODULE_SCREENS: Partial<Record<ClientModuleKey, ModuleScreenSpec>> = {
  // ── CRM ──────────────────────────────────────────────────────────────────
  crm: {
    subtitle: 'Comptes, contacts, leads et opportunites',
    lists: [
      {
        title: 'Comptes',
        load: V.fetchCrmAccounts,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'industry', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun compte client enregistre.',
      },
      {
        title: 'Leads',
        load: V.fetchCrmLeads,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'company_name', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun lead dans le pipeline.',
      },
      {
        title: 'Opportunites',
        load: V.fetchCrmOpportunities,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'account_name', role: 'meta' },
          { key: 'stage', role: 'badge' },
          { key: 'amount', role: 'amount' },
        ],
        emptyMessage: 'Aucune opportunite ouverte.',
      },
    ],
  },

  // ── COMPTABILITE ─────────────────────────────────────────────────────────
  accounting: {
    subtitle: 'Documents, grand livre et balance',
    loadKpis: V.fetchAccountingBalance as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Total debit', path: 'total_debit', format: 'money', accent: '#2F6BFF' },
      { label: 'Total credit', path: 'total_credit', format: 'money', accent: '#00C2A8' },
      { label: 'Solde', path: 'balance', format: 'money', accent: '#22C55E' },
    ],
    lists: [
      {
        title: 'Documents',
        load: V.fetchAccountingDocuments,
        columns: [
          { key: 'number', role: 'title' },
          { key: 'contact_name', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'total', role: 'amount' },
        ],
        emptyMessage: 'Aucun document comptable.',
      },
    ],
  },

  // ── MARKETING ────────────────────────────────────────────────────────────
  marketing: {
    subtitle: 'Publications reseaux sociaux et performance',
    lists: [
      {
        title: 'Publications',
        load: V.fetchSocialPosts,
        columns: [
          { key: 'content', role: 'title' },
          { key: 'network', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucune publication planifiee.',
      },
    ],
  },

  // ── RESTAURANT ───────────────────────────────────────────────────────────
  restaurant: {
    subtitle: 'Salle, caisse et performance du service',
    loadKpis: V.fetchRestaurantManagerKpis as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: "Chiffre d'affaires", path: 'revenue_today', format: 'money', accent: '#F97316' },
      { label: 'Commandes', path: 'orders_today', format: 'number', accent: '#2F6BFF' },
      { label: 'Ticket moyen', path: 'average_ticket', format: 'money', accent: '#00C2A8' },
    ],
    lists: [
      {
        title: 'Commandes en cours',
        load: V.fetchRestaurantServerOrders,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'table_name', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'total', role: 'amount' },
        ],
        emptyMessage: 'Aucune commande en cours.',
      },
      {
        title: 'Etablissements',
        load: V.fetchRestaurantBranches,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'city', role: 'meta' },
        ],
        emptyMessage: 'Aucun etablissement configure.',
      },
    ],
  },

  restaurant_kitchen: {
    subtitle: 'File de preparation en cuisine',
    lists: [
      {
        title: 'A preparer',
        load: V.fetchRestaurantServerOrders,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'table_name', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun ticket en cuisine.',
      },
    ],
    note: 'La file de cuisine lit les commandes du service (endpoint mobile serveur).',
  },

  // ── AGENCE DE VOYAGE ─────────────────────────────────────────────────────
  travel: {
    subtitle: 'Trajets, reservations et ventes',
    lists: [
      {
        title: 'Trajets',
        load: V.fetchTravelTrips,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'destination', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'price', role: 'amount' },
        ],
        emptyMessage: 'Aucun trajet programme.',
      },
      {
        title: 'Reservations',
        load: V.fetchTravelBookings,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'customer_name', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'total', role: 'amount' },
        ],
        emptyMessage: 'Aucune reservation.',
      },
    ],
  },

  travel_portal: {
    subtitle: 'Recherche de trajets et reservation',
    lists: [
      {
        title: 'Trajets disponibles',
        load: V.fetchTravelTrips,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'destination', role: 'meta' },
          { key: 'seats_available', role: 'badge' },
          { key: 'price', role: 'amount' },
        ],
        emptyMessage: 'Aucun trajet disponible a la vente.',
      },
    ],
    note: 'Le portail voyageur expose les trajets publies par l\u2019agence.',
  },

  // ── COMMERCE ─────────────────────────────────────────────────────────────
  commerce: {
    subtitle: 'Produits, stock et ventes',
    loadKpis: V.fetchRetailSalesReport as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: "Chiffre d'affaires", path: 'revenue', format: 'money', accent: '#8B5CF6' },
      { label: 'Commandes', path: 'orders', format: 'number', accent: '#2F6BFF' },
      { label: 'Ticket moyen', path: 'average_ticket', format: 'money', accent: '#00C2A8' },
    ],
    lists: [
      {
        title: 'Produits',
        load: V.fetchRetailProducts,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'sku', role: 'meta' },
          { key: 'stock_quantity', role: 'badge' },
          { key: 'price', role: 'amount' },
        ],
        emptyMessage: 'Aucun produit au catalogue.',
      },
      {
        title: 'Alertes de stock',
        load: V.fetchRetailStockAlerts,
        columns: [
          { key: 'product_name', role: 'title' },
          { key: 'location_name', role: 'meta' },
          { key: 'quantity', role: 'badge' },
        ],
        emptyMessage: 'Aucune alerte de stock.',
      },
    ],
  },

  // ── SANTE ────────────────────────────────────────────────────────────────
  health: {
    subtitle: 'Patients, rendez-vous et occupation des lits',
    loadKpis: V.fetchHealthDashboard as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Rendez-vous du jour', path: 'appointments_today', format: 'number', accent: '#EF4444' },
      { label: 'Patients', path: 'patients_count', format: 'number', accent: '#2F6BFF' },
      { label: 'Hospitalisations', path: 'admissions_active', format: 'number', accent: '#F59E0B' },
      { label: 'Occupation', path: 'occupancy.occupancy_rate', format: 'percent', accent: '#00C2A8' },
    ],
    lists: [
      {
        title: 'Patients recents',
        load: V.fetchHealthPatients,
        columns: [
          { key: 'full_name', role: 'title' },
          { key: 'mrn', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun patient enregistre.',
      },
      {
        title: 'Rendez-vous',
        load: V.fetchHealthAppointments,
        columns: [
          { key: 'patient_name', role: 'title' },
          { key: 'practitioner_name', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun rendez-vous planifie.',
      },
    ],
  },

  health_patients: {
    subtitle: 'Registre des patients',
    lists: [
      {
        title: 'Patients',
        load: V.fetchHealthPatients,
        columns: [
          { key: 'full_name', role: 'title' },
          { key: 'mrn', role: 'meta' },
          { key: 'sex', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun patient enregistre.',
      },
    ],
  },

  health_appointments: {
    subtitle: 'Agenda des consultations',
    lists: [
      {
        title: 'Rendez-vous',
        load: V.fetchHealthAppointments,
        columns: [
          { key: 'patient_name', role: 'title' },
          { key: 'practitioner_name', role: 'meta' },
          { key: 'scheduled_at', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun rendez-vous planifie.',
      },
    ],
  },

  health_admissions: {
    subtitle: 'Hospitalisations et occupation des lits',
    loadKpis: V.fetchHealthDashboard as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Lits occupes', path: 'occupancy.occupied_beds', format: 'number', accent: '#EF4444' },
      { label: 'Lits libres', path: 'occupancy.free_beds', format: 'number', accent: '#22C55E' },
      { label: 'Taux occupation', path: 'occupancy.occupancy_rate', format: 'percent', accent: '#F59E0B' },
    ],
    lists: [],
    note: 'Le detail des hospitalisations se consulte depuis le web (vue admissions).',
  },

  health_billing: {
    subtitle: 'Actes et facturation des soins',
    loadKpis: V.fetchHealthDashboard as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'CA du mois', path: 'month_revenue', format: 'money', accent: '#22C55E' },
      { label: 'Patients', path: 'patients_count', format: 'number', accent: '#2F6BFF' },
    ],
    lists: [],
    note: 'La facturation detaillee des actes se consulte depuis le web.',
  },

  health_referential: {
    subtitle: 'Structure : services, chambres, lits, specialites',
    lists: [],
    note: 'Le referentiel structure (services, chambres, lits, praticiens) se gere depuis le web.',
  },

  // ── PHARMACIE ────────────────────────────────────────────────────────────
  pharmacy: {
    subtitle: 'Officine : produits, stock et alertes',
    loadKpis: V.fetchPharmacyDashboard as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Produits', path: 'products_count', format: 'number', accent: '#2F6BFF' },
      { label: 'Stock bas', path: 'low_stock_count', format: 'number', accent: '#F59E0B' },
      { label: 'Peremptions', path: 'expiring_count', format: 'number', accent: '#EF4444' },
    ],
    lists: [
      {
        title: 'Produits',
        load: V.fetchPharmacyProducts,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'dosage', role: 'meta' },
          { key: 'stock_quantity', role: 'badge' },
          { key: 'price', role: 'amount' },
        ],
        emptyMessage: 'Aucun produit en officine.',
      },
    ],
  },

  // ── COMMERCE INTERNATIONAL ───────────────────────────────────────────────
  trade: {
    subtitle: 'Sourcing, offres et commandes',
    lists: [
      {
        title: 'Demandes de prix (RFQ)',
        load: V.fetchTradeRfqs,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'counterparty_name', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucune demande de prix.',
      },
      {
        title: 'Offres',
        load: V.fetchTradeQuotes,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'valid_until', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'total', role: 'amount' },
        ],
        emptyMessage: 'Aucune offre en cours.',
      },
      {
        title: 'Commandes',
        load: V.fetchTradeOrders,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'counterparty_name', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'total', role: 'amount' },
        ],
        emptyMessage: 'Aucune commande internationale.',
      },
    ],
  },

  // ── HOSPITALITY ──────────────────────────────────────────────────────────
  hospitality: {
    subtitle: 'Occupation, arrivees et departs',
    loadKpis: V.fetchHospitalityKpis as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Occupation', path: 'occupancy.rate', format: 'percent', accent: '#14B8A6' },
      { label: 'Arrivees', path: 'arrivals_today', format: 'number', accent: '#22C55E' },
      { label: 'Departs', path: 'departures_today', format: 'number', accent: '#F59E0B' },
      { label: 'En attente', path: 'pending_online', format: 'number', accent: '#2F6BFF' },
    ],
    lists: [
      {
        title: 'Reservations',
        load: V.fetchHospitalityReservations,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'guest_name', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'total', role: 'amount' },
        ],
        emptyMessage: 'Aucune reservation.',
      },
      {
        title: 'Etablissements',
        load: V.fetchHospitalityProperties,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'city', role: 'meta' },
          { key: 'type', role: 'badge' },
        ],
        emptyMessage: 'Aucun etablissement.',
      },
    ],
  },

  // ── VTC ──────────────────────────────────────────────────────────────────
  vtc: {
    subtitle: 'Courses actives et chauffeurs',
    lists: [
      {
        title: 'Courses',
        load: V.fetchVtcDispatchRides,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'pickup_address', role: 'meta' },
          { key: 'status', role: 'badge' },
          { key: 'fare', role: 'amount' },
        ],
        emptyMessage: 'Aucune course en cours.',
      },
      {
        title: 'Chauffeurs',
        load: V.fetchVtcDispatchDrivers,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'vehicle_plate', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun chauffeur connecte.',
      },
    ],
  },

  // ── STATION-SERVICE ──────────────────────────────────────────────────────
  fuel: {
    subtitle: 'Ventes, volumes et equipes',
    lists: [
      {
        title: 'Ventes',
        load: V.fetchFuelSales,
        columns: [
          { key: 'reference', role: 'title' },
          { key: 'pump_name', role: 'meta' },
          { key: 'litres', role: 'badge' },
          { key: 'amount', role: 'amount' },
        ],
        emptyMessage: 'Aucune vente enregistree.',
      },
      {
        title: 'Equipes (shifts)',
        load: V.fetchFuelShifts,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'starts_at', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucune equipe planifiee.',
      },
    ],
  },

  // ── SCOLARITE ────────────────────────────────────────────────────────────
  edu_manager: {
    subtitle: 'Eleves, classes et scolarite',
    lists: [
      {
        title: 'Eleves',
        load: V.fetchEduStudents,
        columns: [
          { key: 'full_name', role: 'title' },
          { key: 'class_name', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun eleve inscrit.',
      },
      {
        title: 'Classes',
        load: V.fetchEduClasses,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'campus_name', role: 'meta' },
          { key: 'students_count', role: 'badge' },
        ],
        emptyMessage: 'Aucune classe configuree.',
      },
    ],
  },

  // ── SITE VITRINE ─────────────────────────────────────────────────────────
  showcase: {
    subtitle: 'Site public de l\u2019entreprise',
    lists: [],
    note: 'L\u2019edition du site vitrine (sections, medias, publication) se fait depuis le web.',
  },

  // ── BIBLIOTHEQUE ─────────────────────────────────────────────────────────
  library: {
    subtitle: 'Ressources de l\u2019entreprise',
    loadKpis: V.fetchLibraryStats as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Ressources', path: 'resources_count', format: 'number', accent: '#2F6BFF' },
      { label: 'Publiees', path: 'published_count', format: 'number', accent: '#22C55E' },
      { label: 'Telechargements', path: 'downloads', format: 'number', accent: '#00C2A8' },
    ],
    lists: [
      {
        title: 'Ressources',
        load: V.fetchLibraryResources,
        columns: [
          { key: 'title', role: 'title' },
          { key: 'author', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucune ressource publiee.',
      },
    ],
  },

  // ── CAGNOTTES ────────────────────────────────────────────────────────────
  fundraising: {
    subtitle: 'Collectes solidaires',
    lists: [
      {
        title: 'Cagnottes',
        load: V.fetchFundraisers,
        columns: [
          { key: 'title', role: 'title' },
          { key: 'status', role: 'badge' },
          { key: 'collected_amount', role: 'amount' },
        ],
        emptyMessage: 'Aucune cagnotte creee.',
      },
    ],
  },

  // ── COMMUNICATION ────────────────────────────────────────────────────────
  communication: {
    subtitle: 'Boite mail connectee',
    lists: [
      {
        title: 'Conversations',
        load: V.fetchCommunicationThreads,
        columns: [
          { key: 'subject', role: 'title' },
          { key: 'from_name', role: 'meta' },
          { key: 'last_message_at', role: 'meta' },
        ],
        emptyMessage: 'Aucune conversation.',
      },
    ],
  },

  // ── CAMERAS ──────────────────────────────────────────────────────────────
  cameras: {
    subtitle: 'Surveillance du site',
    lists: [
      {
        title: 'Cameras',
        load: V.fetchCameras,
        columns: [
          { key: 'name', role: 'title' },
          { key: 'location', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucune camera configuree.',
      },
      {
        title: 'Alertes',
        load: V.fetchCameraAlerts,
        columns: [
          { key: 'camera_name', role: 'title' },
          { key: 'type', role: 'meta' },
          { key: 'severity', role: 'badge' },
        ],
        emptyMessage: 'Aucune alerte.',
      },
    ],
    note: 'Le flux video direct est diffuse par l\u2019application Leopardo Cameras dediee.',
  },

  // ── FLOTTE ───────────────────────────────────────────────────────────────
  fleet: {
    subtitle: 'Vehicules, positions et itineraires',
    loadKpis: V.fetchFleetOverview as unknown as () => Promise<Record<string, unknown>>,
    kpis: [
      { label: 'Vehicules', path: 'vehicles_count', format: 'number', accent: '#2F6BFF' },
      { label: 'En service', path: 'active_count', format: 'number', accent: '#22C55E' },
      { label: 'Alertes', path: 'alerts_count', format: 'number', accent: '#EF4444' },
    ],
    lists: [
      {
        title: 'Mes vehicules',
        load: V.fetchMyVehicles,
        columns: [
          { key: 'plate', role: 'title' },
          { key: 'model', role: 'meta' },
          { key: 'status', role: 'badge' },
        ],
        emptyMessage: 'Aucun vehicule assigne.',
      },
    ],
  },
};

/** Le module a-t-il un ecran dedie ? */
export function hasModuleScreen(key: ClientModuleKey): boolean {
  return Boolean(MODULE_SCREENS[key]);
}
