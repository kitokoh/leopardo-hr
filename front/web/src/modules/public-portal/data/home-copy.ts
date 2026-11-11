/**
 * Leopardo Public — copie de la PAGE D'ACCUEIL du portail grand public.
 *
 * ## Pourquoi un fichier séparé de `copy.ts`
 *
 * `copy.ts` porte le chrome partagé et les annuaires : il est lu par six
 * surfaces et par la garde `chrome-routes.test.ts`. La page d'accueil, elle,
 * a un vocabulaire propre (promesse, recherche, preuves, appel business) qui
 * n'a rien à faire dans le contrat des annuaires. Séparer évite de faire
 * grossir un type déjà large et permet de faire évoluer la home sans toucher
 * aux six autres pages.
 *
 * ## Règles
 *
 * - Les 4 locales sont OBLIGATOIRES (fr/en/tr/ar) : l'arabe est RTL.
 * - Aucune chaîne visible en dur dans les composants : tout vient d'ici.
 * - Pas de chiffre inventé. Les seules données chiffrées affichées par la
 *   home sont celles que l'API publique renvoie réellement (`stats`), ou des
 *   faits vérifiables sur le produit (nombre de verticales servies).
 */

import type { PublicPortalLocale } from './copy';

export interface PublicHomeCopy {
  /** Métadonnées SEO de `/`. */
  meta: {
    title: string;
    description: string;
  };
  hero: {
    eyebrow: string;
    title: string;
    /** Partie du titre mise en valeur (dégradé). */
    titleAccent: string;
    subtitle: string;
    searchLabel: string;
    searchPlaceholder: string;
    cityLabel: string;
    cityPlaceholder: string;
    searchCta: string;
    /** Libellé du bouton de géolocalisation. */
    aroundMe: string;
    /** État pendant la demande de permission. */
    locating: string;
    /** Message quand la permission est refusée. */
    locationDenied: string;
    /** Invitation à saisir la ville après un refus. */
    locationDeniedHint: string;
    /** Action pour oublier la position courante. */
    changeLocation: string;
    /** Lien secondaire vers l'explorateur complet. */
    exploreCta: string;
    /** Bouton d'entrée vers la vitrine business. */
    businessCta: string;
    businessHint: string;
  };
  /** Filtres conditionnels de la recherche. */
  filters: {
    title: string;
    openNow: string;
    nearest: string;
  };
  /** Bandeau de confiance sous le hero. */
  trust: {
    title: string;
    items: { title: string; body: string }[];
  };
  /** Accès direct par verticale. */
  verticals: {
    title: string;
    subtitle: string;
    cta: string;
  };
  /** « Comment ça marche ». */
  how: {
    title: string;
    subtitle: string;
    steps: { title: string; body: string }[];
  };
  /** Appel à l'action professionnel (vers /business). */
  business: {
    title: string;
    body: string;
    cta: string;
    secondaryCta: string;
  };
  /** Bascule de thème. */
  theme: {
    label: string;
    light: string;
    dark: string;
    system: string;
  };
}

const fr: PublicHomeCopy = {
  meta: {
    title: 'Leopardo — trouvez un professionnel près de chez vous',
    description:
      'Restaurants, boutiques, hébergements, VTC et entreprises : cherchez par métier, par ville ou autour de vous, puis contactez directement le professionnel. Sans intermédiaire ni commission.',
  },
  hero: {
    eyebrow: 'Le portail public',
    title: 'Tout ce qui vous entoure,',
    titleAccent: 'en un seul endroit.',
    subtitle:
      'Cherchez un métier, un service ou un nom — sans savoir dans quelle catégorie il se range. Vous arrivez directement sur la fiche du professionnel, et vous le contactez sans intermédiaire.',
    searchLabel: 'Que cherchez-vous ?',
    searchPlaceholder: 'Un restaurant, un garage, un taxi, une boutique…',
    cityLabel: 'Où ?',
    cityPlaceholder: 'Ville ou quartier',
    searchCta: 'Rechercher',
    aroundMe: 'Autour de moi',
    locating: 'Localisation…',
    locationDenied: 'Ville ou quartier',
    locationDeniedHint: 'Localisation refusée — saisissez votre ville.',
    changeLocation: 'Changer',
    exploreCta: 'Explorer tout le portail',
    businessCta: 'Leopardo Business',
    businessHint: 'Vous êtes un professionnel ?',
  },
  filters: {
    title: 'Filtres',
    openNow: 'Ouvert maintenant',
    nearest: 'Plus proches',
  },
  trust: {
    title: 'Ce que le portail garantit',
    items: [
      {
        title: 'Aucun intermédiaire',
        body: 'Vous écrivez au professionnel, pas à une plateforme. Aucune commission sur la mise en relation.',
      },
      {
        title: 'Fiches vérifiées',
        body: 'Seuls les professionnels qui ont publié leur fiche et dont le compte est actif apparaissent.',
      },
      {
        title: 'Sans compte',
        body: 'Consulter une fiche ne demande aucune inscription. Le compte n’est utile que pour écrire.',
      },
    ],
  },
  verticals: {
    title: 'Parcourir par catégorie',
    subtitle: 'Cinq annuaires publics, un seul point d’entrée.',
    cta: 'Voir l’annuaire',
  },
  how: {
    title: 'Comment ça marche',
    subtitle: 'Trois étapes, aucune inscription obligatoire.',
    steps: [
      {
        title: 'Cherchez',
        body: 'Un métier, un service, un nom ou une ville. La recherche traverse toutes les catégories à la fois.',
      },
      {
        title: 'Comparez',
        body: 'Adresse, description, horaires, moyens de contact : tout est sur la fiche publique.',
      },
      {
        title: 'Contactez',
        body: 'Écrivez directement au professionnel depuis sa fiche. Il vous répond dans son espace.',
      },
    ],
  },
  business: {
    title: 'Vous gérez une entreprise ?',
    body: 'Leopardo Business réunit les outils de gestion, la facturation, les équipes et la publication de votre fiche publique dans un seul espace.',
    cta: 'Découvrir Leopardo Business',
    secondaryCta: 'Voir les tarifs',
  },
  theme: {
    label: 'Apparence',
    light: 'Clair',
    dark: 'Sombre',
    system: 'Système',
  },
};

const en: PublicHomeCopy = {
  meta: {
    title: 'Leopardo — find a business near you',
    description:
      'Restaurants, shops, stays, rides and companies: search by trade, by city or around you, then contact the business directly. No middleman, no commission.',
  },
  hero: {
    eyebrow: 'The public portal',
    title: 'Everything around you,',
    titleAccent: 'in one place.',
    subtitle:
      'Search a trade, a service or a name — without knowing which category it belongs to. You land straight on the business page and contact them with no middleman.',
    searchLabel: 'What are you looking for?',
    searchPlaceholder: 'A restaurant, a garage, a taxi, a shop…',
    cityLabel: 'Where?',
    cityPlaceholder: 'City or neighbourhood',
    searchCta: 'Search',
    aroundMe: 'Around me',
    locating: 'Locating…',
    locationDenied: 'City or neighbourhood',
    locationDeniedHint: 'Location denied — enter your city.',
    changeLocation: 'Change',
    exploreCta: 'Explore the whole portal',
    businessCta: 'Leopardo Business',
    businessHint: 'Are you a business owner?',
  },
  filters: {
    title: 'Filters',
    openNow: 'Open now',
    nearest: 'Nearest',
  },
  trust: {
    title: 'What the portal guarantees',
    items: [
      {
        title: 'No middleman',
        body: 'You write to the business, not to a platform. No commission on the introduction.',
      },
      {
        title: 'Verified listings',
        body: 'Only businesses that published their page and hold an active account are listed.',
      },
      {
        title: 'No account needed',
        body: 'Browsing a listing requires no sign-up. An account is only needed to write.',
      },
    ],
  },
  verticals: {
    title: 'Browse by category',
    subtitle: 'Five public directories, one entry point.',
    cta: 'Open the directory',
  },
  how: {
    title: 'How it works',
    subtitle: 'Three steps, no mandatory sign-up.',
    steps: [
      {
        title: 'Search',
        body: 'A trade, a service, a name or a city. The search crosses every category at once.',
      },
      {
        title: 'Compare',
        body: 'Address, description, opening hours, contact channels: it is all on the public page.',
      },
      {
        title: 'Contact',
        body: 'Write to the business straight from its page. They reply from their own workspace.',
      },
    ],
  },
  business: {
    title: 'Running a business?',
    body: 'Leopardo Business brings management tools, invoicing, teams and your public listing into a single workspace.',
    cta: 'Discover Leopardo Business',
    secondaryCta: 'See pricing',
  },
  theme: {
    label: 'Appearance',
    light: 'Light',
    dark: 'Dark',
    system: 'System',
  },
};

const tr: PublicHomeCopy = {
  meta: {
    title: 'Leopardo — yakınınızdaki bir işletmeyi bulun',
    description:
      'Restoranlar, mağazalar, konaklama, ulaşım ve şirketler: mesleğe, şehre veya konumunuza göre arayın, ardından işletmeyle doğrudan iletişime geçin. Aracı yok, komisyon yok.',
  },
  hero: {
    eyebrow: 'Kamusal portal',
    title: 'Etrafınızdaki her şey,',
    titleAccent: 'tek bir yerde.',
    subtitle:
      'Bir meslek, hizmet veya isim arayın — hangi kategoriye girdiğini bilmenize gerek yok. Doğrudan işletmenin sayfasına ulaşır ve aracı olmadan iletişime geçersiniz.',
    searchLabel: 'Ne arıyorsunuz?',
    searchPlaceholder: 'Bir restoran, bir tamirci, bir taksi, bir mağaza…',
    cityLabel: 'Nerede?',
    cityPlaceholder: 'Şehir veya mahalle',
    searchCta: 'Ara',
    aroundMe: 'Yakınımda',
    locating: 'Konum alınıyor…',
    locationDenied: 'Şehir veya mahalle',
    locationDeniedHint: 'Konum reddedildi — şehrinizi yazın.',
    changeLocation: 'Değiştir',
    exploreCta: 'Tüm portalı keşfet',
    businessCta: 'Leopardo Business',
    businessHint: 'Bir işletme sahibi misiniz?',
  },
  filters: {
    title: 'Filtreler',
    openNow: 'Şu an açık',
    nearest: 'En yakın',
  },
  trust: {
    title: 'Portalın garantileri',
    items: [
      {
        title: 'Aracı yok',
        body: 'Platforma değil, doğrudan işletmeye yazarsınız. Tanıştırma için komisyon alınmaz.',
      },
      {
        title: 'Doğrulanmış kayıtlar',
        body: 'Yalnızca sayfasını yayımlamış ve hesabı etkin işletmeler listelenir.',
      },
      {
        title: 'Hesap gerekmez',
        body: 'Bir kaydı görüntülemek için kayıt olmanız gerekmez. Hesap yalnızca yazmak için gerekir.',
      },
    ],
  },
  verticals: {
    title: 'Kategoriye göre göz atın',
    subtitle: 'Beş kamusal dizin, tek giriş noktası.',
    cta: 'Dizini aç',
  },
  how: {
    title: 'Nasıl çalışır',
    subtitle: 'Üç adım, zorunlu kayıt yok.',
    steps: [
      {
        title: 'Arayın',
        body: 'Bir meslek, hizmet, isim veya şehir. Arama tüm kategorileri aynı anda tarar.',
      },
      {
        title: 'Karşılaştırın',
        body: 'Adres, açıklama, çalışma saatleri, iletişim kanalları: hepsi kamusal sayfada.',
      },
      {
        title: 'İletişime geçin',
        body: 'İşletmeye doğrudan sayfasından yazın. Yanıtı kendi çalışma alanından verir.',
      },
    ],
  },
  business: {
    title: 'Bir işletme mi yönetiyorsunuz?',
    body: 'Leopardo Business; yönetim araçlarını, faturalamayı, ekipleri ve kamusal kaydınızı tek bir çalışma alanında birleştirir.',
    cta: "Leopardo Business'ı keşfedin",
    secondaryCta: 'Fiyatları görün',
  },
  theme: {
    label: 'Görünüm',
    light: 'Açık',
    dark: 'Koyu',
    system: 'Sistem',
  },
};

const ar: PublicHomeCopy = {
  meta: {
    title: 'ليوباردو — اعثر على مهني قريب منك',
    description:
      'مطاعم ومتاجر وإقامة ووسائل نقل وشركات: ابحث حسب المهنة أو المدينة أو من حولك، ثم تواصل مع المهني مباشرة. بلا وسيط وبلا عمولة.',
  },
  hero: {
    eyebrow: 'البوابة العامة',
    title: 'كل ما حولك،',
    titleAccent: 'في مكان واحد.',
    subtitle:
      'ابحث عن مهنة أو خدمة أو اسم — دون أن تعرف إلى أي فئة تنتمي. تصل مباشرة إلى صفحة المهني وتتواصل معه بلا وسيط.',
    searchLabel: 'عمّ تبحث؟',
    searchPlaceholder: 'مطعم، ورشة، سيارة أجرة، متجر…',
    cityLabel: 'أين؟',
    cityPlaceholder: 'المدينة أو الحي',
    searchCta: 'ابحث',
    aroundMe: 'حولي',
    locating: 'جارٍ تحديد الموقع…',
    locationDenied: 'المدينة أو الحي',
    locationDeniedHint: 'تم رفض الموقع — أدخل مدينتك.',
    changeLocation: 'تغيير',
    exploreCta: 'استكشف البوابة كاملة',
    businessCta: 'ليوباردو للأعمال',
    businessHint: 'هل تدير شركة؟',
  },
  filters: {
    title: 'الفلاتر',
    openNow: 'مفتوح الآن',
    nearest: 'الأقرب',
  },
  trust: {
    title: 'ما تضمنه البوابة',
    items: [
      {
        title: 'بلا وسيط',
        body: 'تكتب إلى المهني لا إلى منصة. لا عمولة على التعريف.',
      },
      {
        title: 'بطاقات موثّقة',
        body: 'لا تظهر إلا الشركات التي نشرت بطاقتها وحسابها نشط.',
      },
      {
        title: 'بلا حساب',
        body: 'تصفح البطاقة لا يتطلب أي تسجيل. الحساب ضروري للكتابة فقط.',
      },
    ],
  },
  verticals: {
    title: 'تصفح حسب الفئة',
    subtitle: 'خمسة أدلة عامة، ونقطة دخول واحدة.',
    cta: 'افتح الدليل',
  },
  how: {
    title: 'كيف يعمل',
    subtitle: 'ثلاث خطوات، بلا تسجيل إلزامي.',
    steps: [
      {
        title: 'ابحث',
        body: 'مهنة أو خدمة أو اسم أو مدينة. البحث يعبر كل الفئات في وقت واحد.',
      },
      {
        title: 'قارن',
        body: 'العنوان والوصف وساعات العمل ووسائل التواصل: كلها في البطاقة العامة.',
      },
      {
        title: 'تواصل',
        body: 'اكتب إلى المهني من بطاقته مباشرة. يجيبك من مساحته الخاصة.',
      },
    ],
  },
  business: {
    title: 'هل تدير شركة؟',
    body: 'يجمع ليوباردو للأعمال أدوات الإدارة والفوترة والفرق ونشر بطاقتك العامة في مساحة واحدة.',
    cta: 'اكتشف ليوباردو للأعمال',
    secondaryCta: 'اطلع على الأسعار',
  },
  theme: {
    label: 'المظهر',
    light: 'فاتح',
    dark: 'داكن',
    system: 'النظام',
  },
};

const HOME_COPY: Record<PublicPortalLocale, PublicHomeCopy> = { fr, en, tr, ar };

/** Copie de la page d'accueil pour une locale (repli FR). */
export function getPublicHomeCopy(locale: string): PublicHomeCopy {
  return HOME_COPY[(locale as PublicPortalLocale) in HOME_COPY ? (locale as PublicPortalLocale) : 'fr'];
}
