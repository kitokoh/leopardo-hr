/**
 * solution-stack.ts — Données de la « Pile Leopardo » (visuel 3D du hero).
 *
 * Objectif produit : montrer d'un coup d'œil que Leopardo est
 *   UN SOCLE  +  DES BRIQUES HORIZONTALES  +  DES VERTICALES MÉTIER,
 * et que chaque verticale se BRANCHE sur les mêmes briques horizontales.
 *
 * ── Provenance des données (aucune invention) ────────────────────────────
 *  • Horizontaux : `front/web/src/lib/client-features.ts` → `CLIENT_MODULES`
 *    filtrés sur `scope !== 'business'` (les modules `business` SONT les
 *    verticales et sont exclus de la couche horizontale).
 *  • Verticales  : `src/lib/client-features.ts` → `BusinessVertical`
 *    ('restaurant' | 'travel' | 'education' | 'fuel').
 *  • Consommation d'une verticale (`consumes`) : dérivée des manifests
 *    serveur, source de vérité de ce qu'une verticale réclame réellement :
 *      api/app/Modules/RestaurantManager/Domain/Manifests/RestaurantManagerManifest.php
 *      api/app/Modules/FuelStation/Domain/Solution/FuelStationManifest.php
 *      api/app/Modules/EduManager/Domain/Solution/EduManagerManifest.php
 *      api/app/Modules/TravelAgency/Domain/Manifests/TravelAgencyManifest.php
 *    Les modules déclarés qui n'ont PAS de brique visible côté client
 *    (`documents`, `notifications`, `fleet`) ne sont pas maquillés en tuile :
 *    ils sont restitués en texte via `extraModules` (voir ci-dessous).
 *
 * ── Convention de copie ──────────────────────────────────────────────────
 * Comme `modules/vitrine/lib/vitrine-locale.ts`, la copie de la vitrine est
 * portée en clair pour les 4 locales (fr/en/tr/ar) plutôt que dans le
 * catalogue i18n partagé : la vitrine a sa propre table de copie, ce qui
 * évite de casser la parité/le sync de `shared/i18n` (check-i18n-diff).
 */

import type { AppLocale } from '@/lib/i18n';

// ─────────────────────────────────────────────────────────────────────────
// Typages
// ─────────────────────────────────────────────────────────────────────────

export type HorizontalKey =
  | 'dashboard'
  | 'employees'
  | 'attendance'
  | 'attendance_geo'
  | 'absences'
  | 'contracts'
  | 'payroll'
  | 'training'
  | 'reports'
  | 'partner'
  | 'billing'
  | 'integrations'
  | 'marketing'
  | 'accounting'
  | 'crm'
  | 'showcase';

export type VerticalKey = 'restaurant' | 'travel' | 'education' | 'fuel';

export interface HorizontalBlock {
  key: HorizontalKey;
  /** Colonne de la grille 4×4 de la couche horizontale. */
  col: number;
  /** Rangée de la grille 4×4. */
  row: number;
}

export interface VerticalSolution {
  key: VerticalKey;
  /**
   * Couleur d'accent de la colonne 3D.
   *
   * DOIT provenir de `docs/REFERENTIEL_PRODUIT/COULEURS.md` : la garde CI
   * `check-web-design-tokens.sh` refuse tout `#rrggbb` hors palette dans
   * `front/web/src`. La teinte claire (halo, chapeau, faisceaux) n'est PAS
   * écrite en dur — elle est DÉRIVÉE de cette couleur au chargement de la
   * scène (voir `SolutionStack3D`), ce qui évite d'inventer des variantes.
   */
  color: string;
  /**
   * Briques horizontales réellement consommées par cette verticale,
   * dérivées de ses `requiredModules`/`optionalModules` (voir en-tête).
   */
  consumes: HorizontalKey[];
  /**
   * Modules déclarés par le manifest qui n'ont pas de brique horizontale
   * visible côté client — restitués en texte, jamais en fausse tuile.
   */
  extraModules: string[];
}

// ─────────────────────────────────────────────────────────────────────────
// Couche horizontale — 16 briques transverses (grille 4×4)
// ─────────────────────────────────────────────────────────────────────────

export const HORIZONTAL_BLOCKS: HorizontalBlock[] = [
  { key: 'dashboard', col: 0, row: 0 },
  { key: 'employees', col: 1, row: 0 },
  { key: 'attendance', col: 2, row: 0 },
  { key: 'attendance_geo', col: 3, row: 0 },
  { key: 'absences', col: 0, row: 1 },
  { key: 'contracts', col: 1, row: 1 },
  { key: 'payroll', col: 2, row: 1 },
  { key: 'training', col: 3, row: 1 },
  { key: 'reports', col: 0, row: 2 },
  { key: 'partner', col: 1, row: 2 },
  { key: 'billing', col: 2, row: 2 },
  { key: 'integrations', col: 3, row: 2 },
  { key: 'marketing', col: 0, row: 3 },
  { key: 'accounting', col: 1, row: 3 },
  { key: 'crm', col: 2, row: 3 },
  { key: 'showcase', col: 3, row: 3 },
];

// ─────────────────────────────────────────────────────────────────────────
// Couche verticale — 4 solutions métier
// ─────────────────────────────────────────────────────────────────────────

export const VERTICALS: VerticalSolution[] = [
  {
    key: 'restaurant',
    color: '#F59E0B', // Finance/ambre — COULEURS.md
    // Manifest : rh, documents, notifications, crm, accounting, marketing
    consumes: ['employees', 'contracts', 'absences', 'crm', 'accounting', 'marketing'],
    extraModules: ['documents', 'notifications'],
  },
  {
    key: 'travel',
    color: '#06B6D4', // cyan de la palette vitrine — COULEURS.md
    // Manifest : rh, documents, notifications, crm, accounting, marketing
    consumes: ['employees', 'contracts', 'absences', 'crm', 'accounting', 'marketing'],
    extraModules: ['documents', 'notifications'],
  },
  {
    key: 'education',
    color: '#7C3AED', // IA/violet — COULEURS.md
    // Manifest : rh, documents, notifications, crm, marketing, accounting,
    //            payroll, attendance
    consumes: [
      'employees',
      'contracts',
      'absences',
      'attendance',
      'attendance_geo',
      'payroll',
      'crm',
      'accounting',
      'marketing',
    ],
    extraModules: ['documents', 'notifications'],
  },
  {
    key: 'fuel',
    color: '#EF4444', // Danger/rouge — COULEURS.md
    // Manifest : rh, attendance, documents, notifications, crm, accounting,
    //            payroll, marketing, fleet
    consumes: [
      'employees',
      'contracts',
      'absences',
      'attendance',
      'attendance_geo',
      'payroll',
      'crm',
      'accounting',
      'marketing',
    ],
    extraModules: ['documents', 'notifications', 'fleet'],
  },
];

// ─────────────────────────────────────────────────────────────────────────
// Copie — 4 locales
// ─────────────────────────────────────────────────────────────────────────

export interface SolutionStackCopy {
  eyebrow: string;
  title: string;
  subtitle: string;
  /** Nom + description de chaque couche, dans l'ordre socle → verticales. */
  layerPlatform: { name: string; desc: string };
  layerHorizontal: { name: string; desc: string };
  layerVertical: { name: string; desc: string };
  /** Invite d'interaction affichée sous le canvas. */
  hint: string;
  /** Préfixe de la liste « + aussi : … » des modules sans tuile. */
  alsoPrefix: string;
  /** Libellés de la couche horizontale (16). */
  horizontals: Record<HorizontalKey, string>;
  /** Libellés des verticales (4). */
  verticals: Record<VerticalKey, string>;
  /** Légende accessible du canvas (aria-label). */
  canvasAlt: string;
}

const COPY: Record<AppLocale, SolutionStackCopy> = {
  fr: {
    eyebrow: 'Architecture de la solution',
    title: 'Une plateforme. Des briques horizontales. Vos verticales.',
    subtitle:
      "Le socle et les outils transverses sont partagés par tous les métiers : chaque verticale vient s'y brancher au lieu de repartir de zéro.",
    layerPlatform: {
      name: 'Socle',
      desc: 'Identité, multi-tenant, rôles et permissions, audit, API ouverte.',
    },
    layerHorizontal: {
      name: 'Horizontale',
      desc: '16 briques transverses, identiques pour tous les clients',
    },
    layerVertical: {
      name: 'Verticales',
      desc: '4 solutions métier, branchées sur les mêmes briques',
    },
    hint: 'Sélectionnez une verticale pour voir les briques horizontales qu’elle consomme.',
    alsoPrefix: 'S’appuie aussi sur :',
    canvasAlt:
      'Représentation 3D de la plateforme Leopardo : un socle commun, une couche horizontale de 16 briques transverses, et 4 verticales métier branchées dessus.',
    horizontals: {
      dashboard: 'Tableau de bord',
      employees: 'Employés',
      attendance: 'Pointages',
      attendance_geo: 'Pointages GPS',
      absences: 'Absences',
      contracts: 'Contrats',
      payroll: 'Paie',
      training: 'Formations',
      reports: 'Rapports',
      partner: 'Programme Partenaire',
      billing: 'Facturation',
      integrations: 'Intégrations',
      marketing: 'Marketing',
      accounting: 'Comptabilité',
      crm: 'CRM Client',
      showcase: 'Site vitrine',
    },
    verticals: {
      restaurant: 'Restaurant',
      travel: 'Agence de voyage',
      education: 'École & campus',
      fuel: 'Station-service',
    },
  },

  en: {
    eyebrow: 'Solution architecture',
    title: 'One platform. Horizontal building blocks. Your verticals.',
    subtitle:
      'The foundation and the cross-cutting tools are shared across every industry: each vertical plugs into them instead of starting from scratch.',
    layerPlatform: {
      name: 'Foundation',
      desc: 'Identity, multi-tenancy, roles and permissions, audit, open API.',
    },
    layerHorizontal: {
      name: 'Horizontal',
      desc: '16 cross-cutting building blocks, identical for every customer',
    },
    layerVertical: {
      name: 'Verticals',
      desc: '4 industry solutions, plugged into the same building blocks',
    },
    hint: 'Select a vertical to see which horizontal building blocks it consumes.',
    alsoPrefix: 'Also relies on:',
    canvasAlt:
      '3D representation of the Leopardo platform: a shared foundation, an horizontal layer of 16 cross-cutting building blocks, and 4 industry verticals plugged into it.',
    horizontals: {
      dashboard: 'Dashboard',
      employees: 'Employees',
      attendance: 'Time tracking',
      attendance_geo: 'GPS sessions',
      absences: 'Leave',
      contracts: 'Contracts',
      payroll: 'Payroll',
      training: 'Training',
      reports: 'Reports',
      partner: 'Partner program',
      billing: 'Billing',
      integrations: 'Integrations',
      marketing: 'Marketing',
      accounting: 'Accounting',
      crm: 'Customer CRM',
      showcase: 'Website builder',
    },
    verticals: {
      restaurant: 'Restaurant',
      travel: 'Travel agency',
      education: 'School & campus',
      fuel: 'Fuel station',
    },
  },

  tr: {
    eyebrow: 'Çözüm mimarisi',
    title: 'Tek platform. Yatay yapı taşları. Dikey çözümleriniz.',
    subtitle:
      'Temel katman ve yatay araçlar tüm sektörlerle paylaşılır: her dikey çözüm sıfırdan başlamak yerine bunlara bağlanır.',
    layerPlatform: {
      name: 'Temel',
      desc: 'Kimlik, çok kiracılı yapı, roller ve yetkiler, denetim, açık API.',
    },
    layerHorizontal: {
      name: 'Yatay',
      desc: '16 yatay yapı taşı, tüm müşteriler için aynı',
    },
    layerVertical: {
      name: 'Dikey',
      desc: 'Aynı yapı taşlarına bağlanan 4 sektörel çözüm',
    },
    hint: 'Bir dikey çözüm seçin ve hangi yatay yapı taşlarını kullandığını görün.',
    alsoPrefix: 'Ayrıca şunları kullanır:',
    canvasAlt:
      'Leopardo platformunun 3B gösterimi: ortak bir temel, 16 yatay yapı taşından oluşan bir katman ve bunlara bağlı 4 sektörel dikey çözüm.',
    horizontals: {
      dashboard: 'Panel',
      employees: 'Çalışanlar',
      attendance: 'Giriş-çıkış',
      attendance_geo: 'GPS oturumları',
      absences: 'İzinler',
      contracts: 'Sözleşmeler',
      payroll: 'Bordro',
      training: 'Eğitimler',
      reports: 'Raporlar',
      partner: 'İş ortağı programı',
      billing: 'Faturalama',
      integrations: 'Entegrasyonlar',
      marketing: 'Pazarlama',
      accounting: 'Muhasebe',
      crm: 'Müşteri CRM',
      showcase: 'Web sitesi',
    },
    verticals: {
      restaurant: 'Restoran',
      travel: 'Seyahat acentesi',
      education: 'Okul ve kampüs',
      fuel: 'Akaryakıt istasyonu',
    },
  },

  ar: {
    eyebrow: 'بنية الحل',
    title: 'منصة واحدة. مكوّنات أفقية. حلولك القطاعية.',
    subtitle:
      'الأساس والأدوات الأفقية مشتركة بين جميع القطاعات: كل حل قطاعي يتصل بها بدل أن يبدأ من الصفر.',
    layerPlatform: {
      name: 'الأساس',
      desc: 'الهوية، تعدد المستأجرين، الأدوار والصلاحيات، التدقيق، واجهة برمجية مفتوحة.',
    },
    layerHorizontal: {
      name: 'أفقية',
      desc: '16 مكوّناً أفقيًا مشتركًا بين جميع العملاء',
    },
    layerVertical: {
      name: 'قطاعية',
      desc: '4 حلول قطاعية متصلة بالمكوّنات نفسها',
    },
    hint: 'اختر حلًا قطاعيًا لعرض المكوّنات الأفقية التي يستخدمها.',
    alsoPrefix: 'يعتمد أيضًا على:',
    canvasAlt:
      'تمثيل ثلاثي الأبعاد لمنصة ليوباردو: أساس مشترك، وطبقة أفقية من 16 مكوّنًا، وأربعة حلول قطاعية متصلة بها.',
    horizontals: {
      dashboard: 'لوحة التحكم',
      employees: 'الموظفون',
      attendance: 'تسجيل الحضور',
      attendance_geo: 'جلسات GPS',
      absences: 'الإجازات',
      contracts: 'العقود',
      payroll: 'الرواتب',
      training: 'التدريب',
      reports: 'التقارير',
      partner: 'برنامج الشركاء',
      billing: 'الفوترة',
      integrations: 'التكاملات',
      marketing: 'التسويق',
      accounting: 'المحاسبة',
      crm: 'إدارة العملاء',
      showcase: 'موقع إلكتروني',
    },
    verticals: {
      restaurant: 'مطعم',
      travel: 'وكالة سفر',
      education: 'مدارس وحرم جامعي',
      fuel: 'محطة وقود',
    },
  },
};

export function getSolutionStackCopy(locale: AppLocale): SolutionStackCopy {
  return COPY[locale] ?? COPY.fr;
}

/**
 * Teinte claire d'une verticale — chapeau de colonne, faisceaux, libellé,
 * et repli CSS. **Dérivée** de la couleur de base, jamais écrite en dur :
 * la garde CI `check-web-design-tokens.sh` refuse les hex hors palette dans
 * `front/web/src`, et une variante de halo n'a pas à devenir un token produit.
 *
 * @param amount proportion de blanc mélangée (0 = couleur d'origine, 1 = blanc)
 */
export function verticalGlow(color: string, amount = 0.42): string {
  const raw = color.replace('#', '').trim();
  const full =
    raw.length === 3
      ? raw
          .split('')
          .map((char) => char + char)
          .join('')
      : raw;

  if (!/^[0-9a-fA-F]{6}$/.test(full)) return color;

  const value = Number.parseInt(full, 16);
  const channels = [(value >> 16) & 255, (value >> 8) & 255, value & 255];
  const lighten = (channel: number): number => Math.round(channel + (255 - channel) * amount);

  return `#${channels.map((c) => lighten(c).toString(16).padStart(2, '0')).join('')}`;
}

/** Libellés traduits des modules sans brique visible (documents, notifications, fleet). */
export const EXTRA_MODULE_LABELS: Record<AppLocale, Record<string, string>> = {
  fr: { documents: 'Documents', notifications: 'Notifications', fleet: 'Flotte' },
  en: { documents: 'Documents', notifications: 'Notifications', fleet: 'Fleet' },
  tr: { documents: 'Belgeler', notifications: 'Bildirimler', fleet: 'Filo' },
  ar: { documents: 'المستندات', notifications: 'الإشعارات', fleet: 'الأسطول' },
};
