import type { AppLocale } from '@/lib/i18n'

export type PricingPlan = {
  name: string
  price: string
  annualPrice: string
  period: string
  annualPeriod: string
  description: string
  features: string[]
  cta: string
  popular: boolean
  gradient: string
  employeeLimit: string
}

const pricingByLocale: Record<AppLocale, PricingPlan[]> = {
  fr: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '23',
      period: '/mois',
      annualPeriod: '/mois, facture annuellement',
      description: 'Ideal pour les petites equipes',
      employeeLimit: '1-10 employes',
      features: ['Jusqu\'a 10 employes', 'Pointage de base', 'Gestion des absences', 'Support email', 'Export PDF', 'Application mobile'],
      cta: 'Commencer gratuitement',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '63',
      period: '/mois',
      annualPeriod: '/mois, facture annuellement',
      description: 'Pour les entreprises en croissance',
      employeeLimit: '11-100 employes',
      features: ['Jusqu\'a 100 employes', 'Paie automatisee multi-pays', 'Leo IA Assistant', 'API & Webhooks', 'Rapports avances', 'Support prioritaire 24/7', 'Cabinet numerique', 'Calendrier partage'],
      cta: 'Essai gratuit 14 jours',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'Sur devis',
      annualPrice: 'Sur devis',
      period: '',
      annualPeriod: '',
      description: 'Solution sur mesure illimitee',
      employeeLimit: 'Illimite',
      features: ['Employes illimites', 'Deploiement dedie on-premise ou cloud', 'SSO SAML/OIDC', 'SLA 99.99%', 'Gestionnaire de compte dedie', 'Formation et onboarding personnalise', 'Audit trail complet', 'Multi-pays et multi-devises'],
      cta: 'Contacter les ventes',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  en: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '23',
      period: '/month',
      annualPeriod: '/mo, billed annually',
      description: 'Best for small teams',
      employeeLimit: '1-10 employees',
      features: ['Up to 10 employees', 'Core attendance', 'Leave management', 'Email support', 'PDF export', 'Mobile app'],
      cta: 'Start free',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '63',
      period: '/month',
      annualPeriod: '/mo, billed annually',
      description: 'For scaling operations',
      employeeLimit: '11-100 employees',
      features: ['Up to 100 employees', 'Multi-country automated payroll', 'Leo AI assistant', 'API & webhooks', 'Advanced reporting', 'Priority 24/7 support', 'Digital document vault', 'Shared calendar'],
      cta: 'Start 14-day trial',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'Custom',
      annualPrice: 'Custom',
      period: '',
      annualPeriod: '',
      description: 'Unlimited tailored deployment',
      employeeLimit: 'Unlimited',
      features: ['Unlimited employees', 'Dedicated on-premise or cloud deployment', 'SSO SAML/OIDC', '99.99% SLA', 'Dedicated account manager', 'Custom onboarding and training', 'Complete audit trail', 'Multi-country and currency'],
      cta: 'Talk to sales',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  tr: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '23',
      period: '/ay',
      annualPeriod: '/ay, yillik faturalanir',
      description: 'Kucuk ekipler icin ideal',
      employeeLimit: '1-10 calisan',
      features: ['10 calisana kadar', 'Temel takip', 'Izin yonetimi', 'E-posta destegi', 'PDF aktarimi', 'Mobil uygulama'],
      cta: 'Ucretsiz basla',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '63',
      period: '/ay',
      annualPeriod: '/ay, yillik faturalanir',
      description: 'Buyuyen sirketler icin',
      employeeLimit: '11-100 calisan',
      features: ['100 calisana kadar', 'Cok ulkeli otomatik bordro', 'Leo IA asistani', 'API ve webhook', 'Gelismis raporlar', 'Oncelikli 7/24 destek', 'Dijital belge kasasi', 'Paylasilmis takvim'],
      cta: '14 gun dene',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'Teklif',
      annualPrice: 'Teklif',
      period: '',
      annualPeriod: '',
      description: 'Sinirsiz kurumsal cozum',
      employeeLimit: 'Sinirsiz',
      features: ['Sinirsiz calisan', 'Ozel yerlestirme veya bulut dagitimi', 'SSO SAML/OIDC', '99.99% SLA', 'Atanmis hesap yoneticisi', 'Ozel onboarding ve egitim', 'Tam denetim izi', 'Cok ulke ve para birimi'],
      cta: 'Satis ile gorus',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  ar: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '23',
      period: '/شهريا',
      annualPeriod: '/شهريا، يفوتر سنويا',
      description: 'مثالي للفرق الصغيرة',
      employeeLimit: '1-10 موظفين',
      features: ['حتى 10 موظفين', 'حضور اساسي', 'ادارة الاجازات', 'دعم بالبريد', 'تصدير PDF', 'تطبيق الجوال'],
      cta: 'ابدأ مجانا',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '63',
      period: '/شهريا',
      annualPeriod: '/شهريا، يفوتر سنويا',
      description: 'للشركات المتنامية',
      employeeLimit: '11-100 موظف',
      features: ['حتى 100 موظف', 'رواتب مؤتمتة متعددة البلدان', 'مساعد Leo IA', 'API و Webhooks', 'تقارير متقدمة', 'دعم اولوية 24/7', 'خزنة مستندات رقمية', 'تقويم مشترك'],
      cta: 'تجربة 14 يوما',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: 'حسب الطلب',
      annualPrice: 'حسب الطلب',
      period: '',
      annualPeriod: '',
      description: 'حل مؤسسي غير محدود',
      employeeLimit: 'غير محدود',
      features: ['موظفون غير محدودين', 'نشر مخصص محلي أو سحابي', 'SSO SAML/OIDC', 'SLA 99.99%', 'مدير حساب مخصص', 'تهيئة وتدريب مخصص', 'سجل تدقيق كامل', 'متعدد البلدان والعملات'],
      cta: 'تواصل مع المبيعات',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
}

export function getPricingPlans(locale: AppLocale): PricingPlan[] {
  return pricingByLocale[locale] ?? pricingByLocale.fr
}
