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
      'discoveryLocationDenied':
          'Sans votre position, choisissez votre ville pour voir ce qui est autour.',

      // États obligatoires (chaque écran)
      'stateLoading': 'Chargement…',
      'stateEmptyTitle': 'Rien ici pour le moment',
      'stateEmptyBody':
          'Aucun commerce publié dans cette ville pour l’instant. Revenez bientôt.',
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
      'stateNotFoundBody':
          'Ce commerce n’est plus disponible. Il a peut-être fermé ou cessé d’être publié.',
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
      'businessMultipleEstablishments':
          'Cet établissement fait partie d’un commerce avec plusieurs adresses.',

      // Commun
      'commonCancel': 'Annuler',
      'commonClose': 'Fermer',
      'commonBack': 'Retour',
      'commonShareText': 'Découvrez %s sur Leopardo',
      'commonNoModule': 'Ce module n’est pas encore disponible.',

      // Messages (M2) — présents pour que l’onglet ne soit jamais muet
      'messagesEmptyTitle': 'Aucune conversation',
      'messagesEmptyBody':
          'Quand vous contacterez un commerçant, la conversation apparaîtra ici.',
      'messagesAssistant': 'Léa',
      'messagesAssistantBadge': 'Assistante IA',
      'messagesUnread': 'Non lus',
      'messagesComingSoon':
          'La messagerie arrive avec votre compte. Aucun message n’est perdu entre-temps.',

      // Activités (M3)
      'activityEmptyTitle': 'Aucune activité',
      'activityEmptyBody':
          'Vos commandes et réservations apparaîtront ici dès votre premier contact.',
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
      'profileNotSignedInBody':
          'Vous pouvez tout consulter. Un compte n’est demandé qu’au moment de contacter un commerçant.',
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

      // ── Parcours transactionnels des 8 verticales (#9907) ──
      'journeyTitleOrder': 'Commander',
      'journeyTitleStay': 'Réserver',
      'journeyTitleContact': 'Contacter',
      'journeyCatalogNotice':
          'Les prix sont ceux du commerçant. Le montant final est confirmé par le serveur au moment de la commande.',
      'journeyProductUnavailable': 'Indisponible',
      'journeyFieldRequired': 'Ce champ est requis',
      'journeyFieldInvalidEmail': 'Adresse e-mail invalide',
      'journeyCheckFields': 'Vérifiez les informations',
      'journeyCustomer': 'Vos coordonnées',
      'journeyCustomerName': 'Nom complet',
      'journeyCustomerPhone': 'Téléphone',
      'journeyCustomerEmail': 'E-mail (optionnel)',
      'journeyDelivery': 'Livraison',
      'journeyDeliveryAddress': 'Adresse',
      'journeyDeliveryCity': 'Ville',
      'journeyDeliveryNotes': 'Instructions (optionnel)',
      'journeyPaymentMethod': 'Paiement',
      'journeyPaymentCash': 'À la livraison',
      'journeyPaymentOnline': 'En ligne',
      'journeyPaymentStatus': 'Statut du paiement',
      'journeySubmitOrder': 'Confirmer la commande',
      'journeyCheckout': 'Commander',
      'journeyTotal': 'Total',
      'journeyConfirmationSent': 'C’est envoyé.',
      'journeyConfirmationTitle': 'À conserver',
      'journeyConfirmationReference': 'Référence',
      'journeyConfirmationTrackingCode': 'Code de suivi',
      'journeyKeepCodeNotice':
          'Ce code ne sera plus affiché. Notez-le avec la référence : il est demandé pour suivre la commande.',
      'journeyPayNow': 'Payer maintenant',
      'journeyTrackTitle': 'Suivre',
      'journeyTrackCode': 'Code de suivi',
      'journeyTrack': 'Suivre',
      'journeyTrackStatus': 'Statut',
      'journeyBlockedTitle': 'Pas encore disponible ici',
      'journeyBlockedBody':
          'Ce parcours n’a pas de contrat public appelable depuis l’application. Voici ce qui manque, précisément :',
      'journeyBlockerRestaurantOrder':
          'La commande restaurant exige un jeton de boutique émis par l’établissement : elle reste possible sur sa page web.',
      'journeyBlockerTradeQuote':
          'Le devis B2B structuré (lignes, quantités, incoterm) n’existe pas encore dans le contrat public : seule une demande libre est possible.',
      'journeyBlockerVtcRide':
          'La course VTC exige des coordonnées d’arrivée ; l’application n’a pas de recherche d’adresse, un devis serait faux.',
      'journeyBlockerPharmacyCatalog':
          'Aucune route publique n’expose le catalogue d’une pharmacie.',
      'journeyBlockerPharmacyContact':
          'La passerelle de demande n’accepte pas le type « pharmacie » : elle répondrait 422.',
      'journeyBlockerTravelEntry':
          'La billetterie n’est pas exposée par la découverte : aucune entrée depuis l’application.',
      'journeyBlockerCareersEntry':
          'Le portail emploi n’est pas exposé par la découverte : aucune entrée depuis l’application.',
      'journeyVerticalRestaurant': 'Restaurant',
      'journeyVerticalRetail': 'Boutique',
      'journeyVerticalHospitality': 'Séjour',
      'journeyVerticalTrade': 'Entreprise',
      'journeyVerticalPharmacy': 'Pharmacie',
      'journeyVerticalVtc': 'Opérateur VTC',
      'journeyVerticalTravel': 'Billets',
      'journeyVerticalCareers': 'Emploi',
      'journeyStayNotice':
          'Les disponibilités sont calculées par l’établissement pour vos dates exactes.',
      'journeyFrom': 'Arrivée',
      'journeyTo': 'Départ',
      'journeyDateHint': 'Date au format AAAA-MM-JJ',
      'journeyAdults': 'Adultes',
      'journeyChildren': 'Enfants',
      'journeyCheckAvailability': 'Vérifier les disponibilités',
      'journeyRoomsAvailable': 'Chambres disponibles',
      'journeyRoomAvailable': 'Disponible',
      'journeyRoomSoldOut': 'Complet sur ces dates',
      'journeyRoomsEmptyTitle': 'Aucune chambre sur ces dates',
      'journeyRoomsEmptyBody':
          'Essayez d’autres dates : l’établissement n’a rien de libre sur cette période.',
      'journeyChangeDates': 'Changer les dates',
      'journeyGuest': 'Voyageur',
      'journeyGuestName': 'Nom du voyageur',
      'journeyContactRequired':
          'Un e-mail ou un téléphone est nécessaire pour confirmer la réservation.',
      'journeySubmitBooking': 'Réserver',
      'journeyRequestNotice':
          'Votre demande est transmise directement à ce professionnel.',
      'journeyMessage': 'Message',
      'journeyMessageTooShort':
          'Décrivez votre demande en quelques mots (10 caractères minimum)',
      'journeyConsentLabel':
          'J’accepte que mes coordonnées soient transmises à ce professionnel pour répondre à ma demande.',
      'journeyConsentRequired': 'Cochez la case de consentement pour envoyer.',
      'journeySubmitRequest': 'Envoyer la demande',
      'journeyPrivacyNotice':
          'Aucune donnée n’est conservée par l’application : la demande part directement au professionnel.',
      'journeyRequestSentBody': 'Votre demande a été transmise.',
      'journeyRequestSentHint':
          'Le professionnel vous répond sur le contact que vous avez indiqué. Aucun suivi dans l’application pour ce type de demande.',
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
      'stateNotFoundBody':
          'This business is no longer available. It may have closed or stopped publishing.',
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
      'messagesComingSoon':
          'Messaging arrives with your account. No message is lost in the meantime.',
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
      'profileNotSignedInBody':
          'You can browse everything. An account is only asked for when you contact a business.',
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

      // ── Transactional journeys across the 8 verticals (#9907) ──
      'journeyTitleOrder': 'Order',
      'journeyTitleStay': 'Book',
      'journeyTitleContact': 'Contact',
      'journeyCatalogNotice':
          'Prices come from the merchant. The final amount is confirmed by the server when the order is placed.',
      'journeyProductUnavailable': 'Unavailable',
      'journeyFieldRequired': 'This field is required',
      'journeyFieldInvalidEmail': 'Invalid email address',
      'journeyCheckFields': 'Check your details',
      'journeyCustomer': 'Your details',
      'journeyCustomerName': 'Full name',
      'journeyCustomerPhone': 'Phone',
      'journeyCustomerEmail': 'Email (optional)',
      'journeyDelivery': 'Delivery',
      'journeyDeliveryAddress': 'Address',
      'journeyDeliveryCity': 'City',
      'journeyDeliveryNotes': 'Instructions (optional)',
      'journeyPaymentMethod': 'Payment',
      'journeyPaymentCash': 'On delivery',
      'journeyPaymentOnline': 'Online',
      'journeyPaymentStatus': 'Payment status',
      'journeySubmitOrder': 'Confirm order',
      'journeyCheckout': 'Order',
      'journeyTotal': 'Total',
      'journeyConfirmationSent': 'Sent.',
      'journeyConfirmationTitle': 'Keep this',
      'journeyConfirmationReference': 'Reference',
      'journeyConfirmationTrackingCode': 'Tracking code',
      'journeyKeepCodeNotice':
          'This code will not be shown again. Note it with the reference: it is required to track the order.',
      'journeyPayNow': 'Pay now',
      'journeyTrackTitle': 'Track',
      'journeyTrackCode': 'Tracking code',
      'journeyTrack': 'Track',
      'journeyTrackStatus': 'Status',
      'journeyBlockedTitle': 'Not available here yet',
      'journeyBlockedBody':
          'This journey has no public contract the app can call. Here is exactly what is missing:',
      'journeyBlockerRestaurantOrder':
          'Restaurant ordering requires a shop token issued by the venue: it remains available on its web page.',
      'journeyBlockerTradeQuote':
          'A structured B2B quote (lines, quantities, incoterm) does not exist in the public contract yet: only a free-form request is possible.',
      'journeyBlockerVtcRide':
          'A VTC ride needs drop-off coordinates; the app has no address search, so any estimate would be wrong.',
      'journeyBlockerPharmacyCatalog':
          'No public route exposes a pharmacy catalogue.',
      'journeyBlockerPharmacyContact':
          'The request gateway does not accept the "pharmacy" type: it would answer 422.',
      'journeyBlockerTravelEntry':
          'Ticketing is not exposed by discovery: there is no entry point from the app.',
      'journeyBlockerCareersEntry':
          'The careers portal is not exposed by discovery: there is no entry point from the app.',
      'journeyVerticalRestaurant': 'Restaurant',
      'journeyVerticalRetail': 'Shop',
      'journeyVerticalHospitality': 'Stay',
      'journeyVerticalTrade': 'Company',
      'journeyVerticalPharmacy': 'Pharmacy',
      'journeyVerticalVtc': 'Ride operator',
      'journeyVerticalTravel': 'Tickets',
      'journeyVerticalCareers': 'Jobs',
      'journeyStayNotice':
          'Availability is computed by the property for your exact dates.',
      'journeyFrom': 'Check-in',
      'journeyTo': 'Check-out',
      'journeyDateHint': 'Date as YYYY-MM-DD',
      'journeyAdults': 'Adults',
      'journeyChildren': 'Children',
      'journeyCheckAvailability': 'Check availability',
      'journeyRoomsAvailable': 'Available rooms',
      'journeyRoomAvailable': 'Available',
      'journeyRoomSoldOut': 'Sold out for these dates',
      'journeyRoomsEmptyTitle': 'No room for these dates',
      'journeyRoomsEmptyBody':
          'Try other dates: the property has nothing free for this period.',
      'journeyChangeDates': 'Change dates',
      'journeyGuest': 'Guest',
      'journeyGuestName': 'Guest name',
      'journeyContactRequired':
          'An email or a phone number is required to confirm the booking.',
      'journeySubmitBooking': 'Book',
      'journeyRequestNotice':
          'Your request goes straight to this professional.',
      'journeyMessage': 'Message',
      'journeyMessageTooShort':
          'Describe your request in a few words (10 characters minimum)',
      'journeyConsentLabel':
          'I agree that my contact details are sent to this professional to answer my request.',
      'journeyConsentRequired': 'Tick the consent box to send.',
      'journeySubmitRequest': 'Send request',
      'journeyPrivacyNotice':
          'The app stores nothing: the request goes directly to the professional.',
      'journeyRequestSentBody': 'Your request has been sent.',
      'journeyRequestSentHint':
          'The professional replies to the contact details you provided. There is no in-app tracking for this kind of request.',
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

      // ── مسارات المعاملات للقطاعات الثمانية (#9907) ──
      'journeyTitleOrder': 'اطلب',
      'journeyTitleStay': 'احجز',
      'journeyTitleContact': 'تواصل',
      'journeyCatalogNotice':
          'الأسعار من التاجر. المبلغ النهائي يؤكده الخادم عند تسجيل الطلب.',
      'journeyProductUnavailable': 'غير متاح',
      'journeyFieldRequired': 'هذا الحقل مطلوب',
      'journeyFieldInvalidEmail': 'بريد إلكتروني غير صالح',
      'journeyCheckFields': 'تحقق من المعلومات',
      'journeyCustomer': 'بياناتك',
      'journeyCustomerName': 'الاسم الكامل',
      'journeyCustomerPhone': 'الهاتف',
      'journeyCustomerEmail': 'البريد الإلكتروني (اختياري)',
      'journeyDelivery': 'التوصيل',
      'journeyDeliveryAddress': 'العنوان',
      'journeyDeliveryCity': 'المدينة',
      'journeyDeliveryNotes': 'تعليمات (اختياري)',
      'journeyPaymentMethod': 'الدفع',
      'journeyPaymentCash': 'عند التوصيل',
      'journeyPaymentOnline': 'عبر الإنترنت',
      'journeyPaymentStatus': 'حالة الدفع',
      'journeySubmitOrder': 'تأكيد الطلب',
      'journeyCheckout': 'اطلب',
      'journeyTotal': 'المجموع',
      'journeyConfirmationSent': 'تم الإرسال.',
      'journeyConfirmationTitle': 'احتفظ بهذا',
      'journeyConfirmationReference': 'المرجع',
      'journeyConfirmationTrackingCode': 'رمز التتبع',
      'journeyKeepCodeNotice':
          'لن يظهر هذا الرمز مرة أخرى. دوّنه مع المرجع: يُطلب لتتبع الطلب.',
      'journeyPayNow': 'ادفع الآن',
      'journeyTrackTitle': 'تتبع',
      'journeyTrackCode': 'رمز التتبع',
      'journeyTrack': 'تتبع',
      'journeyTrackStatus': 'الحالة',
      'journeyBlockedTitle': 'غير متاح هنا بعد',
      'journeyBlockedBody':
          'هذا المسار لا يملك عقدًا عامًا يمكن للتطبيق استدعاؤه. إليك ما ينقص بالتحديد:',
      'journeyBlockerRestaurantOrder':
          'طلب المطعم يتطلب رمز متجر يصدره المطعم: يبقى ممكنًا عبر صفحته على الويب.',
      'journeyBlockerTradeQuote':
          'عرض السعر المهيكل بين الشركات (الأسطر، الكميات، شروط التسليم) غير موجود بعد في العقد العام: المتاح طلب حر فقط.',
      'journeyBlockerVtcRide':
          'رحلة النقل تتطلب إحداثيات الوصول؛ التطبيق لا يملك بحث عنوان، وأي تسعير سيكون خاطئًا.',
      'journeyBlockerPharmacyCatalog': 'لا يوجد مسار عام يعرض كتالوج الصيدلية.',
      'journeyBlockerPharmacyContact':
          'بوابة الطلبات لا تقبل نوع «صيدلية»: سترد بخطأ 422.',
      'journeyBlockerTravelEntry':
          'بيع التذاكر غير معروض في الاكتشاف: لا مدخل من التطبيق.',
      'journeyBlockerCareersEntry':
          'بوابة التوظيف غير معروضة في الاكتشاف: لا مدخل من التطبيق.',
      'journeyVerticalRestaurant': 'مطعم',
      'journeyVerticalRetail': 'متجر',
      'journeyVerticalHospitality': 'إقامة',
      'journeyVerticalTrade': 'شركة',
      'journeyVerticalPharmacy': 'صيدلية',
      'journeyVerticalVtc': 'مشغل نقل',
      'journeyVerticalTravel': 'تذاكر',
      'journeyVerticalCareers': 'توظيف',
      'journeyStayNotice': 'يتحقق مكان الإقامة من التوفر لتواريخك بالضبط.',
      'journeyFrom': 'الوصول',
      'journeyTo': 'المغادرة',
      'journeyDateHint': 'التاريخ بصيغة YYYY-MM-DD',
      'journeyAdults': 'بالغون',
      'journeyChildren': 'أطفال',
      'journeyCheckAvailability': 'تحقق من التوفر',
      'journeyRoomsAvailable': 'غرف متاحة',
      'journeyRoomAvailable': 'متاحة',
      'journeyRoomSoldOut': 'مكتملة في هذه التواريخ',
      'journeyRoomsEmptyTitle': 'لا غرف في هذه التواريخ',
      'journeyRoomsEmptyBody':
          'جرّب تواريخ أخرى: لا شيء متاح في المؤسسة خلال هذه الفترة.',
      'journeyChangeDates': 'تغيير التواريخ',
      'journeyGuest': 'المسافر',
      'journeyGuestName': 'اسم المسافر',
      'journeyContactRequired': 'يلزم بريد إلكتروني أو هاتف لتأكيد الحجز.',
      'journeySubmitBooking': 'احجز',
      'journeyRequestNotice': 'طلبك يُرسل مباشرة إلى هذا المهني.',
      'journeyMessage': 'الرسالة',
      'journeyMessageTooShort': 'اشرح طلبك بكلمات قليلة (10 أحرف على الأقل)',
      'journeyConsentLabel':
          'أوافق على إرسال بيانات الاتصال الخاصة بي إلى هذا المهني للرد على طلبي.',
      'journeyConsentRequired': 'حدّد مربع الموافقة للإرسال.',
      'journeySubmitRequest': 'أرسل الطلب',
      'journeyPrivacyNotice':
          'التطبيق لا يحفظ شيئًا: الطلب يذهب مباشرة إلى المهني.',
      'journeyRequestSentBody': 'تم إرسال طلبك.',
      'journeyRequestSentHint':
          'سيرد المهني على بيانات الاتصال التي أدخلتها. لا يوجد تتبع داخل التطبيق لهذا النوع من الطلبات.',
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
      'stateNotFoundBody':
          'Bu işletme artık mevcut değil. Kapanmış veya yayımdan kaldırılmış olabilir.',
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
      'messagesComingSoon':
          'Mesajlaşma hesabınla birlikte gelir. Bu arada hiçbir mesaj kaybolmaz.',
      'activityEmptyTitle': 'Etkinlik yok',
      'activityEmptyBody':
          'Siparişlerin ve rezervasyonların ilk iletişiminden sonra burada görünecek.',
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
      'profileNotSignedInBody':
          'Her şeye bakabilirsin. Hesap yalnızca bir işletmeyle iletişime geçerken istenir.',
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

      // ── 8 dikey için işlem akışları (#9907) ──
      'journeyTitleOrder': 'Sipariş ver',
      'journeyTitleStay': 'Rezervasyon',
      'journeyTitleContact': 'İletişim',
      'journeyCatalogNotice':
          'Fiyatlar işletmeden gelir. Kesin tutar sipariş sırasında sunucu tarafından onaylanır.',
      'journeyProductUnavailable': 'Mevcut değil',
      'journeyFieldRequired': 'Bu alan zorunlu',
      'journeyFieldInvalidEmail': 'Geçersiz e-posta adresi',
      'journeyCheckFields': 'Bilgileri kontrol edin',
      'journeyCustomer': 'Bilgileriniz',
      'journeyCustomerName': 'Ad soyad',
      'journeyCustomerPhone': 'Telefon',
      'journeyCustomerEmail': 'E-posta (isteğe bağlı)',
      'journeyDelivery': 'Teslimat',
      'journeyDeliveryAddress': 'Adres',
      'journeyDeliveryCity': 'Şehir',
      'journeyDeliveryNotes': 'Talimatlar (isteğe bağlı)',
      'journeyPaymentMethod': 'Ödeme',
      'journeyPaymentCash': 'Teslimatta',
      'journeyPaymentOnline': 'Çevrimiçi',
      'journeyPaymentStatus': 'Ödeme durumu',
      'journeySubmitOrder': 'Siparişi onayla',
      'journeyCheckout': 'Sipariş ver',
      'journeyTotal': 'Toplam',
      'journeyConfirmationSent': 'Gönderildi.',
      'journeyConfirmationTitle': 'Bunu saklayın',
      'journeyConfirmationReference': 'Referans',
      'journeyConfirmationTrackingCode': 'Takip kodu',
      'journeyKeepCodeNotice':
          'Bu kod bir daha gösterilmeyecek. Referansla birlikte not alın: siparişi takip etmek için gerekir.',
      'journeyPayNow': 'Şimdi öde',
      'journeyTrackTitle': 'Takip',
      'journeyTrackCode': 'Takip kodu',
      'journeyTrack': 'Takip et',
      'journeyTrackStatus': 'Durum',
      'journeyBlockedTitle': 'Burada henüz yok',
      'journeyBlockedBody':
          'Bu akışın uygulamadan çağrılabilen bir genel sözleşmesi yok. Eksik olan tam olarak şu:',
      'journeyBlockerRestaurantOrder':
          'Restoran siparişi işletme tarafından verilen bir mağaza jetonu gerektirir: web sayfasında hâlâ mümkün.',
      'journeyBlockerTradeQuote':
          'Yapılandırılmış B2B teklif (satırlar, miktarlar, teslim şartı) genel sözleşmede yok: yalnızca serbest talep mümkün.',
      'journeyBlockerVtcRide':
          'VTC yolculuğu varış koordinatları gerektirir; uygulamada adres arama yok, her fiyat yanlış olurdu.',
      'journeyBlockerPharmacyCatalog':
          'Eczane kataloğunu yayınlayan genel bir yol yok.',
      'journeyBlockerPharmacyContact':
          'Talep geçidi «eczane» türünü kabul etmiyor: 422 dönerdi.',
      'journeyBlockerTravelEntry':
          'Bilet satışı keşifte yayınlanmıyor: uygulamadan giriş yok.',
      'journeyBlockerCareersEntry':
          'İşe alım portalı keşifte yayınlanmıyor: uygulamadan giriş yok.',
      'journeyVerticalRestaurant': 'Restoran',
      'journeyVerticalRetail': 'Mağaza',
      'journeyVerticalHospitality': 'Konaklama',
      'journeyVerticalTrade': 'Şirket',
      'journeyVerticalPharmacy': 'Eczane',
      'journeyVerticalVtc': 'Ulaşım operatörü',
      'journeyVerticalTravel': 'Biletler',
      'journeyVerticalCareers': 'İş',
      'journeyStayNotice':
          'Müsaitlik, tam tarihleriniz için tesis tarafından hesaplanır.',
      'journeyFrom': 'Giriş',
      'journeyTo': 'Çıkış',
      'journeyDateHint': 'Tarih YYYY-AA-GG biçiminde',
      'journeyAdults': 'Yetişkin',
      'journeyChildren': 'Çocuk',
      'journeyCheckAvailability': 'Müsaitliği kontrol et',
      'journeyRoomsAvailable': 'Müsait odalar',
      'journeyRoomAvailable': 'Müsait',
      'journeyRoomSoldOut': 'Bu tarihlerde dolu',
      'journeyRoomsEmptyTitle': 'Bu tarihlerde oda yok',
      'journeyRoomsEmptyBody':
          'Başka tarihler deneyin: tesisin bu dönem için boş odası yok.',
      'journeyChangeDates': 'Tarihleri değiştir',
      'journeyGuest': 'Misafir',
      'journeyGuestName': 'Misafir adı',
      'journeyContactRequired':
          'Rezervasyonu onaylamak için bir e-posta veya telefon gerekir.',
      'journeySubmitBooking': 'Rezervasyon yap',
      'journeyRequestNotice': 'Talebiniz doğrudan bu işletmeye iletilir.',
      'journeyMessage': 'Mesaj',
      'journeyMessageTooShort':
          'Talebinizi birkaç kelimeyle yazın (en az 10 karakter)',
      'journeyConsentLabel':
          'İletişim bilgilerimin talebime yanıt vermek için bu işletmeye iletilmesini kabul ediyorum.',
      'journeyConsentRequired': 'Göndermek için onay kutusunu işaretleyin.',
      'journeySubmitRequest': 'Talebi gönder',
      'journeyPrivacyNotice':
          'Uygulama hiçbir veriyi saklamaz: talep doğrudan işletmeye gider.',
      'journeyRequestSentBody': 'Talebiniz iletildi.',
      'journeyRequestSentHint':
          'İşletme verdiğiniz iletişim bilgisinden yanıtlar. Bu talep türü için uygulama içi takip yok.',
    },
  };
}
