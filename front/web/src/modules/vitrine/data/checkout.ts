import type { AppLocale } from '@/lib/i18n'

/* ─────────────────────────────────────────────────────────────
   CHECKOUT i18n (issue #4185 / #4218)
   Tunnel de souscription (checkout + success) : 100 % des chaînes
   visibles sont localisées. Avant cette refonte, la page était
   codée en dur en FR pour les 4 locales de la vitrine.
   Vocabulaire aligné sur pricing.ts (#2977/#3919).
────────────────────────────────────────────────────────────── */

export type CheckoutPlanKey = 'free' | 'pilot' | 'operations' | 'enterprise'

export interface CheckoutPlanCopy {
  label: string
  employeeLimit: string
  features: string[]
}

export interface CheckoutCopy {
  meta: {
    title: string
    description: string
  }
  backToPricing: string
  back: string
  steps: {
    recap: string
    account: string
    payment: string
  }
  planChosen: string
  quote: string
  perMonth: string
  /** Unité du nombre de jours d'essai, localisée (jours/gün/أيام/days). */
  trialDaysUnit: string
  /** Libellé de la devise d'affichage des prix (EUR — devise canonique ADR-0014). */
  currencyLabel: string
  trialBadge: string // « {days} jours gratuits inclus · Aucune CB débitée avant la fin de l'essai »
  billedAnnually: string // « Facturé annuellement — économisez EUR {savings}/an »
  checkoutUnavailableTitle: string
  checkoutUnavailableCtaTrial: string
  checkoutUnavailableCtaContact: string
  monthly: string
  annual: string
  trust: {
    secure: string
    rgpd: string
    cancel: string
  }
  continueWithGoogle: string
  recap: {
    title: string
    subtitle: string
    trialNote: string // « Essai gratuit de {days} jours. Votre carte ne sera débitée… »
    wrongPlan: string
    viewAllPlans: string
    continueCta: string // « Continuer — EUR {price}/mois »
  }
  account: {
    title: string
    subtitle: string
    orEmail: string
    firstName: string
    lastName: string
    email: string
    company: string
    phone: string
    employees: string
    choose: string
    placeholders: {
      firstName: string
      lastName: string
      email: string
      company: string
      phone: string
    }
    errors: {
      firstName: string
      lastName: string
      email: string
      company: string
    }
    next: string
  }
  payment: {
    title: string
    sandboxNoticeTitle: string
    sandboxNoticeBody: string
    fillTestCard: string
    filledTestCard: string
    cardLabel: string
    expiryLabel: string
    cvcLabel: string
    cardNameLabel: string
    cardNamePlaceholder: string
    testBadge: string
    planRow: string // « Plan {label} »
    freeTrialRow: string
    dueToday: string
    processing: string
    submitCta: string // « Démarrer l'essai gratuit — EUR 0,00 dû maintenant »
    legal: {
      prefix: string
      terms: string
      and: string
      privacy: string
      suffix: string
    }
    errors: {
      fillAll: string
      generic: string
      network: string
    }
  }
  free: {
    badge: string
    title: string
    body: string
    cta: string
    seePricing: string
  }
  success: {
    badgeSandbox: string
    badgePaid: string
    title: string
    titleAccent: string
    subtitle: string // « {company} — Plan {plan} ({period}). 14 jours offerts, aucune carte débitée aujourd'hui. »
    periodAnnual: string
    periodMonthly: string
    cardTitle: string
    cardSubtitle: string
    emailRow: string
    planRow: string
    trialPeriodRow: string
    trialValue: string
    chargedTodayRow: string
    zeroAmount: string
    modeRow: string
    sandboxBadge: string
    sessionRow: string
    copyTitle: string
    copied: string
    emailNotice: string // « Un email de confirmation a été envoyé à {email} avec vos identifiants… »
    emailFallback: string
    nextStepsTitle: string
    nextSteps: Array<{ title: string; desc: string; cta: string }>
    primaryCta: string
    secondaryCta: string
    helpPrefix: string
    helpLink: string
    helpSuffix: string
  }
  plans: Record<CheckoutPlanKey, CheckoutPlanCopy>
}

export const checkoutCopyByLocale: Record<AppLocale, CheckoutCopy> = {
  fr: {
    meta: {
      title: 'Souscription — Leopardo HR',
      description:
        'Choisissez votre plan, créez votre compte et lancez votre essai gratuit de 14 jours.',
    },
    backToPricing: 'Retour aux tarifs',
    back: 'Retour',
    steps: { recap: 'Récapitulatif', account: 'Compte', payment: 'Paiement' },
    planChosen: 'Plan choisi',
    quote: 'Sur devis',
    perMonth: '/mois',
    trialDaysUnit: 'jours',
    currencyLabel: 'EUR',
    trialBadge:
      "{days} jours gratuits inclus · Aucune CB débitée avant la fin de l'essai",
    billedAnnually: 'Facturé annuellement — économisez EUR {savings}/an',
  checkoutUnavailableTitle: 'Le paiement en ligne est temporairement indisponible.',
  checkoutUnavailableCtaTrial: 'Démarrer l’essai gratuit',
  checkoutUnavailableCtaContact: 'Contacter le support',
    monthly: 'Mensuel',
    annual: 'Annuel',
    trust: {
      secure: 'Paiement sécurisé TLS 1.3 + AES-256',
      rgpd: 'Données hébergées en Europe — conforme RGPD',
      cancel: 'Sans engagement · Résiliation en 2 clics',
    },
    continueWithGoogle: 'Continuer avec Google',
    recap: {
      title: 'Votre plan sélectionné',
      subtitle: 'Vérifiez les détails avant de créer votre compte.',
      trialNote:
        "Essai gratuit de {days} jours. Votre carte ne sera débitée qu'après la période d'essai. Annulez à tout moment depuis votre tableau de bord.",
      wrongPlan: 'Mauvais plan ?',
      viewAllPlans: 'Voir tous les plans',
      continueCta: 'Continuer — EUR {price}/mois',
    },
    account: {
      title: 'Créez votre compte',
      subtitle: 'Votre espace Leopardo sera prêt en quelques secondes.',
      orEmail: 'ou avec votre email',
      firstName: 'Prénom',
      lastName: 'Nom',
      email: 'Email professionnel',
      company: 'Société',
      phone: 'Téléphone',
      employees: 'Effectif',
      choose: 'Choisir',
      placeholders: {
        firstName: 'Marie',
        lastName: 'Dupont',
        email: 'marie@societe.com',
        company: 'Nom de votre entreprise',
        phone: '+33 6 00 00 00 00',
      },
      errors: {
        firstName: 'Prénom requis',
        lastName: 'Nom requis',
        email: 'Email professionnel valide requis',
        company: 'Nom de société requis',
      },
      next: 'Passer au paiement',
    },
    payment: {
      title: 'Informations de paiement',
      sandboxNoticeTitle: 'Mode test activé — Aucune carte réelle débitée',
      sandboxNoticeBody:
        "Les Stripe Price IDs ne sont pas encore configurés. Utilisez la carte de test ci-dessous.",
      fillTestCard: 'Remplir avec la carte test',
      filledTestCard: '✓ Carte test remplie',
      cardLabel: 'Numéro de carte',
      expiryLabel: "Date d'expiration",
      cvcLabel: 'CVC',
      cardNameLabel: 'Nom sur la carte',
      cardNamePlaceholder: 'Marie Dupont',
      testBadge: 'TEST',
      planRow: 'Plan {label}',
      freeTrialRow: 'Essai gratuit',
      dueToday: "Dû aujourd'hui",
      processing: 'Traitement en cours...',
      submitCta: "Démarrer l'essai gratuit — EUR 0,00 dû maintenant",
      legal: {
        prefix: 'En confirmant, vous acceptez nos',
        terms: "conditions d'utilisation",
        and: 'et notre',
        privacy: 'politique de confidentialité',
        suffix: '.',
      },
      errors: {
        fillAll: 'Veuillez remplir tous les champs de paiement.',
        generic: 'Erreur lors du traitement du paiement.',
        network: 'Impossible de contacter le serveur. Vérifiez votre connexion.',
      },
    },
    free: {
      badge: 'Plan Free — 0 €/mois · 5 employés',
      title: "L'essai guidé démarre ici",
      body: "Le plan Free se souscrit via une demande d'essai guidé : notre équipe configure votre entreprise et vous accompagne. Aucune carte bancaire requise.",
      cta: "Démarrer l'essai guidé",
      seePricing: 'Voir les tarifs',
    },
    success: {
      badgeSandbox: 'Simulation réussie — Mode sandbox',
      badgePaid: 'Paiement confirmé',
      title: 'Votre espace Leopardo',
      titleAccent: 'est prêt !',
      subtitle:
        "{company} — Plan {plan} ({period}). 14 jours offerts, aucune carte débitée aujourd'hui.",
      periodAnnual: 'annuel',
      periodMonthly: 'mensuel',
      cardTitle: 'Récapitulatif de votre essai',
      cardSubtitle: 'Gardez ces informations',
      emailRow: 'Email',
      planRow: 'Plan',
      trialPeriodRow: "Période d'essai",
      trialValue: '14 jours gratuits',
      chargedTodayRow: "Débité aujourd'hui",
      zeroAmount: 'EUR 0,00',
      modeRow: 'Mode',
      sandboxBadge: 'SANDBOX — simulation',
      sessionRow: 'Réf. session',
      copyTitle: 'Copier la référence',
      copied: 'Copié !',
      emailNotice:
        "Un email de confirmation a été envoyé à {email} avec vos identifiants d'accès et les instructions de démarrage.",
      emailFallback: 'votre adresse',
      nextStepsTitle: 'Prochaines étapes',
      nextSteps: [
        {
          title: 'Connectez-vous à votre espace',
          desc: 'Accédez au dashboard manager et configurez votre première équipe.',
          cta: 'Accéder au dashboard',
        },
        {
          title: 'Téléchargez les apps mobiles',
          desc: 'Apps Employee et Manager disponibles sur Android et iOS.',
          cta: 'Télécharger les apps',
        },
        {
          title: 'Invitez votre équipe',
          desc: "Envoyez les invitations depuis le dashboard — vos employés reçoivent leur accès par email.",
          cta: 'Gérer les invitations',
        },
      ],
      primaryCta: 'Accéder à mon espace',
      secondaryCta: 'Télécharger les apps',
      helpPrefix: 'Des questions ?',
      helpLink: 'Contacter le support',
      helpSuffix: '· Nous répondons sous 24h ouvrables.',
    },
    plans: {
      free: {
        label: 'Free',
        employeeLimit: "Jusqu'à 5 employés",
        features: [
          'Pointage web',
          'Absences, congés et soldes',
          'Dossiers employés et documents RH',
          'App mobile Employee incluse',
          'Support email',
        ],
      },
      pilot: {
        label: 'Pilot',
        employeeLimit: "Jusqu'à 30 employés",
        features: [
          'Pointage web & mobile',
          'Absences & congés',
          'Dossiers employés',
          'Bulletins de paie PDF',
          'Dashboard manager',
          'Apps Employee & Manager',
          'Support email 48h',
        ],
      },
      operations: {
        label: 'Operations',
        employeeLimit: "Jusqu'à 250 employés",
        features: [
          'Tout Pilot inclus',
          'Paie automatisée',
          'Biométrie ZKTeco',
          'API & Webhooks',
          'Exports comptables',
          'Support prioritaire 24h',
        ],
      },
      enterprise: {
        label: 'Enterprise',
        employeeLimit: 'Employés illimités',
        features: [
          'Tout Operations inclus',
          'Multi-pays & multi-devises',
          'SSO SAML/OIDC (bientôt disponible)',
          'Audit trail immuable',
          'Schéma PostgreSQL isolé',
          'Account manager dédié',
        ],
      },
    },
  },

  en: {
    meta: {
      title: 'Checkout — Leopardo HR',
      description:
        'Pick your plan, create your account and start your 14-day free trial.',
    },
    backToPricing: 'Back to pricing',
    back: 'Back',
    steps: { recap: 'Summary', account: 'Account', payment: 'Payment' },
    planChosen: 'Selected plan',
    quote: 'Custom quote',
    perMonth: '/month',
    trialDaysUnit: 'days',
    currencyLabel: 'EUR',
    trialBadge: '{days} free days included · No card is charged before the trial ends',
    billedAnnually: 'Billed annually — save EUR {savings}/year',
  checkoutUnavailableTitle: 'Online payment is temporarily unavailable.',
  checkoutUnavailableCtaTrial: 'Start the free trial',
  checkoutUnavailableCtaContact: 'Contact support',
    monthly: 'Monthly',
    annual: 'Annual',
    trust: {
      secure: 'Secure payment — TLS 1.3 + AES-256',
      rgpd: 'Data hosted in Europe — GDPR compliant',
      cancel: 'No commitment · Cancel in 2 clicks',
    },
    continueWithGoogle: 'Continue with Google',
    recap: {
      title: 'Your selected plan',
      subtitle: 'Review the details before creating your account.',
      trialNote:
        'Free {days}-day trial. Your card will only be charged after the trial period. Cancel anytime from your dashboard.',
      wrongPlan: 'Wrong plan?',
      viewAllPlans: 'View all plans',
      continueCta: 'Continue — EUR {price}/month',
    },
    account: {
      title: 'Create your account',
      subtitle: 'Your Leopardo workspace will be ready in seconds.',
      orEmail: 'or with your email',
      firstName: 'First name',
      lastName: 'Last name',
      email: 'Work email',
      company: 'Company',
      phone: 'Phone',
      employees: 'Team size',
      choose: 'Select',
      placeholders: {
        firstName: 'Marie',
        lastName: 'Dupont',
        email: 'marie@company.com',
        company: 'Your company name',
        phone: '+1 555 000 0000',
      },
      errors: {
        firstName: 'First name is required',
        lastName: 'Last name is required',
        email: 'A valid work email is required',
        company: 'A company name is required',
      },
      next: 'Proceed to payment',
    },
    payment: {
      title: 'Payment details',
      sandboxNoticeTitle: 'Test mode enabled — No real card is charged',
      sandboxNoticeBody: 'Stripe Price IDs are not configured yet. Use the test card below.',
      fillTestCard: 'Fill with test card',
      filledTestCard: '✓ Test card filled',
      cardLabel: 'Card number',
      expiryLabel: 'Expiry date',
      cvcLabel: 'CVC',
      cardNameLabel: 'Name on card',
      cardNamePlaceholder: 'Marie Dupont',
      testBadge: 'TEST',
      planRow: 'Plan {label}',
      freeTrialRow: 'Free trial',
      dueToday: 'Due today',
      processing: 'Processing...',
      submitCta: 'Start free trial — EUR 0.00 due now',
      legal: {
        prefix: 'By confirming, you accept our',
        terms: 'Terms of Service',
        and: 'and our',
        privacy: 'Privacy Policy',
        suffix: '.',
      },
      errors: {
        fillAll: 'Please fill in all payment fields.',
        generic: 'An error occurred while processing the payment.',
        network: 'Unable to reach the server. Check your connection.',
      },
    },
    free: {
      badge: 'Free plan — €0/month · 5 employees',
      title: 'Your guided trial starts here',
      body: 'The Free plan is subscribed through a guided trial: our team sets up your company and supports you. No credit card required.',
      cta: 'Start the guided trial',
      seePricing: 'See pricing',
    },
    success: {
      badgeSandbox: 'Simulation successful — Sandbox mode',
      badgePaid: 'Payment confirmed',
      title: 'Your Leopardo workspace',
      titleAccent: 'is ready!',
      subtitle:
        '{company} — {plan} plan ({period}). 14 days free, no card charged today.',
      periodAnnual: 'annual',
      periodMonthly: 'monthly',
      cardTitle: 'Your trial summary',
      cardSubtitle: 'Keep this information',
      emailRow: 'Email',
      planRow: 'Plan',
      trialPeriodRow: 'Trial period',
      trialValue: '14 days free',
      chargedTodayRow: 'Charged today',
      zeroAmount: 'EUR 0.00',
      modeRow: 'Mode',
      sandboxBadge: 'SANDBOX — simulation',
      sessionRow: 'Session ref.',
      copyTitle: 'Copy reference',
      copied: 'Copied!',
      emailNotice:
        'A confirmation email has been sent to {email} with your access credentials and getting-started instructions.',
      emailFallback: 'your address',
      nextStepsTitle: 'Next steps',
      nextSteps: [
        {
          title: 'Log in to your workspace',
          desc: 'Open the manager dashboard and set up your first team.',
          cta: 'Open the dashboard',
        },
        {
          title: 'Download the mobile apps',
          desc: 'Employee and Manager apps available on Android and iOS.',
          cta: 'Download the apps',
        },
        {
          title: 'Invite your team',
          desc: 'Send invitations from the dashboard — your employees receive access by email.',
          cta: 'Manage invitations',
        },
      ],
      primaryCta: 'Access my workspace',
      secondaryCta: 'Download the apps',
      helpPrefix: 'Questions?',
      helpLink: 'Contact support',
      helpSuffix: '· We reply within 24 business hours.',
    },
    plans: {
      free: {
        label: 'Free',
        employeeLimit: 'Up to 5 employees',
        features: [
          'Web attendance',
          'Leave, absences and balances',
          'Employee records and HR documents',
          'Employee mobile app included',
          'Email support',
        ],
      },
      pilot: {
        label: 'Pilot',
        employeeLimit: 'Up to 30 employees',
        features: [
          'Web & mobile attendance',
          'Absences & leave',
          'Employee records',
          'PDF payslips',
          'Manager dashboard',
          'Employee & Manager apps',
          'Email support within 48h',
        ],
      },
      operations: {
        label: 'Operations',
        employeeLimit: 'Up to 250 employees',
        features: [
          'Everything in Pilot',
          'Automated payroll',
          'ZKTeco biometrics',
          'API & Webhooks',
          'Accounting exports',
          'Priority support within 24h',
        ],
      },
      enterprise: {
        label: 'Enterprise',
        employeeLimit: 'Unlimited employees',
        features: [
          'Everything in Operations',
          'Multi-country & multi-currency',
          'SSO SAML/OIDC (coming soon)',
          'Immutable audit trail',
          'Isolated PostgreSQL schema',
          'Dedicated account manager',
        ],
      },
    },
  },

  tr: {
    meta: {
      title: 'Ödeme — Leopardo HR',
      description:
        'Planınızı seçin, hesabınızı oluşturun ve 14 günlük ücretsiz denemeye başlayın.',
    },
    backToPricing: 'Fiyatlara dön',
    back: 'Geri',
    steps: { recap: 'Özet', account: 'Hesap', payment: 'Ödeme' },
    planChosen: 'Seçilen plan',
    quote: 'Özel teklif',
    perMonth: '/ay',
    trialDaysUnit: 'gün',
    currencyLabel: 'EUR',
    trialBadge: '{days} gün ücretsiz dahil · Deneme bitmeden karttan ücret alınmaz',
    billedAnnually: 'Yıllık faturalandırılır — yılda EUR {savings} tasarruf',
  checkoutUnavailableTitle: 'Çevrimiçi ödeme geçici olarak kullanılamıyor.',
  checkoutUnavailableCtaTrial: 'Ücretsiz denemeyi başlat',
  checkoutUnavailableCtaContact: 'Destek ile iletişime geçin',
    monthly: 'Aylık',
    annual: 'Yıllık',
    trust: {
      secure: 'Güvenli ödeme — TLS 1.3 + AES-256',
      rgpd: "Veriler Avrupa'da — KVKK/GDPR uyumlu",
      cancel: 'Taahhüt yok · 2 tıkla iptal',
    },
    continueWithGoogle: 'Google ile devam et',
    recap: {
      title: 'Seçtiğiniz plan',
      subtitle: 'Hesabınızı oluşturmadan önce ayrıntıları kontrol edin.',
      trialNote:
        '{days} gün ücretsiz deneme. Kartınıza yalnızca deneme süresi sonunda ücret yansır. Panelinizden istediğiniz zaman iptal edebilirsiniz.',
      wrongPlan: 'Yanlış plan mı?',
      viewAllPlans: 'Tüm planları gör',
      continueCta: 'Devam et — ayda EUR {price}',
    },
    account: {
      title: 'Hesabınızı oluşturun',
      subtitle: 'Leopardo alanınız saniyeler içinde hazır olacak.',
      orEmail: 'veya e-postanızla',
      firstName: 'Ad',
      lastName: 'Soyad',
      email: 'İş e-postası',
      company: 'Şirket',
      phone: 'Telefon',
      employees: 'Çalışan sayısı',
      choose: 'Seçin',
      placeholders: {
        firstName: 'Ayşe',
        lastName: 'Yılmaz',
        email: 'ayse@sirket.com',
        company: 'Şirket adınız',
        phone: '+90 555 000 0000',
      },
      errors: {
        firstName: 'Ad zorunludur',
        lastName: 'Soyad zorunludur',
        email: 'Geçerli bir iş e-postası zorunludur',
        company: 'Şirket adı zorunludur',
      },
      next: 'Ödemeye geç',
    },
    payment: {
      title: 'Ödeme bilgileri',
      sandboxNoticeTitle: 'Test modu etkin — Gerçek karttan ücret alınmaz',
      sandboxNoticeBody: "Stripe Price ID'leri henüz yapılandırılmadı. Aşağıdaki test kartını kullanın.",
      fillTestCard: 'Test kartıyla doldur',
      filledTestCard: '✓ Test kartı dolduruldu',
      cardLabel: 'Kart numarası',
      expiryLabel: 'Son kullanma tarihi',
      cvcLabel: 'CVC',
      cardNameLabel: 'Kart üzerindeki ad',
      cardNamePlaceholder: 'Ayşe Yılmaz',
      testBadge: 'TEST',
      planRow: '{label} planı',
      freeTrialRow: 'Ücretsiz deneme',
      dueToday: 'Bugün ödenecek',
      processing: 'İşleniyor...',
      submitCta: 'Ücretsiz denemeye başla — şimdi EUR 0,00',
      legal: {
        prefix: 'Onaylayarak',
        terms: "Kullanım Koşulları'nı",
        and: 've',
        privacy: "Gizlilik Politikası'nı",
        suffix: 'kabul etmiş olursunuz.',
      },
      errors: {
        fillAll: 'Lütfen tüm ödeme alanlarını doldurun.',
        generic: 'Ödeme işlenirken bir hata oluştu.',
        network: 'Sunucuya ulaşılamıyor. Bağlantınızı kontrol edin.',
      },
    },
    free: {
      badge: 'Free planı — 0 €/ay · 5 çalışan',
      title: 'Rehberli deneme burada başlıyor',
      body: 'Free planı rehberli deneme yoluyla alınır: ekibimiz şirketinizi yapılandırır ve size eşlik eder. Kredi kartı gerekmez.',
      cta: 'Rehberli denemeye başla',
      seePricing: 'Fiyatları gör',
    },
    success: {
      badgeSandbox: 'Simülasyon başarılı — Sandbox modu',
      badgePaid: 'Ödeme onaylandı',
      title: 'Leopardo alanınız',
      titleAccent: 'hazır!',
      subtitle: '{company} — {plan} planı ({period}). 14 gün ücretsiz, bugün karttan ücret alınmadı.',
      periodAnnual: 'yıllık',
      periodMonthly: 'aylık',
      cardTitle: 'Deneme özetiniz',
      cardSubtitle: 'Bu bilgileri saklayın',
      emailRow: 'E-posta',
      planRow: 'Plan',
      trialPeriodRow: 'Deneme süresi',
      trialValue: '14 gün ücretsiz',
      chargedTodayRow: 'Bugün alınan ücret',
      zeroAmount: 'EUR 0,00',
      modeRow: 'Mod',
      sandboxBadge: 'SANDBOX — simülasyon',
      sessionRow: 'Oturum ref.',
      copyTitle: 'Referansı kopyala',
      copied: 'Kopyalandı!',
      emailNotice:
        '{email} adresine erişim bilgilerinizi ve başlangıç talimatlarınızı içeren bir onay e-postası gönderildi.',
      emailFallback: 'adresinize',
      nextStepsTitle: 'Sonraki adımlar',
      nextSteps: [
        {
          title: 'Alanınıza giriş yapın',
          desc: 'Yönetici paneline erişin ve ilk ekibinizi yapılandırın.',
          cta: 'Panele eriş',
        },
        {
          title: 'Mobil uygulamaları indirin',
          desc: 'Employee ve Manager uygulamaları Android ve iOS için mevcut.',
          cta: 'Uygulamaları indir',
        },
        {
          title: 'Ekibinizi davet edin',
          desc: 'Davetleri panelden gönderin — çalışanlarınız erişimi e-postayla alır.',
          cta: 'Davetleri yönet',
        },
      ],
      primaryCta: 'Alanıma eriş',
      secondaryCta: 'Uygulamaları indir',
      helpPrefix: 'Sorularınız mı var?',
      helpLink: 'Destekle iletişime geçin',
      helpSuffix: '· 24 iş saati içinde yanıtlıyoruz.',
    },
    plans: {
      free: {
        label: 'Free',
        employeeLimit: '5 çalışana kadar',
        features: [
          'Web yoklama',
          'İzin, devamsızlık ve bakiye takibi',
          'Çalışan dosyaları ve İK belgeleri',
          'Employee mobil uygulaması dahil',
          'E-posta desteği',
        ],
      },
      pilot: {
        label: 'Pilot',
        employeeLimit: '30 çalışana kadar',
        features: [
          'Web ve mobil yoklama',
          'İzin ve devamsızlık',
          'Çalışan dosyaları',
          'PDF bordro dökümü',
          'Yönetici paneli',
          'Employee ve Manager uygulamaları',
          '48 saat içinde e-posta desteği',
        ],
      },
      operations: {
        label: 'Operations',
        employeeLimit: '250 çalışana kadar',
        features: [
          "Pilot'un tamamı",
          'Otomatik bordro',
          'ZKTeco biyometri',
          "API ve Webhook'lar",
          'Muhasebe dışa aktarımları',
          '24 saat içinde öncelikli destek',
        ],
      },
      enterprise: {
        label: 'Enterprise',
        employeeLimit: 'Sınırsız çalışan',
        features: [
          "Operations'un tamamı",
          'Çok ülkeli ve çok para birimli',
          'SSO SAML/OIDC (yakında)',
          'Değiştirilemez denetim izi',
          'İzole PostgreSQL şeması',
          'Özel hesap yöneticisi',
        ],
      },
    },
  },

  ar: {
    meta: {
      title: 'إتمام الدفع — Leopardo HR',
      description: 'اختر باقتك، أنشئ حسابك وابدأ تجربتك المجانية لمدة 14 يوماً.',
    },
    backToPricing: 'العودة إلى الأسعار',
    back: 'رجوع',
    steps: { recap: 'الملخص', account: 'الحساب', payment: 'الدفع' },
    planChosen: 'الباقة المختارة',
    quote: 'عرض مخصص',
    perMonth: '/شهر',
    trialDaysUnit: 'أيام',
    currencyLabel: 'EUR',
    trialBadge: '{days} أيام مجانية مشمولة · لن يُخصم من بطاقتك قبل نهاية التجربة',
    billedAnnually: 'فوترة سنوية — وفّر EUR {savings} سنوياً',
  checkoutUnavailableTitle: 'الدفع عبر الإنترنت غير متاح مؤقتًا.',
  checkoutUnavailableCtaTrial: 'ابدأ النسخة التجريبية المجانية',
  checkoutUnavailableCtaContact: 'تواصل مع الدعم',
    monthly: 'شهري',
    annual: 'سنوي',
    trust: {
      secure: 'دفع آمن — TLS 1.3 + AES-256',
      rgpd: 'بياناتك مستضافة في أوروبا — متوافق مع GDPR',
      cancel: 'بدون التزام · إلغاء بنقرتين',
    },
    continueWithGoogle: 'المتابعة عبر Google',
    recap: {
      title: 'باقتك المختارة',
      subtitle: 'راجع التفاصيل قبل إنشاء حسابك.',
      trialNote:
        'تجربة مجانية لمدة {days} يوماً. لن يُخصم من بطاقتك إلا بعد انتهاء فترة التجربة. يمكنك الإلغاء في أي وقت من لوحة التحكم.',
      wrongPlan: 'الباقة خاطئة؟',
      viewAllPlans: 'عرض كل الباقات',
      continueCta: 'متابعة — EUR {price}/شهر',
    },
    account: {
      title: 'أنشئ حسابك',
      subtitle: 'ستكون مساحة Leopardo جاهزة خلال ثوانٍ.',
      orEmail: 'أو عبر بريدك الإلكتروني',
      firstName: 'الاسم الأول',
      lastName: 'اسم العائلة',
      email: 'البريد المهني',
      company: 'الشركة',
      phone: 'الهاتف',
      employees: 'عدد الموظفين',
      choose: 'اختر',
      placeholders: {
        firstName: 'مريم',
        lastName: 'بن علي',
        email: 'mariam@company.com',
        company: 'اسم شركتك',
        phone: '+216 55 000 000',
      },
      errors: {
        firstName: 'الاسم الأول مطلوب',
        lastName: 'اسم العائلة مطلوب',
        email: 'بريد مهني صالح مطلوب',
        company: 'اسم الشركة مطلوب',
      },
      next: 'المتابعة إلى الدفع',
    },
    payment: {
      title: 'معلومات الدفع',
      sandboxNoticeTitle: 'وضع الاختبار مفعّل — لن يُخصم أي مبلغ حقيقي',
      sandboxNoticeBody: 'لم يتم تكوين معرفات أسعار Stripe بعد. استخدم بطاقة الاختبار أدناه.',
      fillTestCard: 'تعبئة ببطاقة الاختبار',
      filledTestCard: '✓ تمت تعبئة بطاقة الاختبار',
      cardLabel: 'رقم البطاقة',
      expiryLabel: 'تاريخ الانتهاء',
      cvcLabel: 'CVC',
      cardNameLabel: 'الاسم على البطاقة',
      cardNamePlaceholder: 'مريم بن علي',
      testBadge: 'تجريبي',
      planRow: 'باقة {label}',
      freeTrialRow: 'تجربة مجانية',
      dueToday: 'المستحق اليوم',
      processing: 'جارٍ المعالجة...',
      submitCta: 'ابدأ التجربة المجانية — EUR 0,00 الآن',
      legal: {
        prefix: 'بالتأكيد، فإنك تقبل',
        terms: 'شروط الاستخدام',
        and: 'و',
        privacy: 'سياسة الخصوصية',
        suffix: '.',
      },
      errors: {
        fillAll: 'يرجى ملء جميع حقول الدفع.',
        generic: 'حدث خطأ أثناء معالجة الدفع.',
        network: 'تعذر الوصول إلى الخادم. تحقق من اتصالك.',
      },
    },
    free: {
      badge: 'الباقة المجانية — 0 €/شهر · 5 موظفين',
      title: 'تبدأ تجربتك الموجّهة هنا',
      body: 'يتم الاشتراك في الباقة المجانية عبر تجربة موجّهة: يهيّئ فريقنا شركتك ويرافقك. لا حاجة لبطاقة بنكية.',
      cta: 'ابدأ التجربة الموجّهة',
      seePricing: 'عرض الأسعار',
    },
    success: {
      badgeSandbox: 'اكتملت المحاكاة — وضع التجربة',
      badgePaid: 'تم تأكيد الدفع',
      title: 'مساحة Leopardo الخاصة بك',
      titleAccent: 'جاهزة!',
      subtitle: '{company} — باقة {plan} ({period}). 14 يوماً مجاناً، لم يُخصم أي مبلغ اليوم.',
      periodAnnual: 'سنوي',
      periodMonthly: 'شهري',
      cardTitle: 'ملخص تجربتك',
      cardSubtitle: 'احتفظ بهذه المعلومات',
      emailRow: 'البريد الإلكتروني',
      planRow: 'الباقة',
      trialPeriodRow: 'فترة التجربة',
      trialValue: '14 يوماً مجاناً',
      chargedTodayRow: 'المخصوم اليوم',
      zeroAmount: 'EUR 0,00',
      modeRow: 'الوضع',
      sandboxBadge: 'تجريبي — محاكاة',
      sessionRow: 'مرجع الجلسة',
      copyTitle: 'نسخ المرجع',
      copied: 'تم النسخ!',
      emailNotice:
        'تم إرسال بريد تأكيد إلى {email} يتضمن بيانات وصولك وتعليمات البدء.',
      emailFallback: 'بريدك',
      nextStepsTitle: 'الخطوات التالية',
      nextSteps: [
        {
          title: 'سجّل الدخول إلى مساحتك',
          desc: 'افتح لوحة تحكم المدير وأعد فريقك الأول.',
          cta: 'الوصول إلى اللوحة',
        },
        {
          title: 'نزّل تطبيقات الجوال',
          desc: 'تطبيقا Employee و Manager متاحان على Android و iOS.',
          cta: 'نزّل التطبيقات',
        },
        {
          title: 'ادعُ فريقك',
          desc: 'أرسل الدعوات من لوحة التحكم — يتلقى موظفوك الوصول عبر البريد.',
          cta: 'إدارة الدعوات',
        },
      ],
      primaryCta: 'الوصول إلى مساحتي',
      secondaryCta: 'نزّل التطبيقات',
      helpPrefix: 'لديك أسئلة؟',
      helpLink: 'تواصل مع الدعم',
      helpSuffix: '· نرد خلال 24 ساعة عمل.',
    },
    plans: {
      free: {
        label: 'Free',
        employeeLimit: 'حتى 5 موظفين',
        features: [
          'تسجيل الحضور عبر الويب',
          'الإجازات والغياب والأرصدة',
          'ملفات الموظفين والمستندات',
          'تطبيق Employee للجوال مشمول',
          'دعم عبر البريد',
        ],
      },
      pilot: {
        label: 'Pilot',
        employeeLimit: 'حتى 30 موظفاً',
        features: [
          'تسجيل الحضور ويب وجوال',
          'الإجازات والغياب',
          'ملفات الموظفين',
          'قسائم رواتب PDF',
          'لوحة تحكم المدير',
          'تطبيقا Employee و Manager',
          'دعم بريدي خلال 48 ساعة',
        ],
      },
      operations: {
        label: 'Operations',
        employeeLimit: 'حتى 250 موظفاً',
        features: [
          'كل ما في Pilot',
          'رواتب آلية',
          'بصمة ZKTeco',
          'API و Webhooks',
          'تصديرات محاسبية',
          'دعم ذو أولوية خلال 24 ساعة',
        ],
      },
      enterprise: {
        label: 'Enterprise',
        employeeLimit: 'موظفون بلا حدود',
        features: [
          'كل ما في Operations',
          'متعدد البلدان والعملات',
          'SSO SAML/OIDC (قريباً)',
          'سجل تدقيق غير قابل للتغيير',
          'مخطط PostgreSQL معزول',
          'مدير حساب مخصص',
        ],
      },
    },
  },
}

export function getCheckoutCopy(locale: AppLocale): CheckoutCopy {
  return checkoutCopyByLocale[locale] ?? checkoutCopyByLocale.en
}
