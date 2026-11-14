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

/** Libellés des formes géométriques (outil `surface-volume`). */
export const SHAPE_LABELS: Record<ToolboxLocale, Record<string, string>> = {
  fr: {
    carre: 'Carré',
    rectangle: 'Rectangle',
    triangle: 'Triangle',
    cercle: 'Cercle',
    cube: 'Cube',
    'pave-droit': 'Pavé droit',
    cylindre: 'Cylindre',
    sphere: 'Sphère',
  },
  en: {
    carre: 'Square',
    rectangle: 'Rectangle',
    triangle: 'Triangle',
    cercle: 'Circle',
    cube: 'Cube',
    'pave-droit': 'Rectangular box',
    cylindre: 'Cylinder',
    sphere: 'Sphere',
  },
  tr: {
    carre: 'Kare',
    rectangle: 'Dikdörtgen',
    triangle: 'Üçgen',
    cercle: 'Daire',
    cube: 'Küp',
    'pave-droit': 'Dikdörtgen prizma',
    cylindre: 'Silindir',
    sphere: 'Küre',
  },
  ar: {
    carre: 'مربع',
    rectangle: 'مستطيل',
    triangle: 'مثلث',
    cercle: 'دائرة',
    cube: 'مكعب',
    'pave-droit': 'متوازي مستطيلات',
    cylindre: 'أسطوانة',
    sphere: 'كرة',
  },
};

/**
 * Libellés des jours de la semaine (outil `calcul-date-echeance`).
 *
 * Indexés par `Date.getUTCDay()` : 0 = dimanche … 6 = samedi. L'ordre suit donc
 * la convention JavaScript, pas la convention française « lundi = 1 ».
 */
export const WEEKDAY_LABELS: Record<ToolboxLocale, Record<number, string>> = {
  fr: {
    0: 'Dimanche',
    1: 'Lundi',
    2: 'Mardi',
    3: 'Mercredi',
    4: 'Jeudi',
    5: 'Vendredi',
    6: 'Samedi',
  },
  en: {
    0: 'Sunday',
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
  },
  tr: {
    0: 'Pazar',
    1: 'Pazartesi',
    2: 'Salı',
    3: 'Çarşamba',
    4: 'Perşembe',
    5: 'Cuma',
    6: 'Cumartesi',
  },
  ar: {
    0: 'الأحد',
    1: 'الاثنين',
    2: 'الثلاثاء',
    3: 'الأربعاء',
    4: 'الخميس',
    5: 'الجمعة',
    6: 'السبت',
  },
};

/** Libellés des modes de transport (outil `cbm-poids-volumetrique`). */
export const FREIGHT_MODE_LABELS: Record<ToolboxLocale, Record<string, string>> = {
  fr: {
    aerien: 'Aérien (167 kg/m³)',
    routier: 'Routier (333 kg/m³)',
    maritime: 'Maritime (1000 kg/m³)',
  },
  en: {
    aerien: 'Air (167 kg/m³)',
    routier: 'Road (333 kg/m³)',
    maritime: 'Sea (1000 kg/m³)',
  },
  tr: {
    aerien: 'Hava (167 kg/m³)',
    routier: 'Kara (333 kg/m³)',
    maritime: 'Deniz (1000 kg/m³)',
  },
  ar: {
    aerien: 'جوي (167 كغ/م³)',
    routier: 'بري (333 كغ/م³)',
    maritime: 'بحري (1000 كغ/م³)',
  },
};
