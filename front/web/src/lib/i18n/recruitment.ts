import type { AppLocale } from '@/lib/i18n';

/**
 * Catalogue i18n de l'espace recruteur (ATS #9461).
 *
 * Les libellés de l'écran pipeline et du panneau de gestion des offres vivent
 * ici plutôt qu'en littéraux dans les composants : la garde CI
 * `check-i18n-diff.js` refuse les nouvelles chaînes visibles codées en dur, et
 * elle a raison — un écran RH francophone uniquement est inutilisable pour un
 * tenant arabophone ou turcophone.
 */

export type RecruitmentCopy = {
  jobs: {
    title: string;
    subtitle: string;
    newJob: string;
    cancel: string;
    create: string;
    creating: string;
    loadError: string;
    createError: string;
    empty: string;
    noDetail: string;
    titleField: string;
    titlePlaceholder: string;
    location: string;
    locationPlaceholder: string;
    contract: string;
    workMode: string;
    onSite: string;
    remote: string;
    hybrid: string;
    skills: string;
    skillsHint: string;
    skillsPlaceholder: string;
    description: string;
    descriptionPlaceholder: string;
    publish: string;
    close: string;
    archive: string;
    reopen: string;
    statusDraft: string;
    statusPublished: string;
    statusClosed: string;
    statusArchived: string;
    applicants: (count: number) => string;
  };
  decision: {
    title: string;
    reasonLabel: string;
    reasonPlaceholder: string;
    reasonRequired: string;
    saveError: string;
    hire: string;
    reject: string;
    alreadyDecided: (status: string) => string;
  };
  score: {
    caption: string;
    sources: (sources: string) => string;
    resume: string;
    coverLetter: string;
    notes: string;
  };
};

const fr: RecruitmentCopy = {
  jobs: {
    title: 'Offres d’emploi',
    subtitle: 'Créez et publiez vos offres. Les compétences saisies alimentent le score ATS.',
    newJob: 'Nouvelle offre',
    cancel: 'Annuler',
    create: 'Créer l’offre',
    creating: 'Création...',
    loadError: 'Impossible de charger les offres.',
    createError: 'La création de l’offre a échoué.',
    empty: 'Aucune offre pour le moment.',
    noDetail: 'Aucun détail',
    titleField: 'Intitulé du poste',
    titlePlaceholder: 'Développeur backend senior',
    location: 'Lieu',
    locationPlaceholder: 'Alger',
    contract: 'Contrat',
    workMode: 'Modalité',
    onSite: 'Sur site',
    remote: 'Télétravail',
    hybrid: 'Hybride',
    skills: 'Compétences requises',
    skillsHint:
      'Séparez par des virgules. Ce sont ces compétences qui sont comparées au CV et à la lettre pour calculer le score de correspondance.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    description: 'Description',
    descriptionPlaceholder: 'Missions, profil recherché, avantages...',
    publish: 'Publier',
    close: 'Clôturer',
    archive: 'Archiver',
    reopen: 'Réouvrir',
    statusDraft: 'Brouillon',
    statusPublished: 'Publiée',
    statusClosed: 'Clôturée',
    statusArchived: 'Archivée',
    applicants: (count) => `${count} candidature${count > 1 ? 's' : ''}`,
  },
  decision: {
    title: 'Décision finale',
    reasonLabel: 'Motif (obligatoire)',
    reasonPlaceholder: 'Ex. : profil retenu après entretien technique du 12/11.',
    reasonRequired: 'Un motif est obligatoire pour une décision finale.',
    saveError: 'La décision n’a pas pu être enregistrée.',
    hire: 'Embaucher',
    reject: 'Refuser',
    alreadyDecided: (status) =>
      `Ce dossier est déjà tranché : ${status}. Le motif figure dans l’historique ci-dessous.`,
  },
  score: {
    caption:
      'Score calculé sur le texte du CV, la lettre de motivation et les notes',
    sources: (sources) => ` (sources : ${sources})`,
    resume: 'CV',
    coverLetter: 'lettre',
    notes: 'notes',
  },
};

const en: RecruitmentCopy = {
  jobs: {
    title: 'Job postings',
    subtitle: 'Create and publish your postings. The skills you enter feed the ATS score.',
    newJob: 'New posting',
    cancel: 'Cancel',
    create: 'Create posting',
    creating: 'Creating...',
    loadError: 'Could not load postings.',
    createError: 'Creating the posting failed.',
    empty: 'No posting yet.',
    noDetail: 'No detail',
    titleField: 'Job title',
    titlePlaceholder: 'Senior backend developer',
    location: 'Location',
    locationPlaceholder: 'Algiers',
    contract: 'Contract',
    workMode: 'Work mode',
    onSite: 'On site',
    remote: 'Remote',
    hybrid: 'Hybrid',
    skills: 'Required skills',
    skillsHint:
      'Separate with commas. These skills are compared against the resume and cover letter to compute the match score.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    description: 'Description',
    descriptionPlaceholder: 'Responsibilities, profile, benefits...',
    publish: 'Publish',
    close: 'Close',
    archive: 'Archive',
    reopen: 'Reopen',
    statusDraft: 'Draft',
    statusPublished: 'Published',
    statusClosed: 'Closed',
    statusArchived: 'Archived',
    applicants: (count) => `${count} application${count > 1 ? 's' : ''}`,
  },
  decision: {
    title: 'Final decision',
    reasonLabel: 'Reason (required)',
    reasonPlaceholder: 'E.g. profile retained after the technical interview on 12/11.',
    reasonRequired: 'A reason is required for a final decision.',
    saveError: 'The decision could not be saved.',
    hire: 'Hire',
    reject: 'Reject',
    alreadyDecided: (status) =>
      `This application is already decided: ${status}. The reason is in the history below.`,
  },
  score: {
    caption: 'Score computed from the resume text, the cover letter and the notes',
    sources: (sources) => ` (sources: ${sources})`,
    resume: 'resume',
    coverLetter: 'cover letter',
    notes: 'notes',
  },
};

const tr: RecruitmentCopy = {
  jobs: {
    title: 'İş ilanları',
    subtitle: 'İlanlarınızı oluşturun ve yayınlayın. Girdiğiniz beceriler ATS puanını besler.',
    newJob: 'Yeni ilan',
    cancel: 'İptal',
    create: 'İlanı oluştur',
    creating: 'Oluşturuluyor...',
    loadError: 'İlanlar yüklenemedi.',
    createError: 'İlan oluşturulamadı.',
    empty: 'Henüz ilan yok.',
    noDetail: 'Ayrıntı yok',
    titleField: 'Pozisyon adı',
    titlePlaceholder: 'Kıdemli backend geliştirici',
    location: 'Konum',
    locationPlaceholder: 'Cezayir',
    contract: 'Sözleşme',
    workMode: 'Çalışma biçimi',
    onSite: 'Ofiste',
    remote: 'Uzaktan',
    hybrid: 'Hibrit',
    skills: 'Gerekli beceriler',
    skillsHint:
      'Virgülle ayırın. Eşleşme puanı hesaplanırken bu beceriler özgeçmiş ve ön yazı ile karşılaştırılır.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    description: 'Açıklama',
    descriptionPlaceholder: 'Görevler, aranan profil, avantajlar...',
    publish: 'Yayınla',
    close: 'Kapat',
    archive: 'Arşivle',
    reopen: 'Yeniden aç',
    statusDraft: 'Taslak',
    statusPublished: 'Yayında',
    statusClosed: 'Kapatıldı',
    statusArchived: 'Arşivlendi',
    applicants: (count) => `${count} başvuru`,
  },
  decision: {
    title: 'Nihai karar',
    reasonLabel: 'Gerekçe (zorunlu)',
    reasonPlaceholder: 'Örn. 12/11 teknik görüşmesi sonrası profil uygun bulundu.',
    reasonRequired: 'Nihai karar için gerekçe zorunludur.',
    saveError: 'Karar kaydedilemedi.',
    hire: 'İşe al',
    reject: 'Reddet',
    alreadyDecided: (status) => `Bu başvuru zaten sonuçlandı: ${status}. Gerekçe aşağıdaki geçmişte.`,
  },
  score: {
    caption: 'Puan özgeçmiş metni, ön yazı ve notlardan hesaplanır',
    sources: (sources) => ` (kaynaklar: ${sources})`,
    resume: 'özgeçmiş',
    coverLetter: 'ön yazı',
    notes: 'notlar',
  },
};

const ar: RecruitmentCopy = {
  jobs: {
    title: 'عروض العمل',
    subtitle: 'أنشئ عروضك وانشرها. المهارات التي تدخلها تُغذّي نتيجة ATS.',
    newJob: 'عرض جديد',
    cancel: 'إلغاء',
    create: 'إنشاء العرض',
    creating: 'جارٍ الإنشاء...',
    loadError: 'تعذّر تحميل العروض.',
    createError: 'فشل إنشاء العرض.',
    empty: 'لا يوجد عرض بعد.',
    noDetail: 'لا تفاصيل',
    titleField: 'عنوان الوظيفة',
    titlePlaceholder: 'مطوّر خلفية أول',
    location: 'الموقع',
    locationPlaceholder: 'الجزائر',
    contract: 'نوع العقد',
    workMode: 'نمط العمل',
    onSite: 'في المقر',
    remote: 'عن بُعد',
    hybrid: 'مختلط',
    skills: 'المهارات المطلوبة',
    skillsHint:
      'افصل بفواصل. تُقارن هذه المهارات بالسيرة الذاتية ورسالة التقديم لحساب نتيجة المطابقة.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    description: 'الوصف',
    descriptionPlaceholder: 'المهام، الملف المطلوب، المزايا...',
    publish: 'نشر',
    close: 'إغلاق',
    archive: 'أرشفة',
    reopen: 'إعادة فتح',
    statusDraft: 'مسودة',
    statusPublished: 'منشور',
    statusClosed: 'مغلق',
    statusArchived: 'مؤرشف',
    applicants: (count) => `${count} طلب`,
  },
  decision: {
    title: 'القرار النهائي',
    reasonLabel: 'السبب (إلزامي)',
    reasonPlaceholder: 'مثال: تم اختيار الملف بعد المقابلة التقنية في 12/11.',
    reasonRequired: 'السبب إلزامي للقرار النهائي.',
    saveError: 'تعذّر حفظ القرار.',
    hire: 'توظيف',
    reject: 'رفض',
    alreadyDecided: (status) => `تم البتّ في هذا الملف: ${status}. السبب في السجل أدناه.`,
  },
  score: {
    caption: 'تُحسب النتيجة من نص السيرة الذاتية ورسالة التقديم والملاحظات',
    sources: (sources) => ` (المصادر: ${sources})`,
    resume: 'السيرة',
    coverLetter: 'رسالة التقديم',
    notes: 'الملاحظات',
  },
};

export const recruitmentCopyByLocale: Record<AppLocale, RecruitmentCopy> = {
  fr,
  en,
  tr,
  ar,
};

export function getRecruitmentCopy(locale: AppLocale): RecruitmentCopy {
  return recruitmentCopyByLocale[locale] ?? fr;
}
