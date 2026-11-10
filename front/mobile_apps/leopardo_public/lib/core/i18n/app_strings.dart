/// Catalogue de chaînes de l'application grand public Leopardo (couloir 16).
///
/// ## Pourquoi un catalogue local et pas les ARB de `leopardo_core`
///
/// Les 6 964 clés de `leopardo_core` sont le vocabulaire RH/Business OS :
/// « pointage », « avances sur salaire », « paie ». La règle d'architecture
/// n°1 du couloir interdit de faire ressembler cette app à un logiciel RH avec
/// des boutons ajoutés. Ajouter ici des clés `publicSearch*` dans le catalogue
/// partagé contaminerait 8 autres applications et ferait échouer le garde
/// `check-mobile-l10n-sync.sh` (qui exige toutes les clés ARB dans les
/// localisations générées) pour un périmètre qui n'intéresse personne d'autre.
///
/// Le patron est celui de `leopardo_travel_agent` et `leopardo_accounting` :
/// catalogue local à clés STABLES, 4 locales, repli clé brute.
///
/// ## Contrat des clés
///
/// Une clé est STABLE : elle ne change jamais de nom, même si le texte change.
/// L'éditeur de messages du Superadmin édite les TEXTES, jamais les clés — une
/// clé renommée casserait silencieusement l'écran qui la référence. Les clés
/// sont donc écrites en anglais technique (`discoverySearchHint`) et le texte
/// dans la langue de la locale.
///
/// ## Locales
///
/// fr (référence), en, ar (RTL), tr. `isRtl` pilote `Directionality` : ce n'est
/// pas une préférence esthétique, c'est ce qui fait flotter l'interface du bon
/// côté en arabe.
class AppStrings {
  const AppStrings._(this._locale);

  final String _locale;

  /// Code de langue résolu ('fr' | 'en' | 'ar' | 'tr').
  String get locale => _locale;

  static const List<String> supportedLocales = ['fr', 'en', 'ar', 'tr'];

  /// Langue canonique, alignée sur le backend (fr par défaut).
  static const String defaultLocale = 'fr';

  /// Vrai pour l'arabe — pilote `Directionality.rtl`.
  bool get isRtl => _locale == 'ar';

  static AppStrings of(String? preferredLocale) =>
      AppStrings._(_normalize(preferredLocale));

  static String _normalize(String? value) {
    if (value == null || value.isEmpty) return defaultLocale;
    final base = value.split(RegExp(r'[-_]')).first.toLowerCase();
    return supportedLocales.contains(base) ? base : defaultLocale;
  }

  /// Texte d'une clé. Repli : locale active → fr → clé brute.
  ///
  /// Le repli sur la clé brute est DÉLIBÉRÉ : un `discoverySearchHint` visible
  /// en production signale une clé oubliée, alors qu'un texte vide laisse un
  /// bouton muet que personne ne remarque.
  String t(String key) =>
      _values[_locale]?[key] ?? _values[defaultLocale]?[key] ?? key;

  /// Clés de la locale active — utilisé par le test de parité fr/en/ar/tr.
  Set<String> get keys => (_values[_locale] ?? const {}).keys.toSet();

  /// Toutes les clés du catalogue, indépendamment de la locale.
  static Set<String> get allKeys =>
      (_values[defaultLocale] ?? const {}).keys.toSet();

  static const Map<String, Map<String, String>> _values = {
    // ───────────────────────────── FR (référence) ─────────────────────────────
    'fr': {
      'appName': 'Leopardo',
      'tabHome': 'Accueil',
      'tabMessages': 'Messages',
      'tabActivity': 'Activités',
      'tabProfile': 'Profil',

      // Accueil — recherche
      'discoverySearchHint': 'Que cherchez-vous près de chez vous ?',
      'discoverySearchLabel': 'Rechercher un commerce',
      'discoveryCityLabel': 'Ville',
      'discoveryCityHint': 'Choisir une ville',
      'discoveryViewList': 'Liste',
      'discoveryViewMap': 'Carte',
      'discoveryOpenNow': 'Ouvert maintenant',
      'discoveryResume': 'Reprendre où vous étiez',
      'discoveryCategories': 'Catégories',
      'discoveryResultsCount': '%s résultats',
      'discoveryNearby': 'Près de vous',
      'merchantInviteTitle': 'Vous êtes commerçant ?',
      'merchantInviteBody':
          'Publiez votre Page gratuitement et apparaissez dans la recherche.',
      'merchantInviteCta': 'Publier ma Page',
      'discoveryUseLocation': 'Utiliser ma position',
      'discoveryLocationDenied': 'Sans votre position, choisissez votre ville pour voir ce qui est autour.',

      // États obligatoires (chaque écran)
      'stateLoading': 'Chargement…',
      'stateEmptyTitle': 'Rien ici pour le moment',
      'stateEmptyBody': 'Aucun commerce publié dans cette ville pour l’instant. Revenez bientôt.',
      'stateNoResultsTitle': 'Aucun résultat',
      'stateNoResultsBody': 'Essayez un autre mot, ou élargissez la zone.',
      'stateNoResultsWiden': 'Élargir la recherche',
      'stateErrorTitle': 'Impossible de charger',
      'stateErrorBody': 'Vérifiez votre connexion, puis réessayez.',
      'stateRetry': 'Réessayer',
      'stateOfflineTitle': 'Vous êtes hors ligne',
      'stateOfflineBody':
          'Voici ce que nous avions gardé de votre dernière visite.',
      'stateNotFoundTitle': 'Page introuvable',
      'stateNotFoundBody': 'Ce commerce n’est plus disponible. Il a peut-être fermé ou cessé d’être publié.',
      'stateNotFoundAction': 'Revenir à la recherche',
      'stateNotAvailable': 'Indisponible pour le moment',

      // Fiche commerce
      'businessOpenNow': 'Ouvert',
      'businessClosedNow': 'Fermé',
      'businessVerified': 'Vérifié',
      'businessHours': 'Horaires',
      'businessAddress': 'Adresse',
      'businessItinerary': 'Itinéraire',
      'businessShare': 'Partager',
      'businessReport': 'Signaler',
      'businessReportSent': 'Signalement envoyé. Merci.',
      'businessContact': 'Contacter',
      'businessOrder': 'Commander',
      'businessBooking': 'Réserver',
      'businessMenu': 'Menu',
      'businessAbout': 'À propos',
      'businessPhotos': 'Photos',
      'businessActionsUnavailable':
          'Ce commerce ne propose pas encore cette action.',
      'businessMultipleEstablishments': 'Cet établissement fait partie d’un commerce avec plusieurs adresses.',

      // Commun
      'commonCancel': 'Annuler',
      'commonClose': 'Fermer',
      'commonBack': 'Retour',
      'commonShareText': 'Découvrez %s sur Leopardo',
      'commonNoModule': 'Ce module n’est pas encore disponible.',

      // Messages (M2) — présents pour que l’onglet ne soit jamais muet
      'messagesEmptyTitle': 'Aucune conversation',
      'messagesEmptyBody': 'Quand vous contacterez un commerçant, la conversation apparaîtra ici.',
      'messagesAssistant': 'Léa',
      'messagesAssistantBadge': 'Assistante IA',
      'messagesUnread': 'Non lus',
      'messagesComingSoon': 'La messagerie arrive avec votre compte. Aucun message n’est perdu entre-temps.',

      // Activités (M3)
      'activityEmptyTitle': 'Aucune activité',
      'activityEmptyBody': 'Vos commandes et réservations apparaîtront ici dès votre premier contact.',
      'activityOrders': 'Commandes',
      'activityBookings': 'Réservations',
      'activityFavorites': 'Favoris',
      'activitySearches': 'Recherches récentes',

      // Profil
      'profileLanguage': 'Langue',
      'profilePrivacy': 'Confidentialité',
      'profileNotifications': 'Notifications',
      'profileSessions': 'Sessions et appareils',
      'profileExport': 'Exporter mes données',
      'profileDelete': 'Supprimer mon compte',
      'profileSignIn': 'Se connecter',
      'profileNotSignedIn': 'Vous naviguez sans compte',
      'profileNotSignedInBody': 'Vous pouvez tout consulter. Un compte n’est demandé qu’au moment de contacter un commerçant.',
      'profileComingSoon': 'Disponible avec votre compte.',

      // Activités, favoris, historique (M3)
      'activityOrderTitle': 'Commande',
      'activityBookingTitle': 'Réservation',
      'activitySectionOrders': 'Commandes',
      'activitySectionBookings': 'Réservations',
      'activityStatusOpen': 'En cours',
      'activitySignInRequired': 'Connectez-vous pour retrouver vos activités.',
      'favoritesTitle': 'Favoris',
      'favoritesEmptyTitle': 'Aucun favori',
      'favoritesUnavailable': 'Ce commerce n’est plus disponible',
      'searchHistoryTitle': 'Recherches récentes',
      'searchHistoryClear': 'Effacer l’historique',
    },

    // ───────────────────────────── EN ─────────────────────────────
    'en': {
      'appName': 'Leopardo',
      'tabHome': 'Home',
      'tabMessages': 'Messages',
      'tabActivity': 'Activity',
      'tabProfile': 'Profile',
      'discoverySearchHint': 'What are you looking for nearby?',
      'discoverySearchLabel': 'Search a business',
      'discoveryCityLabel': 'City',
      'discoveryCityHint': 'Choose a city',
      'discoveryViewList': 'List',
      'discoveryViewMap': 'Map',
      'discoveryOpenNow': 'Open now',
      'discoveryResume': 'Pick up where you left off',
      'discoveryCategories': 'Categories',
      'discoveryResultsCount': '%s results',
      'discoveryNearby': 'Near you',
      'merchantInviteTitle': 'Are you a business owner?',
      'merchantInviteBody': 'Publish your Page for free and show up in search.',
      'merchantInviteCta': 'Publish my Page',
      'discoveryUseLocation': 'Use my location',
      'discoveryLocationDenied':
          'Without your location, choose your city to see what is around.',
      'stateLoading': 'Loading…',
      'stateEmptyTitle': 'Nothing here yet',
      'stateEmptyBody':
          'No business has been published in this city yet. Check back soon.',
      'stateNoResultsTitle': 'No results',
      'stateNoResultsBody': 'Try another word, or widen the area.',
      'stateNoResultsWiden': 'Widen the search',
      'stateErrorTitle': 'Could not load',
      'stateErrorBody': 'Check your connection, then try again.',
      'stateRetry': 'Try again',
      'stateOfflineTitle': 'You are offline',
      'stateOfflineBody': 'Here is what we kept from your last visit.',
      'stateNotFoundTitle': 'Page not found',
      'stateNotFoundBody': 'This business is no longer available. It may have closed or stopped publishing.',
      'stateNotFoundAction': 'Back to search',
      'stateNotAvailable': 'Not available right now',
      'businessOpenNow': 'Open',
      'businessClosedNow': 'Closed',
      'businessVerified': 'Verified',
      'businessHours': 'Opening hours',
      'businessAddress': 'Address',
      'businessItinerary': 'Directions',
      'businessShare': 'Share',
      'businessReport': 'Report',
      'businessReportSent': 'Report sent. Thank you.',
      'businessContact': 'Contact',
      'businessOrder': 'Order',
      'businessBooking': 'Book',
      'businessMenu': 'Menu',
      'businessAbout': 'About',
      'businessPhotos': 'Photos',
      'businessActionsUnavailable': 'This business does not offer that yet.',
      'businessMultipleEstablishments':
          'This place is one of several addresses of the same business.',
      'commonCancel': 'Cancel',
      'commonClose': 'Close',
      'commonBack': 'Back',
      'commonShareText': 'Discover %s on Leopardo',
      'commonNoModule': 'This module is not available yet.',
      'messagesEmptyTitle': 'No conversation',
      'messagesEmptyBody':
          'When you contact a business, the conversation will appear here.',
      'messagesAssistant': 'Léa',
      'messagesAssistantBadge': 'AI assistant',
      'messagesUnread': 'Unread',
      'messagesComingSoon': 'Messaging arrives with your account. No message is lost in the meantime.',
      'activityEmptyTitle': 'No activity',
      'activityEmptyBody':
          'Your orders and bookings will appear here after your first contact.',
      'activityOrders': 'Orders',
      'activityBookings': 'Bookings',
      'activityFavorites': 'Favourites',
      'activitySearches': 'Recent searches',
      'profileLanguage': 'Language',
      'profilePrivacy': 'Privacy',
      'profileNotifications': 'Notifications',
      'profileSessions': 'Sessions and devices',
      'profileExport': 'Export my data',
      'profileDelete': 'Delete my account',
      'profileSignIn': 'Sign in',
      'profileNotSignedIn': 'You are browsing without an account',
      'profileNotSignedInBody': 'You can browse everything. An account is only asked for when you contact a business.',
      'profileComingSoon': 'Available with your account.',

      // Activities, favourites, history (M3)
      'activityOrderTitle': 'Order',
      'activityBookingTitle': 'Booking',
      'activitySectionOrders': 'Orders',
      'activitySectionBookings': 'Bookings',
      'activityStatusOpen': 'In progress',
      'activitySignInRequired': 'Sign in to see your activity.',
      'favoritesTitle': 'Favourites',
      'favoritesEmptyTitle': 'No favourites',
      'favoritesUnavailable': 'This business is no longer available',
      'searchHistoryTitle': 'Recent searches',
      'searchHistoryClear': 'Clear history',
    },

    // ───────────────────────────── AR (RTL) ─────────────────────────────
    'ar': {
      'appName': 'ليوباردو',
      'tabHome': 'الرئيسية',
      'tabMessages': 'الرسائل',
      'tabActivity': 'الأنشطة',
      'tabProfile': 'الملف',
      'discoverySearchHint': 'عمّ تبحث بالقرب منك؟',
      'discoverySearchLabel': 'ابحث عن متجر',
      'discoveryCityLabel': 'المدينة',
      'discoveryCityHint': 'اختر مدينة',
      'discoveryViewList': 'قائمة',
      'discoveryViewMap': 'خريطة',
      'discoveryOpenNow': 'مفتوح الآن',
      'discoveryResume': 'تابع من حيث توقفت',
      'discoveryCategories': 'الفئات',
      'discoveryResultsCount': '%s نتيجة',
      'discoveryNearby': 'بالقرب منك',
      'merchantInviteTitle': 'هل أنت صاحب عمل؟',
      'merchantInviteBody': 'انشر صفحتك مجانًا واظهر في نتائج البحث.',
      'merchantInviteCta': 'انشر صفحتي',
      'discoveryUseLocation': 'استخدم موقعي',
      'discoveryLocationDenied': 'بدون موقعك، اختر مدينتك لترى ما هو حولك.',
      'stateLoading': 'جارٍ التحميل…',
      'stateEmptyTitle': 'لا يوجد شيء بعد',
      'stateEmptyBody': 'لم يُنشر أي متجر في هذه المدينة بعد. عد قريبًا.',
      'stateNoResultsTitle': 'لا نتائج',
      'stateNoResultsBody': 'جرّب كلمة أخرى، أو وسّع النطاق.',
      'stateNoResultsWiden': 'توسيع البحث',
      'stateErrorTitle': 'تعذّر التحميل',
      'stateErrorBody': 'تحقق من اتصالك، ثم أعد المحاولة.',
      'stateRetry': 'أعد المحاولة',
      'stateOfflineTitle': 'أنت غير متصل',
      'stateOfflineBody': 'هذا ما احتفظنا به من زيارتك الأخيرة.',
      'stateNotFoundTitle': 'الصفحة غير موجودة',
      'stateNotFoundBody':
          'هذا المتجر لم يعد متاحًا. ربما أغلق أو توقف عن النشر.',
      'stateNotFoundAction': 'العودة إلى البحث',
      'stateNotAvailable': 'غير متاح حاليًا',
      'businessOpenNow': 'مفتوح',
      'businessClosedNow': 'مغلق',
      'businessVerified': 'موثّق',
      'businessHours': 'أوقات العمل',
      'businessAddress': 'العنوان',
      'businessItinerary': 'الاتجاهات',
      'businessShare': 'مشاركة',
      'businessReport': 'إبلاغ',
      'businessReportSent': 'تم إرسال الإبلاغ. شكرًا.',
      'businessContact': 'تواصل',
      'businessOrder': 'اطلب',
      'businessBooking': 'احجز',
      'businessMenu': 'القائمة',
      'businessAbout': 'نبذة',
      'businessPhotos': 'الصور',
      'businessActionsUnavailable': 'هذا المتجر لا يقدّم ذلك بعد.',
      'businessMultipleEstablishments':
          'هذا المكان أحد عناوين عدة لنفس المتجر.',
      'commonCancel': 'إلغاء',
      'commonClose': 'إغلاق',
      'commonBack': 'رجوع',
      'commonShareText': 'اكتشف %s على ليوباردو',
      'commonNoModule': 'هذه الوحدة غير متاحة بعد.',
      'messagesEmptyTitle': 'لا محادثات',
      'messagesEmptyBody': 'عندما تتواصل مع متجر، ستظهر المحادثة هنا.',
      'messagesAssistant': 'ليا',
      'messagesAssistantBadge': 'مساعدة ذكاء اصطناعي',
      'messagesUnread': 'غير مقروءة',
      'messagesComingSoon':
          'الرسائل تصل مع حسابك. لا تضيع أي رسالة في الأثناء.',
      'activityEmptyTitle': 'لا أنشطة',
      'activityEmptyBody': 'ستظهر طلباتك وحجوزاتك هنا بعد أول تواصل لك.',
      'activityOrders': 'الطلبات',
      'activityBookings': 'الحجوزات',
      'activityFavorites': 'المفضلة',
      'activitySearches': 'عمليات البحث الأخيرة',
      'profileLanguage': 'اللغة',
      'profilePrivacy': 'الخصوصية',
      'profileNotifications': 'الإشعارات',
      'profileSessions': 'الجلسات والأجهزة',
      'profileExport': 'تصدير بياناتي',
      'profileDelete': 'حذف حسابي',
      'profileSignIn': 'تسجيل الدخول',
      'profileNotSignedIn': 'تتصفح بدون حساب',
      'profileNotSignedInBody':
          'يمكنك تصفح كل شيء. يُطلب الحساب فقط عند التواصل مع متجر.',
      'profileComingSoon': 'متاح مع حسابك.',

      // الأنشطة والمفضلة وسجل البحث (M3)
      'activityOrderTitle': 'طلب',
      'activityBookingTitle': 'حجز',
      'activitySectionOrders': 'الطلبات',
      'activitySectionBookings': 'الحجوزات',
      'activityStatusOpen': 'قيد التنفيذ',
      'activitySignInRequired': 'سجّل الدخول لعرض أنشطتك.',
      'favoritesTitle': 'المفضلة',
      'favoritesEmptyTitle': 'لا توجد مفضلات',
      'favoritesUnavailable': 'هذا المتجر لم يعد متاحًا',
      'searchHistoryTitle': 'عمليات البحث الأخيرة',
      'searchHistoryClear': 'مسح السجل',
    },

    // ───────────────────────────── TR ─────────────────────────────
    'tr': {
      'appName': 'Leopardo',
      'tabHome': 'Ana sayfa',
      'tabMessages': 'Mesajlar',
      'tabActivity': 'Etkinlik',
      'tabProfile': 'Profil',
      'discoverySearchHint': 'Yakınında ne arıyorsun?',
      'discoverySearchLabel': 'İşletme ara',
      'discoveryCityLabel': 'Şehir',
      'discoveryCityHint': 'Bir şehir seç',
      'discoveryViewList': 'Liste',
      'discoveryViewMap': 'Harita',
      'discoveryOpenNow': 'Şu an açık',
      'discoveryResume': 'Kaldığın yerden devam et',
      'discoveryCategories': 'Kategoriler',
      'discoveryResultsCount': '%s sonuç',
      'discoveryNearby': 'Yakınında',
      'merchantInviteTitle': 'Bir işletme sahibi misiniz?',
      'merchantInviteBody':
          'Sayfanızı ücretsiz yayınlayın ve aramalarda görünün.',
      'merchantInviteCta': 'Sayfamı yayınla',
      'discoveryUseLocation': 'Konumumu kullan',
      'discoveryLocationDenied':
          'Konumun olmadan, çevreni görmek için şehrini seç.',
      'stateLoading': 'Yükleniyor…',
      'stateEmptyTitle': 'Henüz bir şey yok',
      'stateEmptyBody':
          'Bu şehirde henüz yayımlanmış işletme yok. Yakında tekrar bak.',
      'stateNoResultsTitle': 'Sonuç yok',
      'stateNoResultsBody': 'Başka bir kelime dene veya alanı genişlet.',
      'stateNoResultsWiden': 'Aramayı genişlet',
      'stateErrorTitle': 'Yüklenemedi',
      'stateErrorBody': 'Bağlantını kontrol et, sonra tekrar dene.',
      'stateRetry': 'Tekrar dene',
      'stateOfflineTitle': 'Çevrimdışısın',
      'stateOfflineBody': 'Son ziyaretinden sakladıklarımız bunlar.',
      'stateNotFoundTitle': 'Sayfa bulunamadı',
      'stateNotFoundBody': 'Bu işletme artık mevcut değil. Kapanmış veya yayımdan kaldırılmış olabilir.',
      'stateNotFoundAction': 'Aramaya dön',
      'stateNotAvailable': 'Şu an kullanılamıyor',
      'businessOpenNow': 'Açık',
      'businessClosedNow': 'Kapalı',
      'businessVerified': 'Doğrulanmış',
      'businessHours': 'Çalışma saatleri',
      'businessAddress': 'Adres',
      'businessItinerary': 'Yol tarifi',
      'businessShare': 'Paylaş',
      'businessReport': 'Bildir',
      'businessReportSent': 'Bildirim gönderildi. Teşekkürler.',
      'businessContact': 'İletişim',
      'businessOrder': 'Sipariş ver',
      'businessBooking': 'Rezervasyon',
      'businessMenu': 'Menü',
      'businessAbout': 'Hakkında',
      'businessPhotos': 'Fotoğraflar',
      'businessActionsUnavailable': 'Bu işletme bunu henüz sunmuyor.',
      'businessMultipleEstablishments':
          'Bu mekân, aynı işletmenin birkaç adresinden biri.',
      'commonCancel': 'İptal',
      'commonClose': 'Kapat',
      'commonBack': 'Geri',
      'commonShareText': '%s işletmesini Leopardo’da keşfet',
      'commonNoModule': 'Bu modül henüz kullanılamıyor.',
      'messagesEmptyTitle': 'Konuşma yok',
      'messagesEmptyBody':
          'Bir işletmeyle iletişime geçtiğinde konuşma burada görünecek.',
      'messagesAssistant': 'Léa',
      'messagesAssistantBadge': 'Yapay zekâ asistanı',
      'messagesUnread': 'Okunmamış',
      'messagesComingSoon': 'Mesajlaşma hesabınla birlikte gelir. Bu arada hiçbir mesaj kaybolmaz.',
      'activityEmptyTitle': 'Etkinlik yok',
      'activityEmptyBody': 'Siparişlerin ve rezervasyonların ilk iletişiminden sonra burada görünecek.',
      'activityOrders': 'Siparişler',
      'activityBookings': 'Rezervasyonlar',
      'activityFavorites': 'Favoriler',
      'activitySearches': 'Son aramalar',
      'profileLanguage': 'Dil',
      'profilePrivacy': 'Gizlilik',
      'profileNotifications': 'Bildirimler',
      'profileSessions': 'Oturumlar ve cihazlar',
      'profileExport': 'Verilerimi dışa aktar',
      'profileDelete': 'Hesabımı sil',
      'profileSignIn': 'Giriş yap',
      'profileNotSignedIn': 'Hesapsız geziyorsun',
      'profileNotSignedInBody': 'Her şeye bakabilirsin. Hesap yalnızca bir işletmeyle iletişime geçerken istenir.',
      'profileComingSoon': 'Hesabınla kullanılabilir.',

      // Etkinlikler, favoriler, arama geçmişi (M3)
      'activityOrderTitle': 'Sipariş',
      'activityBookingTitle': 'Rezervasyon',
      'activitySectionOrders': 'Siparişler',
      'activitySectionBookings': 'Rezervasyonlar',
      'activityStatusOpen': 'Devam ediyor',
      'activitySignInRequired': 'Etkinliklerini görmek için giriş yap.',
      'favoritesTitle': 'Favoriler',
      'favoritesEmptyTitle': 'Favori yok',
      'favoritesUnavailable': 'Bu işletme artık mevcut değil',
      'searchHistoryTitle': 'Son aramalar',
      'searchHistoryClear': 'Geçmişi temizle',
    },
  };
}
