import { isFiniteNumber, safeDivide } from '../../lib/validation';

/**
 * CBM (mètre cube) et poids volumétrique — logistique et transport.
 *
 * Formules :
 *   - CBM d'un colis   : (L × l × H) en mètres → m³
 *   - CBM total        : CBM unitaire × quantité
 *   - Poids volumétrique = CBM × facteur de conversion (kg/m³)
 *   - Poids taxable    = max(poids réel, poids volumétrique)
 *
 * Facteurs de conversion courants (kg par m³) — valeurs de marché, à vérifier
 * auprès du transporteur :
 *   - aérien   : 167 kg/m³ (soit 6000 cm³/kg)
 *   - routier  : 333 kg/m³ (soit 3000 cm³/kg)
 *   - maritime : 1000 kg/m³ (soit 1 m³ = 1 tonne)
 *
 * Unités : les dimensions sont saisies en CENTIMÈTRES (usage courant en
 * logistique) et converties en mètres ; le poids réel est en kilogrammes.
 *
 * Hypothèses : dimensions et poids strictement positifs. Résultat INDICATIF :
 * le poids taxable réel dépend du contrat transporteur (arrondis, palettes,
 * poids minimum facturable). Ne remplace pas un devis de transport.
 */

export type FreightMode = 'aerien' | 'routier' | 'maritime';

/** Facteurs kg/m³ par mode de transport. */
export const VOLUMETRIC_FACTORS: Record<FreightMode, number> = {
  aerien: 167,
  routier: 333,
  maritime: 1000,
};

export interface CbmInput {
  /** Longueur en centimètres. */
  lengthCm: number;
  /** Largeur en centimètres. */
  widthCm: number;
  /** Hauteur en centimètres. */
  heightCm: number;
  /** Nombre de colis identiques (défaut 1). */
  quantity?: number;
  /** Poids réel total en kilogrammes (défaut 0). */
  actualWeightKg?: number;
  /** Mode de transport (détermine le facteur volumétrique). */
  mode: FreightMode;
}

export interface CbmResult {
  /** CBM d'un colis, en m³. */
  unitCbm: number;
  /** CBM total (unitaire × quantité), en m³. */
  totalCbm: number;
  /** Poids volumétrique total, en kg. */
  volumetricWeightKg: number;
  /** Poids taxable = max(poids réel, poids volumétrique), en kg. */
  chargeableWeightKg: number;
}

/**
 * Calcule CBM, poids volumétrique et poids taxable.
 * Retourne `null` si une dimension est non finie ou ≤ 0, si la quantité est
 * non finie ou < 1, ou si le poids réel est non fini ou < 0.
 */
export function computeCbm(input: CbmInput): CbmResult | null {
  const { lengthCm, widthCm, heightCm, mode } = input;
  const quantity = input.quantity ?? 1;
  const actualWeightKg = input.actualWeightKg ?? 0;

  if (!isFiniteNumber(lengthCm) || lengthCm <= 0) return null;
  if (!isFiniteNumber(widthCm) || widthCm <= 0) return null;
  if (!isFiniteNumber(heightCm) || heightCm <= 0) return null;
  if (!isFiniteNumber(quantity) || quantity < 1) return null;
  if (!isFiniteNumber(actualWeightKg) || actualWeightKg < 0) return null;

  const factor = VOLUMETRIC_FACTORS[mode];
  if (!isFiniteNumber(factor)) return null;

  // cm³ → m³ : diviser par 1 000 000.
  const unitCbm = (lengthCm * widthCm * heightCm) / 1_000_000;
  const totalCbm = unitCbm * quantity;
  const volumetricWeightKg = totalCbm * factor;
  const chargeableWeightKg = Math.max(actualWeightKg, volumetricWeightKg);

  return { unitCbm, totalCbm, volumetricWeightKg, chargeableWeightKg };
}

/**
 * Poids volumétrique d'un volume donné (m³) pour un mode.
 * Retourne `null` si le volume est non fini ou ≤ 0.
 */
export function volumetricWeight(cbm: number, mode: FreightMode): number | null {
  if (!isFiniteNumber(cbm) || cbm <= 0) return null;
  const factor = VOLUMETRIC_FACTORS[mode];
  if (!isFiniteNumber(factor)) return null;
  return cbm * factor;
}

/**
 * Densité d'une expédition (kg/m³) — utile pour savoir si la marchandise est
 * « légère » (taxée au volume) ou « dense » (taxée au poids).
 * Retourne `null` si le volume est nul ou les entrées non finies.
 */
export function density(weightKg: number, cbm: number): number | null {
  if (!isFiniteNumber(weightKg) || weightKg < 0) return null;
  return safeDivide(weightKg, cbm);
}
