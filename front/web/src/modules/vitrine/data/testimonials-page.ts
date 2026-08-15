import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /testimonials par locale (issues #3334 + honnêteté #2726).
// Les témoignages eux-mêmes viennent de data/testimonials.ts (marqués DÉMO).

export type TestimonialsPageContent = {
  hero: {
    badge: string
    headline: string
    subheadline: string
    ctaPrimary: string
    ctaSecondary: string
  }
  stats: {
    footnote: string
    items: Array<{ value: string; label: string }>
  }
  cta: {
    headline: string
    subheadline: string
    primary: string
    secondary: string
  }
}

const fr: TestimonialsPageContent = {
  hero: {
    badge: 'Témoignages',
    headline: 'Ils nous font confiance',
    subheadline: 'Découvrez comment nos clients transforment leur gestion RH avec Leopardo',
    ctaPrimary: "Démarrer l'essai gratuit",
    ctaSecondary: 'Voir les études de cas',
  },
  stats: {
    footnote: "Témoignages et chiffres de démonstration — données fictives à titre d'illustration (aucun client payant à ce jour).",
    items: [
      { value: '4.8/5', label: 'Note moyenne (démo)' },
      { value: '6', label: 'Pays couverts' },
      { value: '1200+', label: 'Tests automatisés backend' },
      { value: '14 j', label: "Durée de l'essai gratuit" },
    ],
  },
  cta: {
    headline: 'Prêt à simplifier votre gestion RH ?',
    subheadline: 'Démarrez votre essai gratuit de 14 jours',
    primary: 'Commencer maintenant',
    secondary: 'Demander une démo',
  },
}

const en: TestimonialsPageContent = {
  hero: {
    badge: 'Testimonials',
    headline: 'They trust us',
    subheadline: 'See how our customers transform their HR management with Leopardo',
    ctaPrimary: 'Start the free trial',
    ctaSecondary: 'See case studies',
  },
  stats: {
    footnote: 'Testimonials and figures are demonstration data — fictional, for illustration only (no paying customers yet).',
    items: [
      { value: '4.8/5', label: 'Average rating (demo)' },
      { value: '6', label: 'Countries covered' },
      { value: '1200+', label: 'Automated backend tests' },
      { value: '14 d', label: 'Free trial length' },
    ],
  },
  cta: {
    headline: 'Ready to simplify your HR management?',
    subheadline: 'Start your free 14-day trial',
    primary: 'Start now',
    secondary: 'Request a demo',
  },
}

const tr: TestimonialsPageContent = {
  hero: {
    badge: 'Referanslar',
    headline: 'Bize güveniyorlar',
    subheadline: 'Müşterilerimizin Leopardo ile İK yönetimini nasıl dönüştürdüğünü keşfedin',
    ctaPrimary: 'Ücretsiz denemeye başla',
    ctaSecondary: 'Vaka çalışmalarını gör',
  },
  stats: {
    footnote: 'Referanslar ve rakamlar tanıtım verisidir — yalnızca örnek amaçlı kurgusaldır (henüz ödeme yapan müşteri yok).',
    items: [
      { value: '4.8/5', label: 'Ortalama puan (demo)' },
      { value: '6', label: 'Kapsanan ülke' },
      { value: '1200+', label: 'Otomatikleştirilmiş backend testi' },
      { value: '14 g', label: 'Ücretsiz deneme süresi' },
    ],
  },
  cta: {
    headline: 'İK yönetiminizi basitleştirmeye hazır mısınız?',
    subheadline: '14 günlük ücretsiz denemenize başlayın',
    primary: 'Hemen başla',
    secondary: 'Demo iste',
  },
}

const ar: TestimonialsPageContent = {
  hero: {
    badge: 'الشهادات',
    headline: 'يثقون بنا',
    subheadline: 'اكتشف كيف يحوّل عملاؤنا إدارة الموارد البشرية مع ليوباردو',
    ctaPrimary: 'ابدأ النسخة التجريبية المجانية',
    ctaSecondary: 'عرض دراسات الحالة',
  },
  stats: {
    footnote: 'الشهادات والأرقام بيانات توضيحية — خيالية لأغراض العرض فقط (لا يوجد عملاء يدفعون حتى الآن).',
    items: [
      { value: '4.8/5', label: 'متوسط التقييم (عرض)' },
      { value: '6', label: 'دول مغطاة' },
      { value: '1200+', label: 'اختبار آلي للخلفية' },
      { value: '14 يوم', label: 'مدة النسخة التجريبية' },
    ],
  },
  cta: {
    headline: 'هل أنت مستعد لتبسيط إدارة الموارد البشرية؟',
    subheadline: 'ابدأ نسختك التجريبية المجانية لمدة 14 يوماً',
    primary: 'ابدأ الآن',
    secondary: 'اطلب عرضاً توضيحياً',
  },
}

const testimonialsPageByLocale: Record<AppLocale, TestimonialsPageContent> = { fr, en, tr, ar }

export function getTestimonialsPageContent(locale: AppLocale): TestimonialsPageContent {
  return testimonialsPageByLocale[locale] ?? fr
}

