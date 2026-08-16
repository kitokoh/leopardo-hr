import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /videos par locale (issue #3248 — résiduel : la page
// était 100 % FR codé en dur). Les icônes restent dans la page ; ce fichier
// ne porte que les chaînes.

export type UpcomingVideo = {
  title: string
  description: string
  category: string
}

export type VideosContent = {
  hero: {
    badge: string
    headline: string
    subheadline: string
    ctaPrimary: string
    ctaSecondary: string
  }
  demo: {
    title: string
    description: string
    ariaLabel: string
    fallback: string
  }
  upcoming: {
    badge: string
    title: string
    subtitle: string
    videos: UpcomingVideo[]
  }
  cta: {
    title: string
    description: string
    primary: string
    secondary: string
  }
}

export const videosPageCopy: Record<AppLocale, VideosContent> = {
  fr: {
    hero: {
      badge: 'Vidéos',
      headline: 'Vidéos & Démos',
      subheadline:
        'Découvrez Leopardo RH en action à travers notre démo produit et nos tutoriels',
      ctaPrimary: 'Demander une démo live',
      ctaSecondary: 'Essai gratuit',
    },
    demo: {
      title: 'Présentation complète de Leopardo RH',
      description:
        "Tour d'horizon de la plateforme — pointage, paie, absences, mobile et kiosk.",
      ariaLabel: 'Vidéo de présentation de Leopardo RH',
      fallback: 'Votre navigateur ne supporte pas la lecture vidéo HTML5.',
    },
    upcoming: {
      badge: 'À venir',
      title: 'Plus de vidéos à venir',
      subtitle:
        "Nous préparons de nouveaux tutoriels détaillés. En attendant, découvrez la démo ci-dessus ou réservez une démonstration en direct avec notre équipe.",
      videos: [
        {
          title: 'Configuration du pointage ZKTeco',
          description:
            'Connecter et configurer vos bornes biométriques ZKTeco avec Leopardo RH.',
          category: 'Tutoriel',
        },
        {
          title: 'Paie multi-pays : Algérie, Maroc, France',
          description:
            'Générer des bulletins de paie conformes pour plusieurs pays depuis une seule interface.',
          category: 'Tutoriel',
        },
        {
          title: 'Application mobile pour les employés',
          description:
            'Pointage, demandes de congés et consultation des bulletins depuis le smartphone.',
          category: 'Tutoriel',
        },
        {
          title: 'Intégration API et webhooks',
          description:
            "Connecter Leopardo RH à vos outils existants via l'API REST et les webhooks.",
          category: 'Intégration',
        },
        {
          title: 'Démonstration produit : paie multi-pays',
          description:
            'Parcours illustratif — gestion de la paie et des présences pour une entreprise type de 350 employés répartis sur 3 pays.',
          category: 'Démonstration',
        },
      ],
    },
    cta: {
      title: 'Prêt à voir Leopardo RH en action ?',
      description: 'Réservez une démo personnalisée avec notre équipe',
      primary: 'Réserver ma démo',
      secondary: 'Voir les tarifs',
    },
  },
  en: {
    hero: {
      badge: 'Videos',
      headline: 'Videos & Demos',
      subheadline:
        'See Leopardo RH in action through our product demo and tutorials',
      ctaPrimary: 'Book a live demo',
      ctaSecondary: 'Start free trial',
    },
    demo: {
      title: 'Full Leopardo RH walkthrough',
      description:
        'Platform overview — time tracking, payroll, absences, mobile and kiosk.',
      ariaLabel: 'Leopardo RH presentation video',
      fallback: 'Your browser does not support HTML5 video playback.',
    },
    upcoming: {
      badge: 'Coming soon',
      title: 'More videos coming soon',
      subtitle:
        'We are preparing new in-depth tutorials. Meanwhile, watch the demo above or book a live demonstration with our team.',
      videos: [
        {
          title: 'ZKTeco time tracking setup',
          description:
            'Connect and configure your ZKTeco biometric terminals with Leopardo RH.',
          category: 'Tutorial',
        },
        {
          title: 'Multi-country payroll: Algeria, Morocco, France',
          description:
            'Generate compliant payslips for several countries from a single interface.',
          category: 'Tutorial',
        },
        {
          title: 'Mobile app for employees',
          description:
            'Time tracking, leave requests and payslip access from your smartphone.',
          category: 'Tutorial',
        },
        {
          title: 'API and webhooks integration',
          description:
            'Connect Leopardo RH to your existing tools via the REST API and webhooks.',
          category: 'Integration',
        },
        {
          title: 'Product demonstration: multi-country payroll',
          description:
            'Illustrative walkthrough — payroll and attendance for a typical 350-employee company across 3 countries.',
          category: 'Demonstration',
        },
      ],
    },
    cta: {
      title: 'Ready to see Leopardo RH in action?',
      description: 'Book a personalised demo with our team',
      primary: 'Book my demo',
      secondary: 'See pricing',
    },
  },
  tr: {
    hero: {
      badge: 'Videolar',
      headline: 'Videolar & Demolar',
      subheadline:
        'Leopardo RH’i ürün demomuz ve eğitimlerimizle keşfedin',
      ctaPrimary: 'Canlı demo talep edin',
      ctaSecondary: 'Ücretsiz deneme',
    },
    demo: {
      title: 'Leopardo RH kapsamlı tanıtımı',
      description:
        'Platforma genel bakış — yoklama, maaş, izinler, mobil ve kiosk.',
      ariaLabel: 'Leopardo RH tanıtım videosu',
      fallback: 'Tarayıcınız HTML5 video oynatmayı desteklemiyor.',
    },
    upcoming: {
      badge: 'Yakında',
      title: 'Daha fazla video yakında',
      subtitle:
        'Yeni ayrıntılı eğitimler hazırlıyoruz. Bu sırada yukarıdaki demoyu izleyin veya ekibimizle canlı bir tanıtım planlayın.',
      videos: [
        {
          title: 'ZKTeco yoklama kurulumu',
          description:
            'ZKTeco biyometrik terminallerinizi Leopardo RH ile bağlayın ve yapılandırın.',
          category: 'Eğitim',
        },
        {
          title: 'Çok ülkeli maaş: Cezayir, Fas, Fransa',
          description:
            'Tek bir arayüzden birden fazla ülke için uyumlu maaş bordroları oluşturun.',
          category: 'Eğitim',
        },
        {
          title: 'Çalışanlar için mobil uygulama',
          description:
            'Akıllı telefonunuzdan yoklama, izin talepleri ve maaş bordrosu erişimi.',
          category: 'Eğitim',
        },
        {
          title: 'API ve webhook entegrasyonu',
          description:
            'Leopardo RH’i REST API ve webhook’lar aracılığıyla mevcut araçlarınıza bağlayın.',
          category: 'Entegrasyon',
        },
        {
          title: 'Ürün tanıtımı: çok ülkeli maaş',
          description:
            'Açıklayıcı örnek — 3 ülkeye yayılmış 350 çalışanlı tipik bir şirketin maaş ve devam yönetimi.',
          category: 'Tanıtım',
        },
      ],
    },
    cta: {
      title: 'Leopardo RH’i görmeye hazır mısınız?',
      description: 'Ekibimizle kişisel bir demo planlayın',
      primary: 'Demomu planla',
      secondary: 'Fiyatları gör',
    },
  },
  ar: {
    hero: {
      badge: 'فيديو',
      headline: 'فيديو وعروض توضيحية',
      subheadline: 'اكتشف ليوناردو RH في العمل من خلال عرضنا التوضيحي ودروسنا',
      ctaPrimary: 'اطلب عرضًا مباشرًا',
      ctaSecondary: 'تجربة مجانية',
    },
    demo: {
      title: 'جولة شاملة في ليوناردو RH',
      description:
        'نظرة عامة على المنصة — الحضور، الرواتب، الإجازات، الجوال والكشك.',
      ariaLabel: 'فيديو تقديمي لليوناردو RH',
      fallback: 'متصفحك لا يدعم تشغيل فيديو HTML5.',
    },
    upcoming: {
      badge: 'قريبًا',
      title: 'المزيد من الفيديوهات قريبًا',
      subtitle:
        'نستعد لإطلاق دروس جديدة مفصلة. في هذه الأثناء شاهد العرض أعلاه أو احجز عرضًا مباشرًا مع فريقنا.',
      videos: [
        {
          title: 'إعداد الحضور ZKTeco',
          description: 'قم بتوصيل وتهيئة أجهزة ZKTeco البيومترية مع ليوناردو RH.',
          category: 'درس',
        },
        {
          title: 'رواتب متعددة الدول: الجزائر، المغرب، فرنسا',
          description:
            'أنشئ كشوف رواتب متوافقة لعدة دول من واجهة واحدة.',
          category: 'درس',
        },
        {
          title: 'تطبيق الجوال للموظفين',
          description:
            'الحضور وطلبات الإجازات والاطلاع على كشوف الرواتب من هاتفك الذكي.',
          category: 'درس',
        },
        {
          title: 'دمج API وWebhooks',
          description:
            'اربط ليوناردو RH بأدواتك الحالية عبر REST API وWebhooks.',
          category: 'دمج',
        },
        {
          title: 'عرض المنتج: رواتب متعددة الدول',
          description:
            'مثال توضيحي — إدارة الرواتب والحضور لشركة نموذجية من 350 موظفًا موزعين على 3 دول.',
          category: 'عرض',
        },
      ],
    },
    cta: {
      title: 'هل أنت مستعد لرؤية ليوناردو RH؟',
      description: 'احجز عرضًا مخصصًا مع فريقنا',
      primary: 'احجز عرضي',
      secondary: 'اطلع على الأسعار',
    },
  },
}
