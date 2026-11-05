import type { ToolboxLocale } from './copy';

/**
 * Libellés localisés des OPTIONS de listes déroulantes (catégories d'IMC,
 * catégories d'unités). Séparés de `copy.ts` pour rester lisibles : ce sont des
 * énumérations techniques, pas de la copie marketing.
 *
 * Les identifiants d'unités (mm, cm, kg, °C…) sont des SYMBOLES universels et
 * ne sont pas traduits.
 */

export const IMC_CATEGORY_LABELS: Record<ToolboxLocale, Record<string, string>> = {
  fr: {
    underweight: 'Insuffisance pondérale',
    normal: 'Corpulence normale',
    overweight: 'Surpoids',
    obese: 'Obésité',
  },
  en: {
    underweight: 'Underweight',
    normal: 'Normal weight',
    overweight: 'Overweight',
    obese: 'Obesity',
  },
  tr: {
    underweight: 'Zayıf',
    normal: 'Normal kilo',
    overweight: 'Fazla kilolu',
    obese: 'Obez',
  },
  ar: {
    underweight: 'نقص في الوزن',
    normal: 'وزن طبيعي',
    overweight: 'زيادة في الوزن',
    obese: 'سمنة',
  },
};

export const UNIT_CATEGORY_LABELS: Record<ToolboxLocale, Record<string, string>> = {
  fr: {
    longueur: 'Longueur',
    masse: 'Masse',
    volume: 'Volume',
    surface: 'Surface',
    vitesse: 'Vitesse',
    temperature: 'Température',
  },
  en: {
    longueur: 'Length',
    masse: 'Mass',
    volume: 'Volume',
    surface: 'Area',
    vitesse: 'Speed',
    temperature: 'Temperature',
  },
  tr: {
    longueur: 'Uzunluk',
    masse: 'Kütle',
    volume: 'Hacim',
    surface: 'Alan',
    vitesse: 'Hız',
    temperature: 'Sıcaklık',
  },
  ar: {
    longueur: 'الطول',
    masse: 'الكتلة',
    volume: 'الحجم',
    surface: 'المساحة',
    vitesse: 'السرعة',
    temperature: 'درجة الحرارة',
  },
};

/** Symboles d'unités (universels, non traduits). */
export const UNIT_LABELS: Record<string, string> = {
  mm: 'mm',
  cm: 'cm',
  m: 'm',
  km: 'km',
  in: 'in',
  ft: 'ft',
  yd: 'yd',
  mi: 'mi',
  mg: 'mg',
  g: 'g',
  kg: 'kg',
  t: 't',
  oz: 'oz',
  lb: 'lb',
  ml: 'ml',
  cl: 'cl',
  l: 'L',
  m3: 'm³',
  gal_us: 'gal US',
  gal_uk: 'gal UK',
  cm2: 'cm²',
  m2: 'm²',
  km2: 'km²',
  ha: 'ha',
  ft2: 'ft²',
  acre: 'acre',
  mps: 'm/s',
  kmh: 'km/h',
  mph: 'mph',
  knot: 'kn',
  c: '°C',
  f: '°F',
  k: 'K',
};
