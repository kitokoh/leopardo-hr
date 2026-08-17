/**
 * #4704 : catalogue localisé du blog (badges, libellés, grille, newsletter,
 * CTA). Vivait dans la page ; déplacé ici pour que la vitrine reste un
 * mécanisme i18n (PA2-I18N-014 : les données sous `modules/vitrine/data/`
 * sont le catalogue — pas des chaînes hardcodées hors catalogue).
 */
import type { AppLocale } from '@/lib/i18n';

export const blogCopy: Record<AppLocale, {
  dateLocale: string;
  readingTime: string;
  archived: string;
  hero: { headline: string; subheadline: string; cta: string; badge: string };
  grid: { title: string; subtitle: string; badge: string; all: string; previous: string; next: string };
  newsletter: { badge: string; title: string; description: string; note: string; placeholder: string; submit: string; submitting: string; success: string; error: string };
  cta: { headline: string; subheadline: string; primary: string; secondary: string };
}> = {
  fr: {
    dateLocale: 'fr-FR',
    readingTime: 'min de lecture',
    archived: 'Archivé',
    hero: {
      headline: 'Blog et ressources RH',
      subheadline: 'Guides, articles et conseils pour structurer vos RH, votre paie et votre croissance.',
      cta: "S'inscrire a la newsletter",
      badge: 'Contenu gratuit',
    },
    grid: {
      title: 'Nos articles',
      subtitle: 'Conseils pratiques pour équipes RH ambitieuses',
      badge: 'Ressources',
      all: 'Tous',
      previous: 'Precedent',
      next: 'Suivant',
    },
    newsletter: {
      badge: 'Newsletter',
      title: 'Recevez nos conseils hebdomadaires',
      description: 'Articles, guides et retours terrain pour lancer une plateforme RH plus solide.',
      note: 'Pas de spam, uniquement des conseils utiles. Desinscription facile.',
      placeholder: 'Votre email',
      submit: "S'inscrire",
      submitting: 'Envoi...',
      success: 'Inscription reussie !',
      error: "Erreur lors de l'inscription",
    },
    cta: {
      headline: 'Besoin d un avis expert ?',
      subheadline: 'Contactez notre équipe pour cadrer vos priorites RH et digitales.',
      primary: 'Nous contacter',
      secondary: 'Essai gratuit',
    },
  },
  en: {
    dateLocale: 'en-US',
    readingTime: 'min read',
    archived: 'Archived',
    hero: {
      headline: 'HR blog and resources',
      subheadline: 'Guides, articles and practical advice to structure HR, payroll and growth.',
      cta: 'Join the newsletter',
      badge: 'Free content',
    },
    grid: {
      title: 'Latest articles',
      subtitle: 'Practical insight for ambitious HR teams',
      badge: 'Resources',
      all: 'All',
      previous: 'Previous',
      next: 'Next',
    },
    newsletter: {
      badge: 'Newsletter',
      title: 'Get weekly HR insights',
      description: 'Articles, guides and field notes to build a stronger HR platform.',
      note: 'No spam, only useful advice. Unsubscribe anytime.',
      placeholder: 'Your email',
      submit: 'Subscribe',
      submitting: 'Sending...',
      success: 'Subscription confirmed!',
      error: 'Unable to subscribe',
    },
    cta: {
      headline: 'Need an expert opinion?',
      subheadline: 'Talk to our team to clarify your HR and digital priorities.',
      primary: 'Contact us',
      secondary: 'Start free trial',
    },
  },
  tr: {
    dateLocale: 'tr-TR',
    readingTime: 'dk okuma',
    archived: 'Arsivlenmis',
    hero: {
      headline: 'IK blogu ve kaynaklar',
      subheadline: 'IK, bordro ve buyumeyi daha sistemli hale getirmek icin rehberler ve pratik oneriler.',
      cta: 'Bultene katil',
      badge: 'Ucretsiz icerik',
    },
    grid: {
      title: 'Son yazilar',
      subtitle: 'Iddiali IK ekipleri icin pratik icgoru',
      badge: 'Kaynaklar',
      all: 'Tumu',
      previous: 'Onceki',
      next: 'Sonraki',
    },
    newsletter: {
      badge: 'Bulten',
      title: 'Haftalik IK onerileri alin',
      description: 'Daha saglam bir IK platformu kurmak icin yazilar, rehberler ve saha notlari.',
      note: 'Spam yok, sadece faydali icerik. Isteyen herkes kolayca ayrilabilir.',
      placeholder: 'E-posta adresiniz',
      submit: 'Kaydol',
      submitting: 'Gonderiliyor...',
      success: 'Kayit tamamlandi!',
      error: 'Kayit yapilamadi',
    },
    cta: {
      headline: 'Uzman gorusune ihtiyaciniz var mi?',
      subheadline: 'IK ve dijital onceliklerinizi netlestirmek icin ekibimizle gorusun.',
      primary: 'Iletisime gec',
      secondary: 'Ucretsiz dene',
    },
  },
  ar: {
    dateLocale: 'ar',
    readingTime: 'دقيقة قراءة',
    archived: 'مؤرشف',
    hero: {
      headline: 'مدونة وموارد الموارد البشرية',
      subheadline: 'أدلة ومقالات ونصائح عملية لتنظيم الموارد البشرية والرواتب والنمو.',
      cta: 'اشترك في النشرة',
      badge: 'محتوى مجاني',
    },
    grid: {
      title: 'أحدث المقالات',
      subtitle: 'رؤى عملية لفرق موارد بشرية طموحة',
      badge: 'الموارد',
      all: 'الكل',
      previous: 'السابق',
      next: 'التالي',
    },
    newsletter: {
      badge: 'النشرة البريدية',
      title: 'استلم نصائح أسبوعية للموارد البشرية',
      description: 'مقالات وأدلة وتجارب عملية لبناء منصة موارد بشرية أقوى.',
      note: 'بدون رسائل مزعجة، فقط نصائح مفيدة. يمكنك إلغاء الاشتراك بسهولة.',
      placeholder: 'بريدك الإلكتروني',
      submit: 'اشترك',
      submitting: 'جار الإرسال...',
      success: 'تم الاشتراك بنجاح!',
      error: 'تعذر الاشتراك',
    },
    cta: {
      headline: 'هل تحتاج إلى رأي خبير؟',
      subheadline: 'تواصل مع فريقنا لتحديد أولويات الموارد البشرية والتحول الرقمي.',
      primary: 'تواصل معنا',
      secondary: 'ابدأ تجربة مجانية',
    },
  },
};
