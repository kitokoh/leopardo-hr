/**
 * Catalogue de copie des vitrines publiques verticales — 4 locales.
 *
 * ## Pourquoi un catalogue inline plutôt que `shared/i18n`
 *
 * Les vitrines publiques sont des **surfaces autonomes** : elles portent leur
 * propre identité éditoriale (nom, accroche, libellés d'annuaire) et doivent
 * rester lisibles sans dépendre du catalogue applicatif, qui décrit l'espace de
 * travail connecté (tableau de bord, RH, paie) et non un site grand public.
 * C'est exactement la convention déjà retenue pour les modules publics
 * `toolbox/data/` et `library/data/` : le catalogue EST le mécanisme de
 * localisation du module.
 *
 * ## Contrat
 *
 * - **Toute** chaîne visible par un visiteur vit ici, jamais dans un composant.
 * - Les 4 locales sont obligatoires : `SurfaceCopy` est un type fermé, donc une
 *   locale manquante casse la compilation, pas le rendu.
 * - `getSurfaceCopy` retombe sur le français : une locale inconnue n'affiche
 *   jamais une clé brute.
 */

import type { SurfaceVertical } from '../kernel';

/** Locales servies par les vitrines. */
export const SURFACE_COPY_LOCALES = ['fr', 'en', 'tr', 'ar'] as const;

export type SurfaceCopyLocale = (typeof SURFACE_COPY_LOCALES)[number];

/** Copie éditoriale d'une verticale. */
export interface VerticalCopy {
  /** Nom public de la vitrine (marque de la surface, pas d'un tenant). */
  readonly displayName: string;
  /** Accroche d'une ligne, orientée visiteur. */
  readonly tagline: string;
  /** Sur-titre du bandeau. */
  readonly eyebrow: string;
  /** Libellé du lien d'exploration. */
  readonly exploreCta: string;
}

/** Libellés transverses aux vitrines. */
export interface SharedCopy {
  readonly noReviews: string;
  readonly unavailable: string;
  readonly empty: string;
  readonly priceOnRequest: string;
  readonly comingSoon: string;
  readonly bookable: string;
  readonly soldOut: string;
  readonly catalogEmpty: string;
  readonly product: string;
  readonly products: string;
  readonly place: string;
  readonly places: string;
  readonly address: string;
  readonly perNight: string;
  readonly from: string;
  readonly allShops: string;
  readonly allStays: string;
  readonly allCompanies: string;
  readonly allAddresses: string;
  readonly allDestinations: string;
  readonly company: string;
  readonly property: string;
  readonly pharmacy: string;
  readonly shop: string;
  readonly trip: string;
  readonly scheduleTbc: string;
  readonly importLabel: string;
  readonly exportLabel: string;
  readonly domesticLabel: string;
  readonly onDuty: string;
  readonly regularHours: string;
  readonly reviews: string;
}

/** Catalogue complet d'une locale. */
export interface SurfaceCopy {
  readonly shared: SharedCopy;
  readonly verticals: Readonly<Record<SurfaceVertical, VerticalCopy>>;
}

const FR: SurfaceCopy = {
  shared: {
    noReviews: 'Pas encore d’avis',
    unavailable: 'Le service est momentanément indisponible. Réessayez dans un instant.',
    empty: 'Aucun résultat ne correspond à votre recherche.',
    priceOnRequest: 'Tarif sur demande',
    comingSoon: 'Cette verticale sera publiée prochainement.',
    bookable: 'Réservable',
    soldOut: 'Complet',
    catalogEmpty: 'Catalogue vide',
    product: 'produit',
    products: 'produits',
    place: 'place',
    places: 'places',
    address: 'Adresse',
    perNight: 'la nuit',
    from: 'À partir de',
    allShops: 'Toutes les boutiques',
    allStays: 'Tous les séjours',
    allCompanies: 'Toutes les entreprises',
    allAddresses: 'Explorer toutes les adresses',
    allDestinations: 'Explorer les destinations',
    company: 'Entreprise',
    property: 'Hébergement',
    pharmacy: 'Officine',
    shop: 'Boutique',
    trip: 'Trajet',
    scheduleTbc: 'Horaires à confirmer',
    importLabel: 'Import',
    exportLabel: 'Export',
    domesticLabel: 'Marché domestique',
    onDuty: 'De garde',
    regularHours: 'Horaires habituels',
    reviews: 'avis',
  },
  verticals: {
    restaurant: {
      displayName: 'Leopardo Restaurants',
      tagline: 'Trouvez votre table, découvrez la carte, réservez en deux gestes.',
      eyebrow: 'Annuaire public',
      exploreCta: 'Explorer toutes les adresses',
    },
    retail: {
      displayName: 'Leopardo Marché',
      tagline: 'Les boutiques près de chez vous, leur catalogue et leurs nouveautés.',
      eyebrow: 'Boutique',
      exploreCta: 'Toutes les boutiques',
    },
    hospitality: {
      displayName: 'Leopardo Séjours',
      tagline: 'Hôtels, résidences et maisons d’hôtes — disponibilités et réservation directe.',
      eyebrow: 'Hébergement',
      exploreCta: 'Tous les séjours',
    },
    trade: {
      displayName: 'Leopardo Entreprises',
      tagline: 'Fournisseurs et négociants vérifiés — import, export et marché domestique.',
      eyebrow: 'Entreprise',
      exploreCta: 'Toutes les entreprises',
    },
    travel: {
      displayName: 'Leopardo Billets',
      tagline: 'Comparez les trajets, réservez votre place, recevez votre billet.',
      eyebrow: 'Billetterie',
      exploreCta: 'Explorer les destinations',
    },
    pharmacy: {
      displayName: 'Leopardo Pharmacies',
      tagline: 'Trouvez une officine, ses horaires et sa garde, près de chez vous.',
      eyebrow: 'Officines',
      exploreCta: 'Explorer toutes les adresses',
    },
  },
};

const EN: SurfaceCopy = {
  shared: {
    noReviews: 'No reviews yet',
    unavailable: 'The service is temporarily unavailable. Please try again shortly.',
    empty: 'No result matches your search.',
    priceOnRequest: 'Price on request',
    comingSoon: 'This vertical will be published soon.',
    bookable: 'Bookable',
    soldOut: 'Sold out',
    catalogEmpty: 'Empty catalogue',
    product: 'product',
    products: 'products',
    place: 'seat',
    places: 'seats',
    address: 'Address',
    perNight: 'per night',
    from: 'From',
    allShops: 'All shops',
    allStays: 'All stays',
    allCompanies: 'All companies',
    allAddresses: 'Browse all addresses',
    allDestinations: 'Browse destinations',
    company: 'Company',
    property: 'Property',
    pharmacy: 'Pharmacy',
    shop: 'Shop',
    trip: 'Trip',
    scheduleTbc: 'Schedule to be confirmed',
    importLabel: 'Import',
    exportLabel: 'Export',
    domesticLabel: 'Domestic market',
    onDuty: 'On duty',
    regularHours: 'Regular hours',
    reviews: 'reviews',
  },
  verticals: {
    restaurant: {
      displayName: 'Leopardo Restaurants',
      tagline: 'Find your table, browse the menu, book in two taps.',
      eyebrow: 'Public directory',
      exploreCta: 'Browse all addresses',
    },
    retail: {
      displayName: 'Leopardo Market',
      tagline: 'Shops near you, their catalogue and their latest arrivals.',
      eyebrow: 'Shop',
      exploreCta: 'All shops',
    },
    hospitality: {
      displayName: 'Leopardo Stays',
      tagline: 'Hotels, residences and guesthouses — availability and direct booking.',
      eyebrow: 'Property',
      exploreCta: 'All stays',
    },
    trade: {
      displayName: 'Leopardo Business',
      tagline: 'Verified suppliers and traders — import, export and domestic market.',
      eyebrow: 'Company',
      exploreCta: 'All companies',
    },
    travel: {
      displayName: 'Leopardo Tickets',
      tagline: 'Compare trips, book your seat, receive your ticket.',
      eyebrow: 'Ticketing',
      exploreCta: 'Browse destinations',
    },
    pharmacy: {
      displayName: 'Leopardo Pharmacies',
      tagline: 'Find a pharmacy, its opening hours and its on-duty schedule.',
      eyebrow: 'Pharmacies',
      exploreCta: 'Browse all addresses',
    },
  },
};

const TR: SurfaceCopy = {
  shared: {
    noReviews: 'Henüz değerlendirme yok',
    unavailable: 'Hizmet şu anda kullanılamıyor. Lütfen biraz sonra tekrar deneyin.',
    empty: 'Aramanızla eşleşen sonuç yok.',
    priceOnRequest: 'Fiyat talep üzerine',
    comingSoon: 'Bu dikey yakında yayınlanacak.',
    bookable: 'Rezerve edilebilir',
    soldOut: 'Dolu',
    catalogEmpty: 'Katalog boş',
    product: 'ürün',
    products: 'ürün',
    place: 'koltuk',
    places: 'koltuk',
    address: 'Adres',
    perNight: 'gecelik',
    from: 'Başlangıç',
    allShops: 'Tüm mağazalar',
    allStays: 'Tüm konaklamalar',
    allCompanies: 'Tüm şirketler',
    allAddresses: 'Tüm adresleri keşfet',
    allDestinations: 'Destinasyonları keşfet',
    company: 'Şirket',
    property: 'Konaklama',
    pharmacy: 'Eczane',
    shop: 'Mağaza',
    trip: 'Sefer',
    scheduleTbc: 'Saatler teyit edilecek',
    importLabel: 'İthalat',
    exportLabel: 'İhracat',
    domesticLabel: 'Yurt içi pazar',
    onDuty: 'Nöbetçi',
    regularHours: 'Normal saatler',
    reviews: 'değerlendirme',
  },
  verticals: {
    restaurant: {
      displayName: 'Leopardo Restoranlar',
      tagline: 'Masanızı bulun, menüye göz atın, iki dokunuşla rezervasyon yapın.',
      eyebrow: 'Herkese açık rehber',
      exploreCta: 'Tüm adresleri keşfet',
    },
    retail: {
      displayName: 'Leopardo Pazar',
      tagline: 'Yakınınızdaki mağazalar, katalogları ve yeni ürünleri.',
      eyebrow: 'Mağaza',
      exploreCta: 'Tüm mağazalar',
    },
    hospitality: {
      displayName: 'Leopardo Konaklama',
      tagline: 'Oteller, rezidanslar ve konuk evleri — müsaitlik ve doğrudan rezervasyon.',
      eyebrow: 'Konaklama',
      exploreCta: 'Tüm konaklamalar',
    },
    trade: {
      displayName: 'Leopardo İşletmeler',
      tagline: 'Doğrulanmış tedarikçiler ve tacirler — ithalat, ihracat ve yurt içi pazar.',
      eyebrow: 'Şirket',
      exploreCta: 'Tüm şirketler',
    },
    travel: {
      displayName: 'Leopardo Biletler',
      tagline: 'Seferleri karşılaştırın, yerinizi ayırtın, biletinizi alın.',
      eyebrow: 'Bilet satışı',
      exploreCta: 'Destinasyonları keşfet',
    },
    pharmacy: {
      displayName: 'Leopardo Eczaneler',
      tagline: 'Bir eczane, çalışma saatleri ve nöbet bilgisi bulun.',
      eyebrow: 'Eczaneler',
      exploreCta: 'Tüm adresleri keşfet',
    },
  },
};

const AR: SurfaceCopy = {
  shared: {
    noReviews: 'لا توجد تقييمات بعد',
    unavailable: 'الخدمة غير متاحة مؤقتًا. يرجى المحاولة بعد قليل.',
    empty: 'لا يوجد نتيجة تطابق بحثك.',
    priceOnRequest: 'السعر عند الطلب',
    comingSoon: 'سيتم نشر هذا القطاع قريبًا.',
    bookable: 'قابل للحجز',
    soldOut: 'مكتمل',
    catalogEmpty: 'الكتالوج فارغ',
    product: 'منتج',
    products: 'منتجات',
    place: 'مقعد',
    places: 'مقاعد',
    address: 'العنوان',
    perNight: 'لكل ليلة',
    from: 'ابتداءً من',
    allShops: 'كل المتاجر',
    allStays: 'كل الإقامات',
    allCompanies: 'كل الشركات',
    allAddresses: 'استكشف كل العناوين',
    allDestinations: 'استكشف الوجهات',
    company: 'شركة',
    property: 'إقامة',
    pharmacy: 'صيدلية',
    shop: 'متجر',
    trip: 'رحلة',
    scheduleTbc: 'المواعيد قيد التأكيد',
    importLabel: 'استيراد',
    exportLabel: 'تصدير',
    domesticLabel: 'السوق المحلي',
    onDuty: 'صيدلية مناوبة',
    regularHours: 'ساعات العمل العادية',
    reviews: 'تقييم',
  },
  verticals: {
    restaurant: {
      displayName: 'مطاعم ليوباردو',
      tagline: 'اعثر على طاولتك، تصفح القائمة، واحجز بلمستين.',
      eyebrow: 'دليل عام',
      exploreCta: 'استكشف كل العناوين',
    },
    retail: {
      displayName: 'سوق ليوباردو',
      tagline: 'المتاجر القريبة منك وكتالوجاتها وأحدث منتجاتها.',
      eyebrow: 'متجر',
      exploreCta: 'كل المتاجر',
    },
    hospitality: {
      displayName: 'إقامات ليوباردو',
      tagline: 'فنادق ومساكن وبيوت ضيافة — التوفر والحجز المباشر.',
      eyebrow: 'إقامة',
      exploreCta: 'كل الإقامات',
    },
    trade: {
      displayName: 'شركات ليوباردو',
      tagline: 'موردون وتجار موثوقون — استيراد وتصدير وسوق محلي.',
      eyebrow: 'شركة',
      exploreCta: 'كل الشركات',
    },
    travel: {
      displayName: 'تذاكر ليوباردو',
      tagline: 'قارن الرحلات، احجز مقعدك، واستلم تذكرتك.',
      eyebrow: 'بيع التذاكر',
      exploreCta: 'استكشف الوجهات',
    },
    pharmacy: {
      displayName: 'صيدليات ليوباردو',
      tagline: 'اعثر على صيدلية وساعات عملها وجدول المناوبة.',
      eyebrow: 'صيدليات',
      exploreCta: 'استكشف كل العناوين',
    },
  },
};

/**
 * Copie de l'invitation professionnelle d'une vitrine (#9614).
 *
 * Le bandeau s'adresse au PROFESSIONNEL qui visite une vitrine grand public :
 * il nomme son métier et l'envoie vers l'espace existant. Le titre est décliné
 * par verticale — « Devenir pharmacien » n'a rien à voir avec « Devenir
 * hôtelier », et un libellé générique ferait perdre le visiteur.
 */
export interface TenantInvitationCopy {
  /** Sur-titre du bandeau. */
  readonly eyebrow: string;
  /** Corps : ce que le professionnel obtient. */
  readonly body: string;
  /** Libellé du bouton. */
  readonly cta: string;
  /** Précision rassurante (gratuit, sans engagement). */
  readonly note: string;
  /** Titre par verticale — nomme le métier du visiteur. */
  readonly titles: Readonly<Record<SurfaceVertical, string>>;
}

/** Invitation professionnelle, 4 locales. */
export const TENANT_INVITATION_COPY: Readonly<Record<SurfaceCopyLocale, TenantInvitationCopy>> = {
  fr: {
    eyebrow: 'Professionnels',
    body: 'Vous exercez ce métier ? Créez votre espace en quelques minutes — votre offre devient visible ici même.',
    cta: 'Créer mon espace',
    note: 'Gratuit, sans engagement.',
    titles: {
      restaurant: 'Vous êtes restaurateur ?',
      retail: 'Vous tenez une boutique ?',
      hospitality: 'Vous gérez un hébergement ?',
      trade: 'Vous faites de l’import-export ?',
      travel: 'Vous vendez des billets ?',
      pharmacy: 'Vous êtes pharmacien ?',
    },
  },
  en: {
    eyebrow: 'For professionals',
    body: 'Do you run this business? Create your workspace in minutes — your offer becomes visible right here.',
    cta: 'Create my workspace',
    note: 'Free, no commitment.',
    titles: {
      restaurant: 'Are you a restaurateur?',
      retail: 'Do you run a shop?',
      hospitality: 'Do you manage a property?',
      trade: 'Do you import or export?',
      travel: 'Do you sell tickets?',
      pharmacy: 'Are you a pharmacist?',
    },
  },
  tr: {
    eyebrow: 'Profesyoneller için',
    body: 'Bu işi mi yapıyorsunuz? Alanınızı dakikalar içinde oluşturun — teklifiniz tam burada görünür olsun.',
    cta: 'Alanımı oluştur',
    note: 'Ücretsiz, taahhütsüz.',
    titles: {
      restaurant: 'Restoran işletiyor musunuz?',
      retail: 'Dükkân mı işletiyorsunuz?',
      hospitality: 'Konaklama mı yönetiyorsunuz?',
      trade: 'İthalat veya ihracat mı yapıyorsunuz?',
      travel: 'Bilet mi satıyorsunuz?',
      pharmacy: 'Eczacı mısınız?',
    },
  },
  ar: {
    eyebrow: 'للمهنيين',
    body: 'هل تمارس هذه المهنة؟ أنشئ مساحتك في دقائق — ليصبح عرضك ظاهرًا هنا.',
    cta: 'أنشئ مساحتي',
    note: 'مجانًا وبدون التزام.',
    titles: {
      restaurant: 'هل أنت صاحب مطعم؟',
      retail: 'هل تدير متجرًا؟',
      hospitality: 'هل تدير مكان إقامة؟',
      trade: 'هل تستورد أو تصدّر؟',
      travel: 'هل تبيع التذاكر؟',
      pharmacy: 'هل أنت صيدلي؟',
    },
  },
};

/** Invitation professionnelle d'une locale, avec repli sur le français. */
export function getTenantInvitationCopy(locale: string | null | undefined): TenantInvitationCopy {
  if (locale && (SURFACE_COPY_LOCALES as readonly string[]).includes(locale)) {
    return TENANT_INVITATION_COPY[locale as SurfaceCopyLocale];
  }

  return TENANT_INVITATION_COPY.fr;
}

const CATALOGS: Readonly<Record<SurfaceCopyLocale, SurfaceCopy>> = {
  fr: FR,
  en: EN,
  tr: TR,
  ar: AR,
};

/**
 * Copie d'une locale, avec repli sur le français.
 *
 * Une locale inconnue (ou absente) ne doit jamais produire une clé brute à
 * l'écran : le visiteur voit du français, pas `verticals.restaurant.tagline`.
 */
export function getSurfaceCopy(locale: string | null | undefined): SurfaceCopy {
  if (locale && (SURFACE_COPY_LOCALES as readonly string[]).includes(locale)) {
    return CATALOGS[locale as SurfaceCopyLocale];
  }

  return CATALOGS.fr;
}

/** Copie éditoriale d'une verticale pour une locale donnée. */
export function getVerticalCopy(
  vertical: SurfaceVertical,
  locale: string | null | undefined,
): VerticalCopy {
  return getSurfaceCopy(locale).verticals[vertical];
}
