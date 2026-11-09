/**
 * Leopardo Public — copie i18n du portail grand public (fr, en, tr, ar).
 *
 * Le portail est AUTONOME : sa copie vit ici plutôt que dans le catalogue
 * partagé `shared/i18n` (propagé vers le backend et le mobile, où le portail
 * public n'existe pas). Les 4 locales sont obligatoires — la vitrine est
 * servie en fr/en/tr/ar et l'arabe est RTL.
 *
 * ⚠️ Toute chaîne visible doit venir d'ici : aucune copie en dur dans les
 * composants (convention vitrine, gardes mojibake/accents).
 */

export const PUBLIC_PORTAL_LOCALES = ['fr', 'en', 'tr', 'ar'] as const;
export type PublicPortalLocale = (typeof PUBLIC_PORTAL_LOCALES)[number];

export interface PublicPortalCopy {
  /** Chrome partagé (en-tête + pied de page). */
  chrome: {
    brand: string;
    brandTagline: string;
    home: string;
    discover: string;
    restaurants: string;
    shops: string;
    operators: string;
    companies: string;
    stays: string;
    library: string;
    toolbox: string;
    menu: string;
    closeMenu: string;
    openMenu: string;
    language: string;
    skipToContent: string;
    backToPortal: string;
    footerTagline: string;
    footerDiscoverTitle: string;
    footerBusinessTitle: string;
    footerLegalTitle: string;
    footerPricing: string;
    footerAbout: string;
    footerContact: string;
    footerCareers: string;
    footerPrivacy: string;
    footerTerms: string;
    footerLegalNotice: string;
    footerStatus: string;
    footerRights: string;
    footerProSpace: string;
    footerProSpaceHint: string;
    footerProSpaceCta: string;
  };
  /** Page d'accueil du portail (`/decouvrir`). */
  home: {
    title: string;
    description: string;
    heroTitle: string;
    heroSubtitle: string;
    heroCta: string;
    heroSecondaryCta: string;
    categoriesTitle: string;
    categoriesSubtitle: string;
    howTitle: string;
    howStep1Title: string;
    howStep1Body: string;
    howStep2Title: string;
    howStep2Body: string;
    howStep3Title: string;
    howStep3Body: string;
    proTitle: string;
    proBody: string;
    proCta: string;
  };
  /** Annuaire générique par verticale (`/boutiques`, `/operateurs`, …). */
  directory: {
    title: string;
    description: string;
    searchLabel: string;
    searchPlaceholder: string;
    cityLabel: string;
    cityPlaceholder: string;
    submit: string;
    nearMe: string;
    locating: string;
    nearActive: string;
    clearNear: string;
    geoDenied: string;
    geoUnsupported: string;
    loading: string;
    loadError: string;
    retry: string;
    noResults: string;
    resultsCount: string;
    previous: string;
    next: string;
    pageLabel: string;
    viewProfile: string;
    distanceSuffix: string;
    backToDiscover: string;
    emptyHint: string;
  };
  /** Libellés des types canoniques exposés par `/public/discover`. */
  types: {
    showcase: string;
    restaurant: string;
    hospitality_property: string;
    retail_shop: string;
    vtc_operator: string;
    trade_company: string;
  };
  /**
   * Titre + description SEO propres à chaque annuaire de verticale.
   *
   * Une description générique partagée par `/boutiques`, `/operateurs`,
   * `/entreprises` et `/stay` produirait quatre pages aux métadonnées
   * identiques — du contenu dupliqué aux yeux des moteurs. Chaque verticale
   * porte donc sa propre promesse.
   */
  directoryByType: Record<
    'retail_shop' | 'vtc_operator' | 'trade_company' | 'hospitality_property',
    { title: string; description: string }
  >;
}

const fr: PublicPortalCopy = {
  chrome: {
    brand: 'Leopardo',
    brandTagline: 'Le portail public',
    home: 'Accueil',
    discover: 'Découvrir',
    restaurants: 'Restaurants',
    shops: 'Boutiques',
    operators: 'VTC & taxis',
    companies: 'Entreprises',
    stays: 'Hébergements',
    library: 'Bibliothèque',
    toolbox: 'Boîte à outils',
    menu: 'Menu',
    closeMenu: 'Fermer le menu',
    openMenu: 'Ouvrir le menu',
    language: 'Langue',
    skipToContent: 'Aller au contenu principal',
    backToPortal: 'Retour au portail',
    footerTagline: 'Trouvez un professionnel près de chez vous et contactez-le directement.',
    footerDiscoverTitle: 'Découvrir',
    footerBusinessTitle: 'Leopardo',
    footerLegalTitle: 'Légal',
    footerPricing: 'Tarifs',
    footerAbout: 'À propos',
    footerContact: 'Contact',
    footerCareers: 'Carrières',
    footerPrivacy: 'Confidentialité',
    footerTerms: "Conditions d'utilisation",
    footerLegalNotice: 'Mentions légales',
    footerStatus: 'État des services',
    footerRights: 'Tous droits réservés.',
    footerProSpace: 'Vous êtes un professionnel ?',
    footerProSpaceHint: 'Publiez votre fiche et recevez des demandes de clients.',
    footerProSpaceCta: 'Découvrir Leopardo',
  },
  home: {
    title: 'Trouvez un professionnel près de chez vous',
    description:
      'Recherchez un métier, un service ou un nom, puis contactez le professionnel directement.',
    heroTitle: 'Trouvez un professionnel près de chez vous',
    heroSubtitle:
      'Restaurants, boutiques, hébergements, VTC, entreprises : cherchez par métier, par ville ou autour de vous, puis contactez directement le professionnel.',
    heroCta: 'Lancer une recherche',
    heroSecondaryCta: 'Parcourir les catégories',
    categoriesTitle: 'Parcourir par catégorie',
    categoriesSubtitle: 'Choisissez un type de professionnel pour affiner votre recherche.',
    howTitle: 'Comment ça marche',
    howStep1Title: 'Cherchez',
    howStep1Body: 'Un métier, un service, un nom ou une ville — sans connaître la catégorie.',
    howStep2Title: 'Comparez',
    howStep2Body: 'Consultez les fiches publiques : adresse, description, horaires et avis.',
    howStep3Title: 'Contactez',
    howStep3Body: 'Écrivez directement au professionnel, sans intermédiaire ni commission.',
    proTitle: 'Vous êtes un professionnel ?',
    proBody:
      'Publiez votre fiche publique et recevez les demandes de vos clients directement dans votre espace Leopardo.',
    proCta: 'Découvrir Leopardo pour les professionnels',
  },
  directory: {
    title: 'Annuaire',
    description: 'Parcourez les professionnels publiés sur Leopardo.',
    searchLabel: 'Recherche',
    searchPlaceholder: 'Nom, service, mot-clé…',
    cityLabel: 'Ville ou zone',
    cityPlaceholder: 'Ex. Casablanca',
    submit: 'Rechercher',
    nearMe: 'Autour de moi',
    locating: 'Localisation…',
    nearActive: 'Résultats autour de votre position',
    clearNear: 'Effacer la position',
    geoDenied:
      "Géolocalisation refusée — autorisez l'accès à votre position ou saisissez une ville.",
    geoUnsupported: "La géolocalisation n'est pas disponible sur cet appareil.",
    loading: 'Chargement…',
    loadError: 'Impossible de charger les résultats. Réessayez.',
    retry: 'Réessayer',
    noResults: 'Aucun professionnel ne correspond à votre recherche.',
    resultsCount: 'résultats',
    previous: 'Précédent',
    next: 'Suivant',
    pageLabel: 'Page',
    viewProfile: 'Voir la fiche',
    distanceSuffix: 'km',
    backToDiscover: 'Tous les professionnels',
    emptyHint: 'Essayez une autre ville ou une autre catégorie.',
  },
  types: {
    showcase: 'Professionnel',
    restaurant: 'Restaurant',
    hospitality_property: 'Hébergement',
    retail_shop: 'Boutique',
    vtc_operator: 'VTC / taxi',
    trade_company: 'Entreprise',
  },
  directoryByType: {
    retail_shop: {
      title: 'Boutiques',
      description:
        'Découvrez les boutiques en ligne publiées sur Leopardo : produits, catégories, villes et contact direct avec le commerçant.',
    },
    vtc_operator: {
      title: 'VTC & taxis',
      description:
        'Trouvez un opérateur VTC ou taxi publié sur Leopardo, consultez sa zone d’activité et demandez un devis pour votre trajet.',
    },
    trade_company: {
      title: 'Entreprises',
      description:
        'Parcourez les entreprises de commerce international et B2B publiées sur Leopardo : produits, pays, import et export.',
    },
    hospitality_property: {
      title: 'Hébergements',
      description:
        'Trouvez un hôtel, une maison d’hôtes ou un appartement publié sur Leopardo, comparez les chambres et réservez directement.',
    },
  },
};

const en: PublicPortalCopy = {
  chrome: {
    brand: 'Leopardo',
    brandTagline: 'The public portal',
    home: 'Home',
    discover: 'Discover',
    restaurants: 'Restaurants',
    shops: 'Shops',
    operators: 'Ride-hailing & taxis',
    companies: 'Companies',
    stays: 'Stays',
    library: 'Library',
    toolbox: 'Toolbox',
    menu: 'Menu',
    closeMenu: 'Close menu',
    openMenu: 'Open menu',
    language: 'Language',
    skipToContent: 'Skip to main content',
    backToPortal: 'Back to the portal',
    footerTagline: 'Find a professional near you and contact them directly.',
    footerDiscoverTitle: 'Discover',
    footerBusinessTitle: 'Leopardo',
    footerLegalTitle: 'Legal',
    footerPricing: 'Pricing',
    footerAbout: 'About',
    footerContact: 'Contact',
    footerCareers: 'Careers',
    footerPrivacy: 'Privacy',
    footerTerms: 'Terms of use',
    footerLegalNotice: 'Legal notice',
    footerStatus: 'Service status',
    footerRights: 'All rights reserved.',
    footerProSpace: 'Are you a professional?',
    footerProSpaceHint: 'Publish your listing and receive customer requests.',
    footerProSpaceCta: 'Discover Leopardo',
  },
  home: {
    title: 'Find a professional near you',
    description: 'Search for a trade, a service or a name, then contact the professional directly.',
    heroTitle: 'Find a professional near you',
    heroSubtitle:
      'Restaurants, shops, stays, ride-hailing, companies: search by trade, by city or around you, then contact the professional directly.',
    heroCta: 'Start a search',
    heroSecondaryCta: 'Browse categories',
    categoriesTitle: 'Browse by category',
    categoriesSubtitle: 'Pick a type of professional to narrow your search.',
    howTitle: 'How it works',
    howStep1Title: 'Search',
    howStep1Body: 'A trade, a service, a name or a city — without knowing the category.',
    howStep2Title: 'Compare',
    howStep2Body: 'Read the public listings: address, description, opening hours and reviews.',
    howStep3Title: 'Contact',
    howStep3Body: 'Message the professional directly, with no middleman and no commission.',
    proTitle: 'Are you a professional?',
    proBody:
      'Publish your public listing and receive your customers’ requests directly in your Leopardo workspace.',
    proCta: 'Discover Leopardo for professionals',
  },
  directory: {
    title: 'Directory',
    description: 'Browse the professionals published on Leopardo.',
    searchLabel: 'Search',
    searchPlaceholder: 'Name, service, keyword…',
    cityLabel: 'City or area',
    cityPlaceholder: 'e.g. Casablanca',
    submit: 'Search',
    nearMe: 'Near me',
    locating: 'Locating…',
    nearActive: 'Results around your position',
    clearNear: 'Clear position',
    geoDenied: 'Location denied — allow access to your position or enter a city.',
    geoUnsupported: 'Geolocation is not available on this device.',
    loading: 'Loading…',
    loadError: 'Could not load the results. Please try again.',
    retry: 'Retry',
    noResults: 'No professional matches your search.',
    resultsCount: 'results',
    previous: 'Previous',
    next: 'Next',
    pageLabel: 'Page',
    viewProfile: 'View listing',
    distanceSuffix: 'km',
    backToDiscover: 'All professionals',
    emptyHint: 'Try another city or another category.',
  },
  types: {
    showcase: 'Professional',
    restaurant: 'Restaurant',
    hospitality_property: 'Stay',
    retail_shop: 'Shop',
    vtc_operator: 'Ride-hailing / taxi',
    trade_company: 'Company',
  },
  directoryByType: {
    retail_shop: {
      title: 'Shops',
      description:
        'Browse the online shops published on Leopardo: products, categories, cities and direct contact with the merchant.',
    },
    vtc_operator: {
      title: 'Ride-hailing & taxis',
      description:
        'Find a ride-hailing or taxi operator published on Leopardo, check their service area and request a quote for your trip.',
    },
    trade_company: {
      title: 'Companies',
      description:
        'Browse the international trade and B2B companies published on Leopardo: products, countries, import and export.',
    },
    hospitality_property: {
      title: 'Stays',
      description:
        'Find a hotel, guesthouse or apartment published on Leopardo, compare rooms and book directly.',
    },
  },
};

const tr: PublicPortalCopy = {
  chrome: {
    brand: 'Leopardo',
    brandTagline: 'Herkese açık portal',
    home: 'Ana sayfa',
    discover: 'Keşfet',
    restaurants: 'Restoranlar',
    shops: 'Mağazalar',
    operators: 'Taksi ve transfer',
    companies: 'Şirketler',
    stays: 'Konaklama',
    library: 'Kütüphane',
    toolbox: 'Araç kutusu',
    menu: 'Menü',
    closeMenu: 'Menüyü kapat',
    openMenu: 'Menüyü aç',
    language: 'Dil',
    skipToContent: 'Ana içeriğe geç',
    backToPortal: 'Portala dön',
    footerTagline: 'Yakınınızdaki bir uzmanı bulun ve doğrudan iletişime geçin.',
    footerDiscoverTitle: 'Keşfet',
    footerBusinessTitle: 'Leopardo',
    footerLegalTitle: 'Yasal',
    footerPricing: 'Fiyatlar',
    footerAbout: 'Hakkımızda',
    footerContact: 'İletişim',
    footerCareers: 'Kariyer',
    footerPrivacy: 'Gizlilik',
    footerTerms: 'Kullanım koşulları',
    footerLegalNotice: 'Yasal bildirim',
    footerStatus: 'Hizmet durumu',
    footerRights: 'Tüm hakları saklıdır.',
    footerProSpace: 'Bir uzman mısınız?',
    footerProSpaceHint: 'Profilinizi yayınlayın ve müşteri talepleri alın.',
    footerProSpaceCta: "Leopardo'yu keşfedin",
  },
  home: {
    title: 'Yakınınızdaki bir uzmanı bulun',
    description: 'Bir meslek, hizmet veya isim arayın, ardından uzmanla doğrudan iletişime geçin.',
    heroTitle: 'Yakınınızdaki bir uzmanı bulun',
    heroSubtitle:
      'Restoranlar, mağazalar, konaklama, taksi, şirketler: mesleğe, şehre veya konumunuza göre arayın, ardından uzmanla doğrudan iletişime geçin.',
    heroCta: 'Aramaya başla',
    heroSecondaryCta: 'Kategorilere göz at',
    categoriesTitle: 'Kategoriye göre göz at',
    categoriesSubtitle: 'Aramanızı daraltmak için bir uzman türü seçin.',
    howTitle: 'Nasıl çalışır',
    howStep1Title: 'Arayın',
    howStep1Body: 'Bir meslek, hizmet, isim veya şehir — kategoriyi bilmeden.',
    howStep2Title: 'Karşılaştırın',
    howStep2Body: 'Herkese açık profilleri okuyun: adres, açıklama, çalışma saatleri ve yorumlar.',
    howStep3Title: 'İletişime geçin',
    howStep3Body: 'Aracı ve komisyon olmadan doğrudan uzmana yazın.',
    proTitle: 'Bir uzman mısınız?',
    proBody:
      'Herkese açık profilinizi yayınlayın ve müşterilerinizin taleplerini doğrudan Leopardo çalışma alanınızda alın.',
    proCta: "Uzmanlar için Leopardo'yu keşfedin",
  },
  directory: {
    title: 'Rehber',
    description: "Leopardo'da yayınlanan uzmanlara göz atın.",
    searchLabel: 'Arama',
    searchPlaceholder: 'Ad, hizmet, anahtar kelime…',
    cityLabel: 'Şehir veya bölge',
    cityPlaceholder: 'Örn. İstanbul',
    submit: 'Ara',
    nearMe: 'Yakınımda',
    locating: 'Konum alınıyor…',
    nearActive: 'Konumunuz çevresindeki sonuçlar',
    clearNear: 'Konumu temizle',
    geoDenied: 'Konum reddedildi — konum erişimine izin verin veya bir şehir girin.',
    geoUnsupported: 'Bu cihazda konum hizmeti kullanılamıyor.',
    loading: 'Yükleniyor…',
    loadError: 'Sonuçlar yüklenemedi. Lütfen tekrar deneyin.',
    retry: 'Tekrar dene',
    noResults: 'Aramanızla eşleşen uzman yok.',
    resultsCount: 'sonuç',
    previous: 'Önceki',
    next: 'Sonraki',
    pageLabel: 'Sayfa',
    viewProfile: 'Profili gör',
    distanceSuffix: 'km',
    backToDiscover: 'Tüm uzmanlar',
    emptyHint: 'Başka bir şehir veya kategori deneyin.',
  },
  types: {
    showcase: 'Uzman',
    restaurant: 'Restoran',
    hospitality_property: 'Konaklama',
    retail_shop: 'Mağaza',
    vtc_operator: 'Taksi / transfer',
    trade_company: 'Şirket',
  },
  directoryByType: {
    retail_shop: {
      title: 'Mağazalar',
      description:
        "Leopardo'da yayınlanan çevrimiçi mağazalara göz atın: ürünler, kategoriler, şehirler ve satıcıyla doğrudan iletişim.",
    },
    vtc_operator: {
      title: 'Taksi ve transfer',
      description:
        "Leopardo'da yayınlanan taksi veya transfer operatörünü bulun, hizmet bölgesini görün ve yolculuğunuz için teklif isteyin.",
    },
    trade_company: {
      title: 'Şirketler',
      description:
        "Leopardo'da yayınlanan uluslararası ticaret ve B2B şirketlerine göz atın: ürünler, ülkeler, ithalat ve ihracat.",
    },
    hospitality_property: {
      title: 'Konaklama',
      description:
        "Leopardo'da yayınlanan otel, pansiyon veya daireyi bulun, odaları karşılaştırın ve doğrudan rezervasyon yapın.",
    },
  },
};

const ar: PublicPortalCopy = {
  chrome: {
    brand: 'ليوباردو',
    brandTagline: 'البوابة العامة',
    home: 'الرئيسية',
    discover: 'اكتشف',
    restaurants: 'المطاعم',
    shops: 'المتاجر',
    operators: 'سيارات الأجرة والنقل',
    companies: 'الشركات',
    stays: 'الإقامة',
    library: 'المكتبة',
    toolbox: 'صندوق الأدوات',
    menu: 'القائمة',
    closeMenu: 'إغلاق القائمة',
    openMenu: 'فتح القائمة',
    language: 'اللغة',
    skipToContent: 'الانتقال إلى المحتوى الرئيسي',
    backToPortal: 'العودة إلى البوابة',
    footerTagline: 'اعثر على مهني قريب منك وتواصل معه مباشرة.',
    footerDiscoverTitle: 'اكتشف',
    footerBusinessTitle: 'ليوباردو',
    footerLegalTitle: 'قانوني',
    footerPricing: 'الأسعار',
    footerAbout: 'من نحن',
    footerContact: 'اتصل بنا',
    footerCareers: 'الوظائف',
    footerPrivacy: 'الخصوصية',
    footerTerms: 'شروط الاستخدام',
    footerLegalNotice: 'الإشعار القانوني',
    footerStatus: 'حالة الخدمات',
    footerRights: 'جميع الحقوق محفوظة.',
    footerProSpace: 'هل أنت مهني؟',
    footerProSpaceHint: 'انشر ملفك العام واستقبل طلبات العملاء.',
    footerProSpaceCta: 'اكتشف ليوباردو',
  },
  home: {
    title: 'اعثر على مهني قريب منك',
    description: 'ابحث عن مهنة أو خدمة أو اسم، ثم تواصل مع المهني مباشرة.',
    heroTitle: 'اعثر على مهني قريب منك',
    heroSubtitle:
      'مطاعم ومتاجر وإقامة وسيارات أجرة وشركات: ابحث حسب المهنة أو المدينة أو حول موقعك، ثم تواصل مع المهني مباشرة.',
    heroCta: 'ابدأ البحث',
    heroSecondaryCta: 'تصفح الفئات',
    categoriesTitle: 'تصفح حسب الفئة',
    categoriesSubtitle: 'اختر نوع المهني لتضييق نطاق بحثك.',
    howTitle: 'كيف يعمل',
    howStep1Title: 'ابحث',
    howStep1Body: 'مهنة أو خدمة أو اسم أو مدينة — دون معرفة الفئة.',
    howStep2Title: 'قارن',
    howStep2Body: 'اطلع على الملفات العامة: العنوان والوصف وساعات العمل والتقييمات.',
    howStep3Title: 'تواصل',
    howStep3Body: 'راسل المهني مباشرة، دون وسيط ودون عمولة.',
    proTitle: 'هل أنت مهني؟',
    proBody: 'انشر ملفك العام واستقبل طلبات عملائك مباشرة في مساحة عمل ليوباردو.',
    proCta: 'اكتشف ليوباردو للمهنيين',
  },
  directory: {
    title: 'الدليل',
    description: 'تصفح المهنيين المنشورين على ليوباردو.',
    searchLabel: 'بحث',
    searchPlaceholder: 'الاسم، الخدمة، كلمة مفتاحية…',
    cityLabel: 'المدينة أو المنطقة',
    cityPlaceholder: 'مثال: الدار البيضاء',
    submit: 'بحث',
    nearMe: 'قريب مني',
    locating: 'جارٍ تحديد الموقع…',
    nearActive: 'نتائج حول موقعك',
    clearNear: 'مسح الموقع',
    geoDenied: 'تم رفض الموقع — اسمح بالوصول إلى موقعك أو أدخل مدينة.',
    geoUnsupported: 'تحديد الموقع غير متاح على هذا الجهاز.',
    loading: 'جارٍ التحميل…',
    loadError: 'تعذر تحميل النتائج. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    noResults: 'لا يوجد مهني يطابق بحثك.',
    resultsCount: 'نتيجة',
    previous: 'السابق',
    next: 'التالي',
    pageLabel: 'صفحة',
    viewProfile: 'عرض الملف',
    distanceSuffix: 'كم',
    backToDiscover: 'كل المهنيين',
    emptyHint: 'جرّب مدينة أو فئة أخرى.',
  },
  types: {
    showcase: 'مهني',
    restaurant: 'مطعم',
    hospitality_property: 'إقامة',
    retail_shop: 'متجر',
    vtc_operator: 'سيارة أجرة / نقل',
    trade_company: 'شركة',
  },
  directoryByType: {
    retail_shop: {
      title: 'المتاجر',
      description:
        'تصفح المتاجر الإلكترونية المنشورة على ليوباردو: المنتجات والفئات والمدن والتواصل المباشر مع التاجر.',
    },
    vtc_operator: {
      title: 'سيارات الأجرة والنقل',
      description:
        'اعثر على مشغل سيارة أجرة أو نقل منشور على ليوباردو، واطلع على منطقة خدمته واطلب عرض سعر لرحلتك.',
    },
    trade_company: {
      title: 'الشركات',
      description:
        'تصفح شركات التجارة الدولية والأعمال المنشورة على ليوباردو: المنتجات والبلدان والاستيراد والتصدير.',
    },
    hospitality_property: {
      title: 'الإقامة',
      description:
        'اعثر على فندق أو بيت ضيافة أو شقة منشورة على ليوباردو، وقارن الغرف واحجز مباشرة.',
    },
  },
};

const COPY: Record<PublicPortalLocale, PublicPortalCopy> = { fr, en, tr, ar };

/** Retourne la copie du portail pour une locale (repli FR). */
export function getPublicPortalCopy(locale: string): PublicPortalCopy {
  return COPY[(locale as PublicPortalLocale) in COPY ? (locale as PublicPortalLocale) : 'fr'];
}

/** Libellé localisé d'un type canonique de `/public/discover`. */
export function publicTypeLabel(locale: string, type: string): string {
  const copy = getPublicPortalCopy(locale);
  const key = type as keyof PublicPortalCopy['types'];
  return copy.types[key] ?? type;
}
