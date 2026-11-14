import type { AppLocale } from '@/lib/i18n';

/**
 * Libellés des outils CANDIDAT du portail carrières (ATS #9461).
 *
 * Séparé de `tenant-careers.ts` : ce dernier porte le portail de consultation
 * des offres, qui existait déjà et est couvert par ses propres tests. Mélanger
 * les deux ferait grossir un fichier déjà long et rendrait les diffs de l'un
 * illisibles à cause de l'autre.
 *
 * Deux outils :
 *  - **suivi de candidature** — le candidat retrouve l'état de ses dossiers ;
 *  - **éditeur de CV** — il construit un CV et l'exporte en PDF.
 */

export type CandidateToolsCopy = {
  track: {
    title: string;
    intro: string;
    emailLabel: string;
    emailPlaceholder: string;
    submit: string;
    submitting: string;
    empty: string;
    emptyHint: string;
    error: string;
    invalidEmail: string;
    reference: string;
    status: string;
    appliedAt: string;
    lastUpdate: string;
    retention: (days: number) => string;
    resultsTitle: (count: number) => string;
    backToJobs: string;
    privacyNote: string;
  };
  cv: {
    title: string;
    intro: string;
    fullName: string;
    headline: string;
    email: string;
    phone: string;
    location: string;
    summary: string;
    experience: string;
    education: string;
    skills: string;
    skillsHint: string;
    skillsPlaceholder: string;
    addExperience: string;
    addEducation: string;
    role: string;
    company: string;
    period: string;
    description: string;
    degree: string;
    school: string;
    remove: string;
    preview: string;
    download: string;
    saved: string;
    saveHint: string;
    emptyPreview: string;
    backToJobs: string;
    privacyNote: string;
  };
  statusLabels: Record<string, string>;
};

const fr: CandidateToolsCopy = {
  track: {
    title: 'Suivre mes candidatures',
    intro:
      'Saisissez l’adresse email utilisée lors de votre candidature pour consulter l’état de vos dossiers.',
    emailLabel: 'Adresse email',
    emailPlaceholder: 'vous@exemple.com',
    submit: 'Voir mes candidatures',
    submitting: 'Recherche en cours...',
    empty: 'Aucune candidature trouvée pour cette adresse.',
    emptyHint:
      'Vérifiez l’adresse saisie : c’est celle utilisée au moment de postuler. Les candidatures déposées chez une autre entreprise n’apparaissent pas ici.',
    error: 'La recherche a échoué. Merci de réessayer dans un instant.',
    invalidEmail: 'Merci de saisir une adresse email valide.',
    reference: 'Référence',
    status: 'Statut',
    appliedAt: 'Déposée le',
    lastUpdate: 'Dernière mise à jour',
    retention: (days) =>
      `Vos données sont conservées ${days} jours à compter de votre candidature, puis supprimées automatiquement.`,
    resultsTitle: (count) => `${count} candidature${count > 1 ? 's' : ''}`,
    backToJobs: 'Retour aux offres',
    privacyNote:
      'Seuls l’intitulé du poste, le statut et les dates vous sont communiqués. Les notes internes du recruteur restent confidentielles.',
  },
  cv: {
    title: 'Créer mon CV',
    intro:
      'Renseignez vos informations : votre CV se met en forme automatiquement et vous pouvez le télécharger en PDF.',
    fullName: 'Nom complet',
    headline: 'Titre professionnel',
    email: 'Email',
    phone: 'Téléphone',
    location: 'Ville',
    summary: 'Profil',
    experience: 'Expériences',
    education: 'Formations',
    skills: 'Compétences',
    skillsHint: 'Séparez les compétences par des virgules.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    addExperience: 'Ajouter une expérience',
    addEducation: 'Ajouter une formation',
    role: 'Poste',
    company: 'Entreprise',
    period: 'Période',
    description: 'Description',
    degree: 'Diplôme',
    school: 'Établissement',
    remove: 'Supprimer',
    preview: 'Aperçu',
    download: 'Télécharger en PDF',
    saved: 'Enregistré sur cet appareil',
    saveHint:
      'Votre CV est enregistré dans ce navigateur. Il n’est envoyé à aucune entreprise tant que vous ne joignez pas le fichier à une candidature.',
    emptyPreview: 'Commencez par renseigner votre nom pour voir l’aperçu.',
    backToJobs: 'Retour aux offres',
    privacyNote:
      'Aucune donnée n’est transmise à nos serveurs : le CV reste sur votre appareil jusqu’à ce que vous le joigniez vous-même à une candidature.',
  },
  statusLabels: {
    new: 'Reçue',
    screening: 'En cours d’étude',
    interview: 'Entretien',
    offer: 'Proposition',
    hired: 'Recruté',
    rejected: 'Non retenue',
    withdrawn: 'Retirée',
  },
};

const en: CandidateToolsCopy = {
  track: {
    title: 'Track my applications',
    intro: 'Enter the email address you used to apply to see the status of your applications.',
    emailLabel: 'Email address',
    emailPlaceholder: 'you@example.com',
    submit: 'Show my applications',
    submitting: 'Searching...',
    empty: 'No application found for this address.',
    emptyHint:
      'Check the address you entered: it must be the one used when you applied. Applications sent to another company do not appear here.',
    error: 'The search failed. Please try again in a moment.',
    invalidEmail: 'Please enter a valid email address.',
    reference: 'Reference',
    status: 'Status',
    appliedAt: 'Applied on',
    lastUpdate: 'Last update',
    retention: (days) =>
      `Your data is kept for ${days} days from your application, then deleted automatically.`,
    resultsTitle: (count) => `${count} application${count > 1 ? 's' : ''}`,
    backToJobs: 'Back to jobs',
    privacyNote:
      'Only the job title, status and dates are shared with you. Recruiter notes stay confidential.',
  },
  cv: {
    title: 'Build my resume',
    intro:
      'Fill in your details: your resume is formatted automatically and you can download it as a PDF.',
    fullName: 'Full name',
    headline: 'Professional headline',
    email: 'Email',
    phone: 'Phone',
    location: 'City',
    summary: 'Summary',
    experience: 'Experience',
    education: 'Education',
    skills: 'Skills',
    skillsHint: 'Separate skills with commas.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    addExperience: 'Add experience',
    addEducation: 'Add education',
    role: 'Role',
    company: 'Company',
    period: 'Period',
    description: 'Description',
    degree: 'Degree',
    school: 'School',
    remove: 'Remove',
    preview: 'Preview',
    download: 'Download as PDF',
    saved: 'Saved on this device',
    saveHint:
      'Your resume is stored in this browser. It is not sent to any company until you attach the file to an application.',
    emptyPreview: 'Start by entering your name to see the preview.',
    backToJobs: 'Back to jobs',
    privacyNote:
      'No data is sent to our servers: the resume stays on your device until you attach it to an application yourself.',
  },
  statusLabels: {
    new: 'Received',
    screening: 'Under review',
    interview: 'Interview',
    offer: 'Offer',
    hired: 'Hired',
    rejected: 'Not selected',
    withdrawn: 'Withdrawn',
  },
};

const tr: CandidateToolsCopy = {
  track: {
    title: 'Başvurularımı takip et',
    intro: 'Başvuru sırasında kullandığınız e-posta adresini girin ve başvurularınızın durumunu görün.',
    emailLabel: 'E-posta adresi',
    emailPlaceholder: 'siz@ornek.com',
    submit: 'Başvurularımı göster',
    submitting: 'Aranıyor...',
    empty: 'Bu adres için başvuru bulunamadı.',
    emptyHint:
      'Girdiğiniz adresi kontrol edin: başvuru sırasında kullanılan adres olmalıdır. Başka bir şirkete yapılan başvurular burada görünmez.',
    error: 'Arama başarısız oldu. Lütfen biraz sonra tekrar deneyin.',
    invalidEmail: 'Lütfen geçerli bir e-posta adresi girin.',
    reference: 'Referans',
    status: 'Durum',
    appliedAt: 'Başvuru tarihi',
    lastUpdate: 'Son güncelleme',
    retention: (days) => `Verileriniz başvurunuzdan itibaren ${days} gün saklanır, ardından otomatik silinir.`,
    resultsTitle: (count) => `${count} başvuru`,
    backToJobs: 'İlanlara dön',
    privacyNote:
      'Sizinle yalnızca pozisyon adı, durum ve tarihler paylaşılır. İşe alım notları gizli kalır.',
  },
  cv: {
    title: 'Özgeçmiş oluştur',
    intro: 'Bilgilerinizi girin: özgeçmişiniz otomatik biçimlendirilir ve PDF olarak indirebilirsiniz.',
    fullName: 'Ad soyad',
    headline: 'Mesleki unvan',
    email: 'E-posta',
    phone: 'Telefon',
    location: 'Şehir',
    summary: 'Profil',
    experience: 'Deneyim',
    education: 'Eğitim',
    skills: 'Beceriler',
    skillsHint: 'Becerileri virgülle ayırın.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    addExperience: 'Deneyim ekle',
    addEducation: 'Eğitim ekle',
    role: 'Pozisyon',
    company: 'Şirket',
    period: 'Dönem',
    description: 'Açıklama',
    degree: 'Derece',
    school: 'Kurum',
    remove: 'Kaldır',
    preview: 'Önizleme',
    download: 'PDF olarak indir',
    saved: 'Bu cihazda kaydedildi',
    saveHint:
      'Özgeçmişiniz bu tarayıcıda saklanır. Dosyayı bir başvuruya eklemediğiniz sürece hiçbir şirkete gönderilmez.',
    emptyPreview: 'Önizlemeyi görmek için adınızı girin.',
    backToJobs: 'İlanlara dön',
    privacyNote:
      'Sunucularımıza hiçbir veri gönderilmez: özgeçmiş, siz bir başvuruya ekleyene kadar cihazınızda kalır.',
  },
  statusLabels: {
    new: 'Alındı',
    screening: 'İnceleniyor',
    interview: 'Mülakat',
    offer: 'Teklif',
    hired: 'İşe alındı',
    rejected: 'Olumsuz',
    withdrawn: 'Geri çekildi',
  },
};

const ar: CandidateToolsCopy = {
  track: {
    title: 'تتبّع طلباتي',
    intro: 'أدخل البريد الإلكتروني الذي استخدمته عند التقديم لعرض حالة طلباتك.',
    emailLabel: 'البريد الإلكتروني',
    emailPlaceholder: 'you@example.com',
    submit: 'عرض طلباتي',
    submitting: 'جارٍ البحث...',
    empty: 'لا يوجد طلب بهذا البريد.',
    emptyHint:
      'تحقّق من البريد المُدخل: يجب أن يكون نفسه المستخدم عند التقديم. الطلبات المقدَّمة إلى شركة أخرى لا تظهر هنا.',
    error: 'فشل البحث. يرجى المحاولة بعد قليل.',
    invalidEmail: 'يرجى إدخال بريد إلكتروني صالح.',
    reference: 'المرجع',
    status: 'الحالة',
    appliedAt: 'تاريخ التقديم',
    lastUpdate: 'آخر تحديث',
    retention: (days) => `يُحفظ بياناتك لمدة ${days} يوماً من تاريخ التقديم ثم تُحذف تلقائياً.`,
    resultsTitle: (count) => `${count} طلب`,
    backToJobs: 'العودة إلى الوظائف',
    privacyNote: 'نشاركك اسم الوظيفة والحالة والتواريخ فقط. تبقى ملاحظات المسؤولين سرية.',
  },
  cv: {
    title: 'إنشاء سيرتي الذاتية',
    intro: 'أدخل بياناتك: تُنسَّق سيرتك تلقائياً ويمكنك تنزيلها بصيغة PDF.',
    fullName: 'الاسم الكامل',
    headline: 'المسمى المهني',
    email: 'البريد الإلكتروني',
    phone: 'الهاتف',
    location: 'المدينة',
    summary: 'نبذة',
    experience: 'الخبرات',
    education: 'التكوين',
    skills: 'المهارات',
    skillsHint: 'افصل المهارات بفواصل.',
    skillsPlaceholder: 'PHP, Laravel, PostgreSQL',
    addExperience: 'إضافة خبرة',
    addEducation: 'إضافة تكوين',
    role: 'المنصب',
    company: 'الشركة',
    period: 'الفترة',
    description: 'الوصف',
    degree: 'الشهادة',
    school: 'المؤسسة',
    remove: 'حذف',
    preview: 'معاينة',
    download: 'تنزيل بصيغة PDF',
    saved: 'محفوظة على هذا الجهاز',
    saveHint:
      'تُحفظ سيرتك في هذا المتصفح. لا تُرسل إلى أي شركة حتى تُرفق الملف بطلب تقديم بنفسك.',
    emptyPreview: 'ابدأ بإدخال اسمك لعرض المعاينة.',
    backToJobs: 'العودة إلى الوظائف',
    privacyNote:
      'لا تُرسل أي بيانات إلى خوادمنا: تبقى السيرة على جهازك حتى تُرفقها بطلب تقديم بنفسك.',
  },
  statusLabels: {
    new: 'مستلمة',
    screening: 'قيد الدراسة',
    interview: 'مقابلة',
    offer: 'عرض',
    hired: 'تم التوظيف',
    rejected: 'غير مقبولة',
    withdrawn: 'مسحوبة',
  },
};

export const candidateToolsByLocale: Record<AppLocale, CandidateToolsCopy> = {
  fr,
  en,
  tr,
  ar,
};

export function getCandidateToolsCopy(locale: AppLocale): CandidateToolsCopy {
  return candidateToolsByLocale[locale] ?? fr;
}
