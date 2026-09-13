/**
 * Études de cas indexées par slug, issues du contenu des modules vitrine
 * (content.ts). Chaque carte de module pointe vers /case-studies/<slug> ;
 * cette table garantit qu'aucun de ces liens ne mène à une 404.
 */

import { modulePageContent } from './content';
import type { AppLocale } from '@/lib/i18n';

export type CaseStudyModule = 'employes' | 'documents' | 'comptabilite' | 'marketing';

export type CaseStudy = {
  slug: string;
  title: string;
  description: string;
  industry: string;
  metrics: Array<{ label: string; value: string }>;
  module: CaseStudyModule;
  moduleLabel: string;
  moduleHref: string;
};

const moduleMeta: Record<CaseStudyModule, { label: string; href: string }> = {
  employes: { label: 'Gestion RH', href: '/employes' },
  documents: { label: 'Documents', href: '/documents' },
  comptabilite: { label: 'Paie & Comptabilité', href: '/comptabilite' },
  marketing: { label: 'Marketing', href: '/marketing' },
};

/**
 * #4703 (audit 360° 2026-08-16) : labels de module localisés — le détail
 * /case-studies/[slug] injecte `moduleLabel` dans des chaînes ui.* déjà
 * traduites ; un label FR cassait l'i18n des pages en/tr/ar.
 */
const moduleLabelsByLocale: Record<AppLocale, Record<CaseStudyModule, string>> = {
  fr: {
    employes: 'Gestion RH',
    documents: 'Documents',
    comptabilite: 'Paie & Comptabilité',
    marketing: 'Marketing',
  },
  en: {
    employes: 'HR Management',
    documents: 'Documents',
    comptabilite: 'Payroll & Accounting',
    marketing: 'Marketing',
  },
  tr: {
    employes: 'İK Yönetimi',
    documents: 'Belgeler',
    comptabilite: 'Maaş & Muhasebe',
    marketing: 'Pazarlama',
  },
  ar: {
    employes: 'إدارة الموارد البشرية',
    documents: 'المستندات',
    comptabilite: 'الرواتب والمحاسبة',
    marketing: 'التسويق',
  },
};

export function getModuleLabel(module: CaseStudyModule, locale: AppLocale): string {
  return moduleLabelsByLocale[locale]?.[module] ?? moduleLabelsByLocale.fr[module];
}

function toSlug(link: string): string {
  return link.replace(/^\/case-studies\//, '');
}

/**
 * #AI-SEO (audit 2026-09-13) — métadonnées SEO propres aux études de cas.
 *
 * Avant : `title`/`description` étaient repris tels quels de `modulePageContent`
 * (FR uniquement). Deux défauts mesurés en production :
 *   1. titres de 21 à 35 caractères et descriptions de 39 à 54 — très en dessous
 *      des cibles (50-60 / 120-155), donc un SERP pauvre sur 12 pages ;
 *   2. contenu FR servi aux pages en/tr/ar — les études de cas étaient la
 *      dernière surface vitrine non localisée.
 *
 * Ces entrées sont la couche SEO/localisation du détail d'étude de cas. Le
 * contenu long (métriques, module, industrie) reste dans `content.ts` — on ne
 * duplique pas le contenu, on surcharge le titre et la description.
 *
 * Fichier exempté de la garde i18n PA2-I18N-014 (catalogue inline, même
 * mécanique que `vitrine-locale.ts` / `seo.ts`).
 */
type CaseStudySeo = { title: string; description: string };

const caseStudySeoByLocale: Record<AppLocale, Record<string, CaseStudySeo>> = {
  fr: {
    startup: {
      title: 'Logiciel RH pour startup en croissance : étude de cas',
      description:
        "Comment une startup technologique a structuré pointage, absences et paie de 5 à 50 employés avec Leopardo RH, sans recruter de profil RH dédié.",
    },
    retail: {
      title: 'Pointage centralisé pour une chaîne de 50 magasins',
      description:
        'Une chaîne de 50 points de vente centralise le pointage de 500 employés, réduit les écarts de paie et fiabilise ses plannings avec Leopardo RH.',
    },
    factory: {
      title: 'Pointage biométrique pour une usine de 200 salariés',
      description:
        'Une usine de 200 salariés remplace le pointage papier par la biométrie ZKTeco : 99,9 % de précision et 95 % de fraude en moins sur les trois équipes.',
    },
    'law-firm': {
      title: "Dossiers clients sécurisés dans un cabinet d'avocats",
      description:
        "Un cabinet d'avocats centralise ses dossiers RH confidentiels, contrôle les accès et trace chaque consultation pour ses audits avec Leopardo RH.",
    },
    'hr-files': {
      title: 'Dossiers employés : centralisation et conformité RH',
      description:
        'Tous les dossiers employés réunis dans un espace unique et chiffré, avec historique des accès, alertes d’échéance et export pour les audits RH.',
    },
    accounting: {
      title: 'Archivage des documents comptables : étude de cas',
      description:
        'Les pièces comptables sont archivées automatiquement, rattachées à la paie et exportées vers Sage ou QuickBooks, sans double saisie ni ressaisie manuelle.',
    },
    'sme-payroll': {
      title: 'PME de 50 salariés : paie mensuelle automatisée',
      description:
        'Une PME en croissance passe de trois jours à deux heures de paie mensuelle et supprime les erreurs de calcul sur deux pays grâce à Leopardo RH.',
    },
    'startup-advances': {
      title: 'Avances sur salaire : workflow automatisé en startup',
      description:
        "Les demandes d'avance sur salaire sont instruites et validées automatiquement, sans échange par messagerie ni ressaisie manuelle en paie.",
    },
    'group-payroll': {
      title: 'Paie multi-entités et multi-devises pour un groupe',
      description:
        'Un groupe gère plusieurs entités et devises dans une seule paie consolidée, avec règles locales, validations par entité et exports par société.',
    },
    recruitment: {
      title: 'Recrutement : campagnes ciblées et automatisées',
      description:
        'Pipeline de recrutement unifié, relances automatiques des candidats et suivi des postes ouverts, sans tableur partagé ni relance manuelle.',
    },
    'employee-engagement': {
      title: 'Engagement des équipes : newsletters et annonces',
      description:
        "Des newsletters internes et des annonces ciblées par équipe augmentent la lecture des communications RH et réduisent l'absentéisme sur les sites.",
    },
    'customer-campaigns': {
      title: 'Campagnes de promotion clients et suivi des envois',
      description:
        'Des campagnes de promotion envoyées aux clients depuis la même plateforme que la paie, avec suivi des envois, des conversions et du ciblage.',
    },
  },
  en: {
    startup: {
      title: 'HR software for a fast-growing startup: case study',
      description:
        'How a technology startup structured attendance, leave and payroll while scaling from 5 to 50 employees with Leopardo HR, without a dedicated HR hire.',
    },
    retail: {
      title: 'Centralized time tracking for a 50-store retail chain',
      description:
        'A 50-store retail chain centralizes attendance for 500 employees, cuts payroll discrepancies and makes shift planning reliable with Leopardo HR.',
    },
    factory: {
      title: 'Biometric time tracking for a 200-employee factory',
      description:
        'A 200-employee factory replaces paper timesheets with ZKTeco biometrics: 99.9% accuracy and 95% less time fraud across its three shifts.',
    },
    'law-firm': {
      title: 'Secure client files in a law firm: HR case study',
      description:
        'A law firm centralizes its confidential HR files, controls access and traces every consultation for compliance audits with Leopardo HR.',
    },
    'hr-files': {
      title: 'Employee records: centralization and HR compliance',
      description:
        'Every employee file in one encrypted space, with access history, deadline alerts and payroll-ready exports for HR audits.',
    },
    accounting: {
      title: 'Accounting document archiving and payroll exports',
      description:
        'Accounting records are archived automatically, linked to payroll and exported to Sage or QuickBooks without double entry or manual retyping.',
    },
    'sme-payroll': {
      title: 'Automated monthly payroll for a 50-employee SMB',
      description:
        'A growing SMB cuts monthly payroll from three days to two hours and removes payroll calculation errors across two countries with Leopardo HR.',
    },
    'startup-advances': {
      title: 'Salary advances: an automated workflow for startups',
      description:
        'Salary advance requests are reviewed and approved automatically, with no messaging back-and-forth and no manual payroll re-entry.',
    },
    'group-payroll': {
      title: 'Multi-entity, multi-currency payroll for a group',
      description:
        'A group runs several entities and currencies in one consolidated payroll, with local rules, per-entity approvals and company-level exports.',
    },
    recruitment: {
      title: 'Recruitment: targeted and automated campaigns',
      description:
        'A unified recruitment pipeline, automatic candidate follow-ups and open-role tracking — without shared spreadsheets or manual reminders.',
    },
    'employee-engagement': {
      title: 'Employee engagement: newsletters and announcements',
      description:
        'Internal newsletters and team-targeted announcements increase readership of HR communications and reduce absenteeism across field sites.',
    },
    'customer-campaigns': {
      title: 'Customer promotion campaigns and delivery tracking',
      description:
        'Promotion campaigns sent to customers from the same platform as payroll, with delivery, conversion and audience segmentation tracking.',
    },
  },
  tr: {
    startup: {
      title: 'Hızlı büyüyen startup için İK yazılımı: vaka analizi',
      description:
        "Bir teknoloji startup'ı 5'ten 50 çalışana büyürken giriş-çıkış, izin ve bordroyu Leopardo İK ile nasıl düzenledi? Ayrı bir İK uzmanı işe almadan.",
    },
    retail: {
      title: '50 mağazalı perakende zincirinde merkezi yoklama',
      description:
        '50 satış noktası 500 çalışanın yoklamasını tek yerden yönetiyor, bordro farklarını azaltıyor ve vardiya planlamasını güvenilir kılıyor.',
    },
    factory: {
      title: '200 çalışanlı bir fabrikada biyometrik yoklama',
      description:
        '200 çalışanlı bir fabrika kağıt puantajı ZKTeco biyometrisiyle değiştirdi: %99,9 doğruluk ve üç vardiyada %95 daha az kayıt hilesi.',
    },
    'law-firm': {
      title: 'Hukuk bürosunda güvenli müvekkil dosyaları',
      description:
        'Bir hukuk bürosu gizli İK dosyalarını tek yerde topluyor, erişimleri kontrol ediyor ve her görüntülemeyi denetim için kayıt altına alıyor.',
    },
    'hr-files': {
      title: 'Çalışan dosyaları: merkezileştirme ve İK uyumu',
      description:
        'Tüm çalışan dosyaları tek şifreli alanda: erişim geçmişi, süre uyarıları ve İK denetimleri için bordroya hazır dışa aktarımlar.',
    },
    accounting: {
      title: 'Muhasebe belgelerinin arşivlenmesi ve bordro aktarımı',
      description:
        "Muhasebe kayıtları otomatik arşivleniyor, bordroya bağlanıyor ve Sage ya da QuickBooks'a çift giriş olmadan aktarılıyor.",
    },
    'sme-payroll': {
      title: '50 çalışanlı KOBİ için otomatik aylık bordro',
      description:
        'Büyüyen bir KOBİ aylık bordroyu üç günden iki saate indiriyor ve iki ülkedeki hesaplama hatalarını Leopardo İK ile ortadan kaldırıyor.',
    },
    'startup-advances': {
      title: "Maaş avansı: startup'lar için otomatik iş akışı",
      description:
        'Maaş avansı talepleri otomatik inceleniyor ve onaylanıyor; mesajlaşma trafiği ve bordroda elle giriş kalmıyor.',
    },
    'group-payroll': {
      title: 'Bir grup için çok şirketli ve çok para birimli bordro',
      description:
        'Bir grup birden fazla şirketi ve para birimini tek konsolide bordroda yönetiyor; yerel kurallar, şirket bazlı onaylar ve çıktılar.',
    },
    recruitment: {
      title: 'İşe alım: hedefli ve otomatik kampanyalar',
      description:
        'Tek bir işe alım hattı, otomatik aday takibi ve açık pozisyon takibi — paylaşılan tablo ya da elle hatırlatma olmadan.',
    },
    'employee-engagement': {
      title: 'Çalışan bağlılığı: iç bültenler ve duyurular',
      description:
        'İç bültenler ve ekibe özel duyurular İK iletişimlerinin okunma oranını artırıyor ve saha ekiplerinde devamsızlığı azaltıyor.',
    },
    'customer-campaigns': {
      title: 'Müşteri promosyon kampanyaları ve gönderim takibi',
      description:
        'Bordro ile aynı platformdan müşterilere gönderilen promosyon kampanyaları; gönderim, dönüşüm ve hedef kitle takibiyle.',
    },
  },
  ar: {
    startup: {
      title: 'برنامج موارد بشرية لشركة ناشئة سريعة النمو: دراسة حالة',
      description:
        'كيف نظّمت شركة تقنية ناشئة الحضور والإجازات والرواتب أثناء نموها من 5 إلى 50 موظفًا مع ليوباردو، دون تعيين مسؤول موارد بشرية مختص.',
    },
    retail: {
      title: 'تسجيل حضور مركزي لسلسلة من 50 نقطة بيع',
      description:
        'سلسلة من 50 نقطة بيع توحّد حضور 500 موظف وتقلّل فروقات الرواتب وتجعل جدولة الورديات موثوقة مع ليوباردو.',
    },
    factory: {
      title: 'تسجيل حضور بيومتري لمصنع يضم 200 موظف',
      description:
        'مصنع يضم 200 موظف يستبدل كشوف الحضور الورقية بالبصمة ZKTeco: دقة 99.9% وانخفاض التلاعب بنسبة 95% في الورديات الثلاث.',
    },
    'law-firm': {
      title: 'ملفات العملاء المؤمّنة في مكتب محاماة: دراسة حالة',
      description:
        'مكتب محاماة يجمع ملفاته السرية، ويضبط صلاحيات الوصول، ويسجّل كل استعراض لأغراض التدقيق مع ليوباردو.',
    },
    'hr-files': {
      title: 'ملفات الموظفين: مركزية وامتثال للموارد البشرية',
      description:
        'كل ملفات الموظفين في مساحة مشفّرة واحدة مع سجل الوصول وتنبيهات المواعيد وتصديرات جاهزة للرواتب والتدقيق.',
    },
    accounting: {
      title: 'أرشفة المستندات المحاسبية وتصديرها للرواتب',
      description:
        'تُؤرشف المستندات المحاسبية تلقائيًا وتُربط بالرواتب وتُصدَّر إلى Sage أو QuickBooks دون إدخال مزدوج أو إعادة كتابة يدوية.',
    },
    'sme-payroll': {
      title: 'رواتب شهرية آلية لشركة صغيرة تضم 50 موظفًا',
      description:
        'شركة صغيرة تقلّص إعداد الرواتب من ثلاثة أيام إلى ساعتين وتزيل أخطاء الحساب في بلدين مع ليوباردو.',
    },
    'startup-advances': {
      title: 'السلف على الراتب: مسار آلي للشركات الناشئة',
      description:
        'طلبات السلف على الراتب تُراجَع وتُعتمد تلقائيًا، دون مراسلات متبادلة ودون إعادة إدخال يدوي في الرواتب.',
    },
    'group-payroll': {
      title: 'رواتب متعددة الكيانات والعملات لمجموعة شركات',
      description:
        'تدير المجموعة عدة كيانات وعملات في كشف رواتب موحّد، مع قواعد محلية وموافقات لكل كيان وتصديرات لكل شركة.',
    },
    recruitment: {
      title: 'التوظيف: حملات مستهدفة ومؤتمتة بالكامل',
      description:
        'مسار توظيف موحّد، ومتابعة آلية للمرشحين، وتتبّع للوظائف المفتوحة دون جداول مشتركة أو تذكير يدوي.',
    },
    'employee-engagement': {
      title: 'تفاعل الموظفين: رسائل داخلية وإعلانات',
      description:
        'الرسائل الداخلية والإعلانات الموجّهة لكل فريق ترفع نسبة قراءة تواصل الموارد البشرية وتقلّل الغياب في المواقع الميدانية.',
    },
    'customer-campaigns': {
      title: 'حملات ترويجية للعملاء مع تتبّع الإرسال',
      description:
        'حملات ترويجية تُرسل للعملاء من نفس منصة الرواتب، مع تتبّع الإرسال والتحويلات وتقسيم الجمهور.',
    },
  },
};

/** Titre/description SEO localisés d'une étude de cas (repli : contenu source). */
export function getCaseStudySeo(slug: string, locale: AppLocale): CaseStudySeo | undefined {
  return caseStudySeoByLocale[locale]?.[slug] ?? caseStudySeoByLocale.fr[slug];
}

export function getAllCaseStudies(locale: AppLocale = 'fr'): CaseStudy[] {
  const studies: CaseStudy[] = [];

  (Object.keys(modulePageContent) as CaseStudyModule[]).forEach((module) => {
    const section = modulePageContent[module];
    const items = section?.caseStudies?.items ?? [];

    items.forEach((item) => {
      const slug = toSlug(item.link);
      if (!slug || !/^[a-z0-9-]+$/.test(slug)) return;

      // #AI-SEO : titre/description SEO localisés quand ils existent — le
      // reste du contenu (métriques, industrie, module) reste la source unique.
      const seo = getCaseStudySeo(slug, locale);

      studies.push({
        slug,
        title: seo?.title ?? item.title,
        description: seo?.description ?? item.description,
        industry: item.industry,
        metrics: item.metrics,
        module,
        moduleLabel: getModuleLabel(module, locale),
        moduleHref: moduleMeta[module].href,
      });
    });
  });

  return studies;
}

export function getCaseStudy(slug: string, locale: AppLocale = 'fr'): CaseStudy | undefined {
  return getAllCaseStudies(locale).find((study) => study.slug === slug);
}

export function getAllCaseStudySlugs(): string[] {
  return getAllCaseStudies().map((study) => study.slug);
}
