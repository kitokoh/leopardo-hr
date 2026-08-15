import type { AppLocale } from '@/lib/i18n'

/**
 * Source de vérité des montants : api/database/seeders/PlanSeeder.php
 *   Starter   29€/mois  (290€/an · 20 employés inclus)
 *   Business  79€/mois  (790€/an · 200 employés inclus)
 *   Enterprise 199€/mois (1990€/an · employés illimités)
 * Essai : 14 jours (décision propriétaire D-E4-01, PRs #3135/#3218).
 * Aucun supplément par employé actif : les plafonds d'employés sont inclus
 * dans le prix (cohérent avec PlanSeeder.max_employees).
 * Tarif annuel affiché au mois arrondi (24/66/166 €) — équivalent 290/790/1990 €/an.
 */

export type PricingPlan = {
  name: string
  price: string
  annualPrice: string
  period: string
  annualPeriod: string
  description: string
  priceNote?: string
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
      annualPrice: '24',
      period: '/mois',
      annualPeriod: '/mois, facturé annuellement',
      description: 'Pour tester Leopardo sur un site, une equipe ou une agence',
      priceNote: '14 jours offerts. 20 employés inclus.',
      employeeLimit: "Jusqu'à 20 employés",
      features: [
        'Pointage web et mobile',
        'Absences, conges et soldes',
        'Dossiers employes et documents RH',
        'Bulletins de paie PDF et exports essentiels',
        'Portail client et espace manager',
        'Apps Employee et Manager incluses',
        'Support email sous 48h',
      ],
      cta: 'Lancer un essai gratuit',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '66',
      period: '/mois',
      annualPeriod: '/mois, facturé annuellement',
      description: 'Pour les PME multi-equipes qui veulent piloter terrain, RH et paie simple',
      priceNote: '14 jours offerts. 200 employés inclus.',
      employeeLimit: "Jusqu'à 200 employés",
      features: [
        'Tout Starter, plus :',
        'Paie multi-pays et validations RH',
        'Managers, equipes et workflows d approbation',
        'Pointage ZKTeco, kiosque et mobile',
        'Analytics RH, readiness et exports avances',
        'API, webhooks et integrations',
        'Support prioritaire sous 24h',
      ],
      cta: 'Essayer Business',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: '199',
      annualPrice: '166',
      period: '/mois',
      annualPeriod: '/mois, facturé annuellement',
      description: 'Pour groupes multi-pays, franchises, reseaux de sites et exigences fortes',
      priceNote: '14 jours offerts. Employés illimités.',
      employeeLimit: 'Employés illimités',
      features: [
        'Tout Business, plus :',
        'SSO SAML/OIDC (bientot disponible) et politiques avancees',
        'SLA, accompagnement migration et formation',
        'Environnements dedies ou region cloud choisie',
        'Audit trail, exports compliance et support prioritaire',
        'Options IA, connecteurs et gouvernance sur mesure',
      ],
      cta: 'Essayer Enterprise',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  en: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '24',
      period: '/month',
      annualPeriod: '/month, billed annually',
      description: 'For testing Leopardo on one site, team or branch',
      priceNote: '14-day free trial. 20 employees included.',
      employeeLimit: 'Up to 20 employees',
      features: [
        'Web and mobile attendance',
        'Leave, absences and balances',
        'Employee records and HR documents',
        'PDF pay slips and basic exports',
        'Client portal and manager space',
        'Employee and Manager apps included',
        'Email support within 48h',
      ],
      cta: 'Start a free trial',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '66',
      period: '/month',
      annualPeriod: '/month, billed annually',
      description: 'For SMBs managing field teams, HR workflows and simple payroll',
      priceNote: '14-day free trial. 200 employees included.',
      employeeLimit: 'Up to 200 employees',
      features: [
        'Everything in Starter, plus:',
        'Multi-country payroll and HR validations',
        'Managers, teams and approval workflows',
        'ZKTeco, kiosk and mobile attendance',
        'HR analytics, readiness and advanced exports',
        'API, webhooks and integrations',
        'Priority support within 24h',
      ],
      cta: 'Try Business',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: '199',
      annualPrice: '166',
      period: '/month',
      annualPeriod: '/month, billed annually',
      description: 'For multi-country groups, franchises, site networks and strict governance',
      priceNote: '14-day free trial. Unlimited employees.',
      employeeLimit: 'Unlimited employees',
      features: [
        'Everything in Business, plus:',
        'SAML/OIDC SSO (coming soon) and advanced policies',
        'SLA, migration guidance and training',
        'Dedicated environments or selected cloud region',
        'Audit trail, compliance exports and priority support',
        'AI, connectors and governance options',
      ],
      cta: 'Try Enterprise',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  tr: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '24',
      period: '/ay',
      annualPeriod: '/ay, yillik faturalama',
      description: 'Leopardo yu tek saha, ekip veya subede denemek icin',
      priceNote: '14 gun ucretsiz deneme. 20 calisan dahil.',
      employeeLimit: '20 calisana kadar',
      features: [
        'Web ve mobil yoklama',
        'Izin, devamsizlik ve bakiye takibi',
        'Calisan dosyalari ve IK belgeleri',
        'PDF bordro dokumu ve temel dis aktarimlar',
        'Musteri portali ve yonetici alani',
        'Employee ve Manager uygulamalari dahil',
        '48 saat icinde e-posta destegi',
      ],
      cta: 'Ucretsiz deneme baslat',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '66',
      period: '/ay',
      annualPeriod: '/ay, yillik faturalama',
      description: 'Saha ekipleri, IK akislari ve basit bordro yoneten KOBI ler icin',
      priceNote: '14 gun ucretsiz deneme. 200 calisan dahil.',
      employeeLimit: '200 calisana kadar',
      features: [
        'Starter taki her sey, arti:',
        'Cok ulkeli bordro ve IK onaylari',
        'Yoneticiler, ekipler ve onay akislari',
        'ZKTeco, kiosk ve mobil yoklama',
        'IK analitigi, readiness ve gelismis dis aktarimlar',
        'API, webhook ve entegrasyonlar',
        '24 saat icinde oncelikli destek',
      ],
      cta: 'Business dene',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: '199',
      annualPrice: '166',
      period: '/ay',
      annualPeriod: '/ay, yillik faturalama',
      description: 'Cok ulkeli gruplar, franchise lar, saha aglari ve yuksek yonetisim icin',
      priceNote: '14 gun ucretsiz deneme. Sinirsiz calisan.',
      employeeLimit: 'Sinirsiz calisan',
      features: [
        'Business taki her sey, arti:',
        'SAML/OIDC SSO (yakinda) ve gelismis politikalar',
        'SLA, gecis destegi ve egitim',
        'Ozel ortamlar veya secilen bulut bolgesi',
        'Denetim izi, uyumluluk dis aktarimlari ve oncelikli destek',
        'IA, baglayicilar ve yonetisim opsiyonlari',
      ],
      cta: 'Enterprise dene',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  ar: [
    {
      name: 'Starter',
      price: '29',
      annualPrice: '24',
      period: '/شهر',
      annualPeriod: '/شهر مع فوترة سنوية',
      description: 'لاختبار Leopardo على موقع واحد أو فريق أو فرع',
      priceNote: '14 يوما مجانا. يشمل 20 موظفا.',
      employeeLimit: 'حتى 20 موظفا',
      features: [
        'الحضور عبر الويب والجوال',
        'الإجازات والغيابات والأرصدة',
        'ملفات الموظفين والوثائق الإدارية',
        'قسائم رواتب PDF وتصديرات أساسية',
        'بوابة العميل ومساحة المدير',
        'تطبيقا Employee وManager مشمولان',
        'دعم عبر البريد خلال 48 ساعة',
      ],
      cta: 'ابدأ تجربة مجانية',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      name: 'Business',
      price: '79',
      annualPrice: '66',
      period: '/شهر',
      annualPeriod: '/شهر مع فوترة سنوية',
      description: 'للشركات الصغيرة والمتوسطة التي تحتاج الحضور والرواتب والمديرين والتحليلات',
      priceNote: '14 يوما مجانا. يشمل 200 موظف.',
      employeeLimit: 'حتى 200 موظف',
      features: [
        'كل ما في Starter، بالإضافة إلى:',
        'رواتب متعددة البلدان وموافقات موارد بشرية',
        'مديرون وفرق ومسارات اعتماد',
        'حضور ZKTeco والكشك والجوال',
        'تحليلات موارد بشرية وجاهزية وتصديرات متقدمة',
        'API وwebhooks وتكاملات',
        'دعم أولوية خلال 24 ساعة',
      ],
      cta: 'جرّب Business',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      name: 'Enterprise',
      price: '199',
      annualPrice: '166',
      period: '/شهر',
      annualPeriod: '/شهر مع فوترة سنوية',
      description: 'للمجموعات متعددة البلدان وشبكات المواقع والحوكمة المتقدمة',
      priceNote: '14 يوما مجانا. موظفون بلا حدود.',
      employeeLimit: 'موظفون بلا حدود',
      features: [
        'كل ما في Business، بالإضافة إلى:',
        'SSO عبر SAML/OIDC (قريبًا) وسياسات متقدمة',
        'اتفاقية خدمة ومرافقة انتقال وتدريب',
        'بيئات مخصصة أو منطقة سحابية مختارة',
        'سجل تدقيق وتصديرات امتثال ودعم أولوية',
        'خيارات ذكاء اصطناعي وتكاملات وحوكمة مخصصة',
      ],
      cta: 'جرّب Enterprise',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
}

export function getPricingPlans(locale: AppLocale): PricingPlan[] {
  return pricingByLocale[locale] ?? pricingByLocale.fr
}
