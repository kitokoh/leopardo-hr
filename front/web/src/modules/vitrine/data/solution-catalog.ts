/**
 * Catalogue des solutions présentées par la constellation du hero.
 *
 * Une seule source : les solutions qui ont déjà une page « pack » réutilisent
 * `PACK_PAGES` / `packDisplayName` (aucune donnée métier n'est dupliquée ici).
 * Les autres environnements portent une copie courte, qui vit sous `data/`
 * (chemin exempté par la garde PA2-I18N-014 — même mécanique que
 * `pack-pages.ts`).
 *
 * Ordre du tableau = ordre de création des modules dans la constellation 3D :
 * anneau INTÉRIEUR (5) = les solutions métier offertes en pack ; anneau
 * EXTÉRIEUR (5) = les autres environnements métier et le socle partagé.
 * Voir `HeroBusinessOS3D` (`MODULES_PER_RING = [5, 5]`).
 */

import type { AppLocale } from '@/lib/i18n';
import { PACK_PAGES, packDisplayName, type PackVerticalSlug } from './pack-pages';

export type SolutionId =
  | 'restaurant'
  | 'travel'
  | 'fuel'
  | 'education'
  | 'mobility'
  | 'trade'
  | 'community'
  | 'hr'
  | 'accounting'
  | 'documents';

export type SolutionCopy = {
  /** Nom court affiché sur l'étoile et en titre de fiche. */
  name: string;
  /** Une ligne, sous le titre. */
  tagline: string;
  /** Paragraphe de la fiche. */
  description: string;
  /** Profils utilisateurs concernés. */
  users: string[];
  /** Principales fonctionnalités réellement disponibles. */
  features: string[];
  /** Libellé du bouton principal de la fiche. */
  cta: string;
};

export type SolutionEntry = {
  id: SolutionId;
  /** Destination du bouton principal de la fiche. */
  href: string;
  /**
   * Nom affiché par l'étoile et en titre de la fiche : le nommage « … OS »
   * qui donne la lecture « une plateforme, des environnements métier ».
   * Libellé de PRÉSENTATION (les données métier, elles, viennent du pack).
   */
  osLabel: Record<AppLocale, string>;
  /** Renseigné quand une page pack existe : la fiche réutilise PACK_PAGES. */
  packSlug?: PackVerticalSlug;
  /** Copie locale — uniquement pour les solutions sans page pack. */
  copy?: Record<AppLocale, SolutionCopy>;
};

export const SOLUTIONS: readonly SolutionEntry[] = [
  // ── Anneau intérieur : les environnements métier ouverts en pack ──────────
  {
    id: 'restaurant',
    href: '/restaurateur',
    osLabel: {
      fr: 'Restaurant OS',
      en: 'Restaurant OS',
      tr: 'Restoran OS',
      ar: 'نظام المطاعم',
    },
    copy: {
      fr: {
        name: 'Restaurant OS',
        tagline: 'Salle, cuisine et paie dans un seul espace.',
        description:
          'L’environnement des restaurants : les équipes pointent en salle comme en cuisine, le planning suit les rotations du service et les heures remontent directement dans la paie. Le pack de démarrage est offert.',
        users: ['Gérant', 'Responsable de salle', 'Cuisine', 'Équipe terrain'],
        features: [
          'Pointage en salle et en cuisine (badge, QR, GPS mobile)',
          'Planning, rotations et remplacements de service',
          'Heures et majorations reprises dans la paie, sans ressaisie',
          'Contrats et documents des employés au même endroit',
          'Apps mobile Employé et Manager',
        ],
        cta: 'Composer le pack Restaurant',
      },
      en: {
        name: 'Restaurant OS',
        tagline: 'Floor, kitchen and payroll in one place.',
        description:
          'The restaurant environment: crews clock in on the floor and in the kitchen, scheduling follows service rotations and hours flow straight into payroll. The starter pack is free.',
        users: ['Owner', 'Floor manager', 'Kitchen', 'Field crew'],
        features: [
          'Clock-in on the floor and in the kitchen (badge, QR, mobile GPS)',
          'Scheduling, rotations and shift replacements',
          'Hours and premiums carried into payroll without re-entry',
          'Employee contracts and documents in one place',
          'Employee and Manager mobile apps',
        ],
        cta: 'Build the Restaurant pack',
      },
      tr: {
        name: 'Restoran OS',
        tagline: 'Salon, mutfak ve bordro tek yerde.',
        description:
          'Restoranlar için ortam: ekipler hem salonda hem mutfakta giriş yapar, planlama servis vardiyalarını izler ve saatler doğrudan bordroya akar. Başlangıç paketi ücretsizdir.',
        users: ['İşletme sahibi', 'Salon sorumlusu', 'Mutfak', 'Saha ekibi'],
        features: [
          'Salonda ve mutfakta yoklama (kart, QR, mobil GPS)',
          'Planlama, vardiya döngüleri ve yer değiştirme',
          'Saatler ve ek ödemeler yeniden giriş olmadan bordroda',
          'Çalışan sözleşmeleri ve belgeleri tek yerde',
          'Employee ve Manager mobil uygulamaları',
        ],
        cta: 'Restoran paketini oluştur',
      },
      ar: {
        name: 'نظام المطاعم',
        tagline: 'الصالة والمطبخ والرواتب في مكان واحد.',
        description:
          'بيئة المطاعم: تسجّل الفرق حضورها في الصالة كما في المطبخ، وتتبع الجدولة دورات الخدمة، وتنتقل الساعات مباشرة إلى الرواتب. حزمة البداية مجانية.',
        users: ['المدير', 'مسؤول الصالة', 'المطبخ', 'الفريق الميداني'],
        features: [
          'تسجيل الحضور في الصالة والمطبخ (بطاقة، QR، GPS الجوال)',
          'الجدولة والدورات واستبدال الورديات',
          'الساعات والبدلات تنتقل إلى الرواتب دون إعادة إدخال',
          'عقود الموظفين ومستنداتهم في مكان واحد',
          'تطبيقا Employee وManager للجوال',
        ],
        cta: 'أنشئ حزمة المطعم',
      },
    },
  },
  {
    id: 'travel',
    href: '/packs/agence-de-voyage',
    packSlug: 'agence-de-voyage',
    osLabel: { fr: 'Travel OS', en: 'Travel OS', tr: 'Seyahat OS', ar: 'نظام السفر' },
  },
  {
    id: 'fuel',
    href: '/packs/station-service',
    packSlug: 'station-service',
    osLabel: { fr: 'Fuel Station OS', en: 'Fuel Station OS', tr: 'Akaryakıt OS', ar: 'نظام محطات الوقود' },
  },
  {
    id: 'education',
    href: '/packs/ecole',
    packSlug: 'ecole',
    osLabel: { fr: 'Education OS', en: 'Education OS', tr: 'Eğitim OS', ar: 'نظام التعليم' },
  },
  {
    id: 'mobility',
    href: '/packs/vtc',
    packSlug: 'vtc',
    osLabel: { fr: 'Mobility OS', en: 'Mobility OS', tr: 'Mobilite OS', ar: 'نظام التنقّل' },
  },

  // ── Anneau extérieur : autres environnements et socle partagé ─────────────
  {
    id: 'trade',
    href: '/trade',
    osLabel: {
      fr: 'International Trade OS',
      en: 'International Trade OS',
      tr: 'Uluslararası Ticaret OS',
      ar: 'نظام التجارة الدولية',
    },
    copy: {
      fr: {
        name: 'International Trade OS',
        tagline: 'Import, export et marché local sur la même plateforme.',
        description:
          'Trade est le point d’entrée du Business OS : vous déclarez votre modèle d’activité (international ou domestique), votre espace est créé et configuré selon votre métier, puis vos modules et votre équipe sont activés — sans aucune étape technique.',
        users: ['Direction', 'Achats / import-export', 'Logistique', 'Comptabilité'],
        features: [
          'Parcours guidé : activité, entreprise, modules, équipe',
          'International (import, export, dédouanement) ou marché local',
          'Modules activés selon le métier déclaré',
          'Rejoindre un espace existant sans créer de doublon',
          'Reprise du parcours là où vous l’avez laissé',
        ],
        cta: 'Configurer mon espace',
      },
      en: {
        name: 'International Trade OS',
        tagline: 'Import, export and domestic trade on one platform.',
        description:
          'Trade is the Business OS entry point: declare your business model (international or domestic), your workspace is created and configured for your trade, then your modules and team are activated — with no technical step.',
        users: ['Leadership', 'Purchasing / trade', 'Logistics', 'Accounting'],
        features: [
          'Guided path: activity, company, modules, team',
          'International (import, export, customs) or domestic market',
          'Modules activated for the trade you declared',
          'Join an existing workspace without creating a duplicate',
          'Resume the path exactly where you left it',
        ],
        cta: 'Configure my workspace',
      },
      tr: {
        name: 'Uluslararası Ticaret OS',
        tagline: 'İthalat, ihracat ve yerel pazar tek platformda.',
        description:
          'Trade, Business OS giriş noktasıdır: iş modelinizi (uluslararası veya yerel) belirtirsiniz, çalışma alanınız mesleğinize göre kurulur ve modülleriniz ile ekibiniz etkinleşir — hiçbir teknik adım olmadan.',
        users: ['Yönetim', 'Satın alma / dış ticaret', 'Lojistik', 'Muhasebe'],
        features: [
          'Yönlendirilmiş akış: faaliyet, şirket, modüller, ekip',
          'Uluslararası (ithalat, ihracat, gümrük) veya yerel pazar',
          'Belirttiğiniz mesleğe göre etkinleşen modüller',
          'Mevcut çalışma alanına kopya oluşturmadan katılma',
          'Akışa bıraktığınız yerden devam etme',
        ],
        cta: 'Çalışma alanımı kur',
      },
      ar: {
        name: 'نظام التجارة الدولية',
        tagline: 'الاستيراد والتصدير والسوق المحلي على منصة واحدة.',
        description:
          'Trade هي نقطة الدخول إلى Business OS: تحدّد نموذج نشاطك (دولي أو محلي)، ويُنشأ مساحتك وتُهيّأ وفق مهنتك، ثم تُفعَّل وحداتك وفريقك — دون أي خطوة تقنية.',
        users: ['الإدارة', 'المشتريات / التجارة', 'اللوجستيك', 'المحاسبة'],
        features: [
          'مسار موجَّه: النشاط، الشركة، الوحدات، الفريق',
          'دولي (استيراد، تصدير، تخليص جمركي) أو السوق المحلي',
          'وحدات تُفعَّل حسب المهنة المُعلنة',
          'الانضمام إلى مساحة قائمة دون إنشاء نسخة مكررة',
          'استئناف المسار من حيث توقفت',
        ],
        cta: 'هيّئ مساحتي',
      },
    },
  },
  {
    id: 'community',
    href: '/packs/cagnottes',
    packSlug: 'cagnottes',
    osLabel: { fr: 'Community OS', en: 'Community OS', tr: 'Topluluk OS', ar: 'نظام المجتمع' },
  },
  {
    id: 'hr',
    href: '/employes',
    osLabel: {
      fr: 'HR & Payroll OS',
      en: 'HR & Payroll OS',
      tr: 'İK ve Bordro OS',
      ar: 'نظام الموارد البشرية والرواتب',
    },
    copy: {
      fr: {
        name: 'HR & Payroll OS',
        tagline: 'Le socle partagé : équipes, temps de travail et paie.',
        description:
          'Le socle de la plateforme, commun à tous les environnements métier : pointage, absences, contrats, documents et variables de paie. Les environnements métier s’y branchent, ils ne le remplacent pas.',
        users: ['RH', 'Manager', 'Direction', 'Employé'],
        features: [
          'Pointage biométrique ZKTeco, QR, borne ou GPS mobile',
          'Congés, absences et validations manager',
          'Variables de paie préparées depuis les heures réelles',
          'Contrats et dossiers employés centralisés',
          'Self-service employé sur mobile',
        ],
        cta: 'Voir la gestion des employés',
      },
      en: {
        name: 'HR & Payroll OS',
        tagline: 'The shared foundation: teams, time and payroll.',
        description:
          'The platform foundation, shared by every business environment: clock-in, leave, contracts, documents and payroll variables. Business environments plug into it — they never replace it.',
        users: ['HR', 'Manager', 'Leadership', 'Employee'],
        features: [
          'ZKTeco biometric clock-in, QR, kiosk or mobile GPS',
          'Leave, absence and manager approvals',
          'Payroll variables prepared from real hours',
          'Centralised employee contracts and records',
          'Employee self-service on mobile',
        ],
        cta: 'See employee management',
      },
      tr: {
        name: 'İK ve Bordro OS',
        tagline: 'Ortak temel: ekipler, zaman ve bordro.',
        description:
          'Her sektör ortamının paylaştığı platform temeli: yoklama, izinler, sözleşmeler, belgeler ve bordro değişkenleri. Sektör ortamları bu temele bağlanır, onun yerini almaz.',
        users: ['İK', 'Yönetici', 'Yönetim', 'Çalışan'],
        features: [
          'ZKTeco biyometrik yoklama, QR, kiosk veya mobil GPS',
          'İzin, devamsızlık ve yönetici onayları',
          'Gerçek saatlerden hazırlanan bordro değişkenleri',
          'Merkezî çalışan sözleşmeleri ve dosyaları',
          'Mobilde çalışan öz-hizmeti',
        ],
        cta: 'Çalışan yönetimini gör',
      },
      ar: {
        name: 'نظام الموارد البشرية والرواتب',
        tagline: 'الأساس المشترك: الفرق والوقت والرواتب.',
        description:
          'أساس المنصة، المشترك بين كل البيئات المهنية: الحضور والإجازات والعقود والمستندات ومتغيّرات الرواتب. البيئات المهنية تتصل به ولا تحلّ محلّه.',
        users: ['الموارد البشرية', 'المدير', 'الإدارة', 'الموظف'],
        features: [
          'حضور بيومتري ZKTeco أو QR أو جهاز حضور أو GPS الجوال',
          'الإجازات والغياب وموافقات المدير',
          'متغيّرات الرواتب مبنية على الساعات الفعلية',
          'عقود وملفات الموظفين في مكان واحد',
          'الخدمة الذاتية للموظف على الجوال',
        ],
        cta: 'اطّلع على إدارة الموظفين',
      },
    },
  },
  {
    id: 'accounting',
    href: '/comptabilite',
    osLabel: {
      fr: 'Accounting OS',
      en: 'Accounting OS',
      tr: 'Muhasebe OS',
      ar: 'نظام المحاسبة',
    },
    copy: {
      fr: {
        name: 'Accounting OS',
        tagline: 'La comptabilité reliée aux opérations.',
        description:
          'Votre comptabilité dans la même plateforme que les opérations : les pièces viennent du terrain, les écritures restent rattachées à leur dossier et la direction pilote sur des chiffres à jour.',
        users: ['Comptable', 'Responsable administratif et financier', 'Direction'],
        features: [
          'Journal et écritures rattachées aux dossiers',
          'Pièces justificatives centralisées',
          'Rapprochements et suivi des opérations',
          'Exports pour votre comptable',
          'Tableaux de bord de pilotage',
        ],
        cta: 'Voir la comptabilité',
      },
      en: {
        name: 'Accounting OS',
        tagline: 'Accounting wired to operations.',
        description:
          'Accounting in the same platform as operations: entries come from the field, ledger lines stay attached to their file and leadership steers on up-to-date figures.',
        users: ['Accountant', 'Finance and admin lead', 'Leadership'],
        features: [
          'Ledger and entries attached to their files',
          'Centralised supporting documents',
          'Reconciliations and operations tracking',
          'Exports for your accountant',
          'Management dashboards',
        ],
        cta: 'See accounting',
      },
      tr: {
        name: 'Muhasebe OS',
        tagline: 'Operasyonlara bağlı muhasebe.',
        description:
          'Muhasebe, operasyonlarla aynı platformda: kayıtlar sahadan gelir, fişler dosyalarına bağlı kalır ve yönetim güncel rakamlarla yön verir.',
        users: ['Muhasebeci', 'Finans ve idari sorumlu', 'Yönetim'],
        features: [
          'Dosyalara bağlı yevmiye ve kayıtlar',
          'Merkezî destekleyici belgeler',
          'Mutabakat ve operasyon takibi',
          'Muhasebeciniz için dışa aktarımlar',
          'Yönetim panelleri',
        ],
        cta: 'Muhasebeyi gör',
      },
      ar: {
        name: 'نظام المحاسبة',
        tagline: 'محاسبة مرتبطة بالعمليات.',
        description:
          'محاسبتك على المنصة نفسها مع العمليات: القيود تأتي من الميدان، وتبقى القيود مرتبطة بملفاتها، وتقود الإدارة على أرقام محدَّثة.',
        users: ['المحاسب', 'المسؤول المالي والإداري', 'الإدارة'],
        features: [
          'دفتر اليومية والقيود المرتبطة بالملفات',
          'المستندات الثبوتية في مكان واحد',
          'التسويات ومتابعة العمليات',
          'تصدير لمحاسبك',
          'لوحات قيادة للإدارة',
        ],
        cta: 'اطّلع على المحاسبة',
      },
    },
  },
  {
    id: 'documents',
    href: '/documents',
    osLabel: {
      fr: 'Documents OS',
      en: 'Documents OS',
      tr: 'Belge OS',
      ar: 'نظام المستندات',
    },
    copy: {
      fr: {
        name: 'Documents OS',
        tagline: 'Contrats, justificatifs et dossiers employés.',
        description:
          'Les documents de l’entreprise cessent de se perdre : contrats, attestations et justificatifs sont rattachés à chaque employé et accessibles à qui a le droit, au bureau comme sur le terrain.',
        users: ['RH', 'Manager', 'Employé', 'Direction'],
        features: [
          'Coffre de documents par employé',
          'Attestations et courriers produits depuis les données',
          'Dépôt de justificatifs depuis le mobile',
          'Accès par rôle',
          'Suivi des échéances',
        ],
        cta: 'Voir la gestion des documents',
      },
      en: {
        name: 'Documents OS',
        tagline: 'Contracts, proofs and employee files.',
        description:
          'Company documents stop getting lost: contracts, certificates and supporting proofs are attached to each employee and readable by whoever is entitled — in the office or in the field.',
        users: ['HR', 'Manager', 'Employee', 'Leadership'],
        features: [
          'Document vault per employee',
          'Certificates and letters generated from the data',
          'Upload supporting proofs from mobile',
          'Role-based access',
          'Deadline tracking',
        ],
        cta: 'See document management',
      },
      tr: {
        name: 'Belge OS',
        tagline: 'Sözleşmeler, belgeler ve çalışan dosyaları.',
        description:
          'Şirket belgeleri kaybolmayı bırakır: sözleşmeler, yazılar ve destekleyici belgeler her çalışana bağlanır ve yetkisi olan herkesçe ofiste ya da sahada okunabilir.',
        users: ['İK', 'Yönetici', 'Çalışan', 'Yönetim'],
        features: [
          'Her çalışan için belge kasası',
          'Verilerden üretilen yazılar ve belgeler',
          'Mobilden destekleyici belge yükleme',
          'Rola göre erişim',
          'Vade takibi',
        ],
        cta: 'Belge yönetimini gör',
      },
      ar: {
        name: 'نظام المستندات',
        tagline: 'العقود والمبرّرات وملفات الموظفين.',
        description:
          'توقّف مستندات الشركة عن الضياع: العقود والشهادات والمبرّرات مرتبطة بكل موظف ومتاحة لمن يحق له، في المكتب كما في الميدان.',
        users: ['الموارد البشرية', 'المدير', 'الموظف', 'الإدارة'],
        features: [
          'خزينة مستندات لكل موظف',
          'شهادات ورسائل مُولَّدة من البيانات',
          'رفع المبرّرات من الجوال',
          'وصول حسب الدور',
          'متابعة المواعيد النهائية',
        ],
        cta: 'اطّلع على إدارة المستندات',
      },
    },
  },
];

export type SolutionDetail = {
  id: SolutionId;
  href: string;
  name: string;
  tagline: string;
  description: string;
  users: string[];
  features: string[];
  cta: string;
};

const FALLBACK_LOCALE: AppLocale = 'fr';

function localized<T>(record: Record<AppLocale, T>, locale: AppLocale): T {
  return record[locale] ?? record[FALLBACK_LOCALE];
}

/** Nom localisé d'une solution (étoile de la constellation, titre de fiche). */
export function solutionLabel(id: SolutionId, locale: AppLocale): string {
  const entry = SOLUTIONS.find((solution) => solution.id === id);
  if (!entry) return '';
  return localized(entry.osLabel, locale);
}

/**
 * Nom « catalogue » de la solution (celui de la page pack), quand il existe.
 * Conservé pour ne pas perdre le libellé métier enregistré ; la fiche affiche,
 * elle, le nom OS de la constellation pour rester cohérente avec l'étoile.
 */
export function solutionCatalogName(id: SolutionId, locale: AppLocale): string | null {
  const entry = SOLUTIONS.find((solution) => solution.id === id);
  if (!entry?.packSlug) return null;
  return packDisplayName(entry.packSlug, locale);
}

/**
 * Détail d'une solution, normalisé pour la fiche interactive.
 *
 * Les solutions qui ont une page pack lisent `PACK_PAGES` (source unique du
 * catalogue métier) ; les autres lisent leur copie locale. Retourne `null`
 * pour un identifiant inconnu (fail-closed, comme `pack-signup`).
 */
export function getSolutionDetail(id: SolutionId, locale: AppLocale): SolutionDetail | null {
  const entry = SOLUTIONS.find((solution) => solution.id === id);
  if (!entry) return null;

  if (entry.packSlug) {
    const pack = PACK_PAGES[entry.packSlug];
    if (pack) {
      const copy = localized(pack, locale);
      return {
        id: entry.id,
        href: entry.href,
        name: localized(entry.osLabel, locale),
        tagline: copy.subtitle,
        description: `${copy.title} ${copy.highlight}`.trim(),
        users: [],
        features: copy.included,
        cta: copy.ctaPrimary,
      };
    }
  }

  if (entry.copy) {
    const copy = localized(entry.copy, locale);
    return {
      id: entry.id,
      href: entry.href,
      name: localized(entry.osLabel, locale),
      tagline: copy.tagline,
      description: copy.description,
      users: copy.users,
      features: copy.features,
      cta: copy.cta,
    };
  }

  return null;
}

/**
 * Libellés de l'interface de la fiche et du CTA sous la constellation.
 * Copie nouvelle, donc sous `data/` (chemin exempté PA2-I18N-014).
 */
export const SOLUTION_CARD_COPY: Record<
  AppLocale,
  {
    badge: string;
    usersTitle: string;
    featuresTitle: string;
    close: string;
    explore: string;
    start: string;
    ctaTitle: string;
    ctaSubtitle: string;
    ctaPrimary: string;
    ctaSecondary: string;
    ctaReassurance: string;
  }
> = {
  fr: {
    badge: 'Solution Leopardo',
    usersTitle: 'Pour qui',
    featuresTitle: 'Ce qui est inclus',
    close: 'Fermer',
    explore: 'En savoir plus',
    start: 'Commencer maintenant',
    ctaTitle: 'Trouver la solution adaptée à mon activité',
    ctaSubtitle:
      'Une plateforme, plusieurs environnements métier : choisissez le vôtre, le pack correspondant s’active gratuitement.',
    ctaPrimary: 'Découvrir nos solutions',
    ctaSecondary: 'Commencer maintenant',
    ctaReassurance: 'Pack offert · Sans carte bancaire · Sans engagement',
  },
  en: {
    badge: 'Leopardo solution',
    usersTitle: 'Who it is for',
    featuresTitle: 'What is included',
    close: 'Close',
    explore: 'Learn more',
    start: 'Start now',
    ctaTitle: 'Find the right solution for my business',
    ctaSubtitle:
      'One platform, several business environments: pick yours and the matching pack activates for free.',
    ctaPrimary: 'Explore our solutions',
    ctaSecondary: 'Start now',
    ctaReassurance: 'Free pack · No credit card · No commitment',
  },
  tr: {
    badge: 'Leopardo çözümü',
    usersTitle: 'Kimler için',
    featuresTitle: 'Neler dahil',
    close: 'Kapat',
    explore: 'Daha fazla bilgi',
    start: 'Hemen başla',
    ctaTitle: 'İşletmeme uygun çözümü bulmak istiyorum',
    ctaSubtitle:
      'Tek platform, birkaç sektör ortamı: sizinkini seçin, uygun paket ücretsiz etkinleşsin.',
    ctaPrimary: 'Çözümlerimizi keşfedin',
    ctaSecondary: 'Hemen başla',
    ctaReassurance: 'Ücretsiz paket · Kredi kartı yok · Taahhüt yok',
  },
  ar: {
    badge: 'حل Leopardo',
    usersTitle: 'لمن',
    featuresTitle: 'ما يشمله',
    close: 'إغلاق',
    explore: 'معرفة المزيد',
    start: 'ابدأ الآن',
    ctaTitle: 'أريد الحل المناسب لنشاطي',
    ctaSubtitle:
      'منصة واحدة وعدة بيئات مهنية: اختر بيئتك، وستُفعَّل الحزمة المناسبة مجانًا.',
    ctaPrimary: 'اكتشف حلولنا',
    ctaSecondary: 'ابدأ الآن',
    ctaReassurance: 'حزمة مجانية · بدون بطاقة بنكية · بدون التزام',
  },
};
