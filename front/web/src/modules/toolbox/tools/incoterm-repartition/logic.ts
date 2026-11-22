/**
 * Répartition des frais selon l'Incoterm — qui paie quoi.
 *
 * Table DÉTERMINISTE des Incoterms 2020 (ICC). Aucune interprétation, aucune
 * IA : chaque case dit si le VENDEUR (exportateur) prend le poste en charge.
 * Si le vendeur ne le prend pas, c'est l'ACHETEUR (importateur).
 *
 * Postes suivis :
 *   - exportClearance : formalités et droits d'exportation
 *   - mainCarriage    : transport principal (international)
 *   - insurance       : assurance transport
 *   - importClearance : formalités et droits d'importation
 *   - importDuty      : droits et taxes à l'import
 *
 * ⚠️ Cette table décrit la RÉPARTITION CONTRACTUELLE standard. Un contrat
 * particulier peut déroger à tout : l'outil ne remplace pas la lecture du
 * contrat de vente.
 */

export type Incoterm = 'EXW' | 'FCA' | 'FOB' | 'CFR' | 'CIF' | 'DAP' | 'DDP';

export type CostItem =
  | 'exportClearance'
  | 'mainCarriage'
  | 'insurance'
  | 'importClearance'
  | 'importDuty';

export type CostBearer = 'seller' | 'buyer';

/** Postes suivis, dans l'ordre d'affichage. */
export const COST_ITEMS: CostItem[] = [
  'exportClearance',
  'mainCarriage',
  'insurance',
  'importClearance',
  'importDuty',
];

/** Incoterms supportés, dans l'ordre croissant de prise en charge vendeur. */
export const INCOTERMS: Incoterm[] = ['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'DAP', 'DDP'];

/**
 * Qui prend en charge chaque poste, par Incoterm.
 *
 * Lecture : `true` = VENDEUR ; `false` = ACHETEUR.
 */
const SELLER_BEARS: Record<Incoterm, Record<CostItem, boolean>> = {
  // EXW — le vendeur met la marchandise à disposition dans ses locaux.
  EXW: {
    exportClearance: false,
    mainCarriage: false,
    insurance: false,
    importClearance: false,
    importDuty: false,
  },
  // FCA — le vendeur dédouane à l'export et livre au transporteur désigné.
  FCA: {
    exportClearance: true,
    mainCarriage: false,
    insurance: false,
    importClearance: false,
    importDuty: false,
  },
  // FOB — comme FCA, mais la livraison se fait à bord du navire.
  FOB: {
    exportClearance: true,
    mainCarriage: false,
    insurance: false,
    importClearance: false,
    importDuty: false,
  },
  // CFR — le vendeur paie le transport principal, pas l'assurance.
  CFR: {
    exportClearance: true,
    mainCarriage: true,
    insurance: false,
    importClearance: false,
    importDuty: false,
  },
  // CIF — CFR + assurance jusqu'au port de destination.
  CIF: {
    exportClearance: true,
    mainCarriage: true,
    insurance: true,
    importClearance: false,
    importDuty: false,
  },
  // DAP — livré au lieu convenu, import non dédouané.
  DAP: {
    exportClearance: true,
    mainCarriage: true,
    insurance: true,
    importClearance: false,
    importDuty: false,
  },
  // DDP — tout à la charge du vendeur, y compris droits et taxes d'import.
  DDP: {
    exportClearance: true,
    mainCarriage: true,
    insurance: true,
    importClearance: true,
    importDuty: true,
  },
};

export interface IncotermSplitResult {
  incoterm: Incoterm;
  /** Répartition poste par poste. */
  items: Array<{ item: CostItem; bearer: CostBearer }>;
  /** Nombre de postes à la charge du vendeur. */
  sellerItems: number;
  /** Nombre de postes à la charge de l'acheteur. */
  buyerItems: number;
}

/**
 * Répartit les postes de coût selon l'Incoterm.
 * Retourne `null` si l'Incoterm n'est pas dans la table (fail-closed : jamais
 * de répartition inventée pour un code inconnu).
 */
export function splitByIncoterm(incoterm: string): IncotermSplitResult | null {
  const normalized = incoterm.trim().toUpperCase();

  if (!isIncoterm(normalized)) return null;

  const table = SELLER_BEARS[normalized];

  const items = COST_ITEMS.map((item) => ({
    item,
    bearer: (table[item] ? 'seller' : 'buyer') as CostBearer,
  }));

  return {
    incoterm: normalized,
    items,
    sellerItems: items.filter((entry) => entry.bearer === 'seller').length,
    buyerItems: items.filter((entry) => entry.bearer === 'buyer').length,
  };
}

/** Vrai si la chaîne est un Incoterm supporté (fail-closed sur l'inconnu). */
export function isIncoterm(value: string): value is Incoterm {
  return (INCOTERMS as string[]).includes(value);
}
