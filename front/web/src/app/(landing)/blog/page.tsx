'use client';

import { useState } from 'react';
import {
  Navbar,
  HeroSection,
  CTASection,
  Footer,
  useScrollReveal,
  BlogGrid,
} from '@/modules/vitrine';
import { getBlogPosts } from '@/modules/vitrine/data/blog';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { NewsletterForm } from '@/components/NewsletterForm';
import { motion } from 'framer-motion';
import { BookOpen } from 'lucide-react';

const blogCopy: Record<AppLocale, {
  dateLocale: string;
  readingTime: string;
  hero: { headline: string; subheadline: string; cta: string; badge: string };
  grid: { title: string; subtitle: string; badge: string; all: string; previous: string; next: string };
  newsletter: { badge: string; title: string; description: string; note: string; placeholder: string; submit: string; submitting: string; success: string; error: string };
  cta: { headline: string; subheadline: string; primary: string; secondary: string };
}> = {
  fr: {
    dateLocale: 'fr-FR',
    readingTime: 'min de lecture',
    hero: {
      headline: 'Blog et ressources RH',
      subheadline: 'Guides, articles et conseils pour structurer vos RH, votre paie et votre croissance.',
      cta: "S'inscrire a la newsletter",
      badge: 'Contenu gratuit',
    },
    grid: {
      title: 'Nos articles',
      subtitle: 'Conseils pratiques pour equipes RH ambitieuses',
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
      subheadline: 'Contactez notre equipe pour cadrer vos priorites RH et digitales.',
      primary: 'Nous contacter',
      secondary: 'Essai gratuit',
    },
  },
  en: {
    dateLocale: 'en-US',
    readingTime: 'min read',
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

export default function BlogPage() {
  const [isDark, setIsDark] = useState(false);
  const { locale, direction } = useVitrineLocale();
  const copy = blogCopy[locale] ?? blogCopy.fr;
  const posts = getBlogPosts(locale);
  useScrollReveal();

  const blogCards = posts.map((post) => ({
    slug: post.slug,
    title: post.title,
    excerpt: post.excerpt,
    image: post.image,
    date: post.date,
    author: post.author,
    category: post.category,
    readingTime: post.readingTime,
  }));

  const categories = Array.from(new Set(posts.map((post) => post.category)));

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta, href: '#newsletter' }}
        badge={{ text: copy.hero.badge, icon: <BookOpen className="w-3 h-3" /> }}
      />

      <BlogGrid
        title={copy.grid.title}
        subtitle={copy.grid.subtitle}
        posts={blogCards}
        categories={categories}
        itemsPerPage={9}
        showPagination
        showFilters
        allLabel={copy.grid.all}
        previousLabel={copy.grid.previous}
        nextLabel={copy.grid.next}
        dateLocale={copy.dateLocale}
        readingTimeLabel={copy.readingTime}
        badge={{ text: copy.grid.badge, icon: <BookOpen className="w-3 h-3" /> }}
      />

      <section id="newsletter" className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-emerald-500/10 via-cyan-500/10 to-emerald-500/10 dark:from-emerald-500/5 dark:via-cyan-500/5 dark:to-emerald-500/5" />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="text-center"
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {copy.newsletter.badge}
            </div>
            <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {copy.newsletter.title}
            </h2>
            <p className="text-lg text-slate-600 dark:text-slate-400 mb-8 leading-relaxed">
              {copy.newsletter.description}
            </p>

            <NewsletterForm
              locale={locale}
              placeholder={copy.newsletter.placeholder}
              submitLabel={copy.newsletter.submit}
              submittingLabel={copy.newsletter.submitting}
              successFallback={copy.newsletter.success}
              errorFallback={copy.newsletter.error}
            />

            <p className="text-sm text-slate-500 dark:text-slate-400 mt-4">
              {copy.newsletter.note}
            </p>
          </motion.div>
        </div>
      </section>

      <CTASection
        headline={copy.cta.headline}
        subheadline={copy.cta.subheadline}
        ctaPrimary={{ text: copy.cta.primary, href: '/contact' }}
        ctaSecondary={{ text: copy.cta.secondary, href: '/signup' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
