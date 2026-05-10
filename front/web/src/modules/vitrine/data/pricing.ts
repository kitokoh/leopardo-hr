import type { AppLocale } from '@/lib/i18n'

export type PricingPlan = {
  name: string
  price: string
  period: string
  description: string
  features: string[]
  cta: string
  popular: boolean
  gradient: string
}

const pricingByLocale: Record<AppLocale, PricingPlan[]> = {
  fr: [
    {
      name: 'Starter',
      price: '29',
      period: '/mois',
      description: 'Ideal pour les petites equipes',
      features: ['Jusqu a 10 employes', 'Pointage de base', 'Gestion des absences', 'Support email', 'Export PDF'],
      cta: 'Commencer gratuitement',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      period: '/mois',
      description: 'Pour les entreprises en croissance',
      features: ['Jusqu a 100 employes', 'Paie automatisee', 'Leo IA Assistant', 'API & Webhooks', 'Rapports avances', 'Support prioritaire 24/7'],
      cta: 'Essai gratuit 14 jours',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'Sur devis',
      period: '',
      description: 'Solution sur mesure illimitee',
      features: ['Employes illimites', 'Deploiement dedie', 'SSO / SAML', 'SLA 99.99%', 'Gestionnaire dedie', 'Formation personnalisee'],
      cta: 'Contacter les ventes',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  en: [
    {
      name: 'Starter',
      price: '29',
      period: '/month',
      description: 'Best for small teams',
      features: ['Up to 10 employees', 'Core attendance', 'Leave management', 'Email support', 'PDF export'],
      cta: 'Start free',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      period: '/month',
      description: 'For scaling operations',
      features: ['Up to 100 employees', 'Automated payroll', 'Leo AI assistant', 'API & webhooks', 'Advanced reporting', 'Priority 24/7 support'],
      cta: 'Start 14-day trial',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'Custom',
      period: '',
      description: 'Unlimited tailored deployment',
      features: ['Unlimited employees', 'Dedicated deployment', 'SSO / SAML', '99.99% SLA', 'Dedicated manager', 'Custom onboarding'],
      cta: 'Talk to sales',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  tr: [
    {
      name: 'Starter',
      price: '29',
      period: '/ay',
      description: 'Kucuk ekipler icin ideal',
      features: ['10 calisana kadar', 'Temel takip', 'Izin yonetimi', 'E-posta destegi', 'PDF aktarimi'],
      cta: 'Ucretsiz basla',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      period: '/ay',
      description: 'Buyuyen sirketler icin',
      features: ['100 calisana kadar', 'Otomatik bordro', 'Leo IA asistani', 'API ve webhook', 'Gelismis raporlar', 'Oncelikli 7/24 destek'],
      cta: '14 gun dene',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'Teklif',
      period: '',
      description: 'Sinirsiz kurumsal cozum',
      features: ['Sinirsiz calisan', 'Ayrik kurulum', 'SSO / SAML', '99.99% SLA', 'Atanmis yonetici', 'Ozel onboarding'],
      cta: 'Satis ile gorus',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  ar: [
    {
      name: 'Starter',
      price: '29',
      period: '/شهريا',
      description: 'مثالي للفرق الصغيرة',
      features: ['حتى 10 موظفين', 'حضور اساسي', 'ادارة الاجازات', 'دعم بالبريد', 'تصدير PDF'],
      cta: 'ابدأ مجانا',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      period: '/شهريا',
      description: 'للشركات المتنامية',
      features: ['حتى 100 موظف', 'رواتب مؤتمتة', 'مساعد Leo IA', 'API و Webhooks', 'تقارير متقدمة', 'دعم اولوية 24/7'],
      cta: 'تجربة 14 يوما',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'حسب الطلب',
      period: '',
      description: 'حل مؤسسي غير محدود',
      features: ['موظفون غير محدودين', 'نشر مخصص', 'SSO / SAML', 'SLA 99.99%', 'مدير حساب', 'تهيئة مخصصة'],
      cta: 'تواصل مع المبيعات',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
}

export function getPricingPlans(locale: AppLocale): PricingPlan[] {
  return pricingByLocale[locale] ?? pricingByLocale.fr
}
