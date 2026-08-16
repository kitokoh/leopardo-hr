import type { AppLocale } from '@/lib/i18n';

// Contenu des portails carrières publics par tenant ([companySlug]/careers*)
// par locale — issue #4448 (résiduel #4176 : la landing /careers était
// localisée, l'ATS public par tenant restait 100 % FR codé en dur).
// Les données métier (intitulés de poste, descriptions, contrats…) viennent
// de l'API ; ce fichier ne porte que les chaînes UI + metadata.

export type TenantCareersCopy = {
  portalNotFoundTitle: string;
  jobNotFoundTitle: string;
  badge: string;
  fallbackCompanyName: string;
  openingsCount: (count: number) => string;
  noOpenings: string;
  noJobsYet: string;
  joinCompany: (company: string) => string;
  feedLabel: string;
  backToJobs: (company: string) => string;
  skillsTitle: string;
  applyTitle: string;
  form: {
    firstName: string;
    lastName: string;
    email: string;
    phoneOptional: string;
    coverLetterOptional: string;
    resumeLabel: string;
    resumeChoose: string;
    resumeTooBig: string;
    submit: string;
    submitting: string;
    successTitle: string;
    successBody: string;
    genericError: string;
  };
};

const fr: TenantCareersCopy = {
  portalNotFoundTitle: 'Portail carrières introuvable',
  jobNotFoundTitle: 'Offre introuvable',
  badge: 'Carrières',
  fallbackCompanyName: 'notre équipe',
  openingsCount: (count) =>
    `${count} poste${count > 1 ? 's' : ''} actuellement ouvert${count > 1 ? 's' : ''}`,
  noOpenings: 'Aucun poste ouvert pour le moment. Revenez bientôt !',
  noJobsYet: "Il n'y a pas d'offre d'emploi publiée pour le moment.",
  joinCompany: (company) => `Rejoignez ${company}`,
  feedLabel: 'Flux XML (Google Jobs / Indeed)',
  backToJobs: (company) => `Retour aux offres chez ${company}`,
  skillsTitle: 'Compétences recherchées',
  applyTitle: 'Postuler à cette offre',
  form: {
    firstName: 'Prénom',
    lastName: 'Nom',
    email: 'Email',
    phoneOptional: 'Téléphone (optionnel)',
    coverLetterOptional: 'Lettre de motivation (optionnel)',
    resumeLabel: 'CV (PDF, optionnel)',
    resumeChoose: 'Choisir un fichier (max 5 Mo)',
    resumeTooBig: 'Le fichier ne doit pas dépasser 5 Mo.',
    submit: 'Envoyer ma candidature',
    submitting: 'Envoi en cours...',
    successTitle: 'Candidature envoyée !',
    successBody:
      'Merci pour votre intérêt. Notre équipe recrutement va étudier votre profil et vous recontactera rapidement.',
    genericError: 'Une erreur est survenue. Merci de réessayer.',
  },
};

const en: TenantCareersCopy = {
  portalNotFoundTitle: 'Careers portal not found',
  jobNotFoundTitle: 'Job not found',
  badge: 'Careers',
  fallbackCompanyName: 'our team',
  openingsCount: (count) => `${count} open position${count > 1 ? 's' : ''} right now`,
  noOpenings: 'No open positions right now. Please check back soon!',
  noJobsYet: 'No job openings have been published yet.',
  joinCompany: (company) => `Join ${company}`,
  feedLabel: 'XML feed (Google Jobs / Indeed)',
  backToJobs: (company) => `Back to jobs at ${company}`,
  skillsTitle: 'Required skills',
  applyTitle: 'Apply for this position',
  form: {
    firstName: 'First name',
    lastName: 'Last name',
    email: 'Email',
    phoneOptional: 'Phone (optional)',
    coverLetterOptional: 'Cover letter (optional)',
    resumeLabel: 'Resume (PDF, optional)',
    resumeChoose: 'Choose a file (max 5 MB)',
    resumeTooBig: 'The file must not exceed 5 MB.',
    submit: 'Submit my application',
    submitting: 'Submitting...',
    successTitle: 'Application sent!',
    successBody:
      'Thank you for your interest. Our recruiting team will review your profile and get back to you shortly.',
    genericError: 'Something went wrong. Please try again.',
  },
};

const tr: TenantCareersCopy = {
  portalNotFoundTitle: 'Kariyer portalı bulunamadı',
  jobNotFoundTitle: 'İlan bulunamadı',
  badge: 'Kariyer',
  fallbackCompanyName: 'ekibimiz',
  openingsCount: (count) => `Şu anda ${count} açık pozisyon`,
  noOpenings: 'Şu anda açık pozisyon yok. Yakında tekrar kontrol edin!',
  noJobsYet: 'Henüz yayınlanmış iş ilanı yok.',
  joinCompany: (company) => `${company} ekibine katılın`,
  feedLabel: 'XML beslemesi (Google Jobs / Indeed)',
  backToJobs: (company) => `${company} ilanlarına geri dön`,
  skillsTitle: 'Aranan beceriler',
  applyTitle: 'Bu pozisyona başvur',
  form: {
    firstName: 'Ad',
    lastName: 'Soyad',
    email: 'E-posta',
    phoneOptional: 'Telefon (isteğe bağlı)',
    coverLetterOptional: 'Ön yazı (isteğe bağlı)',
    resumeLabel: 'Özgeçmiş (PDF, isteğe bağlı)',
    resumeChoose: 'Dosya seçin (en fazla 5 MB)',
    resumeTooBig: 'Dosya 5 MB sınırını aşmamalıdır.',
    submit: 'Başvurumu gönder',
    submitting: 'Gönderiliyor...',
    successTitle: 'Başvuru gönderildi!',
    successBody:
      'İlginiz için teşekkürler. İşe alım ekibimiz profilinizi inceleyip kısa süre içinde size dönecektir.',
    genericError: 'Bir şeyler ters gitti. Lütfen tekrar deneyin.',
  },
};

const ar: TenantCareersCopy = {
  portalNotFoundTitle: 'بوابة الوظائف غير موجودة',
  jobNotFoundTitle: 'الوظيفة غير موجودة',
  badge: 'الوظائف',
  fallbackCompanyName: 'فريقنا',
  openingsCount: (count) => `${count} وظيفة شاغرة حالياً`,
  noOpenings: 'لا توجد وظائف شاغرة حالياً. عد قريباً!',
  noJobsYet: 'لا توجد وظائف منشورة حتى الآن.',
  joinCompany: (company) => `انضم إلى ${company}`,
  feedLabel: 'خلاصة XML (Google Jobs / Indeed)',
  backToJobs: (company) => `العودة إلى وظائف ${company}`,
  skillsTitle: 'المهارات المطلوبة',
  applyTitle: 'قدّم لهذه الوظيفة',
  form: {
    firstName: 'الاسم الأول',
    lastName: 'الاسم الأخير',
    email: 'البريد الإلكتروني',
    phoneOptional: 'الهاتف (اختياري)',
    coverLetterOptional: 'خطاب التقديم (اختياري)',
    resumeLabel: 'السيرة الذاتية (PDF، اختياري)',
    resumeChoose: 'اختر ملفاً (بحد أقصى 5 ميغابايت)',
    resumeTooBig: 'يجب ألا يتجاوز الملف 5 ميغابايت.',
    submit: 'إرسال طلبي',
    submitting: 'جارٍ الإرسال...',
    successTitle: 'تم إرسال الطلب!',
    successBody:
      'شكراً لاهتمامك. سيقوم فريق التوظيف بمراجعة ملفك والتواصل معك قريباً.',
    genericError: 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
  },
};

export const tenantCareersByLocale: Record<AppLocale, TenantCareersCopy> = {
  fr,
  en,
  tr,
  ar,
};

export function getTenantCareersCopy(locale: AppLocale): TenantCareersCopy {
  return tenantCareersByLocale[locale] ?? fr;
}

export function tenantCareersMetaTitle(locale: AppLocale, company: string, count: number): string {
  const copy = getTenantCareersCopy(locale);
  const brand = company ? `${company} - ` : '';
  return `${brand}${copy.badge} - ${copy.openingsCount(count)}`;
}

export function tenantCareersMetaDescription(locale: AppLocale, company: string): string {
  const copy = getTenantCareersCopy(locale);
  const fallbackNames: Record<AppLocale, string> = {
    fr: 'cette entreprise',
    en: 'this company',
    tr: 'bu şirket',
    ar: 'هذه الشركة',
  };
  const target = company || (fallbackNames[locale] ?? fallbackNames.fr);
  if (locale === 'ar') {
    return `اكتشف فرص العمل المتاحة لدى ${target} وقدّم طلبك عبر الإنترنت في دقائق.`;
  }
  if (locale === 'tr') {
    return `${target} şirketindeki açık iş fırsatlarını keşfedin ve dakikalar içinde çevrimiçi başvurun.`;
  }
  if (locale === 'en') {
    return `Discover open job opportunities at ${target} and apply online in minutes.`;
  }
  return `Découvrez les offres d'emploi ouvertes chez ${target} et postulez en ligne en quelques minutes.`;
}

export function tenantJobMetaTitle(locale: AppLocale, jobTitle: string, company: string): string {
  const at: Record<AppLocale, string> = {
    fr: 'chez',
    en: 'at',
    // turc : la flexion possessive dépend du nom (harmonie vocalique) —
    // un séparateur neutre est plus sûr qu'un suffixe faux.
    tr: '-',
    ar: 'في',
  };
  return company ? `${jobTitle} ${at[locale] ?? at.fr} ${company}` : jobTitle;
}

