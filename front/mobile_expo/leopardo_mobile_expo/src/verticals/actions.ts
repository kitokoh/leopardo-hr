import type { ClientModuleKey } from './types';
import * as W from '../api/verticalsWrite';

/**
 * REGISTRE DES ACTIONS D'ECRITURE DES MODULES.
 *
 * Le web expose, pour chaque verticale, des formulaires de creation et des
 * boutons de transition d'etat. Le mobile reprend exactement les memes
 * operations, mais de facon DECLARATIVE : une action decrit ses champs, la
 * construction du payload et l'appel API. Un seul ecran generique
 * (`ModuleActionScreen`) les rend toutes.
 *
 * Deux familles :
 *  - `actions`    : creation (formulaire vierge) — ex. « Nouvelle commande » ;
 *  - `rowActions` : operation sur un element existant — ex. « Encaisser »
 *                   une commande, « Check-in » une reservation.
 *
 * Les payloads respectent les FormRequests du depot `leopardo-hr` : noms de
 * champs, types et contraintes (montants en mineurs, dates `YYYY-MM-DD`,
 * horaires `HH:mm`, enums fermes).
 */

export type FieldType =
  | 'text'
  | 'number'
  | 'money'
  | 'date'
  | 'time'
  | 'select'
  | 'multiline'
  | 'switch';

export type ActionField = {
  key: string;
  label: string;
  type: FieldType;
  required?: boolean;
  placeholder?: string;
  help?: string;
  /** Options statiques (type `select`). */
  options?: { value: string; label: string }[];
  /** Options chargees depuis l'API (type `select`). */
  loadOptions?: () => Promise<{ value: string; label: string }[]>;
  defaultValue?: string;
};

export type ActionValues = Record<string, string>;

export type ModuleAction = {
  key: string;
  label: string;
  description?: string;
  fields: ActionField[];
  /** Construit le payload API depuis les valeurs saisies. */
  build: (values: ActionValues) => Record<string, unknown>;
  submit: (payload: Record<string, unknown>) => Promise<unknown>;
  successMessage: string;
  /** Action irreversible : confirmation demandee avant envoi. */
  destructive?: boolean;
};

export type RowAction = {
  key: string;
  label: string;
  description?: string;
  /** Elements sur lesquels l'action peut porter. */
  loadTargets: () => Promise<{ id: number; label: string; meta?: string }[]>;
  /** Champs supplementaires (montant, motif…). */
  fields?: ActionField[];
  build: (targetId: number, values: ActionValues) => Record<string, unknown>;
  submit: (targetId: number, payload: Record<string, unknown>) => Promise<unknown>;
  successMessage: string;
  destructive?: boolean;
};

// ─────────────────────────────────────────────────────────────────────────────
// Helpers de construction de payload
// ─────────────────────────────────────────────────────────────────────────────

/** Valeur texte nettoyee (chaine vide -> undefined). */
function text(values: ActionValues, key: string): string | undefined {
  const raw = values[key];
  if (raw === undefined) return undefined;
  const trimmed = raw.trim();
  return trimmed === '' ? undefined : trimmed;
}

/** Valeur numerique (chaine vide -> undefined). */
function num(values: ActionValues, key: string): number | undefined {
  const raw = text(values, key);
  if (raw === undefined) return undefined;
  const parsed = Number(raw.replace(',', '.'));
  return Number.isNaN(parsed) ? undefined : parsed;
}

/** Montant saisi en unites -> montant mineur. */
function money(values: ActionValues, key: string): number | undefined {
  const raw = text(values, key);
  if (raw === undefined) return undefined;
  return W.toMinor(raw);
}

/** Booleen depuis un `switch` stocke en 'true' / 'false'. */
function bool(values: ActionValues, key: string): boolean | undefined {
  const raw = values[key];
  if (raw === undefined || raw === '') return undefined;
  return raw === 'true';
}

/** Retire les cles indefinies pour ne pas envoyer de `null` inutile. */
function clean(payload: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(payload)) {
    if (value === undefined) continue;
    out[key] = value;
  }
  return out;
}

/** Options d'un select depuis une liste d'objets API. */
function toOptions<T extends { id: number }>(
  items: T[],
  labelOf: (item: T) => string,
): { value: string; label: string }[] {
  return items.map((item) => ({ value: String(item.id), label: labelOf(item) }));
}

// Champs reutilisables -------------------------------------------------------

const FIELD_DATE: ActionField = {
  key: 'date',
  label: 'Date',
  type: 'date',
  required: true,
  placeholder: 'AAAA-MM-JJ',
  help: 'Format attendu : 2026-11-27',
};

const FIELD_REASON: ActionField = {
  key: 'reason',
  label: 'Motif',
  type: 'multiline',
  required: true,
  placeholder: 'Precisez la raison (3 caracteres minimum)',
};

// ─────────────────────────────────────────────────────────────────────────────
// Registre
// ─────────────────────────────────────────────────────────────────────────────

export type ModuleWriteSpec = {
  actions?: ModuleAction[];
  rowActions?: RowAction[];
};

export const MODULE_WRITES: Partial<Record<ClientModuleKey, ModuleWriteSpec>> = {
  // ═══════════════════════════════════════════════════════════════════════════
  // RESTAURANT
  // ═══════════════════════════════════════════════════════════════════════════
  restaurant: {
    actions: [
      {
        key: 'create_order',
        label: 'Nouvelle commande',
        description: 'Ouvre une commande sur une table ou en vente a emporter.',
        fields: [
          {
            key: 'branch_id',
            label: 'Etablissement',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchRestaurantBranches(), (branch) =>
                [branch.name, branch.city].filter(Boolean).join(' · ') || `#${branch.id}`,
              ),
          },
          {
            key: 'order_type',
            label: 'Type de commande',
            type: 'select',
            required: true,
            defaultValue: 'dine_in',
            options: [
              { value: 'dine_in', label: 'Sur place' },
              { value: 'takeaway', label: 'A emporter' },
              { value: 'delivery', label: 'Livraison' },
            ],
          },
          {
            key: 'table_id',
            label: 'Table',
            type: 'select',
            help: 'Obligatoire pour une commande sur place.',
            loadOptions: async () =>
              toOptions(await W.fetchRestaurantTables(), (table) =>
                [table.name, table.zone_name].filter(Boolean).join(' · ') || `Table ${table.id}`,
              ),
          },
          { key: 'covers', label: 'Nombre de couverts', type: 'number', placeholder: '2' },
        ],
        build: (values) =>
          clean({
            branch_id: num(values, 'branch_id'),
            order_type: text(values, 'order_type') ?? 'dine_in',
            table_id: num(values, 'table_id'),
            covers: num(values, 'covers'),
          }),
        submit: (payload) =>
          W.createRestaurantOrder(
            payload as unknown as Parameters<typeof W.createRestaurantOrder>[0],
          ),
        successMessage: 'Commande ouverte.',
      },
      {
        key: 'create_product',
        label: 'Nouveau produit',
        description: 'Ajoute un article a la carte.',
        fields: [
          { key: 'name', label: 'Nom du produit', type: 'text', required: true },
          { key: 'price', label: 'Prix de vente', type: 'money', required: true, placeholder: '12.50' },
          { key: 'category_id', label: 'Categorie (ID)', type: 'number', placeholder: 'Optionnel' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            price_minor: money(values, 'price'),
            category_id: num(values, 'category_id'),
          }),
        submit: (payload) =>
          W.createRestaurantProduct(
            payload as unknown as Parameters<typeof W.createRestaurantProduct>[0],
          ),
        successMessage: 'Produit ajoute a la carte.',
      },
    ],
    rowActions: [
      {
        key: 'pay_order',
        label: 'Encaisser',
        description: 'Enregistre un reglement sur une commande.',
        loadTargets: async () =>
          (await W.fetchRestaurantServerOrders()).map((order) => ({
            id: order.id,
            label: order.reference ?? `Commande ${order.id}`,
            meta: [order.table_name, order.status].filter(Boolean).join(' · '),
          })),
        fields: [
          {
            key: 'provider_code',
            label: 'Moyen de paiement',
            type: 'select',
            required: true,
            defaultValue: 'cash',
            options: [
              { value: 'cash', label: 'Especes' },
              { value: 'card', label: 'Carte' },
              { value: 'mobile_money', label: 'Mobile money' },
            ],
          },
          { key: 'amount', label: 'Montant', type: 'money', required: true, placeholder: '25.00' },
          { key: 'tip', label: 'Pourboire', type: 'money', placeholder: '0.00' },
        ],
        build: (_targetId, values) =>
          clean({
            provider_code: text(values, 'provider_code') ?? 'cash',
            amount_minor: money(values, 'amount'),
            tip_minor: money(values, 'tip'),
          }),
        submit: (targetId, payload) =>
          W.payRestaurantOrder(targetId, {
            provider_code: (payload.provider_code as 'cash' | 'card' | 'mobile_money') ?? 'cash',
            amount_minor: Number(payload.amount_minor ?? 0),
            tip_minor: payload.tip_minor as number | undefined,
          }),
        successMessage: 'Paiement enregistre.',
      },
      {
        key: 'serve_order',
        label: 'Marquer servie',
        loadTargets: async () =>
          (await W.fetchRestaurantServerOrders()).map((order) => ({
            id: order.id,
            label: order.reference ?? `Commande ${order.id}`,
            meta: order.status ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.serveRestaurantOrder(targetId),
        successMessage: 'Commande marquee comme servie.',
      },
      {
        key: 'cancel_order',
        label: 'Annuler la commande',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchRestaurantServerOrders()).map((order) => ({
            id: order.id,
            label: order.reference ?? `Commande ${order.id}`,
            meta: order.status ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.cancelRestaurantOrder(targetId),
        successMessage: 'Commande annulee.',
      },
    ],
  },

  restaurant_kitchen: {
    rowActions: [
      {
        key: 'start_ticket',
        label: 'Demarrer la preparation',
        loadTargets: async () =>
          (await W.fetchRestaurantServerOrders()).map((order) => ({
            id: order.id,
            label: order.reference ?? `Ticket ${order.id}`,
            meta: order.table_name ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.startRestaurantKitchenOrder(targetId),
        successMessage: 'Preparation demarree.',
      },
      {
        key: 'ready_ticket',
        label: 'Marquer pret',
        loadTargets: async () =>
          (await W.fetchRestaurantServerOrders()).map((order) => ({
            id: order.id,
            label: order.reference ?? `Ticket ${order.id}`,
            meta: order.table_name ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.readyRestaurantKitchenOrder(targetId),
        successMessage: 'Plat marque comme pret.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // AGENCE DE VOYAGE
  // ═══════════════════════════════════════════════════════════════════════════
  travel: {
    actions: [
      {
        key: 'create_trip',
        label: 'Publier un trajet',
        description: 'Cree un depart puis le met en vente.',
        fields: [
          { key: 'code', label: 'Code du trajet', type: 'text', required: true, placeholder: 'TR-2026-001' },
          {
            key: 'route_id',
            label: 'Ligne',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchTravelRoutes(), (route) =>
                [route.origin_name, route.destination_name].filter(Boolean).join(' → ') ||
                route.name ||
                `Ligne ${route.id}`,
              ),
          },
          { key: 'departure_date', label: 'Date de depart', type: 'date', required: true, placeholder: 'AAAA-MM-JJ' },
          { key: 'departure_time', label: 'Heure de depart', type: 'time', required: true, placeholder: '08:30' },
          { key: 'arrival_date', label: "Date d'arrivee", type: 'date', required: true, placeholder: 'AAAA-MM-JJ' },
          { key: 'arrival_time', label: "Heure d'arrivee", type: 'time', required: true, placeholder: '12:45' },
          { key: 'total_seats', label: 'Nombre de places', type: 'number', required: true, placeholder: '45' },
          {
            key: 'carrier_id',
            label: 'Transporteur',
            type: 'select',
            loadOptions: async () =>
              toOptions(await W.fetchTravelCarriers(), (carrier) => carrier.name ?? `#${carrier.id}`),
          },
        ],
        build: (values) =>
          clean({
            code: text(values, 'code'),
            route_id: num(values, 'route_id'),
            departure_date: text(values, 'departure_date'),
            departure_time: text(values, 'departure_time'),
            arrival_date: text(values, 'arrival_date'),
            arrival_time: text(values, 'arrival_time'),
            total_seats: num(values, 'total_seats'),
            carrier_id: num(values, 'carrier_id'),
          }),
        submit: (payload) =>
          W.createTravelTrip(payload as unknown as Parameters<typeof W.createTravelTrip>[0]),
        successMessage: 'Trajet cree.',
      },
      {
        key: 'create_booking',
        label: 'Nouvelle reservation',
        description: 'Vend une place sur un trajet publie.',
        fields: [
          {
            key: 'trip_id',
            label: 'Trajet',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchTravelTrips(), (trip) =>
                [trip.reference, trip.destination].filter(Boolean).join(' · ') || `Trajet ${trip.id}`,
              ),
          },
          { key: 'passenger_name', label: 'Nom du passager', type: 'text', required: true },
          { key: 'contact_phone', label: 'Telephone', type: 'text', placeholder: '+213…' },
          { key: 'contact_email', label: 'Email', type: 'text', placeholder: 'client@exemple.com' },
        ],
        build: (values) =>
          clean({
            trip_id: num(values, 'trip_id'),
            passengers: [{ full_name: text(values, 'passenger_name') }],
            contact_phone: text(values, 'contact_phone'),
            contact_email: text(values, 'contact_email'),
            booking_source: 'office',
          }),
        submit: (payload) =>
          W.createTravelBooking(payload as unknown as Parameters<typeof W.createTravelBooking>[0]),
        successMessage: 'Reservation creee.',
      },
    ],
    rowActions: [
      {
        key: 'publish_trip',
        label: 'Mettre en vente',
        loadTargets: async () =>
          (await W.fetchTravelTrips()).map((trip) => ({
            id: trip.id,
            label: trip.reference ?? `Trajet ${trip.id}`,
            meta: [trip.destination, trip.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.publishTravelTrip(targetId),
        successMessage: 'Trajet mis en vente.',
      },
      {
        key: 'cancel_trip',
        label: 'Annuler le trajet',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchTravelTrips()).map((trip) => ({
            id: trip.id,
            label: trip.reference ?? `Trajet ${trip.id}`,
            meta: trip.status ?? undefined,
          })),
        fields: [FIELD_REASON],
        build: (_targetId, values) => clean({ reason: text(values, 'reason') }),
        submit: (targetId, payload) => W.cancelTravelTrip(targetId, String(payload.reason ?? '')),
        successMessage: 'Trajet annule.',
      },
      {
        key: 'confirm_booking',
        label: 'Confirmer la reservation',
        loadTargets: async () =>
          (await W.fetchTravelBookings()).map((booking) => ({
            id: booking.id,
            label: booking.reference ?? `Reservation ${booking.id}`,
            meta: [booking.customer_name, booking.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.confirmTravelBooking(targetId),
        successMessage: 'Reservation confirmee.',
      },
      {
        key: 'issue_ticket',
        label: 'Emettre les billets',
        loadTargets: async () =>
          (await W.fetchTravelBookings()).map((booking) => ({
            id: booking.id,
            label: booking.reference ?? `Reservation ${booking.id}`,
            meta: booking.status ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.issueTravelTickets(targetId),
        successMessage: 'Billets emis.',
      },
    ],
  },

  travel_portal: {
    actions: [
      {
        key: 'create_booking',
        label: 'Reserver un trajet',
        fields: [
          {
            key: 'trip_id',
            label: 'Trajet',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchTravelTrips(), (trip) =>
                [trip.reference, trip.destination].filter(Boolean).join(' · ') || `Trajet ${trip.id}`,
              ),
          },
          { key: 'passenger_name', label: 'Nom du passager', type: 'text', required: true },
          { key: 'contact_phone', label: 'Telephone', type: 'text' },
        ],
        build: (values) =>
          clean({
            trip_id: num(values, 'trip_id'),
            passengers: [{ full_name: text(values, 'passenger_name') }],
            contact_phone: text(values, 'contact_phone'),
            booking_source: 'online',
          }),
        submit: (payload) =>
          W.createTravelBooking(payload as unknown as Parameters<typeof W.createTravelBooking>[0]),
        successMessage: 'Reservation enregistree.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // COMMERCE
  // ═══════════════════════════════════════════════════════════════════════════
  commerce: {
    actions: [
      {
        key: 'create_product',
        label: 'Creer un produit',
        description: 'Ajoute un article au catalogue.',
        fields: [
          { key: 'name', label: 'Nom', type: 'text', required: true },
          { key: 'sku', label: 'Reference (SKU)', type: 'text', required: true, placeholder: 'SKU-001' },
          { key: 'price', label: 'Prix de vente', type: 'money', required: true, placeholder: '19.90' },
          {
            key: 'category_id',
            label: 'Categorie',
            type: 'select',
            loadOptions: async () =>
              toOptions(await W.fetchRetailCategories(), (category) => category.name ?? `#${category.id}`),
          },
          { key: 'unit', label: 'Unite', type: 'text', placeholder: 'piece, kg…' },
          { key: 'description', label: 'Description', type: 'multiline' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            sku: text(values, 'sku'),
            price_minor: money(values, 'price'),
            category_id: num(values, 'category_id'),
            unit: text(values, 'unit'),
            description: text(values, 'description'),
          }),
        submit: (payload) =>
          W.createRetailProduct(payload as unknown as Parameters<typeof W.createRetailProduct>[0]),
        successMessage: 'Produit cree.',
      },
      {
        key: 'stock_movement',
        label: 'Mouvement de stock',
        description: 'Entree, sortie ou ajustement sur un emplacement.',
        fields: [
          {
            key: 'product_id',
            label: 'Produit',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchRetailProducts(), (product) =>
                [product.name, product.sku].filter(Boolean).join(' · ') || `#${product.id}`,
              ),
          },
          {
            key: 'location_id',
            label: 'Emplacement',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchRetailLocations(), (location) => location.name ?? `#${location.id}`),
          },
          {
            key: 'type',
            label: 'Type de mouvement',
            type: 'select',
            required: true,
            defaultValue: 'in',
            options: [
              { value: 'in', label: 'Entree' },
              { value: 'out', label: 'Sortie' },
              { value: 'adjustment', label: 'Ajustement' },
            ],
          },
          { key: 'quantity', label: 'Quantite', type: 'number', required: true, placeholder: '10' },
          { key: 'note', label: 'Note', type: 'text' },
        ],
        build: (values) => {
          // L'API attend un DELTA signe (`quantity_delta`) et un `reason_code`,
          // pas un type + une quantite absolue : on convertit ici.
          const type = text(values, 'type') ?? 'in';
          const qty = num(values, 'quantity') ?? 0;
          const delta = type === 'out' ? -Math.abs(qty) : Math.abs(qty);
          return clean({
            product_id: num(values, 'product_id'),
            location_id: num(values, 'location_id'),
            quantity_delta: delta,
            reason_code: type === 'adjustment' ? 'adjustment' : type === 'out' ? 'sale' : 'purchase',
            note: text(values, 'note'),
          });
        },
        submit: (payload) =>
          W.createRetailStockMovement(
            payload as unknown as Parameters<typeof W.createRetailStockMovement>[0],
          ),
        successMessage: 'Mouvement de stock enregistre.',
      },
      {
        key: 'open_pos',
        label: 'Ouvrir la caisse',
        fields: [
          {
            key: 'location_id',
            label: 'Emplacement',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchRetailLocations(), (location) => location.name ?? `#${location.id}`),
          },
          { key: 'opening_float', label: 'Fond de caisse', type: 'money', placeholder: '0.00' },
        ],
        build: (values) =>
          clean({
            location_id: num(values, 'location_id'),
            opening_float_minor: money(values, 'opening_float'),
          }),
        submit: (payload) =>
          W.openRetailPosSession(payload as unknown as Parameters<typeof W.openRetailPosSession>[0]),
        successMessage: 'Caisse ouverte.',
      },
    ],
    rowActions: [
      {
        key: 'publish_product',
        label: 'Publier le produit',
        loadTargets: async () =>
          (await W.fetchRetailProducts()).map((product) => ({
            id: product.id,
            label: product.name ?? `Produit ${product.id}`,
            meta: product.sku ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.publishRetailProduct(targetId),
        successMessage: 'Produit publie.',
      },
      {
        key: 'unpublish_product',
        label: 'Depublier le produit',
        loadTargets: async () =>
          (await W.fetchRetailProducts()).map((product) => ({
            id: product.id,
            label: product.name ?? `Produit ${product.id}`,
            meta: product.sku ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.unpublishRetailProduct(targetId),
        successMessage: 'Produit depublie.',
      },
      {
        key: 'close_pos',
        label: 'Cloturer la caisse',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchRetailPosSessions()).map((session) => ({
            id: session.id,
            label: `Session ${session.id}`,
            meta: session.status ?? undefined,
          })),
        fields: [{ key: 'counted_cash', label: 'Especes comptees', type: 'money', placeholder: '0.00' }],
        build: (_targetId, values) => clean({ counted_cash_minor: money(values, 'counted_cash') }),
        submit: (targetId, payload) =>
          W.closeRetailPosSession(targetId, {
            counted_cash_minor: payload.counted_cash_minor as number | undefined,
          }),
        successMessage: 'Caisse cloturee.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // SANTE
  // ═══════════════════════════════════════════════════════════════════════════
  health: {
    actions: [
      {
        key: 'create_patient',
        label: 'Nouveau patient',
        fields: [
          { key: 'full_name', label: 'Nom complet', type: 'text', required: true },
          {
            key: 'sex',
            label: 'Sexe',
            type: 'select',
            required: true,
            defaultValue: 'male',
            options: [
              { value: 'male', label: 'Masculin' },
              { value: 'female', label: 'Feminin' },
              { value: 'other', label: 'Autre' },
            ],
          },
          { key: 'birth_date', label: 'Date de naissance', type: 'date', placeholder: 'AAAA-MM-JJ' },
          { key: 'phone', label: 'Telephone', type: 'text' },
          { key: 'email', label: 'Email', type: 'text' },
        ],
        build: (values) =>
          clean({
            full_name: text(values, 'full_name'),
            sex: text(values, 'sex') ?? 'male',
            birth_date: text(values, 'birth_date'),
            phone: text(values, 'phone'),
            email: text(values, 'email'),
          }),
        submit: (payload) =>
          W.createHealthPatient(payload as unknown as Parameters<typeof W.createHealthPatient>[0]),
        successMessage: 'Patient enregistre.',
      },
      {
        key: 'create_appointment',
        label: 'Planifier un rendez-vous',
        fields: [
          {
            key: 'patient_id',
            label: 'Patient',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchHealthPatients(), (patient) =>
                [patient.full_name, patient.mrn].filter(Boolean).join(' · ') || `#${patient.id}`,
              ),
          },
          {
            key: 'practitioner_id',
            label: 'Praticien',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchHealthPractitioners(), (practitioner) =>
                [practitioner.full_name, practitioner.specialty_name].filter(Boolean).join(' · ') ||
                `#${practitioner.id}`,
              ),
          },
          {
            key: 'starts_at',
            label: 'Debut',
            type: 'text',
            required: true,
            placeholder: '2026-11-27 09:00',
            help: 'Format : AAAA-MM-JJ HH:MM',
          },
          {
            key: 'ends_at',
            label: 'Fin',
            type: 'text',
            required: true,
            placeholder: '2026-11-27 09:30',
            help: 'Doit etre posterieur au debut.',
          },
          { key: 'reason', label: 'Motif', type: 'text' },
        ],
        build: (values) =>
          clean({
            patient_id: num(values, 'patient_id'),
            practitioner_id: num(values, 'practitioner_id'),
            starts_at: text(values, 'starts_at'),
            ends_at: text(values, 'ends_at'),
            reason: text(values, 'reason'),
          }),
        submit: (payload) =>
          W.createHealthAppointment(
            payload as unknown as Parameters<typeof W.createHealthAppointment>[0],
          ),
        successMessage: 'Rendez-vous planifie.',
      },
    ],
    rowActions: [
      {
        key: 'appointment_status',
        label: "Changer l'etat d'un rendez-vous",
        loadTargets: async () =>
          (await W.fetchHealthAppointments()).map((appointment) => ({
            id: appointment.id,
            label: appointment.patient_name ?? `RDV ${appointment.id}`,
            meta: [appointment.scheduled_at, appointment.status].filter(Boolean).join(' · '),
          })),
        fields: [
          {
            key: 'status',
            label: 'Nouvel etat',
            type: 'select',
            required: true,
            options: [
              { value: 'scheduled', label: 'Planifie' },
              { value: 'confirmed', label: 'Confirme' },
              { value: 'checked_in', label: 'Arrive' },
              { value: 'completed', label: 'Termine' },
              { value: 'cancelled', label: 'Annule' },
              { value: 'no_show', label: 'Absent' },
            ],
          },
        ],
        build: (_targetId, values) => clean({ status: text(values, 'status') }),
        submit: (targetId, payload) =>
          W.transitionHealthAppointment(targetId, String(payload.status ?? '')),
        successMessage: 'Rendez-vous mis a jour.',
      },
    ],
  },

  health_patients: {
    actions: [
      {
        key: 'create_patient',
        label: 'Nouveau patient',
        fields: [
          { key: 'full_name', label: 'Nom complet', type: 'text', required: true },
          {
            key: 'sex',
            label: 'Sexe',
            type: 'select',
            required: true,
            defaultValue: 'male',
            options: [
              { value: 'male', label: 'Masculin' },
              { value: 'female', label: 'Feminin' },
              { value: 'other', label: 'Autre' },
            ],
          },
          { key: 'birth_date', label: 'Date de naissance', type: 'date', placeholder: 'AAAA-MM-JJ' },
          { key: 'phone', label: 'Telephone', type: 'text' },
        ],
        build: (values) =>
          clean({
            full_name: text(values, 'full_name'),
            sex: text(values, 'sex') ?? 'male',
            birth_date: text(values, 'birth_date'),
            phone: text(values, 'phone'),
          }),
        submit: (payload) =>
          W.createHealthPatient(payload as unknown as Parameters<typeof W.createHealthPatient>[0]),
        successMessage: 'Patient enregistre.',
      },
    ],
  },

  health_appointments: {
    actions: [
      {
        key: 'create_appointment',
        label: 'Planifier un rendez-vous',
        fields: [
          {
            key: 'patient_id',
            label: 'Patient',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchHealthPatients(), (patient) =>
                [patient.full_name, patient.mrn].filter(Boolean).join(' · ') || `#${patient.id}`,
              ),
          },
          {
            key: 'practitioner_id',
            label: 'Praticien',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchHealthPractitioners(), (practitioner) =>
                practitioner.full_name ?? `#${practitioner.id}`,
              ),
          },
          { key: 'starts_at', label: 'Debut', type: 'text', required: true, placeholder: '2026-11-27 09:00' },
          { key: 'ends_at', label: 'Fin', type: 'text', required: true, placeholder: '2026-11-27 09:30' },
          { key: 'reason', label: 'Motif', type: 'text' },
        ],
        build: (values) =>
          clean({
            patient_id: num(values, 'patient_id'),
            practitioner_id: num(values, 'practitioner_id'),
            starts_at: text(values, 'starts_at'),
            ends_at: text(values, 'ends_at'),
            reason: text(values, 'reason'),
          }),
        submit: (payload) =>
          W.createHealthAppointment(
            payload as unknown as Parameters<typeof W.createHealthAppointment>[0],
          ),
        successMessage: 'Rendez-vous planifie.',
      },
    ],
  },

  health_admissions: {
    rowActions: [
      {
        key: 'discharge',
        label: 'Sortie du patient',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchHealthPatients()).map((patient) => ({
            id: patient.id,
            label: patient.full_name ?? `Patient ${patient.id}`,
            meta: patient.status ?? undefined,
          })),
        fields: [{ key: 'summary', label: 'Resume de sortie', type: 'multiline' }],
        build: (_targetId, values) => clean({ summary: text(values, 'summary') }),
        submit: (targetId, payload) =>
          W.dischargeHealthAdmission(targetId, { summary: payload.summary as string | undefined }),
        successMessage: 'Sortie enregistree.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // PHARMACIE
  // ═══════════════════════════════════════════════════════════════════════════
  pharmacy: {
    actions: [
      {
        key: 'create_product',
        label: 'Nouveau produit',
        fields: [
          { key: 'name', label: 'Nom du medicament', type: 'text', required: true },
          { key: 'form', label: 'Forme', type: 'text', placeholder: 'comprime, sirop…' },
          { key: 'dosage', label: 'Dosage', type: 'text', placeholder: '500 mg' },
          { key: 'sale_price', label: 'Prix de vente', type: 'number', placeholder: '250' },
          { key: 'min_stock_level', label: 'Seuil de stock', type: 'number', placeholder: '10' },
          {
            key: 'prescription_required',
            label: 'Ordonnance obligatoire',
            type: 'switch',
            defaultValue: 'false',
          },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            form: text(values, 'form'),
            dosage: text(values, 'dosage'),
            sale_price: num(values, 'sale_price'),
            min_stock_level: num(values, 'min_stock_level'),
            prescription_required: bool(values, 'prescription_required'),
          }),
        submit: (payload) =>
          W.createPharmacyProduct(payload as unknown as Parameters<typeof W.createPharmacyProduct>[0]),
        successMessage: 'Produit ajoute a l\'officine.',
      },
      {
        key: 'create_sale',
        label: 'Nouvelle vente',
        description: 'Enregistre une delivrance au comptoir.',
        fields: [
          {
            key: 'product_id',
            label: 'Produit',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchPharmacyProducts(), (product) =>
                [product.name, product.dosage].filter(Boolean).join(' · ') || `#${product.id}`,
              ),
          },
          { key: 'quantity', label: 'Quantite', type: 'number', required: true, defaultValue: '1' },
          {
            key: 'payment_method',
            label: 'Reglement',
            type: 'select',
            required: true,
            defaultValue: 'cash',
            options: [
              { value: 'cash', label: 'Especes' },
              { value: 'card', label: 'Carte' },
              { value: 'mobile', label: 'Mobile' },
              { value: 'insurance', label: 'Assurance' },
            ],
          },
          { key: 'customer_name', label: 'Client', type: 'text' },
        ],
        build: (values) =>
          clean({
            payment_method: text(values, 'payment_method') ?? 'cash',
            customer_name: text(values, 'customer_name'),
            lines: [{ product_id: num(values, 'product_id'), quantity: num(values, 'quantity') ?? 1 }],
          }),
        submit: (payload) =>
          W.createPharmacySale(payload as unknown as Parameters<typeof W.createPharmacySale>[0]),
        successMessage: 'Vente enregistree.',
      },
      {
        key: 'stock_adjustment',
        label: 'Ajuster le stock',
        description: 'Ajustement sur un lot (batch) de l\'officine.',
        fields: [
          {
            key: 'batch_id',
            label: 'Lot (batch)',
            type: 'select',
            required: true,
            help: 'L\'ajustement porte sur un lot precis, pas sur le produit.',
            loadOptions: async () =>
              toOptions(await W.fetchPharmacyBatches(), (batch) =>
                [batch.product_name, batch.batch_number].filter(Boolean).join(' · ') ||
                `Lot ${batch.id}`,
              ),
          },
          { key: 'quantity', label: 'Delta (negatif pour retirer)', type: 'number', required: true },
          { key: 'reason', label: 'Motif', type: 'text', required: true, placeholder: 'Casse, peremption…' },
        ],
        build: (values) =>
          clean({
            batch_id: num(values, 'batch_id'),
            quantity_delta: num(values, 'quantity'),
            reason: text(values, 'reason'),
            type: 'adjustment',
          }),
        submit: (payload) =>
          W.createPharmacyStockAdjustment(
            payload as unknown as Parameters<typeof W.createPharmacyStockAdjustment>[0],
          ),
        successMessage: 'Stock ajuste.',
      },
    ],
    rowActions: [
      {
        key: 'void_sale',
        label: 'Annuler une vente',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchPharmacySales()).map((sale) => ({
            id: sale.id,
            label: sale.number ?? `Vente ${sale.id}`,
            meta: [sale.customer_name, sale.status].filter(Boolean).join(' · '),
          })),
        fields: [FIELD_REASON],
        build: (_targetId, values) => clean({ reason: text(values, 'reason') }),
        submit: (targetId, payload) => W.voidPharmacySale(targetId, String(payload.reason ?? '')),
        successMessage: 'Vente annulee.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // COMMERCE INTERNATIONAL
  // ═══════════════════════════════════════════════════════════════════════════
  trade: {
    actions: [
      {
        key: 'create_rfq',
        label: 'Nouvelle demande de prix',
        description: 'Cree un RFQ a diffuser aux contreparties.',
        fields: [
          { key: 'title', label: 'Objet', type: 'text', required: true },
          { key: 'description', label: 'Ligne 1 — description', type: 'text', required: true },
          { key: 'quantity', label: 'Ligne 1 — quantite', type: 'number', required: true, defaultValue: '1' },
          { key: 'unit', label: 'Unite', type: 'text', placeholder: 'tonne, carton…' },
          { key: 'destination_country', label: 'Pays de destination (ISO 2)', type: 'text', placeholder: 'DZ' },
          { key: 'incoterm', label: 'Incoterm', type: 'text', placeholder: 'FOB' },
          { key: 'notes', label: 'Notes', type: 'multiline' },
        ],
        build: (values) =>
          clean({
            title: text(values, 'title'),
            destination_country: text(values, 'destination_country'),
            incoterm: text(values, 'incoterm'),
            notes: text(values, 'notes'),
            lines: [
              {
                description: text(values, 'description'),
                quantity: num(values, 'quantity') ?? 1,
                unit: text(values, 'unit'),
              },
            ],
          }),
        submit: (payload) =>
          W.createTradeRfq(payload as unknown as Parameters<typeof W.createTradeRfq>[0]),
        successMessage: 'Demande de prix creee.',
      },
      {
        key: 'create_counterparty',
        label: 'Nouvelle contrepartie',
        fields: [
          { key: 'name', label: 'Raison sociale', type: 'text', required: true },
          { key: 'country_code', label: 'Pays (ISO 2)', type: 'text', placeholder: 'TR' },
          { key: 'email', label: 'Email', type: 'text' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            country_code: text(values, 'country_code'),
            email: text(values, 'email'),
          }),
        submit: (payload) =>
          W.createTradeCounterparty(
            payload as unknown as Parameters<typeof W.createTradeCounterparty>[0],
          ),
        successMessage: 'Contrepartie creee.',
      },
    ],
    rowActions: [
      {
        key: 'rfq_transition',
        label: "Changer l'etat d'un RFQ",
        loadTargets: async () =>
          (await W.fetchTradeRfqs()).map((rfq) => ({
            id: rfq.id,
            label: rfq.reference ?? rfq.title ?? `RFQ ${rfq.id}`,
            meta: rfq.status ?? undefined,
          })),
        fields: [
          {
            key: 'status',
            label: 'Nouvel etat',
            type: 'select',
            required: true,
            options: [
              { value: 'open', label: 'Ouvert' },
              { value: 'sent', label: 'Envoye' },
              { value: 'closed', label: 'Cloture' },
              { value: 'awarded', label: 'Attribue' },
              { value: 'cancelled', label: 'Annule' },
            ],
          },
          { key: 'close_reason', label: 'Motif de cloture', type: 'text' },
        ],
        build: (_targetId, values) =>
          clean({ status: text(values, 'status'), close_reason: text(values, 'close_reason') }),
        submit: (targetId, payload) =>
          W.transitionTradeRfq(targetId, {
            status: String(payload.status ?? ''),
            close_reason: payload.close_reason as string | undefined,
          }),
        successMessage: 'RFQ mis a jour.',
      },
      {
        key: 'accept_quote',
        label: 'Accepter une offre',
        loadTargets: async () =>
          (await W.fetchTradeQuotes()).map((quote) => ({
            id: quote.id,
            label: quote.reference ?? `Offre ${quote.id}`,
            meta: quote.status ?? undefined,
          })),
        build: () => ({}),
        submit: (targetId) => W.acceptTradeQuote(targetId),
        successMessage: 'Offre acceptee.',
      },
      {
        key: 'order_transition',
        label: "Changer l'etat d'une commande",
        loadTargets: async () =>
          (await W.fetchTradeOrders()).map((order) => ({
            id: order.id,
            label: order.reference ?? `Commande ${order.id}`,
            meta: order.status ?? undefined,
          })),
        fields: [
          { key: 'status', label: 'Nouvel etat', type: 'text', required: true, placeholder: 'shipped' },
          { key: 'note', label: 'Note', type: 'text' },
        ],
        build: (_targetId, values) => clean({ status: text(values, 'status'), note: text(values, 'note') }),
        submit: (targetId, payload) =>
          W.transitionTradeOrder(targetId, {
            status: String(payload.status ?? ''),
            note: payload.note as string | undefined,
          }),
        successMessage: 'Commande mise a jour.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // HOSPITALITY
  // ═══════════════════════════════════════════════════════════════════════════
  hospitality: {
    actions: [
      {
        key: 'create_reservation',
        label: 'Nouvelle reservation',
        description: 'Enregistre un sejour pour un client.',
        fields: [
          {
            key: 'property_id',
            label: 'Etablissement',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchHospitalityProperties(), (property) =>
                [property.name, property.city].filter(Boolean).join(' · ') || `#${property.id}`,
              ),
          },
          {
            key: 'room_type_id',
            label: 'Type de chambre',
            type: 'select',
            required: true,
            help: 'Les types sont charges apres le choix de l\'etablissement.',
            loadOptions: async () => [],
          },
          { key: 'guest_name', label: 'Nom du client', type: 'text', required: true },
          { key: 'check_in', label: 'Arrivee', type: 'date', required: true, placeholder: 'AAAA-MM-JJ' },
          { key: 'check_out', label: 'Depart', type: 'date', required: true, placeholder: 'AAAA-MM-JJ' },
          { key: 'adults', label: 'Adultes', type: 'number', defaultValue: '1' },
          { key: 'children', label: 'Enfants', type: 'number', defaultValue: '0' },
          { key: 'contact_phone', label: 'Telephone', type: 'text' },
        ],
        build: (values) =>
          clean({
            property_id: num(values, 'property_id'),
            room_type_id: num(values, 'room_type_id'),
            guest_name: text(values, 'guest_name'),
            check_in: text(values, 'check_in'),
            check_out: text(values, 'check_out'),
            adults: num(values, 'adults'),
            children: num(values, 'children'),
            contact_phone: text(values, 'contact_phone'),
          }),
        submit: (payload) =>
          W.createHospitalityReservation(
            payload as unknown as Parameters<typeof W.createHospitalityReservation>[0],
          ),
        successMessage: 'Reservation creee.',
      },
      {
        key: 'create_property',
        label: 'Nouvel etablissement',
        fields: [
          { key: 'name', label: 'Nom', type: 'text', required: true },
          { key: 'city', label: 'Ville', type: 'text' },
          { key: 'type', label: 'Type', type: 'text', placeholder: 'hotel, residence…' },
          { key: 'address', label: 'Adresse', type: 'text' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            city: text(values, 'city'),
            type: text(values, 'type'),
            address: text(values, 'address'),
          }),
        submit: (payload) =>
          W.createHospitalityProperty(
            payload as unknown as Parameters<typeof W.createHospitalityProperty>[0],
          ),
        successMessage: 'Etablissement cree.',
      },
    ],
    rowActions: [
      {
        key: 'confirm_reservation',
        label: 'Confirmer la reservation',
        loadTargets: async () =>
          (await W.fetchHospitalityReservations()).map((reservation) => ({
            id: reservation.id,
            label: reservation.reference ?? `Reservation ${reservation.id}`,
            meta: [reservation.guest_name, reservation.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.transitionHospitalityReservation(targetId, 'confirm'),
        successMessage: 'Reservation confirmee.',
      },
      {
        key: 'check_in',
        label: 'Check-in',
        loadTargets: async () =>
          (await W.fetchHospitalityReservations()).map((reservation) => ({
            id: reservation.id,
            label: reservation.reference ?? `Reservation ${reservation.id}`,
            meta: [reservation.guest_name, reservation.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.transitionHospitalityReservation(targetId, 'check-in'),
        successMessage: 'Client enregistre (check-in).',
      },
      {
        key: 'check_out',
        label: 'Check-out',
        loadTargets: async () =>
          (await W.fetchHospitalityReservations()).map((reservation) => ({
            id: reservation.id,
            label: reservation.reference ?? `Reservation ${reservation.id}`,
            meta: [reservation.guest_name, reservation.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.transitionHospitalityReservation(targetId, 'check-out'),
        successMessage: 'Depart enregistre (check-out).',
      },
      {
        key: 'cancel_reservation',
        label: 'Annuler la reservation',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchHospitalityReservations()).map((reservation) => ({
            id: reservation.id,
            label: reservation.reference ?? `Reservation ${reservation.id}`,
            meta: reservation.status ?? undefined,
          })),
        fields: [{ key: 'reason', label: 'Motif', type: 'text' }],
        build: (_targetId, values) => clean({ reason: text(values, 'reason') }),
        submit: (targetId, payload) =>
          W.transitionHospitalityReservation(targetId, 'cancel', {
            reason: payload.reason as string | undefined,
          }),
        successMessage: 'Reservation annulee.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // VTC & TAXI
  // ═══════════════════════════════════════════════════════════════════════════
  vtc: {
    actions: [
      {
        key: 'create_ride',
        label: 'Nouvelle course',
        description: 'Cree une demande de course entre deux points.',
        fields: [
          { key: 'pickup_lat', label: 'Depart — latitude', type: 'number', required: true, placeholder: '36.7538' },
          { key: 'pickup_lng', label: 'Depart — longitude', type: 'number', required: true, placeholder: '3.0588' },
          { key: 'pickup_address', label: 'Adresse de depart', type: 'text' },
          { key: 'dropoff_lat', label: 'Arrivee — latitude', type: 'number', required: true, placeholder: '36.7000' },
          { key: 'dropoff_lng', label: 'Arrivee — longitude', type: 'number', required: true, placeholder: '3.2000' },
          { key: 'dropoff_address', label: "Adresse d'arrivee", type: 'text' },
          { key: 'passenger_name', label: 'Passager', type: 'text' },
          { key: 'passenger_phone', label: 'Telephone', type: 'text' },
        ],
        build: (values) =>
          clean({
            pickup: { lat: num(values, 'pickup_lat'), lng: num(values, 'pickup_lng') },
            dropoff: { lat: num(values, 'dropoff_lat'), lng: num(values, 'dropoff_lng') },
            pickup_address: text(values, 'pickup_address'),
            dropoff_address: text(values, 'dropoff_address'),
            passenger_name: text(values, 'passenger_name'),
            passenger_phone: text(values, 'passenger_phone'),
          }),
        submit: (payload) =>
          W.createVtcRide(payload as unknown as Parameters<typeof W.createVtcRide>[0]),
        successMessage: 'Course creee.',
      },
    ],
    rowActions: [
      {
        key: 'driver_transition',
        label: 'Action chauffeur',
        loadTargets: async () =>
          (await W.fetchVtcDispatchRides()).map((ride) => ({
            id: ride.id,
            label: ride.reference ?? `Course ${ride.id}`,
            meta: [ride.pickup_address, ride.status].filter(Boolean).join(' · '),
          })),
        fields: [
          {
            key: 'action',
            label: 'Action',
            type: 'select',
            required: true,
            options: [
              { value: 'accept', label: 'Accepter' },
              { value: 'arrive', label: 'Arrive sur place' },
              { value: 'start', label: 'Demarrer la course' },
              { value: 'complete', label: 'Terminer' },
              { value: 'decline', label: 'Refuser' },
            ],
          },
        ],
        build: (_targetId, values) => clean({ action: text(values, 'action') }),
        submit: (targetId, payload) =>
          W.transitionVtcDriverRide(
            targetId,
            (payload.action as 'accept' | 'decline' | 'arrive' | 'start' | 'complete') ?? 'accept',
          ),
        successMessage: 'Course mise a jour.',
      },
      {
        key: 'cancel_ride',
        label: 'Annuler la course',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchVtcDispatchRides()).map((ride) => ({
            id: ride.id,
            label: ride.reference ?? `Course ${ride.id}`,
            meta: ride.status ?? undefined,
          })),
        fields: [FIELD_REASON],
        build: (_targetId, values) => clean({ reason: text(values, 'reason') }),
        submit: (targetId, payload) => W.cancelVtcRide(targetId, String(payload.reason ?? '')),
        successMessage: 'Course annulee.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // STATION-SERVICE
  // ═══════════════════════════════════════════════════════════════════════════
  fuel: {
    actions: [
      {
        key: 'create_sale',
        label: 'Enregistrer une vente',
        description: 'Saisie d\'une vente au pistolet.',
        fields: [
          {
            key: 'station_id',
            label: 'Station',
            type: 'select',
            loadOptions: async () =>
              toOptions(await W.fetchFuelStations(), (station) =>
                [station.name, station.city].filter(Boolean).join(' · ') || `#${station.id}`,
              ),
          },
          { key: 'product', label: 'Produit', type: 'text', required: true, placeholder: 'Gasoil, Essence…' },
          { key: 'quantity', label: 'Quantite (litres)', type: 'number', required: true, placeholder: '35.5' },
          { key: 'unit_price', label: 'Prix unitaire', type: 'number', required: true, placeholder: '45.20' },
          {
            key: 'payment_method',
            label: 'Reglement',
            type: 'select',
            defaultValue: 'cash',
            options: [
              { value: 'cash', label: 'Especes' },
              { value: 'card', label: 'Carte' },
              { value: 'mobile', label: 'Mobile' },
              { value: 'credit', label: 'Credit client' },
            ],
          },
          { key: 'notes', label: 'Note', type: 'text' },
        ],
        build: (values) =>
          clean({
            station_id: num(values, 'station_id'),
            product: text(values, 'product'),
            quantity: num(values, 'quantity'),
            unit_price: num(values, 'unit_price'),
            payment_method: text(values, 'payment_method'),
            notes: text(values, 'notes'),
          }),
        submit: (payload) =>
          W.createFuelSale(payload as unknown as Parameters<typeof W.createFuelSale>[0]),
        successMessage: 'Vente enregistree.',
      },
      {
        key: 'open_cash_session',
        label: 'Ouvrir la caisse',
        fields: [
          {
            key: 'station_id',
            label: 'Station',
            type: 'select',
            loadOptions: async () =>
              toOptions(await W.fetchFuelStations(), (station) => station.name ?? `#${station.id}`),
          },
          { key: 'opening_float', label: 'Fond de caisse', type: 'number', placeholder: '0' },
        ],
        build: (values) =>
          clean({ station_id: num(values, 'station_id'), opening_float: num(values, 'opening_float') }),
        submit: (payload) =>
          W.openFuelCashSession(payload as unknown as Parameters<typeof W.openFuelCashSession>[0]),
        successMessage: 'Caisse ouverte.',
      },
      {
        key: 'create_shift',
        label: 'Planifier une equipe',
        fields: [
          { key: 'name', label: 'Nom de l\'equipe', type: 'text', required: true, placeholder: 'Equipe matin' },
          { key: 'starts_at', label: 'Debut', type: 'text', required: true, placeholder: '2026-11-27 06:00' },
          { key: 'ends_at', label: 'Fin', type: 'text', required: true, placeholder: '2026-11-27 14:00' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            starts_at: text(values, 'starts_at'),
            ends_at: text(values, 'ends_at'),
          }),
        submit: (payload) =>
          W.createFuelShift(payload as unknown as Parameters<typeof W.createFuelShift>[0]),
        successMessage: 'Equipe planifiee.',
      },
      {
        key: 'create_incident',
        label: 'Declarer un incident',
        fields: [
          { key: 'title', label: 'Titre', type: 'text', required: true },
          { key: 'description', label: 'Description', type: 'multiline' },
          {
            key: 'severity',
            label: 'Gravite',
            type: 'select',
            defaultValue: 'medium',
            options: [
              { value: 'low', label: 'Faible' },
              { value: 'medium', label: 'Moyenne' },
              { value: 'high', label: 'Elevee' },
              { value: 'critical', label: 'Critique' },
            ],
          },
        ],
        build: (values) =>
          clean({
            title: text(values, 'title'),
            description: text(values, 'description'),
            severity: text(values, 'severity'),
          }),
        submit: (payload) =>
          W.createFuelIncident(payload as unknown as Parameters<typeof W.createFuelIncident>[0]),
        successMessage: 'Incident declare.',
      },
    ],
    rowActions: [
      {
        key: 'cancel_sale',
        label: 'Annuler une vente',
        destructive: true,
        loadTargets: async () =>
          (await W.fetchFuelSales()).map((sale) => ({
            id: sale.id,
            label: sale.reference ?? `Vente ${sale.id}`,
            meta: [sale.pump_name, sale.amount].filter(Boolean).join(' · '),
          })),
        fields: [FIELD_REASON],
        build: (_targetId, values) => clean({ reason: text(values, 'reason') }),
        submit: (targetId, payload) => W.cancelFuelSale(targetId, String(payload.reason ?? '')),
        successMessage: 'Vente annulee.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // SCOLARITE
  // ═══════════════════════════════════════════════════════════════════════════
  edu_manager: {
    actions: [
      {
        key: 'create_student',
        label: 'Inscrire un eleve',
        fields: [
          { key: 'student_number', label: 'Matricule', type: 'text', required: true, placeholder: 'ELV-2026-001' },
          { key: 'display_name', label: 'Nom complet', type: 'text', required: true },
          { key: 'birth_date', label: 'Date de naissance', type: 'date', placeholder: 'AAAA-MM-JJ' },
        ],
        build: (values) =>
          clean({
            student_number: text(values, 'student_number'),
            display_name: text(values, 'display_name'),
            birth_date: text(values, 'birth_date'),
            status: 'active',
          }),
        submit: (payload) =>
          W.createEduStudent(payload as unknown as Parameters<typeof W.createEduStudent>[0]),
        successMessage: 'Eleve inscrit.',
      },
      {
        key: 'create_class',
        label: 'Creer une classe',
        fields: [
          { key: 'name', label: 'Nom de la classe', type: 'text', required: true, placeholder: '6eme A' },
          {
            key: 'campus_id',
            label: 'Campus',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchEduCampuses(), (campus) => campus.name ?? `#${campus.id}`),
          },
          { key: 'level', label: 'Niveau', type: 'text', placeholder: 'Primaire, College…' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            campus_id: num(values, 'campus_id'),
            level: text(values, 'level'),
          }),
        submit: (payload) =>
          W.createEduClass(payload as unknown as Parameters<typeof W.createEduClass>[0]),
        successMessage: 'Classe creee.',
      },
      {
        key: 'create_admission',
        label: 'Nouvelle admission',
        fields: [
          { key: 'display_name', label: 'Nom du candidat', type: 'text', required: true },
          {
            key: 'campus_id',
            label: 'Campus',
            type: 'select',
            loadOptions: async () =>
              toOptions(await W.fetchEduCampuses(), (campus) => campus.name ?? `#${campus.id}`),
          },
          { key: 'guardian_name', label: 'Tuteur', type: 'text' },
          { key: 'guardian_phone', label: 'Telephone du tuteur', type: 'text' },
        ],
        build: (values) =>
          clean({
            display_name: text(values, 'display_name'),
            campus_id: num(values, 'campus_id'),
            guardian_name: text(values, 'guardian_name'),
            guardian_phone: text(values, 'guardian_phone'),
          }),
        submit: (payload) =>
          W.createEduAdmission(payload as unknown as Parameters<typeof W.createEduAdmission>[0]),
        successMessage: 'Dossier d\'admission cree.',
      },
      {
        key: 'create_assessment',
        label: 'Creer une evaluation',
        fields: [
          {
            key: 'class_id',
            label: 'Classe',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchEduClasses(), (klass) => klass.name ?? `#${klass.id}`),
          },
          {
            key: 'subject_id',
            label: 'Matiere',
            type: 'select',
            required: true,
            loadOptions: async () =>
              toOptions(await W.fetchEduSubjects(), (subject) => subject.name ?? `#${subject.id}`),
          },
          { key: 'title', label: 'Titre', type: 'text', required: true, placeholder: 'Controle 1' },
          { key: 'max_score', label: 'Note maximale', type: 'number', defaultValue: '20' },
          { key: 'date', label: 'Date', type: 'date', placeholder: 'AAAA-MM-JJ' },
        ],
        build: (values) =>
          clean({
            class_id: num(values, 'class_id'),
            subject_id: num(values, 'subject_id'),
            title: text(values, 'title'),
            max_score: num(values, 'max_score'),
            date: text(values, 'date'),
          }),
        submit: (payload) =>
          W.createEduAssessment(payload as unknown as Parameters<typeof W.createEduAssessment>[0]),
        successMessage: 'Evaluation creee.',
      },
    ],
    rowActions: [
      {
        key: 'convert_admission',
        label: 'Convertir une admission',
        loadTargets: async () =>
          (await W.fetchEduAdmissions()).map((admission) => ({
            id: admission.id,
            label: admission.display_name ?? `Admission ${admission.id}`,
            meta: [admission.campus_name, admission.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.convertEduAdmission(targetId),
        successMessage: 'Admission convertie en inscription.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // CRM
  // ═══════════════════════════════════════════════════════════════════════════
  crm: {
    actions: [
      {
        key: 'create_lead',
        label: 'Nouveau lead',
        fields: [
          { key: 'first_name', label: 'Prenom', type: 'text', required: true },
          { key: 'last_name', label: 'Nom', type: 'text' },
          { key: 'company_name', label: 'Societe', type: 'text' },
          { key: 'email', label: 'Email', type: 'text' },
          { key: 'phone', label: 'Telephone', type: 'text' },
          { key: 'source', label: 'Source', type: 'text', placeholder: 'site, salon, appel…' },
          { key: 'notes', label: 'Notes', type: 'multiline' },
        ],
        build: (values) =>
          clean({
            first_name: text(values, 'first_name'),
            last_name: text(values, 'last_name'),
            company_name: text(values, 'company_name'),
            email: text(values, 'email'),
            phone: text(values, 'phone'),
            source: text(values, 'source'),
            notes: text(values, 'notes'),
          }),
        submit: (payload) =>
          W.createCrmLead(payload as unknown as Parameters<typeof W.createCrmLead>[0]),
        successMessage: 'Lead cree.',
      },
      {
        key: 'create_account',
        label: 'Nouveau compte',
        fields: [
          { key: 'name', label: 'Nom du compte', type: 'text', required: true },
          { key: 'email', label: 'Email', type: 'text' },
          { key: 'phone', label: 'Telephone', type: 'text' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            email: text(values, 'email'),
            phone: text(values, 'phone'),
            status: 'active',
          }),
        submit: (payload) =>
          W.createCrmAccount(payload as unknown as Parameters<typeof W.createCrmAccount>[0]),
        successMessage: 'Compte cree.',
      },
    ],
    rowActions: [
      {
        key: 'convert_lead',
        label: 'Convertir un lead',
        loadTargets: async () =>
          (await W.fetchCrmLeads()).map((lead) => ({
            id: lead.id,
            label: lead.name ?? `Lead ${lead.id}`,
            meta: [lead.company_name, lead.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.convertCrmLead(targetId),
        successMessage: 'Lead converti.',
      },
      {
        key: 'opportunity_stage',
        label: "Changer l'etape d'une opportunite",
        loadTargets: async () =>
          (await W.fetchCrmOpportunities()).map((opportunity) => ({
            id: opportunity.id,
            label: opportunity.name ?? `Opportunite ${opportunity.id}`,
            meta: [opportunity.account_name, opportunity.stage].filter(Boolean).join(' · '),
          })),
        fields: [
          { key: 'stage', label: 'Nouvelle etape', type: 'text', required: true, placeholder: 'negotiation' },
        ],
        build: (_targetId, values) => clean({ stage: text(values, 'stage') }),
        submit: (targetId, payload) =>
          W.transitionCrmOpportunityStage(targetId, String(payload.stage ?? '')),
        successMessage: 'Etape mise a jour.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // COMPTABILITE
  // ═══════════════════════════════════════════════════════════════════════════
  accounting: {
    actions: [
      {
        key: 'create_document',
        label: 'Nouveau document',
        description: 'Cree une facture ou un devis.',
        fields: [
          {
            key: 'type',
            label: 'Type',
            type: 'select',
            required: true,
            defaultValue: 'invoice',
            options: [
              { value: 'invoice', label: 'Facture' },
              { value: 'quote', label: 'Devis' },
              { value: 'credit_note', label: 'Avoir' },
            ],
          },
          { key: 'description', label: 'Ligne 1 — description', type: 'text', required: true },
          { key: 'quantity', label: 'Ligne 1 — quantite', type: 'number', required: true, defaultValue: '1' },
          { key: 'unit_price', label: 'Ligne 1 — prix unitaire', type: 'money', required: true },
          { key: 'issued_at', label: 'Date d\'emission', type: 'date', placeholder: 'AAAA-MM-JJ' },
          { key: 'due_at', label: 'Echeance', type: 'date', placeholder: 'AAAA-MM-JJ' },
        ],
        build: (values) =>
          clean({
            type: text(values, 'type') ?? 'invoice',
            issued_at: text(values, 'issued_at'),
            due_at: text(values, 'due_at'),
            lines: [
              {
                description: text(values, 'description'),
                quantity: num(values, 'quantity') ?? 1,
                unit_price_minor: money(values, 'unit_price'),
              },
            ],
          }),
        submit: (payload) =>
          W.createAccountingDocument(
            payload as unknown as Parameters<typeof W.createAccountingDocument>[0],
          ),
        successMessage: 'Document cree.',
      },
      {
        key: 'create_contact',
        label: 'Nouveau contact',
        fields: [
          { key: 'name', label: 'Nom', type: 'text', required: true },
          {
            key: 'type',
            label: 'Type',
            type: 'select',
            defaultValue: 'customer',
            options: [
              { value: 'customer', label: 'Client' },
              { value: 'supplier', label: 'Fournisseur' },
            ],
          },
          { key: 'email', label: 'Email', type: 'text' },
          { key: 'phone', label: 'Telephone', type: 'text' },
        ],
        build: (values) =>
          clean({
            name: text(values, 'name'),
            type: text(values, 'type'),
            email: text(values, 'email'),
            phone: text(values, 'phone'),
          }),
        submit: (payload) =>
          W.createAccountingContact(
            payload as unknown as Parameters<typeof W.createAccountingContact>[0],
          ),
        successMessage: 'Contact cree.',
      },
    ],
    rowActions: [
      {
        key: 'send_document',
        label: 'Envoyer un document',
        loadTargets: async () =>
          (await W.fetchAccountingDocuments()).map((document) => ({
            id: document.id,
            label: document.number ?? `Document ${document.id}`,
            meta: [document.contact_name, document.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.sendAccountingDocument(targetId),
        successMessage: 'Document envoye.',
      },
      {
        key: 'add_payment',
        label: 'Encaisser un document',
        loadTargets: async () =>
          (await W.fetchAccountingDocuments()).map((document) => ({
            id: document.id,
            label: document.number ?? `Document ${document.id}`,
            meta: [document.contact_name, document.status].filter(Boolean).join(' · '),
          })),
        fields: [
          { key: 'amount', label: 'Montant', type: 'money', required: true },
          {
            key: 'method',
            label: 'Moyen',
            type: 'select',
            defaultValue: 'cash',
            options: [
              { value: 'cash', label: 'Especes' },
              { value: 'bank', label: 'Virement' },
              { value: 'card', label: 'Carte' },
            ],
          },
        ],
        build: (_targetId, values) =>
          clean({ amount_minor: money(values, 'amount'), method: text(values, 'method') }),
        submit: (targetId, payload) =>
          W.addAccountingPayment(targetId, {
            amount_minor: Number(payload.amount_minor ?? 0),
            method: payload.method as string | undefined,
          }),
        successMessage: 'Paiement enregistre.',
      },
    ],
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // MARKETING
  // ═══════════════════════════════════════════════════════════════════════════
  marketing: {
    actions: [
      {
        key: 'create_post',
        label: 'Nouvelle publication',
        fields: [
          { key: 'content', label: 'Contenu', type: 'multiline', required: true },
          {
            key: 'network',
            label: 'Reseau',
            type: 'select',
            defaultValue: 'facebook',
            options: [
              { value: 'facebook', label: 'Facebook' },
              { value: 'instagram', label: 'Instagram' },
              { value: 'linkedin', label: 'LinkedIn' },
              { value: 'x', label: 'X' },
            ],
          },
          { key: 'scheduled_at', label: 'Planification', type: 'text', placeholder: '2026-11-28 10:00' },
        ],
        build: (values) => {
          // L'API attend `target_platforms` (tableau), pas `network`.
          const network = text(values, 'network');
          return clean({
            content: text(values, 'content'),
            target_platforms: network ? [network] : undefined,
            scheduled_at: text(values, 'scheduled_at'),
          });
        },
        submit: (payload) =>
          W.createSocialPost(payload as unknown as Parameters<typeof W.createSocialPost>[0]),
        successMessage: 'Publication creee.',
      },
    ],
    rowActions: [
      {
        key: 'publish_post',
        label: 'Publier maintenant',
        loadTargets: async () =>
          (await W.fetchSocialPosts()).map((post) => ({
            id: post.id,
            label: (post.content ?? `Publication ${post.id}`).slice(0, 60),
            meta: [post.network, post.status].filter(Boolean).join(' · '),
          })),
        build: () => ({}),
        submit: (targetId) => W.publishSocialPost(targetId),
        successMessage: 'Publication envoyee.',
      },
    ],
  },
};

/** Le module a-t-il des actions d'ecriture ? */
export function hasModuleWrites(key: ClientModuleKey): boolean {
  const spec = MODULE_WRITES[key];
  if (!spec) return false;
  return Boolean((spec.actions?.length ?? 0) + (spec.rowActions?.length ?? 0));
}
