'use client';

import { Fragment, useState } from 'react';
import {
  Navbar,
  HeroSection,
  PricingSection,
  FAQSection,
  CTASection,
  Footer,
  useScrollReveal,
} from '@/modules/vitrine';
import { getPricingPlans } from '@/modules/vitrine/data/pricing';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { Check, Zap } from 'lucide-react';

type ComparisonFeature = {
  name: string;
  starter: boolean;
  business: boolean;
  enterprise: boolean;
};

type ComparisonCategory = {
  category: string;
  features: ComparisonFeature[];
};

type PricingPageCopy = {
  hero: {
    headline: string;
    subheadline: string;
    primary: string;
    secondary: string;
    badge: string;
  };
  plans: {
    title: string;
    subtitle: string;
    badge: string;
    monthly: string;
    annual: string;
    customPrice: string;
    periodAnnual: string;
    periodMonthly: string;
  };
  comparison: {
    badge: string;
    title: string;
    subtitle: string;
    featureColumn: string;
    categories: ComparisonCategory[];
  };
  faq: {
    title: string;
    subtitle: string;
    badge: string;
    all: string;
    categories: string[];
    items: Array<{ id: string; question: string; answer: string; category: string }>;
  };
  cta: {
    badge: string;
    headline: string;
    subheadline: string;
    primary: string;
    secondary: string;
  };
};

const pricingPageCopy: Record<AppLocale, PricingPageCopy> = {
  fr: {
    hero: {
      headline: 'Tarifs clairs pour lancer vos RH sans friction',
      subheadline: 'Choisissez un plan adapte a votre equipe, puis evoluez sans changer de plateforme.',
      primary: 'Essai gratuit',
      secondary: 'Parler aux ventes',
      badge: 'Tarification',
    },
    plans: {
      title: 'Plans Leopardo RH',
      subtitle: 'Simples, evolutifs, sans surprise',
      badge: 'Nos plans',
      monthly: 'Mensuel',
      annual: 'Annuel',
      customPrice: 'Sur devis',
      periodMonthly: '/mois',
      periodAnnual: '/an',
    },
    comparison: {
      badge: 'Comparaison detaillee',
      title: 'Fonctionnalites par plan',
      subtitle: 'Tout ce qui compte avant de choisir',
      featureColumn: 'Fonctionnalites',
      categories: [
        {
          category: 'Gestion RH',
          features: [
            { name: 'Pointage numerique', starter: true, business: true, enterprise: true },
            { name: 'Absences et conges', starter: true, business: true, enterprise: true },
            { name: 'Calendrier partage', starter: true, business: true, enterprise: true },
            { name: 'Evaluations et performance', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Paie et finance',
          features: [
            { name: 'Paie automatisee', starter: true, business: true, enterprise: true },
            { name: 'Bulletins PDF', starter: true, business: true, enterprise: true },
            { name: 'Exports comptables', starter: false, business: true, enterprise: true },
            { name: 'Multi-pays et multi-devises', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Securite et integrations',
          features: [
            { name: 'Cabinet numerique', starter: false, business: true, enterprise: true },
            { name: 'API et webhooks', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML/OIDC', starter: false, business: false, enterprise: true },
            { name: 'Audit trail complet', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      title: 'Questions frequentes',
      subtitle: 'Les points a verifier avant de demarrer',
      badge: 'FAQ tarifs',
      all: 'Tous',
      categories: ['Facturation', 'Essai', 'Support', 'Securite'],
      items: [
        {
          id: 'change-plan',
          question: 'Puis-je changer de plan ?',
          answer: 'Oui. Vous pouvez monter ou descendre de plan selon la croissance de votre entreprise. Les changements sont appliques au cycle de facturation suivant.',
          category: 'Facturation',
        },
        {
          id: 'free-trial',
          question: 'Y a-t-il un essai gratuit ?',
          answer: 'Oui. Les plans incluent un essai de 14 jours pour tester les workflows RH essentiels avant engagement.',
          category: 'Essai',
        },
        {
          id: 'support',
          question: 'Quel support est inclus ?',
          answer: 'Le support email est inclus pour Starter. Business ajoute une priorite de traitement. Enterprise inclut un accompagnement dedie.',
          category: 'Support',
        },
        {
          id: 'security',
          question: 'Les donnees RH sont-elles protegees ?',
          answer: 'Oui. La plateforme applique chiffrement, isolation tenant, roles, journalisation et garde-fous API pour proteger les donnees sensibles.',
          category: 'Securite',
        },
      ],
    },
    cta: {
      badge: 'Pret a demarrer',
      headline: 'Transformez votre gestion RH sans complexite',
      subheadline: 'Demarrez avec un essai gratuit, puis activez les modules dont votre equipe a vraiment besoin.',
      primary: 'Essai gratuit',
      secondary: 'Contacter les ventes',
    },
  },
  en: {
    hero: {
      headline: 'Clear pricing for HR teams ready to scale',
      subheadline: 'Pick the right plan for your team and grow without changing platform.',
      primary: 'Start free trial',
      secondary: 'Talk to sales',
      badge: 'Pricing',
    },
    plans: {
      title: 'Leopardo RH plans',
      subtitle: 'Simple, scalable, predictable',
      badge: 'Our plans',
      monthly: 'Monthly',
      annual: 'Annual',
      customPrice: 'Custom',
      periodMonthly: '/month',
      periodAnnual: '/year',
    },
    comparison: {
      badge: 'Detailed comparison',
      title: 'Features by plan',
      subtitle: 'What matters before you choose',
      featureColumn: 'Features',
      categories: [
        {
          category: 'HR management',
          features: [
            { name: 'Digital attendance', starter: true, business: true, enterprise: true },
            { name: 'Leave management', starter: true, business: true, enterprise: true },
            { name: 'Shared calendar', starter: true, business: true, enterprise: true },
            { name: 'Performance reviews', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Payroll and finance',
          features: [
            { name: 'Automated payroll', starter: true, business: true, enterprise: true },
            { name: 'PDF pay slips', starter: true, business: true, enterprise: true },
            { name: 'Accounting exports', starter: false, business: true, enterprise: true },
            { name: 'Multi-country and currency', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Security and integrations',
          features: [
            { name: 'Digital document vault', starter: false, business: true, enterprise: true },
            { name: 'API and webhooks', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML/OIDC', starter: false, business: false, enterprise: true },
            { name: 'Complete audit trail', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      title: 'Frequently asked questions',
      subtitle: 'What to check before you start',
      badge: 'Pricing FAQ',
      all: 'All',
      categories: ['Billing', 'Trial', 'Support', 'Security'],
      items: [
        {
          id: 'change-plan',
          question: 'Can I change plan later?',
          answer: 'Yes. You can upgrade or downgrade as your company grows. Changes are applied on the next billing cycle.',
          category: 'Billing',
        },
        {
          id: 'free-trial',
          question: 'Is there a free trial?',
          answer: 'Yes. Plans include a 14-day trial so you can test core HR workflows before committing.',
          category: 'Trial',
        },
        {
          id: 'support',
          question: 'What support is included?',
          answer: 'Email support is included with Starter. Business adds priority response. Enterprise includes dedicated onboarding and account support.',
          category: 'Support',
        },
        {
          id: 'security',
          question: 'Are HR data protected?',
          answer: 'Yes. The platform applies encryption, tenant isolation, roles, audit logs and API guardrails for sensitive data.',
          category: 'Security',
        },
      ],
    },
    cta: {
      badge: 'Ready to start',
      headline: 'Upgrade HR operations without adding complexity',
      subheadline: 'Start with a free trial, then enable the modules your team really needs.',
      primary: 'Start free trial',
      secondary: 'Talk to sales',
    },
  },
  tr: {
    hero: {
      headline: 'Buyumeye hazir IK ekipleri icin net fiyatlama',
      subheadline: 'Ekibinize uygun plani secin ve platform degistirmeden olcekleyin.',
      primary: 'Ucretsiz dene',
      secondary: 'Satisla gorus',
      badge: 'Fiyatlar',
    },
    plans: {
      title: 'Leopardo RH planlari',
      subtitle: 'Basit, olceklenebilir, ongorulebilir',
      badge: 'Planlarimiz',
      monthly: 'Aylik',
      annual: 'Yillik',
      customPrice: 'Teklif',
      periodMonthly: '/ay',
      periodAnnual: '/yil',
    },
    comparison: {
      badge: 'Detayli karsilastirma',
      title: 'Plana gore ozellikler',
      subtitle: 'Secimden once bilmeniz gerekenler',
      featureColumn: 'Ozellikler',
      categories: [
        {
          category: 'IK yonetimi',
          features: [
            { name: 'Dijital devam takibi', starter: true, business: true, enterprise: true },
            { name: 'Izin yonetimi', starter: true, business: true, enterprise: true },
            { name: 'Paylasimli takvim', starter: true, business: true, enterprise: true },
            { name: 'Performans degerlendirme', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'Bordro ve finans',
          features: [
            { name: 'Otomatik bordro', starter: true, business: true, enterprise: true },
            { name: 'PDF bordro dokumu', starter: true, business: true, enterprise: true },
            { name: 'Muhasebe dis aktarimi', starter: false, business: true, enterprise: true },
            { name: 'Cok ulke ve cok para birimi', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'Guvenlik ve entegrasyon',
          features: [
            { name: 'Dijital belge kasasi', starter: false, business: true, enterprise: true },
            { name: 'API ve webhook', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML/OIDC', starter: false, business: false, enterprise: true },
            { name: 'Tam denetim kaydi', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      title: 'Sik sorulan sorular',
      subtitle: 'Baslamadan once kontrol edilecek noktalar',
      badge: 'Fiyat SSS',
      all: 'Tumu',
      categories: ['Faturalama', 'Deneme', 'Destek', 'Guvenlik'],
      items: [
        {
          id: 'change-plan',
          question: 'Plani daha sonra degistirebilir miyim?',
          answer: 'Evet. Sirketiniz buyudukce plani yukseltip dusurebilirsiniz. Degisiklikler bir sonraki fatura doneminde uygulanir.',
          category: 'Faturalama',
        },
        {
          id: 'free-trial',
          question: 'Ucretsiz deneme var mi?',
          answer: 'Evet. Temel IK akislarini test etmeniz icin planlarda 14 gunluk deneme bulunur.',
          category: 'Deneme',
        },
        {
          id: 'support',
          question: 'Hangi destek dahil?',
          answer: 'Starter icin e-posta destegi vardir. Business oncelikli destek ekler. Enterprise ozel onboarding ve hesap destegi icerir.',
          category: 'Destek',
        },
        {
          id: 'security',
          question: 'IK verileri korunuyor mu?',
          answer: 'Evet. Platform hassas veriler icin sifreleme, tenant izolasyonu, roller, denetim kayitlari ve API korumalari uygular.',
          category: 'Guvenlik',
        },
      ],
    },
    cta: {
      badge: 'Baslamaya hazir',
      headline: 'IK operasyonlarini karmasiklik eklemeden guclendirin',
      subheadline: 'Ucretsiz deneme ile baslayin, ekibinizin gercekten ihtiyac duydugu modulleri acin.',
      primary: 'Ucretsiz dene',
      secondary: 'Satisla gorus',
    },
  },
  ar: {
    hero: {
      headline: 'أسعار واضحة لفرق الموارد البشرية الجاهزة للنمو',
      subheadline: 'اختر الخطة المناسبة لفريقك ثم وسع العمل بدون تغيير المنصة.',
      primary: 'ابدأ تجربة مجانية',
      secondary: 'تحدث مع المبيعات',
      badge: 'الأسعار',
    },
    plans: {
      title: 'خطط Leopardo RH',
      subtitle: 'بسيطة، قابلة للتوسع، وواضحة',
      badge: 'خططنا',
      monthly: 'شهري',
      annual: 'سنوي',
      customPrice: 'حسب الطلب',
      periodMonthly: '/شهر',
      periodAnnual: '/سنة',
    },
    comparison: {
      badge: 'مقارنة تفصيلية',
      title: 'المزايا حسب الخطة',
      subtitle: 'ما يجب معرفته قبل الاختيار',
      featureColumn: 'المزايا',
      categories: [
        {
          category: 'إدارة الموارد البشرية',
          features: [
            { name: 'الحضور الرقمي', starter: true, business: true, enterprise: true },
            { name: 'إدارة الإجازات', starter: true, business: true, enterprise: true },
            { name: 'تقويم مشترك', starter: true, business: true, enterprise: true },
            { name: 'تقييم الأداء', starter: false, business: true, enterprise: true },
          ],
        },
        {
          category: 'الرواتب والمالية',
          features: [
            { name: 'رواتب آلية', starter: true, business: true, enterprise: true },
            { name: 'قسائم رواتب PDF', starter: true, business: true, enterprise: true },
            { name: 'تصدير محاسبي', starter: false, business: true, enterprise: true },
            { name: 'عدة دول وعملات', starter: false, business: false, enterprise: true },
          ],
        },
        {
          category: 'الأمان والتكاملات',
          features: [
            { name: 'خزنة مستندات رقمية', starter: false, business: true, enterprise: true },
            { name: 'API و Webhooks', starter: false, business: true, enterprise: true },
            { name: 'SSO SAML/OIDC', starter: false, business: false, enterprise: true },
            { name: 'سجل تدقيق كامل', starter: false, business: false, enterprise: true },
          ],
        },
      ],
    },
    faq: {
      title: 'أسئلة شائعة',
      subtitle: 'نقاط مهمة قبل البدء',
      badge: 'أسئلة الأسعار',
      all: 'الكل',
      categories: ['الفوترة', 'التجربة', 'الدعم', 'الأمان'],
      items: [
        {
          id: 'change-plan',
          question: 'هل يمكن تغيير الخطة لاحقا؟',
          answer: 'نعم. يمكنك الترقية أو التخفيض حسب نمو الشركة. يتم تطبيق التغييرات في دورة الفوترة التالية.',
          category: 'الفوترة',
        },
        {
          id: 'free-trial',
          question: 'هل توجد تجربة مجانية؟',
          answer: 'نعم. تتضمن الخطط تجربة لمدة 14 يوما لاختبار مسارات الموارد البشرية الأساسية قبل الاشتراك.',
          category: 'التجربة',
        },
        {
          id: 'support',
          question: 'ما نوع الدعم المتوفر؟',
          answer: 'يتضمن Starter دعما عبر البريد. يضيف Business أولوية في المعالجة. تتضمن Enterprise مرافقة مخصصة.',
          category: 'الدعم',
        },
        {
          id: 'security',
          question: 'هل بيانات الموارد البشرية محمية؟',
          answer: 'نعم. تطبق المنصة التشفير، عزل الشركات، الأدوار، سجلات التدقيق وحواجز API لحماية البيانات الحساسة.',
          category: 'الأمان',
        },
      ],
    },
    cta: {
      badge: 'جاهز للبدء',
      headline: 'طور عمليات الموارد البشرية بدون تعقيد',
      subheadline: 'ابدأ بتجربة مجانية ثم فعّل الوحدات التي يحتاجها فريقك فعلا.',
      primary: 'ابدأ تجربة مجانية',
      secondary: 'تحدث مع المبيعات',
    },
  },
};

function availabilityMark(value: boolean) {
  if (value) {
    return (
      <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/10">
        <Check className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
      </span>
    );
  }

  return <span className="text-slate-400 dark:text-slate-600">-</span>;
}

export default function PricingPage() {
  const [isDark, setIsDark] = useState(false);
  const { locale, direction } = useVitrineLocale();
  const copy = pricingPageCopy[locale] ?? pricingPageCopy.fr;
  const plans = getPricingPlans(locale).map((plan) => ({
    name: plan.name,
    price: Number.isFinite(Number(plan.price)) ? Number(plan.price) : null,
    currency: Number.isFinite(Number(plan.price)) ? 'EUR' : '',
    period: plan.period || copy.plans.customPrice,
    description: plan.description,
    features: plan.features,
    cta: {
      text: plan.cta,
      href: plan.popular ? '/signup?plan=business' : plan.price === '29' ? '/signup?plan=starter' : '/contact?type=enterprise',
    },
    customPriceLabel: copy.plans.customPrice,
    highlighted: plan.popular,
    badge: plan.popular ? copy.plans.badge : undefined,
  }));

  useScrollReveal();

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.primary, href: '/signup' }}
        ctaSecondary={{ text: copy.hero.secondary, href: '/contact?type=enterprise' }}
        badge={{ text: copy.hero.badge, icon: <Zap className="w-3 h-3" /> }}
      />

      <PricingSection
        title={copy.plans.title}
        subtitle={copy.plans.subtitle}
        plans={plans}
        showToggle
        toggleLabel={{ monthly: copy.plans.monthly, annual: copy.plans.annual }}
        periodLabel={{ monthly: copy.plans.periodMonthly, annual: copy.plans.periodAnnual }}
        badge={{ text: copy.plans.badge, icon: <Zap className="w-3 h-3" /> }}
      />

      <section className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 via-white to-slate-50/50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-900/50" />

        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {copy.comparison.badge}
            </div>
            <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {copy.comparison.title}
              <span className="block bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                {copy.comparison.subtitle}
              </span>
            </h2>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="text-left py-4 px-6 font-bold text-slate-900 dark:text-white">
                    {copy.comparison.featureColumn}
                  </th>
                  <th className="text-center py-4 px-6 font-bold text-slate-900 dark:text-white">Starter</th>
                  <th className="text-center py-4 px-6 font-bold text-slate-900 dark:text-white">Business</th>
                  <th className="text-center py-4 px-6 font-bold text-slate-900 dark:text-white">Enterprise</th>
                </tr>
              </thead>
              <tbody>
                {copy.comparison.categories.map((category) => (
                  <Fragment key={category.category}>
                    <tr className="bg-slate-50 dark:bg-slate-900/50">
                      <td colSpan={4} className="py-3 px-6">
                        <h3 className="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">
                          {category.category}
                        </h3>
                      </td>
                    </tr>
                    {category.features.map((feature) => (
                      <tr
                        key={`${category.category}-${feature.name}`}
                        className="border-b border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-colors"
                      >
                        <td className="py-4 px-6 text-slate-700 dark:text-slate-300 font-medium">
                          {feature.name}
                        </td>
                        <td className="py-4 px-6 text-center">{availabilityMark(feature.starter)}</td>
                        <td className="py-4 px-6 text-center">{availabilityMark(feature.business)}</td>
                        <td className="py-4 px-6 text-center">{availabilityMark(feature.enterprise)}</td>
                      </tr>
                    ))}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <FAQSection
        title={copy.faq.title}
        subtitle={copy.faq.subtitle}
        faqs={copy.faq.items}
        categories={copy.faq.categories}
        allLabel={copy.faq.all}
        badge={{ text: copy.faq.badge, icon: <Zap className="w-3 h-3" /> }}
      />

      <CTASection
        headline={copy.cta.headline}
        subheadline={copy.cta.subheadline}
        ctaPrimary={{ text: copy.cta.primary, href: '/signup' }}
        ctaSecondary={{ text: copy.cta.secondary, href: '/contact?type=enterprise' }}
        background="gradient"
        badge={{ text: copy.cta.badge, icon: <Zap className="w-3 h-3" /> }}
      />

      <Footer />
    </div>
  );
}
