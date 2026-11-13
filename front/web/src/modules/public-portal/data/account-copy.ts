/**
 * Leopardo Public — copie i18n des pages « Activités » et « Profil ».
 *
 * ## Pourquoi un fichier séparé
 *
 * `copy.ts` porte le chrome et les annuaires, `home-copy.ts` la page d'accueil.
 * Ces deux pages ont leur propre vocabulaire (statuts d'activité, réglages de
 * compte, export RGPD) qui n'a rien à faire dans un type déjà large. Séparer
 * permet de les faire évoluer sans toucher aux six autres surfaces.
 *
 * ## Règles
 *
 * - Les 4 locales sont OBLIGATOIRES (fr/en/tr/ar) : l'arabe est RTL.
 * - Aucune chaîne visible en dur dans les composants : tout vient d'ici.
 * - Aucun libellé de statut inventé : les clés correspondent aux statuts que
 *   l'API renvoie réellement (`confirmed`, `pending`, `cancelled`, …). Un
 *   statut inconnu retombe sur sa valeur brute plutôt que sur un mensonge.
 */

import type { PublicPortalLocale } from './copy';

export interface PublicAccountCopy {
  /** Métadonnées SEO. */
  meta: {
    activitiesTitle: string;
    activitiesDescription: string;
    profileTitle: string;
    profileDescription: string;
  };

  /** Page « Activités » (onglet 3). */
  activities: {
    title: string;
    subtitle: string;
    /** Sections de l'onglet, dans l'ordre d'affichage. */
    sections: {
      activity: string;
      favorites: string;
      history: string;
    };
    /** États d'écran. */
    loading: string;
    loadError: string;
    retry: string;
    /** Non connecté : l'onglet explique pourquoi il est vide. */
    unauthenticated: string;
    signIn: string;
    /** Listes vides — distinctes par section, jamais un bloc muet. */
    emptyActivities: string;
    emptyFavorites: string;
    emptyHistory: string;
    /** Favori dont le profil n'est plus disponible. */
    favoriteUnavailable: string;
    /** Action de retrait d'un favori. */
    removeFavorite: string;
    removing: string;
    /** Libellé accessible du retrait (le nom du favori y est inséré). */
    removeFavoriteLabel: string;
    /** Effacer l'historique de recherche. */
    clearHistory: string;
    clearing: string;
    /** Statuts d'activité — clés = valeurs renvoyées par l'API. */
    statuses: Record<string, string>;
    /** Libellé de la date d'une ligne. */
    occurredAt: string;
  };

  /** Page « Profil » (onglet 4). */
  profile: {
    title: string;
    subtitle: string;
    loading: string;
    loadError: string;
    retry: string;
    unauthenticated: string;
    signIn: string;
    /** Bloc identité (lecture seule : l'édition vit dans le compte). */
    identity: {
      title: string;
      name: string;
      email: string;
      phone: string;
      phoneUnverified: string;
      phoneVerified: string;
      memberSince: string;
    };
    /** Bloc langue : la préférence suit le COMPTE, pas l'appareil. */
    language: {
      title: string;
      hint: string;
      saving: string;
      saved: string;
      saveError: string;
    };
    /** Bloc notifications. */
    notifications: {
      title: string;
      hint: string;
      on: string;
      off: string;
      consentRecorded: string;
      saveError: string;
    };
    /** Bloc données personnelles (RGPD). */
    data: {
      title: string;
      hint: string;
      exportCta: string;
      exporting: string;
      exportError: string;
      /** Ce que l'export ne contient pas — dit explicitement. */
      exportScope: string;
      deleteCta: string;
      deleteHint: string;
    };
    /** Bloc sessions. */
    sessions: {
      title: string;
      hint: string;
      logoutAll: string;
      loggingOut: string;
      logoutAllError: string;
    };
  };
}

const fr: PublicAccountCopy = {
  meta: {
    activitiesTitle: 'Mes activités — Leopardo',
    activitiesDescription:
      'Vos commandes, réservations, favoris et recherches récentes, réunis dans un seul onglet.',
    profileTitle: 'Mon profil — Leopardo',
    profileDescription:
      'Votre langue, vos notifications, vos données personnelles et vos sessions.',
  },
  activities: {
    title: 'Mes activités',
    subtitle: 'Vos commandes, réservations, favoris et recherches récentes.',
    sections: {
      activity: 'Commandes et réservations',
      favorites: 'Favoris',
      history: 'Recherches récentes',
    },
    loading: 'Chargement…',
    loadError: 'Impossible de charger vos activités. Réessayez.',
    retry: 'Réessayer',
    unauthenticated: 'Connectez-vous pour retrouver vos activités.',
    signIn: 'Se connecter',
    emptyActivities: 'Aucune commande ni réservation pour le moment.',
    emptyFavorites: 'Aucun favori pour le moment.',
    emptyHistory: 'Aucune recherche enregistrée.',
    favoriteUnavailable: 'Ce professionnel n’est plus disponible.',
    removeFavorite: 'Retirer',
    removing: 'Retrait…',
    removeFavoriteLabel: 'Retirer {name} des favoris',
    clearHistory: 'Effacer l’historique',
    clearing: 'Effacement…',
    statuses: {
      confirmed: 'Confirmée',
      pending: 'En attente',
      cancelled: 'Annulée',
      completed: 'Terminée',
      in_progress: 'En cours',
      refunded: 'Remboursée',
    },
    occurredAt: 'Le',
  },
  profile: {
    title: 'Mon profil',
    subtitle: 'Votre compte, votre langue et vos données personnelles.',
    loading: 'Chargement…',
    loadError: 'Impossible de charger votre profil. Réessayez.',
    retry: 'Réessayer',
    unauthenticated: 'Connectez-vous pour accéder à votre profil.',
    signIn: 'Se connecter',
    identity: {
      title: 'Identité',
      name: 'Nom',
      email: 'E-mail',
      phone: 'Téléphone',
      phoneUnverified: 'Non vérifié',
      phoneVerified: 'Vérifié',
      memberSince: 'Membre depuis',
    },
    language: {
      title: 'Langue',
      hint: 'Votre choix est enregistré sur votre compte : il vous suit d’un appareil à l’autre.',
      saving: 'Enregistrement…',
      saved: 'Langue enregistrée.',
      saveError: 'Impossible d’enregistrer la langue. Réessayez.',
    },
    notifications: {
      title: 'Notifications',
      hint: 'Vous pouvez retirer votre consentement à tout moment.',
      on: 'Activées',
      off: 'Désactivées',
      consentRecorded: 'Consentement enregistré le',
      saveError: 'Impossible d’enregistrer votre choix. Réessayez.',
    },
    data: {
      title: 'Vos données',
      hint: 'Vous pouvez récupérer une copie de vos données à tout moment.',
      exportCta: 'Télécharger mes données',
      exporting: 'Préparation…',
      exportError: 'Impossible de préparer l’export. Réessayez.',
      exportScope:
        'L’export contient votre compte, votre profil, vos favoris, vos recherches et vos références d’activité. Il ne contient pas les commandes, réservations ni messages : ces données appartiennent au professionnel.',
      deleteCta: 'Supprimer mon compte',
      deleteHint:
        'La suppression est différée de 30 jours et réversible. Vos commandes passées restent chez le professionnel.',
    },
    sessions: {
      title: 'Sessions',
      hint: 'Déconnectez tous vos appareils si vous en avez perdu un.',
      logoutAll: 'Déconnecter tous mes appareils',
      loggingOut: 'Déconnexion…',
      logoutAllError: 'Impossible de déconnecter vos appareils. Réessayez.',
    },
  },
};

const en: PublicAccountCopy = {
  meta: {
    activitiesTitle: 'My activity — Leopardo',
    activitiesDescription:
      'Your orders, bookings, favourites and recent searches, all in one tab.',
    profileTitle: 'My profile — Leopardo',
    profileDescription: 'Your language, notifications, personal data and sessions.',
  },
  activities: {
    title: 'My activity',
    subtitle: 'Your orders, bookings, favourites and recent searches.',
    sections: {
      activity: 'Orders and bookings',
      favorites: 'Favourites',
      history: 'Recent searches',
    },
    loading: 'Loading…',
    loadError: 'Could not load your activity. Please try again.',
    retry: 'Try again',
    unauthenticated: 'Sign in to see your activity.',
    signIn: 'Sign in',
    emptyActivities: 'No orders or bookings yet.',
    emptyFavorites: 'No favourites yet.',
    emptyHistory: 'No saved searches.',
    favoriteUnavailable: 'This business is no longer available.',
    removeFavorite: 'Remove',
    removing: 'Removing…',
    removeFavoriteLabel: 'Remove {name} from favourites',
    clearHistory: 'Clear history',
    clearing: 'Clearing…',
    statuses: {
      confirmed: 'Confirmed',
      pending: 'Pending',
      cancelled: 'Cancelled',
      completed: 'Completed',
      in_progress: 'In progress',
      refunded: 'Refunded',
    },
    occurredAt: 'On',
  },
  profile: {
    title: 'My profile',
    subtitle: 'Your account, your language and your personal data.',
    loading: 'Loading…',
    loadError: 'Could not load your profile. Please try again.',
    retry: 'Try again',
    unauthenticated: 'Sign in to access your profile.',
    signIn: 'Sign in',
    identity: {
      title: 'Identity',
      name: 'Name',
      email: 'Email',
      phone: 'Phone',
      phoneUnverified: 'Not verified',
      phoneVerified: 'Verified',
      memberSince: 'Member since',
    },
    language: {
      title: 'Language',
      hint: 'Your choice is saved on your account, so it follows you across devices.',
      saving: 'Saving…',
      saved: 'Language saved.',
      saveError: 'Could not save your language. Please try again.',
    },
    notifications: {
      title: 'Notifications',
      hint: 'You can withdraw your consent at any time.',
      on: 'Enabled',
      off: 'Disabled',
      consentRecorded: 'Consent recorded on',
      saveError: 'Could not save your choice. Please try again.',
    },
    data: {
      title: 'Your data',
      hint: 'You can get a copy of your data at any time.',
      exportCta: 'Download my data',
      exporting: 'Preparing…',
      exportError: 'Could not prepare the export. Please try again.',
      exportScope:
        'The export contains your account, profile, favourites, searches and activity references. It does not contain orders, bookings or messages: that data belongs to the business.',
      deleteCta: 'Delete my account',
      deleteHint:
        'Deletion is delayed by 30 days and reversible. Your past orders stay with the business.',
    },
    sessions: {
      title: 'Sessions',
      hint: 'Sign out of all devices if you have lost one.',
      logoutAll: 'Sign out of all devices',
      loggingOut: 'Signing out…',
      logoutAllError: 'Could not sign out your devices. Please try again.',
    },
  },
};

const tr: PublicAccountCopy = {
  meta: {
    activitiesTitle: 'Etkinliklerim — Leopardo',
    activitiesDescription:
      'Siparişleriniz, rezervasyonlarınız, favorileriniz ve son aramalarınız tek sekmede.',
    profileTitle: 'Profilim — Leopardo',
    profileDescription: 'Diliniz, bildirimleriniz, kişisel verileriniz ve oturumlarınız.',
  },
  activities: {
    title: 'Etkinliklerim',
    subtitle: 'Siparişleriniz, rezervasyonlarınız, favorileriniz ve son aramalarınız.',
    sections: {
      activity: 'Siparişler ve rezervasyonlar',
      favorites: 'Favoriler',
      history: 'Son aramalar',
    },
    loading: 'Yükleniyor…',
    loadError: 'Etkinlikleriniz yüklenemedi. Tekrar deneyin.',
    retry: 'Tekrar dene',
    unauthenticated: 'Etkinliklerinizi görmek için giriş yapın.',
    signIn: 'Giriş yap',
    emptyActivities: 'Henüz sipariş veya rezervasyon yok.',
    emptyFavorites: 'Henüz favori yok.',
    emptyHistory: 'Kayıtlı arama yok.',
    favoriteUnavailable: 'Bu işletme artık mevcut değil.',
    removeFavorite: 'Kaldır',
    removing: 'Kaldırılıyor…',
    removeFavoriteLabel: '{name} favorilerden kaldır',
    clearHistory: 'Geçmişi temizle',
    clearing: 'Temizleniyor…',
    statuses: {
      confirmed: 'Onaylandı',
      pending: 'Beklemede',
      cancelled: 'İptal edildi',
      completed: 'Tamamlandı',
      in_progress: 'Devam ediyor',
      refunded: 'İade edildi',
    },
    occurredAt: 'Tarih',
  },
  profile: {
    title: 'Profilim',
    subtitle: 'Hesabınız, diliniz ve kişisel verileriniz.',
    loading: 'Yükleniyor…',
    loadError: 'Profiliniz yüklenemedi. Tekrar deneyin.',
    retry: 'Tekrar dene',
    unauthenticated: 'Profilinize erişmek için giriş yapın.',
    signIn: 'Giriş yap',
    identity: {
      title: 'Kimlik',
      name: 'Ad',
      email: 'E-posta',
      phone: 'Telefon',
      phoneUnverified: 'Doğrulanmadı',
      phoneVerified: 'Doğrulandı',
      memberSince: 'Üyelik başlangıcı',
    },
    language: {
      title: 'Dil',
      hint: 'Seçiminiz hesabınıza kaydedilir, böylece cihazlar arasında sizi takip eder.',
      saving: 'Kaydediliyor…',
      saved: 'Dil kaydedildi.',
      saveError: 'Dil kaydedilemedi. Tekrar deneyin.',
    },
    notifications: {
      title: 'Bildirimler',
      hint: 'Onayınızı istediğiniz zaman geri çekebilirsiniz.',
      on: 'Açık',
      off: 'Kapalı',
      consentRecorded: 'Onay tarihi',
      saveError: 'Seçiminiz kaydedilemedi. Tekrar deneyin.',
    },
    data: {
      title: 'Verileriniz',
      hint: 'Verilerinizin bir kopyasını istediğiniz zaman alabilirsiniz.',
      exportCta: 'Verilerimi indir',
      exporting: 'Hazırlanıyor…',
      exportError: 'Dışa aktarma hazırlanamadı. Tekrar deneyin.',
      exportScope:
        'Dışa aktarma hesabınızı, profilinizi, favorilerinizi, aramalarınızı ve etkinlik referanslarınızı içerir. Siparişleri, rezervasyonları veya mesajları içermez: bu veriler işletmeye aittir.',
      deleteCta: 'Hesabımı sil',
      deleteHint:
        'Silme 30 gün ertelenir ve geri alınabilir. Geçmiş siparişleriniz işletmede kalır.',
    },
    sessions: {
      title: 'Oturumlar',
      hint: 'Bir cihazınızı kaybettiyseniz tüm cihazlardan çıkış yapın.',
      logoutAll: 'Tüm cihazlardan çıkış yap',
      loggingOut: 'Çıkış yapılıyor…',
      logoutAllError: 'Cihazlarınızdan çıkış yapılamadı. Tekrar deneyin.',
    },
  },
};

const ar: PublicAccountCopy = {
  meta: {
    activitiesTitle: 'أنشطتي — ليوباردو',
    activitiesDescription: 'طلباتك وحجوزاتك ومفضلاتك وعمليات البحث الأخيرة في تبويب واحد.',
    profileTitle: 'ملفي الشخصي — ليوباردو',
    profileDescription: 'لغتك وإشعاراتك وبياناتك الشخصية وجلساتك.',
  },
  activities: {
    title: 'أنشطتي',
    subtitle: 'طلباتك وحجوزاتك ومفضلاتك وعمليات البحث الأخيرة.',
    sections: {
      activity: 'الطلبات والحجوزات',
      favorites: 'المفضلة',
      history: 'عمليات البحث الأخيرة',
    },
    loading: 'جارٍ التحميل…',
    loadError: 'تعذّر تحميل أنشطتك. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    unauthenticated: 'سجّل الدخول لعرض أنشطتك.',
    signIn: 'تسجيل الدخول',
    emptyActivities: 'لا توجد طلبات أو حجوزات بعد.',
    emptyFavorites: 'لا توجد مفضلات بعد.',
    emptyHistory: 'لا توجد عمليات بحث محفوظة.',
    favoriteUnavailable: 'هذا النشاط التجاري لم يعد متاحًا.',
    removeFavorite: 'إزالة',
    removing: 'جارٍ الإزالة…',
    removeFavoriteLabel: 'إزالة {name} من المفضلة',
    clearHistory: 'مسح السجل',
    clearing: 'جارٍ المسح…',
    statuses: {
      confirmed: 'مؤكّد',
      pending: 'قيد الانتظار',
      cancelled: 'ملغى',
      completed: 'مكتمل',
      in_progress: 'قيد التنفيذ',
      refunded: 'مسترد',
    },
    occurredAt: 'بتاريخ',
  },
  profile: {
    title: 'ملفي الشخصي',
    subtitle: 'حسابك ولغتك وبياناتك الشخصية.',
    loading: 'جارٍ التحميل…',
    loadError: 'تعذّر تحميل ملفك الشخصي. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    unauthenticated: 'سجّل الدخول للوصول إلى ملفك الشخصي.',
    signIn: 'تسجيل الدخول',
    identity: {
      title: 'الهوية',
      name: 'الاسم',
      email: 'البريد الإلكتروني',
      phone: 'الهاتف',
      phoneUnverified: 'غير مُتحقَّق',
      phoneVerified: 'مُتحقَّق',
      memberSince: 'عضو منذ',
    },
    language: {
      title: 'اللغة',
      hint: 'يُحفظ اختيارك في حسابك، فيتبعك من جهاز إلى آخر.',
      saving: 'جارٍ الحفظ…',
      saved: 'تم حفظ اللغة.',
      saveError: 'تعذّر حفظ اللغة. حاول مرة أخرى.',
    },
    notifications: {
      title: 'الإشعارات',
      hint: 'يمكنك سحب موافقتك في أي وقت.',
      on: 'مفعّلة',
      off: 'معطّلة',
      consentRecorded: 'تم تسجيل الموافقة في',
      saveError: 'تعذّر حفظ اختيارك. حاول مرة أخرى.',
    },
    data: {
      title: 'بياناتك',
      hint: 'يمكنك الحصول على نسخة من بياناتك في أي وقت.',
      exportCta: 'تنزيل بياناتي',
      exporting: 'جارٍ التحضير…',
      exportError: 'تعذّر تحضير التصدير. حاول مرة أخرى.',
      exportScope:
        'يتضمّن التصدير حسابك وملفك الشخصي ومفضلاتك وعمليات بحثك ومراجع أنشطتك. ولا يتضمّن الطلبات أو الحجوزات أو الرسائل: فهذه البيانات تخصّ النشاط التجاري.',
      deleteCta: 'حذف حسابي',
      deleteHint: 'الحذف مؤجّل 30 يومًا وقابل للتراجع. تبقى طلباتك السابقة لدى النشاط التجاري.',
    },
    sessions: {
      title: 'الجلسات',
      hint: 'سجّل الخروج من جميع الأجهزة إذا فقدت أحدها.',
      logoutAll: 'تسجيل الخروج من جميع الأجهزة',
      loggingOut: 'جارٍ تسجيل الخروج…',
      logoutAllError: 'تعذّر تسجيل الخروج من أجهزتك. حاول مرة أخرى.',
    },
  },
};

const ACCOUNT_COPY: Record<PublicPortalLocale, PublicAccountCopy> = { fr, en, tr, ar };

/** Copie des pages Activités/Profil pour une locale (repli FR). */
export function getPublicAccountCopy(locale: string): PublicAccountCopy {
  return ACCOUNT_COPY[
    (locale as PublicPortalLocale) in ACCOUNT_COPY ? (locale as PublicPortalLocale) : 'fr'
  ];
}

/**
 * Libellé d'un statut d'activité.
 *
 * Un statut inconnu retombe sur sa **valeur brute** plutôt que sur un libellé
 * inventé : mieux vaut afficher `partially_refunded` que « Confirmée » sur une
 * commande qui ne l'est pas.
 */
export function activityStatusLabel(locale: string, status: string): string {
  const copy = getPublicAccountCopy(locale);
  return copy.activities.statuses[status] ?? status;
}
