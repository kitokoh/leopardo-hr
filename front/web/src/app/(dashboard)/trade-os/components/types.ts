/**
 * Types partagés de l'UI contreparties Trade — #9780.
 *
 * Ces types reflètent EXACTEMENT `TradeCounterpartyResource` côté API : aucun
 * champ n'est ajouté ici « au cas où ». Un champ absent de la ressource ne doit
 * pas exister dans l'interface, sinon l'écran afficherait une donnée que le
 * serveur n'a jamais renvoyée.
 */

/** Rôles métier du registre fermé `TradeCounterpartyRole`. */
export const COUNTERPARTY_ROLES = [
  'buyer',
  'supplier',
  'manufacturer',
  'wholesaler',
  'carrier',
  'forwarder',
  'customs_broker',
  'inspector',
] as const;

export type CounterpartyRole = (typeof COUNTERPARTY_ROLES)[number];

/**
 * Statut de vérification.
 *
 * `verified` signifie « vérifié par un membre de l'équipe » — jamais « vérifié
 * automatiquement » : aucun service de vérification externe n'est souscrit en
 * v1. L'interface doit le dire ainsi.
 */
export type VerificationStatus = 'none' | 'pending' | 'verified' | 'rejected';

export type TradeCounterparty = {
  id: number | string;
  name: string;
  country?: string | null;
  city?: string | null;
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  roles: string[];
  verification_status: VerificationStatus;
  verified_by_employee_id?: number | null;
  verified_at?: string | null;
  verification_note?: string | null;
  capabilities?: unknown[];
  notes?: string | null;
  crm_account_id?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
};

/**
 * Brouillon du formulaire — tout est texte.
 *
 * La conversion (pays en majuscules, `crm_account_id` en entier) se fait à
 * l'envoi, jamais pendant la frappe : un champ numérique qui se vide sous les
 * doigts de l'utilisateur est un défaut d'ergonomie, pas une validation.
 */
export type CounterpartyDraft = {
  name: string;
  country: string;
  city: string;
  email: string;
  phone: string;
  website: string;
  roles: CounterpartyRole[];
  notes: string;
  crmAccountId: string;
};

export const EMPTY_COUNTERPARTY_DRAFT: CounterpartyDraft = {
  name: '',
  country: '',
  city: '',
  email: '',
  phone: '',
  website: '',
  roles: [],
  notes: '',
  crmAccountId: '',
};
