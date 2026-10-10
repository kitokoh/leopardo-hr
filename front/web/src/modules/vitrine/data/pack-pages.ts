/**
 * Copy ×4 (fr/en/tr/ar) des pages vitrine « Pack offert » par métier
 * (/packs/[vertical]).
 *
 * Vit dans `data/` (chemin exempté par la garde PA2-I18N-014), même
 * mécanique que `restaurant-wizard.ts`. La promesse est identique à la page
 * /restaurateur : le pack métier est OFFERT — c'est l'offre d'entrée grand
 * public du Business OS.
 */

import type { AppLocale } from '@/lib/i18n';

export type PackVerticalSlug = 'station-service' | 'ecole' | 'agence-de-voyage';

export const PACK_VERTICALS: PackVerticalSlug[] = [
  'station-service',
  'ecole',
  'agence-de-voyage',
];

export type PackBenefitIcon =
  | 'clock'
  | 'calendar'
  | 'chart'
  | 'users'
  | 'file'
  | 'wallet'
  | 'ticket'
  | 'store'
  | 'shield';

export type PackPageCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  points: string[];
  benefitsTitle: string;
  benefits: Array<{ icon: PackBenefitIcon; title: string; text: string }>;
  includedTitle: string;
  included: string[];
  ctaPrimary: string;
  ctaSecondary: string;
  note: string;
};

export const PACK_PAGES: Record<PackVerticalSlug, Record<AppLocale, PackPageCopy>> = {
  'station-service': {
    fr: {
      badge: 'Pack Station-service offert',
      title: 'Vos équipes tournantes sous contrôle,',
      highlight: 'le pack est offert.',
      subtitle:
        'Pointage par poste, planning des rotations, heures et majorations vers la paie — sans ressaisie ni paperasse. Activez votre pack Station-service gratuitement et démarrez aujourd\'hui.',
      points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
      benefitsTitle: 'Pensé pour les stations-service',
      benefits: [
        { icon: 'clock', title: 'Pointage par poste', text: 'Chaque employé pointe sur son poste — pompe, boutique, lavage — par QR, GPS ou borne.' },
        { icon: 'calendar', title: 'Rotations sans casse-tête', text: 'Plannings 3×8, remplacements et absences gérés en quelques clics, même multi-sites.' },
        { icon: 'wallet', title: 'Heures vers la paie', text: 'Heures de nuit, dimanches et majorations remontent automatiquement en variables de paie.' },
        { icon: 'chart', title: 'Visibilité direction', text: 'Qui est présent, où, sur quel poste — en temps réel, station par station.' },
      ],
      includedTitle: 'Inclus dans votre pack offert',
      included: [
        'Pointage mobile GPS et QR code',
        'Planning des équipes tournantes',
        'Préparation de paie avec majorations',
        'Dossiers employés et documents',
        'Application Employé et Manager',
      ],
      ctaPrimary: 'Activer mon pack offert',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace. Aucun engagement.',
    },
    en: {
      badge: 'Free Fuel Station Pack',
      title: 'Your rotating crews under control,',
      highlight: 'the pack is free.',
      subtitle:
        'Per-post clock-ins, rotation scheduling, hours and overtime straight to payroll — no re-entry, no paperwork. Activate your Fuel Station Pack for free and start today.',
      points: ['Free pack', 'No credit card', 'Activated in minutes'],
      benefitsTitle: 'Built for fuel stations',
      benefits: [
        { icon: 'clock', title: 'Per-post clock-ins', text: 'Every employee clocks in at their post — pump, shop, wash — by QR, GPS or kiosk.' },
        { icon: 'calendar', title: 'Painless rotations', text: '3×8 schedules, replacements and leave handled in a few clicks, even across sites.' },
        { icon: 'wallet', title: 'Hours to payroll', text: 'Night hours, Sundays and overtime flow automatically into payroll variables.' },
        { icon: 'chart', title: 'Management visibility', text: 'Who is present, where, on which post — in real time, station by station.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'GPS and QR mobile clock-ins',
        'Rotating crew scheduling',
        'Payroll preparation with overtime',
        'Employee records and documents',
        'Employee and Manager apps',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Akaryakıt İstasyonu Paketi',
      title: 'Vardiyalı ekipleriniz kontrol altında,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Posta bazlı yoklama, vardiya planlaması, saatler ve fazla mesai doğrudan bordroya — yeniden giriş yok, evrak yok. Akaryakıt İstasyonu Paketinizi ücretsiz etkinleştirin ve bugün başlayın.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
      benefitsTitle: 'Akaryakıt istasyonları için tasarlandı',
      benefits: [
        { icon: 'clock', title: 'Posta bazlı yoklama', text: 'Her çalışan postasında yoklama yapar — pompa, market, yıkama — QR, GPS veya kiosk ile.' },
        { icon: 'calendar', title: 'Zahmetsiz vardiyalar', text: '3×8 planlar, yerine geçmeler ve izinler birkaç tıkla, çok şubeli bile.' },
        { icon: 'wallet', title: 'Saatler bordroya', text: 'Gece saatleri, pazar günleri ve fazla mesailer otomatik olarak bordro değişkenlerine akar.' },
        { icon: 'chart', title: 'Yönetim görünürlüğü', text: 'Kim nerede, hangi postada — gerçek zamanlı, istasyon istasyon.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'GPS ve QR mobil yoklama',
        'Vardiyalı ekip planlaması',
        'Fazla mesaili bordro hazırlığı',
        'Çalışan dosyaları ve belgeler',
        'Çalışan ve Yönetici uygulamaları',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة محطة الوقود مجانية',
      title: 'فرق المناوبة تحت السيطرة،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'تسجيل حضور حسب الموضع، جدولة المناوبات، الساعات والإضافي مباشرة إلى الرواتب — بلا إعادة إدخال ولا أوراق. فعّل حزمة محطة الوقود مجانًا وابدأ اليوم.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
      benefitsTitle: 'مصممة لمحطات الوقود',
      benefits: [
        { icon: 'clock', title: 'حضور حسب الموضع', text: 'كل موظف يسجل حضوره في موضعه — المضخة، المتجر، الغسيل — عبر QR أو GPS أو الجهاز.' },
        { icon: 'calendar', title: 'مناوبات بلا عناء', text: 'جداول 3×8 والبدلاء والإجازات تُدار في بضع نقرات، حتى عبر عدة مواقع.' },
        { icon: 'wallet', title: 'الساعات إلى الرواتب', text: 'ساعات الليل والأحد والإضافي تنتقل تلقائيًا إلى متغيرات الرواتب.' },
        { icon: 'chart', title: 'رؤية للإدارة', text: 'من حاضر، أين، على أي موضع — في الوقت الفعلي، محطة محطة.' },
      ],
      includedTitle: 'مضمن في حزمتك المجانية',
      included: [
        'تسجيل حضور بالجوال عبر GPS وQR',
        'جدولة فرق المناوبة',
        'إعداد الرواتب مع الإضافي',
        'ملفات الموظفين والمستندات',
        'تطبيقا الموظف والمدير',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'التفعيل مجاني عند إنشاء مساحتك. بلا التزام.',
    },
  },
  ecole: {
    fr: {
      badge: 'Pack École offert',
      title: 'Le personnel scolaire piloté sereinement,',
      highlight: 'le pack est offert.',
      subtitle:
        'Présence du personnel, absences et remplacements, paie alignée sur le calendrier scolaire — enseignants comme administratifs. Activez votre pack École gratuitement et démarrez aujourd\'hui.',
      points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
      benefitsTitle: 'Pensé pour les écoles et la formation',
      benefits: [
        { icon: 'users', title: 'Enseignants et administratifs', text: 'Un seul référentiel pour tout le personnel — contrats, documents, affectations.' },
        { icon: 'calendar', title: 'Calendrier scolaire', text: 'Congés, absences et remplacements alignés sur les périodes de l\'année scolaire.' },
        { icon: 'clock', title: 'Présence sans paperasse', text: 'Pointage du personnel par QR, GPS ou borne — fini le registre papier.' },
        { icon: 'wallet', title: 'Paie sans ressaisie', text: 'Heures, absences et retenues remontent directement en préparation de paie.' },
      ],
      includedTitle: 'Inclus dans votre pack offert',
      included: [
        'Pointage du personnel (QR, GPS, borne)',
        'Gestion des absences et remplacements',
        'Préparation de paie multi-profils',
        'Dossiers du personnel et documents',
        'Application Employé et Manager',
      ],
      ctaPrimary: 'Activer mon pack offert',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace. Aucun engagement.',
    },
    en: {
      badge: 'Free School Pack',
      title: 'School staff management made serene,',
      highlight: 'the pack is free.',
      subtitle:
        'Staff attendance, leave and replacements, payroll aligned with the school calendar — teachers and admin alike. Activate your School Pack for free and start today.',
      points: ['Free pack', 'No credit card', 'Activated in minutes'],
      benefitsTitle: 'Built for schools & training',
      benefits: [
        { icon: 'users', title: 'Teachers and admin staff', text: 'One single record for all staff — contracts, documents, assignments.' },
        { icon: 'calendar', title: 'School calendar', text: 'Leave, absences and replacements aligned with the school year periods.' },
        { icon: 'clock', title: 'Paperless attendance', text: 'Staff clock-ins by QR, GPS or kiosk — goodbye paper register.' },
        { icon: 'wallet', title: 'Payroll without re-entry', text: 'Hours, absences and deductions flow straight into payroll preparation.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'Staff clock-ins (QR, GPS, kiosk)',
        'Leave and replacement management',
        'Multi-profile payroll preparation',
        'Staff records and documents',
        'Employee and Manager apps',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Okul Paketi',
      title: 'Okul personeli yönetimi huzurla,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Personel yoklaması, izin ve yerine geçmeler, okul takvimiyle uyumlu bordro — öğretmenler ve idari kadro birlikte. Okul Paketinizi ücretsiz etkinleştirin ve bugün başlayın.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
      benefitsTitle: 'Okullar ve eğitim için tasarlandı',
      benefits: [
        { icon: 'users', title: 'Öğretmenler ve idari kadro', text: 'Tüm personel için tek kayıt — sözleşmeler, belgeler, görevlendirmeler.' },
        { icon: 'calendar', title: 'Okul takvimi', text: 'İzinler, devamsızlıklar ve yerine geçmeler okul yılı dönemleriyle uyumlu.' },
        { icon: 'clock', title: 'Evraksız yoklama', text: 'Personel QR, GPS veya kiosk ile yoklama yapar — kağıt deftere son.' },
        { icon: 'wallet', title: 'Yeniden girişsiz bordro', text: 'Saatler, devamsızlıklar ve kesintiler doğrudan bordro hazırlığına akar.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'Personel yoklaması (QR, GPS, kiosk)',
        'İzin ve yerine geçme yönetimi',
        'Çok profilli bordro hazırlığı',
        'Personel dosyaları ve belgeler',
        'Çalışan ve Yönetici uygulamaları',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة المدرسة مجانية',
      title: 'إدارة طاقم المدرسة باطمئنان،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'حضور الموظفين والغيابات والبدلاء، ورواتب متوافقة مع التقويم المدرسي — للمعلمين والإداريين معًا. فعّل حزمة المدرسة مجانًا وابدأ اليوم.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
      benefitsTitle: 'مصممة للمدارس والتكوين',
      benefits: [
        { icon: 'users', title: 'المعلمون والإداريون', text: 'سجل واحد لكل الطاقم — العقود والمستندات والتكليفات.' },
        { icon: 'calendar', title: 'التقويم المدرسي', text: 'الإجازات والغيابات والبدلاء بما يناسب فترات السنة الدراسية.' },
        { icon: 'clock', title: 'حضور بلا أوراق', text: 'يسجل الطاقم حضوره عبر QR أو GPS أو الجهاز — وداعًا للسجل الورقي.' },
        { icon: 'wallet', title: 'رواتب بلا إعادة إدخال', text: 'الساعات والغيابات والاقتطاعات تنتقل مباشرة إلى إعداد الرواتب.' },
      ],
      includedTitle: 'مضمن في حزمتك المجانية',
      included: [
        'تسجيل حضور الطاقم (QR، GPS، جهاز)',
        'إدارة الغيابات والبدلاء',
        'إعداد رواتب متعدد الفئات',
        'ملفات الطاقم والمستندات',
        'تطبيقا الموظف والمدير',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'التفعيل مجاني عند إنشاء مساحتك. بلا التزام.',
    },
  },
  'agence-de-voyage': {
    fr: {
      badge: 'Pack Agence de voyage offert',
      title: 'Votre agence au niveau des grandes,',
      highlight: 'le pack est offert.',
      subtitle:
        'Dossiers clients, billetterie, commissions et paie des équipes au même endroit — comptoir comme terrain. Activez votre pack Agence de voyage gratuitement et démarrez aujourd\'hui.',
      points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
      benefitsTitle: 'Pensé pour les agences de voyage',
      benefits: [
        { icon: 'file', title: 'Dossiers clients centralisés', text: 'Réservations, documents et historique client réunis dans un CRM intégré.' },
        { icon: 'ticket', title: 'Billetterie & check-in', text: 'L\'app Travel Agent couvre la billetterie, le check-in et le point de vente au comptoir.' },
        { icon: 'wallet', title: 'Commissions maîtrisées', text: 'Suivi des commissions par agent et paie des équipes sans double saisie.' },
        { icon: 'clock', title: 'Équipes pointées', text: 'Comptoir, accueil groupes, tournées — le pointage suit vos horaires réels.' },
      ],
      includedTitle: 'Inclus dans votre pack offert',
      included: [
        'CRM dossiers clients',
        'App Travel Agent (billetterie, point de vente)',
        'Pointage mobile et planning',
        'Préparation de paie avec commissions',
        'Application Employé et Manager',
      ],
      ctaPrimary: 'Activer mon pack offert',
      ctaSecondary: 'Parler à un conseiller',
      note: 'Activation gratuite à la création de votre espace. Aucun engagement.',
    },
    en: {
      badge: 'Free Travel Agency Pack',
      title: 'Your agency at the level of the big ones,',
      highlight: 'the pack is free.',
      subtitle:
        'Client files, ticketing, commissions and team payroll in one place — front desk and field alike. Activate your Travel Agency Pack for free and start today.',
      points: ['Free pack', 'No credit card', 'Activated in minutes'],
      benefitsTitle: 'Built for travel agencies',
      benefits: [
        { icon: 'file', title: 'Centralized client files', text: 'Bookings, documents and client history brought together in an integrated CRM.' },
        { icon: 'ticket', title: 'Ticketing & check-in', text: 'The Travel Agent app covers ticketing, check-in and point of sale at the counter.' },
        { icon: 'wallet', title: 'Commissions under control', text: 'Per-agent commission tracking and team payroll without double entry.' },
        { icon: 'clock', title: 'Teams clocked in', text: 'Counter, group reception, tours — attendance follows your real schedules.' },
      ],
      includedTitle: 'Included in your free pack',
      included: [
        'Client files CRM',
        'Travel Agent app (ticketing, point of sale)',
        'Mobile clock-ins and scheduling',
        'Payroll preparation with commissions',
        'Employee and Manager apps',
      ],
      ctaPrimary: 'Activate my free pack',
      ctaSecondary: 'Talk to an advisor',
      note: 'Free activation when you create your workspace. No commitment.',
    },
    tr: {
      badge: 'Ücretsiz Seyahat Acentesi Paketi',
      title: 'Acenteniz büyükler seviyesinde,',
      highlight: 'paket ücretsiz.',
      subtitle:
        'Müşteri dosyaları, biletleme, komisyonlar ve ekip bordrosu tek yerde — gişe ve saha birlikte. Seyahat Acentesi Paketinizi ücretsiz etkinleştirin ve bugün başlayın.',
      points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
      benefitsTitle: 'Seyahat acenteleri için tasarlandı',
      benefits: [
        { icon: 'file', title: 'Merkezi müşteri dosyaları', text: 'Rezervasyonlar, belgeler ve müşteri geçmişi entegre CRM\'de bir arada.' },
        { icon: 'ticket', title: 'Biletleme & check-in', text: 'Travel Agent uygulaması biletleme, check-in ve gişe satışını kapsar.' },
        { icon: 'wallet', title: 'Kontrollü komisyonlar', text: 'Acente bazlı komisyon takibi ve çift giriş olmadan ekip bordrosu.' },
        { icon: 'clock', title: 'Ekip yoklaması', text: 'Gişe, grup karşılama, turlar — yoklama gerçek saatlerinizi izler.' },
      ],
      includedTitle: 'Ücretsiz paketinize dahil',
      included: [
        'Müşteri dosyaları CRM\'i',
        'Travel Agent uygulaması (biletleme, satış noktası)',
        'Mobil yoklama ve planlama',
        'Komisyonlu bordro hazırlığı',
        'Çalışan ve Yönetici uygulamaları',
      ],
      ctaPrimary: 'Ücretsiz paketimi etkinleştir',
      ctaSecondary: 'Bir danışmanla konuş',
      note: 'Çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
    },
    ar: {
      badge: 'حزمة وكالة السفر مجانية',
      title: 'وكالتك بمستوى الكبار،',
      highlight: 'والحزمة مجانية.',
      subtitle:
        'ملفات العملاء وإصدار التذاكر والعمولات ورواتب الفريق في مكان واحد — المكتب والميدان معًا. فعّل حزمة وكالة السفر مجانًا وابدأ اليوم.',
      points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
      benefitsTitle: 'مصممة لوكالات السفر',
      benefits: [
        { icon: 'file', title: 'ملفات عملاء مركزية', text: 'الحجوزات والمستندات وسجل العميل مجتمعة في نظام CRM مدمج.' },
        { icon: 'ticket', title: 'التذاكر وتسجيل الوصول', text: 'يغطي تطبيق Travel Agent إصدار التذاكر وتسجيل الوصول ونقطة البيع.' },
        { icon: 'wallet', title: 'عمولات تحت السيطرة', text: 'تتبع العمولات لكل وكيل ورواتب الفريق دون إدخال مزدوج.' },
        { icon: 'clock', title: 'حضور الفريق', text: 'المكتب واستقبال المجموعات والجولات — الحضور يتبع جداولك الفعلية.' },
      ],
      includedTitle: 'مضمن في حزمتك المجانية',
      included: [
        'نظام CRM لملفات العملاء',
        'تطبيق Travel Agent (التذاكر، نقطة البيع)',
        'تسجيل الحضور بالجوال والجدولة',
        'إعداد الرواتب مع العمولات',
        'تطبيقا الموظف والمدير',
      ],
      ctaPrimary: 'فعّل حزمتي المجانية',
      ctaSecondary: 'تحدث إلى مستشار',
      note: 'التفعيل مجاني عند إنشاء مساحتك. بلا التزام.',
    },
  },
};

export function isPackVertical(value: string): value is PackVerticalSlug {
  return (PACK_VERTICALS as string[]).includes(value);
}


/** Copy du hub /packs — la vitrine de l'offre d'entrée grand public :
 *  un pack métier OFFERT par corps de métier. */
export type PacksHubCopy = {
  badge: string;
  title: string;
  highlight: string;
  subtitle: string;
  points: string[];
  cards: Array<{ slug: 'restaurant' | PackVerticalSlug; name: string; line: string; linkLabel: string }>;
  freeBadge: string;
  note: string;
};

export const PACKS_HUB_COPY: Record<AppLocale, PacksHubCopy> = {
  fr: {
    badge: 'Packs métiers offerts',
    title: 'Un pack offert',
    highlight: 'pour votre métier.',
    subtitle:
      'Restaurateur, gérant de station-service, directeur d’école, agent de voyage : votre pack Leopardo réunit les apps, les parcours et les réglages de votre secteur — activé gratuitement dans votre espace.',
    points: ['Pack offert', 'Sans carte bancaire', 'Activé en quelques minutes'],
    cards: [
      { slug: 'restaurant', name: 'Restaurants', line: 'Caisse, cuisine, réservations, stock — composé en 3 questions.', linkLabel: 'Composer mon pack' },
      { slug: 'station-service', name: 'Stations-service', line: 'Rotations 3×8, pointage par poste, majorations vers la paie.', linkLabel: 'Découvrir le pack' },
      { slug: 'ecole', name: 'Écoles & formation', line: 'Présence du personnel, remplacements, paie calendrier scolaire.', linkLabel: 'Découvrir le pack' },
      { slug: 'agence-de-voyage', name: 'Agences de voyage', line: 'Dossiers clients, billetterie, commissions, paie des équipes.', linkLabel: 'Découvrir le pack' },
    ],
    freeBadge: 'Offert',
    note: 'Chaque pack s’active gratuitement à la création de votre espace Leopardo. Aucun engagement.',
  },
  en: {
    badge: 'Free business packs',
    title: 'A free pack',
    highlight: 'for your trade.',
    subtitle:
      'Restaurant owner, fuel station manager, school director, travel agent: your Leopardo pack bundles the apps, flows and defaults of your industry — activated free in your workspace.',
    points: ['Free pack', 'No credit card', 'Activated in minutes'],
    cards: [
      { slug: 'restaurant', name: 'Restaurants', line: 'POS, kitchen, reservations, stock — built in 3 questions.', linkLabel: 'Build my pack' },
      { slug: 'station-service', name: 'Fuel stations', line: '3×8 rotations, per-post clock-ins, overtime to payroll.', linkLabel: 'See the pack' },
      { slug: 'ecole', name: 'Schools & training', line: 'Staff attendance, replacements, school-calendar payroll.', linkLabel: 'See the pack' },
      { slug: 'agence-de-voyage', name: 'Travel agencies', line: 'Client files, ticketing, commissions, team payroll.', linkLabel: 'See the pack' },
    ],
    freeBadge: 'Free',
    note: 'Every pack is activated free when you create your Leopardo workspace. No commitment.',
  },
  tr: {
    badge: 'Ücretsiz iş paketleri',
    title: 'Sektörünüz için',
    highlight: 'ücretsiz paket.',
    subtitle:
      'Restorancı, istasyon müdürü, okul müdürü, seyahat acentesi: Leopardo paketiniz sektörünüzün uygulamalarını, akışlarını ve ayarlarını bir araya getirir — çalışma alanınızda ücretsiz etkinleşir.',
    points: ['Ücretsiz paket', 'Kredi kartı yok', 'Dakikalar içinde aktif'],
    cards: [
      { slug: 'restaurant', name: 'Restoranlar', line: 'Kasa, mutfak, rezervasyon, stok — 3 soruda oluşturun.', linkLabel: 'Paketimi oluştur' },
      { slug: 'station-service', name: 'Akaryakıt istasyonları', line: '3×8 vardiyalar, posta bazlı yoklama, fazla mesai bordroya.', linkLabel: 'Paketi gör' },
      { slug: 'ecole', name: 'Okullar & eğitim', line: 'Personel yoklaması, yerine geçmeler, okul takvimi bordrosu.', linkLabel: 'Paketi gör' },
      { slug: 'agence-de-voyage', name: 'Seyahat acenteleri', line: 'Müşteri dosyaları, biletleme, komisyonlar, ekip bordrosu.', linkLabel: 'Paketi gör' },
    ],
    freeBadge: 'Ücretsiz',
    note: 'Her paket, Leopardo çalışma alanınızı oluştururken ücretsiz etkinleşir. Taahhüt yok.',
  },
  ar: {
    badge: 'حزم الأعمال مجانية',
    title: 'حزمة مجانية',
    highlight: 'لمهنتك.',
    subtitle:
      'صاحب مطعم، مدير محطة وقود، مدير مدرسة، وكيل سفر: حزمتك من ليوباردو تجمع تطبيقات قطاعك ومساراته وإعداداته — وتُفعَّل مجانًا في مساحتك.',
    points: ['حزمة مجانية', 'بدون بطاقة بنكية', 'تُفعَّل في دقائق'],
    cards: [
      { slug: 'restaurant', name: 'المطاعم', line: 'نقاط البيع والمطبخ والحجوزات والمخزون — أنشئها في 3 أسئلة.', linkLabel: 'أنشئ حزمتي' },
      { slug: 'station-service', name: 'محطات الوقود', line: 'مناوبات 3×8، حضور حسب الموضع، الإضافي إلى الرواتب.', linkLabel: 'شاهد الحزمة' },
      { slug: 'ecole', name: 'المدارس والتكوين', line: 'حضور الطاقم والبدلاء ورواتب التقويم المدرسي.', linkLabel: 'شاهد الحزمة' },
      { slug: 'agence-de-voyage', name: 'وكالات السفر', line: 'ملفات العملاء والتذاكر والعمولات ورواتب الفريق.', linkLabel: 'شاهد الحزمة' },
    ],
    freeBadge: 'مجانًا',
    note: 'تُفعَّل كل حزمة مجانًا عند إنشاء مساحة ليوباردو الخاصة بك. بلا التزام.',
  },
};

export function packsHubCardHref(slug: PacksHubCopy['cards'][number]['slug']): string {
  return slug === 'restaurant' ? '/restaurateur' : `/packs/${slug}`;
}

// ── Bannière « Pack offert » de la page tarifs ─────────────────────────────
// Remplace l'ancienne colonne « Self-host » (#8068) : la page tarifs pousse
// désormais l'entrée grand public du Business OS — un pack métier OFFERT —
// au même niveau que les plans cloud payants.
export type PricingPackBannerCopy = {
  badge: string;
  title: string;
  subtitle: string;
  bullets: [string, string, string];
  ctaPrimary: string;
  ctaSecondary: string;
};

export const PRICING_PACK_BANNER: Record<AppLocale, PricingPackBannerCopy> = {
  fr: {
    badge: 'Pack métier offert',
    title: 'Votre métier a son pack Leopardo — il est offert.',
    subtitle:
      'Restauration, station-service, école, agence de voyage… Les apps, les parcours et les réglages de votre secteur, activés gratuitement dans votre espace Leopardo.',
    bullets: ['Activé à l’inscription', 'Sans carte bancaire', 'Enrichi à chaque version'],
    ctaPrimary: 'Découvrir les packs offerts',
    ctaSecondary: 'Composer le pack Restaurant',
  },
  en: {
    badge: 'Free trade pack',
    title: 'Your trade has its Leopardo pack — and it is free.',
    subtitle:
      'Restaurant, fuel station, school, travel agency… Your industry’s apps, flows and defaults, activated free in your Leopardo workspace.',
    bullets: ['Activated on signup', 'No credit card', 'Improved every release'],
    ctaPrimary: 'Explore the free packs',
    ctaSecondary: 'Build the Restaurant pack',
  },
  tr: {
    badge: 'Ücretsiz meslek paketi',
    title: 'Mesleğinizin Leopardo paketi var — üstelik ücretsiz.',
    subtitle:
      'Restoran, akaryakıt istasyonu, okul, seyahat acentesi… Sektörünüzün uygulamaları, akışları ve ayarları, Leopardo çalışma alanınızda ücretsiz etkinleşir.',
    bullets: ['Kayıtta etkinleşir', 'Kredi kartı yok', 'Her sürümde zenginleşir'],
    ctaPrimary: 'Ücretsiz paketleri keşfedin',
    ctaSecondary: 'Restoran paketini oluşturun',
  },
  ar: {
    badge: 'حزمة مهنية مجانية',
    title: 'لمهنتك حزمة Leopardo — وهي مجانية.',
    subtitle:
      'مطعم، محطة وقود، مدرسة، وكالة سفر… تطبيقات قطاعك ومساراته وإعداداته تُفعَّل مجانًا في مساحة ليوباردو الخاصة بك.',
    bullets: ['تُفعَّل عند التسجيل', 'بدون بطاقة بنكية', 'تُثريها كل نسخة'],
    ctaPrimary: 'اكتشف الحزم المجانية',
    ctaSecondary: 'أنشئ حزمة المطعم',
  },
};
