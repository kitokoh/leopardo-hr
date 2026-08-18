import type { AppLocale } from '@/lib/i18n'

/**
 * Source de vérité des montants — alignée sur api/database/seeders/PlanSeeder.php
 * et PlanCode.php (ADR-0014 · 2026-08-15).
 *
 * Plans canoniques :
 *   free        0 €/mois · 5 employés max · 30 jours d'essai (freemium)
 *   pilot      29 €/mois (24,17 €/mois annuel = 290 €/an) · 30 employés max · 14j
 *   operations 79 €/mois (65,83 €/mois annuel = 790 €/an) · 200 employés max · 14j
 *   enterprise  sur devis · illimité · 14j
 *
 * ⚠ Les anciens libellés "Starter" et "Business" sont des alias legacy migrés
 * par PlanSeeder.migrateLegacyPlanNames(). Ne plus les utiliser ici.
 * Le champ `planCode` est envoyé tel quel au checkout — doit correspondre
 * exactement aux valeurs de PlanCode.php.
 */

export type PricingPlan = {
  /** Code métier envoyé au checkout et à l'API — doit être un PlanCode valide */
  planCode: 'free' | 'pilot' | 'operations' | 'enterprise'
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
      planCode: 'free',
      name: 'Free',
      price: '0',
      annualPrice: '0',
      period: '/mois',
      annualPeriod: '/mois',
      description: 'Pour démarrer sans engagement — idéal pour les équipes de 5 personnes',
      priceNote: '14 jours d\'essai. Jusqu\'à 5 employés.',
      employeeLimit: 'Jusqu\'à 5 employés',
      features: [
        'Pointage web et mobile basique',
        'Absences et congés (consultation)',
        'Dossiers employés essentiels',
        'Bulletins de paie PDF',
        'App Employee incluse',
        'Support communautaire',
      ],
      cta: 'Commencer gratuitement',
      popular: false,
      gradient: 'from-gray-500 to-gray-600',
    },
    {
      planCode: 'pilot',
      name: 'Pilot',
      price: '29',
      annualPrice: '24,17',
      period: '/mois',
      annualPeriod: '/mois · 290 €/an facturé annuellement',
      description: 'Pour piloter Leopardo sur un site, une équipe ou une agence',
      priceNote: '14 jours offerts. Jusqu\'à 30 employés.',
      employeeLimit: 'Jusqu\'à 30 employés',
      features: [
        'Pointage web et mobile',
        'Absences, congés et soldes',
        'Dossiers employés et documents RH',
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
      planCode: 'operations',
      name: 'Operations',
      price: '79',
      annualPrice: '65,83',
      period: '/mois',
      annualPeriod: '/mois · 790 €/an facturé annuellement',
      description: 'Pour les PME multi-équipes qui pilotent terrain, RH et paie',
      priceNote: '14 jours offerts. Jusqu\'à 200 employés.',
      employeeLimit: 'Jusqu\'à 200 employés',
      features: [
        'Tout Pilot, plus :',
        'Paie multi-pays et validations RH',
        'Managers, équipes et workflows d\'approbation',
        'Pointage ZKTeco, kiosque et mobile',
        'Analytics RH, readiness et exports avancés',
        'API, webhooks et intégrations',
        'Support prioritaire sous 24h',
      ],
      cta: 'Essayer Operations',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      planCode: 'enterprise',
      name: 'Enterprise',
      price: 'Sur devis',
      annualPrice: 'Sur devis',
      period: '',
      annualPeriod: '',
      description: 'Pour groupes multi-pays, franchises, réseaux de sites et exigences fortes',
      priceNote: '14 jours offerts. Employés illimités.',
      employeeLimit: 'Employés illimités',
      features: [
        'Tout Operations, plus :',
        'SSO SAML/OIDC et politiques avancées',
        'SLA, accompagnement migration et formation',
        'Environnements dédiés ou région cloud choisie',
        'Audit trail, exports compliance et support prioritaire',
        'Options IA, connecteurs et gouvernance sur mesure',
      ],
      cta: 'Contacter les ventes',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  en: [
    {
      planCode: 'free',
      name: 'Free',
      price: '0',
      annualPrice: '0',
      period: '/month',
      annualPeriod: '/month',
      description: 'Start without commitment — ideal for teams of up to 5 people',
      priceNote: '14-day trial. Up to 5 employees.',
      employeeLimit: 'Up to 5 employees',
      features: [
        'Basic web and mobile attendance',
        'Leave and absence consultation',
        'Essential employee records',
        'PDF pay slips',
        'Employee app included',
        'Community support',
      ],
      cta: 'Start for free',
      popular: false,
      gradient: 'from-gray-500 to-gray-600',
    },
    {
      planCode: 'pilot',
      name: 'Pilot',
      price: '29',
      annualPrice: '24,17',
      period: '/month',
      annualPeriod: '/month · billed 290 €/year',
      description: 'For testing Leopardo on one site, team or branch',
      priceNote: '14 days free. Up to 30 employees.',
      employeeLimit: 'Up to 30 employees',
      features: [
        'Web and mobile attendance',
        'Leave, absences and balances',
        'Employee records and HR documents',
        'PDF pay slips and basic exports',
        'Client portal and manager space',
        'Employee and Manager apps included',
        'Email support within 48h',
      ],
      cta: 'Start free trial',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      planCode: 'operations',
      name: 'Operations',
      price: '79',
      annualPrice: '65,83',
      period: '/month',
      annualPeriod: '/month · billed 790 €/year',
      description: 'For SMBs managing field teams, HR workflows and payroll',
      priceNote: '14 days free. Up to 200 employees.',
      employeeLimit: 'Up to 200 employees',
      features: [
        'Everything in Pilot, plus:',
        'Multi-country payroll and HR validations',
        'Managers, teams and approval workflows',
        'ZKTeco, kiosk and mobile attendance',
        'HR analytics, readiness and advanced exports',
        'API, webhooks and integrations',
        'Priority support within 24h',
      ],
      cta: 'Try Operations',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      planCode: 'enterprise',
      name: 'Enterprise',
      price: 'Custom',
      annualPrice: 'Custom',
      period: '',
      annualPeriod: '',
      description: 'For multi-country groups, franchises, site networks and strict governance',
      priceNote: '14 days free. Unlimited employees.',
      employeeLimit: 'Unlimited employees',
      features: [
        'Everything in Operations, plus:',
        'SAML/OIDC SSO and advanced policies',
        'SLA, migration guidance and training',
        'Dedicated environments or selected cloud region',
        'Audit trail, compliance exports and priority support',
        'AI, connectors and governance options',
      ],
      cta: 'Contact sales',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  tr: [
    {
      planCode: 'free',
      name: 'Free',
      price: '0',
      annualPrice: '0',
      period: '/ay',
      annualPeriod: '/ay',
      description: 'Taahhütsüz başlayın — 5 kişiye kadar ekipler için ideal',
      priceNote: '14 gün deneme. 5 çalışana kadar.',
      employeeLimit: '5 çalışana kadar',
      features: [
        'Temel web ve mobil yoklama',
        'İzin ve devamsızlık görüntüleme',
        'Temel çalışan kayıtları',
        'PDF bordro dökümleri',
        'Employee uygulaması dahil',
        'Topluluk desteği',
      ],
      cta: 'Ücretsiz başla',
      popular: false,
      gradient: 'from-gray-500 to-gray-600',
    },
    {
      planCode: 'pilot',
      name: 'Pilot',
      price: '29',
      annualPrice: '24,17',
      period: '/ay',
      annualPeriod: '/ay · yıllık 290 € fatura',
      description: 'Leopardo\'yu tek saha, ekip veya şubede denemek için',
      priceNote: '14 gün ücretsiz. 30 çalışana kadar.',
      employeeLimit: '30 çalışana kadar',
      features: [
        'Web ve mobil yoklama',
        'İzin, devamsızlık ve bakiye takibi',
        'Çalışan dosyaları ve İK belgeleri',
        'PDF bordro dökümleri ve temel dışa aktarımlar',
        'Müşteri portalı ve yönetici alanı',
        'Employee ve Manager uygulamaları dahil',
        '48 saat içinde e-posta desteği',
      ],
      cta: 'Ücretsiz denemeye başla',
      popular: false,
      gradient: 'from-slate-600 to-slate-700',
    },
    {
      planCode: 'operations',
      name: 'Operations',
      price: '79',
      annualPrice: '65,83',
      period: '/ay',
      annualPeriod: '/ay · yıllık 790 € fatura',
      description: 'Saha ekipleri, İK akışları ve bordro yöneten KOBİ\'ler için',
      priceNote: '14 gün ücretsiz. 200 çalışana kadar.',
      employeeLimit: '200 çalışana kadar',
      features: [
        'Pilot\'taki her şey, artı:',
        'Çok ülkeli bordro ve İK onayları',
        'Yöneticiler, ekipler ve onay akışları',
        'ZKTeco, kiosk ve mobil yoklama',
        'İK analitiği, readiness ve gelişmiş dışa aktarımlar',
        'API, webhook ve entegrasyonlar',
        '24 saat içinde öncelikli destek',
      ],
      cta: 'Operations\'ı dene',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      planCode: 'enterprise',
      name: 'Enterprise',
      price: 'Teklif',
      annualPrice: 'Teklif',
      period: '',
      annualPeriod: '',
      description: 'Çok ülkeli gruplar, franchise\'lar, saha ağları ve yüksek yönetişim için',
      priceNote: '14 gün ücretsiz. Sınırsız çalışan.',
      employeeLimit: 'Sınırsız çalışan',
      features: [
        'Operations\'taki her şey, artı:',
        'SAML/OIDC SSO ve gelişmiş politikalar',
        'SLA, geçiş desteği ve eğitim',
        'Özel ortamlar veya seçilen bulut bölgesi',
        'Denetim izi, uyumluluk dışa aktarımları ve öncelikli destek',
        'AI, bağlayıcılar ve yönetişim seçenekleri',
      ],
      cta: 'Satışla iletişime geç',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
  ar: [
    {
      planCode: 'free',
      name: 'Free',
      price: '0',
      annualPrice: '0',
      period: '/شهر',
      annualPeriod: '/شهر',
      description: 'ابدأ بدون التزام — مثالي للفرق حتى 5 أشخاص',
      priceNote: '14 يومًا مجانًا. حتى 5 موظفين.',
      employeeLimit: 'حتى 5 موظفين',
      features: [
        'الحضور الأساسي عبر الويب والجوال',
        'عرض الإجازات والغيابات',
        'سجلات الموظفين الأساسية',
        'قسائم رواتب PDF',
        'تطبيق Employee مشمول',
        'دعم المجتمع',
      ],
      cta: 'ابدأ مجانًا',
      popular: false,
      gradient: 'from-gray-500 to-gray-600',
    },
    {
      planCode: 'pilot',
      name: 'Pilot',
      price: '29',
      annualPrice: '24,17',
      period: '/شهر',
      annualPeriod: '/شهر · يُفوتر 290 € سنوياً',
      description: 'لاختبار Leopardo على موقع واحد أو فريق أو فرع',
      priceNote: '14 يومًا مجانًا. يشمل حتى 30 موظفًا.',
      employeeLimit: 'حتى 30 موظفًا',
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
      planCode: 'operations',
      name: 'Operations',
      price: '79',
      annualPrice: '65,83',
      period: '/شهر',
      annualPeriod: '/شهر · يُفوتر 790 € سنوياً',
      description: 'للشركات الصغيرة والمتوسطة التي تحتاج الحضور والرواتب والمديرين والتحليلات',
      priceNote: '14 يومًا مجانًا. يشمل حتى 200 موظف.',
      employeeLimit: 'حتى 200 موظف',
      features: [
        'كل ما في Pilot، بالإضافة إلى:',
        'رواتب متعددة البلدان وموافقات الموارد البشرية',
        'مديرون وفرق ومسارات اعتماد',
        'حضور ZKTeco والكشك والجوال',
        'تحليلات الموارد البشرية وجاهزية وتصديرات متقدمة',
        'API وwebhooks وتكاملات',
        'دعم أولوية خلال 24 ساعة',
      ],
      cta: 'جرّب Operations',
      popular: true,
      gradient: 'from-emerald-500 to-cyan-500',
    },
    {
      planCode: 'enterprise',
      name: 'Enterprise',
      price: 'حسب الطلب',
      annualPrice: 'حسب الطلب',
      period: '',
      annualPeriod: '',
      description: 'للمجموعات متعددة البلدان وشبكات المواقع والحوكمة المتقدمة',
      priceNote: '14 يومًا مجانًا. موظفون بلا حدود.',
      employeeLimit: 'موظفون بلا حدود',
      features: [
        'كل ما في Operations، بالإضافة إلى:',
        'SSO عبر SAML/OIDC وسياسات متقدمة',
        'اتفاقية خدمة ومرافقة انتقال وتدريب',
        'بيئات مخصصة أو منطقة سحابية مختارة',
        'سجل تدقيق وتصديرات امتثال ودعم أولوية',
        'خيارات ذكاء اصطناعي وتكاملات وحوكمة مخصصة',
      ],
      cta: 'تواصل مع المبيعات',
      popular: false,
      gradient: 'from-violet-600 to-purple-700',
    },
  ],
}

export function getPricingPlans(locale: AppLocale): PricingPlan[] {
  return pricingByLocale[locale] ?? pricingByLocale.fr
}

/**
 * #4404 — source unique « prix machine vs devis ».
 * Un plan « sur devis » n'a pas de prix machine : la carte ne doit afficher
 * aucun montant et son CTA doit mener au contact, jamais au checkout.
 * Liste complète des libellés « devis » par locale (pricing.ts data) —
 * attention aux mots AR distincts : « حسب الطلب » (sur demande) est le
 * libellé de données ; « حسب العرض » (selon l'offre) n'est PAS utilisé.
 */
export function showsCurrency(price: string): boolean {
  return !['Sur devis', 'Custom', 'Teklif', 'Teklif alın', 'حسب الطلب'].includes(price)
}
