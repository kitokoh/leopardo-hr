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

      // ── Annuaires de verticales (#9948) ──
      'journeyDirectoryTitle': 'Annuaire',
      'journeyDirectoryNotice':
          'Les établissements affichés publient volontairement sur la plateforme. Un établissement non publié n’apparaît pas ici.',
      'journeyDirectorySearch': 'Rechercher',
      'journeyDirectorySearchHint': 'Nom, enseigne (optionnel)',
      'journeyDirectoryCity': 'Ville',
      'journeyDirectoryCityHint': 'Ville (optionnel)',
      'journeyDirectorySearchAction': 'Rechercher',
      'journeyDirectoryCategory': 'Catégorie',
      'journeyDirectoryRating': 'Note',
      'journeyDirectoryDistance': 'Distance',
      'journeyDirectoryEmptyTitle': 'Aucun établissement publié',
      'journeyDirectoryEmptyBody':
          'Aucun établissement de cette catégorie n’a encore activé sa publication.',
      'journeyDirectoryUnavailableTitle': 'Service momentanément indisponible',
      'journeyDirectoryUnavailableBody':
          'L’annuaire n’a pas répondu. Ce n’est pas une absence d’établissement : réessayez.',
      'journeyDirectoryUnknownTitle': 'Verticale inconnue',
      'journeyDirectoryUnknownBody':
          'Cette catégorie n’existe pas dans cette version de l’application.',
      'journeyContactAction': 'Contacter',
      'journeyBlockerContactGeneric':
          'La prise de contact n’est pas encore disponible pour cette catégorie.',
      'journeyStatusLabel': 'Statut',
      // ── Verticale Mobilité (#9948) ──
      'vtcOperatorsNotice':
          'Ces opérateurs acceptent des courses réservées en ligne. La course se demande sans compte et se suit par référence.',
      'vtcEmptyTitle': 'Aucun opérateur disponible',
      'vtcEmptyBody':
          'Aucun opérateur de mobilité n’a encore activé sa publication.',
      'vtcCoordinatesNotice':
          'Le devis exige des coordonnées précises : sans elles, la distance et le prix seraient faux. Format attendu : latitude,longitude.',
      'vtcCoordinatesHint': 'ex. 33.5731,-7.5898',
      'vtcPickup': 'Point de départ',
      'vtcPickupAddress': 'Adresse de départ',
      'vtcDropoff': 'Point d’arrivée',
      'vtcDropoffAddress': 'Adresse d’arrivée',
      'vtcAddressHint': 'Adresse lisible (optionnel)',
      'vtcEstimate': 'Obtenir un devis',
      'vtcEstimatedPrice': 'Prix estimé',
      'vtcDistance': 'Distance',
      'vtcPriceToConfirm': 'Prix à confirmer par l’opérateur',
      'vtcRequestRide': 'Demander la course',
      'vtcRideRequested': 'Course demandée',
      'vtcTrackingCode': 'Code de suivi',
      'vtcTrackingCodeNotice':
          'Conservez ce code : il n’est affiché qu’une fois.',
      'vtcTrackingCodeMissing':
          'Le code n’est pas réaffiché sur une demande déjà envoyée. Utilisez la référence et le code reçus.',
      'vtcInvalidPickup':
          'Point de départ invalide (format latitude,longitude)',
      'vtcInvalidDropoff':
          'Point d’arrivée invalide (format latitude,longitude)',
      'vtcMissingName': 'Le nom du passager est requis',
      'vtcMissingPhone': 'Le téléphone du passager est requis',
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
      // Parcours transactionnels par verticale (#9907) — le motif affiché
      // quand l'app ne peut pas mener la transaction elle-même.
      'journeyReasonRestaurantOrder':
          'La commande en ligne n’est pas encore ouverte au grand public. Envoyez votre demande : le restaurant vous répond.',
      'journeyReasonTradeQuote':
          'Le devis détaillé se prépare avec l’entreprise. Envoyez votre demande, elle revient avec une proposition.',
      'journeyReasonVtcRide':
          'Réserver une course demande de choisir une adresse de départ et d’arrivée. Envoyez votre demande, l’opérateur vous rappelle.',
      'journeyReasonTravelPortal':
          'Les billets ne sont pas encore accessibles depuis cette application.',
      'journeyReasonPharmacyCatalog':
          'Le catalogue des pharmacies n’est pas encore publié.',
      'journeyReasonCareersPortal':
          'Les offres d’emploi ne sont pas encore accessibles depuis cette application.',
      'journeyRequestCta': 'Envoyer une demande',
      'journeyNativeCta': 'Commander',

      // ── Verticale Billets (travel) — #9943 ──
      'travelSearchNotice':
          'Les trajets viennent des agences partenaires. Le prix final est confirmé par le serveur au moment de la réservation.',
      'travelOrigin': 'Ville de départ',
      'travelDestination': 'Ville d’arrivée',
      'travelCityHint': 'Nom de la ville',
      'travelDate': 'Date (optionnel)',
      'travelSearch': 'Rechercher des trajets',
      'travelEmptyTitle': 'Aucun trajet sur ces critères',
      'travelEmptyBody':
          'Essayez une autre date, ou retirez un filtre de ville.',
      'travelRouteUnknown': 'Itinéraire non communiqué',
      'travelSoldOut': 'Complet',
      'travelNoFare':
          'Aucun tarif publié pour ce trajet : la réservation n’est pas encore ouverte.',
      'travelDeparture': 'Départ',
      'travelArrival': 'Arrivée',
      'travelAgency': 'Agence',
      'travelSeatsAvailable': 'Places disponibles',
      'travelSeatsFree': 'Sièges libres',
      'travelPrices': 'Tarifs',
      'travelClass': 'Classe',
      'travelAdult': 'Adulte',
      'travelChild': 'Enfant',
      'travelBook': 'Réserver',
      'travelPassengerNotice':
          'Un nom par passager : un billet sans nom n’est pas un billet.',
      'travelPassengers': 'Passagers',
      'travelPassenger': 'Passager',
      'travelAddPassenger': 'Ajouter un passager',
      'travelAgeCategory': 'Catégorie d’âge',
      'travelAge_adult': 'Adulte',
      'travelAge_child': 'Enfant',
      'travelAge_infant': 'Nourrisson',
      'travelNotifyConsent':
          'Je souhaite être prévenu en cas de changement d’horaire.',
      'travelSubmitBooking': 'Confirmer la réservation',
      'travelValidationCode': 'Code de validation',
      'travelExpiresAt': 'À confirmer avant',
      'travelKeepCodeNotice':
          'Conservez la référence et le code de validation : ils sont nécessaires pour suivre ou annuler la réservation.',

      'travelPaymentTitle': 'Paiement en ligne',
      'travelPaymentMethod': 'Moyen de paiement',
      'travelPayment_momo': 'Mobile money',
      'travelPayment_card': 'Carte bancaire',
      'travelPayment_pvit': 'PVit',
      'travelPaymentNotice':
          'Le paiement est initié auprès de l’agence. Vous recevrez la confirmation de l’opérateur sur votre téléphone.',
      'travelPayNow': 'Payer maintenant',
      'travelPaymentInitiated': 'Paiement initié',
      'travelPaymentReference': 'Référence du paiement',
      'travelPaymentProviderReference': 'Référence opérateur',
      'travelPaymentPendingNotice':
          'Le paiement est en attente de confirmation. Conservez la référence du paiement : elle permet de le retrouver.',
      'travelPaymentWatching':
          'Suivi du paiement en cours… le statut se met à jour automatiquement.',
      'travelPaymentSettled': 'Paiement confirmé.',
      'travelPaymentWatchStopped':
          'Le suivi automatique s\'est arrêté sans confirmation. Le paiement peut encore aboutir : vérifiez plus tard.',
      'travelPaymentWatchRetry': 'Vérifier à nouveau',

      // ── Verticale Restaurant — commande par slug (#9959) ──
      'restaurantOrderTitle': 'Commander',
      'restaurantOrderAction': 'Commander en ligne',
      'restaurantNotPublished':
          'Cet établissement n\'est pas publié sur la plateforme. Il n\'est donc pas commandable ici.',
      'restaurantNoMenu':
          'Cet établissement est publié mais n\'a encore rien mis en ligne. Revenez plus tard.',
      'restaurantCart': 'Panier',
      'restaurantGoToCart': 'Voir le panier',
      'restaurantBackToMenu': 'Retour au menu',
      'restaurantPriceOnRequest': 'Prix confirmé par l\'établissement',
      'restaurantTotalOnRequest':
          'Le total sera confirmé par l\'établissement : certains articles n\'ont pas de prix publié.',
      'restaurantOrderType': 'Type de commande',
      'restaurantOrderType_pickup': 'À emporter',
      'restaurantOrderType_delivery': 'Livraison',
      'restaurantOrderNote': 'Note pour l\'établissement',
      'restaurantSubmitOrder': 'Envoyer la commande',
      'restaurantKeepSecretNotice':
          'Conservez le code de suivi : il n\'est affiché qu\'une fois et permet de retrouver la commande.',
      'restaurantReplayNotice':
          'Cette commande existait déjà : le code de suivi est celui obtenu à la première création.',
      'restaurantTrackingSecret': 'Code de suivi',

      // ── Verticale Restaurant — parité web (#9991..#9994) ──
      'restaurantMenuSearch': 'Rechercher dans la carte',
      'restaurantMenuSearchHint': 'Un plat, une boisson…',
      'restaurantMenuAll': 'Tout',
      'restaurantMenuNoMatch':
          'Aucun produit ne correspond à cette recherche. Essayez un autre mot ou choisissez « Tout ».',
      'restaurantOpenNow': 'Ouvert maintenant',
      'restaurantClosedNow': 'Fermé actuellement',
      'restaurantHoursTitle': 'Horaires',
      'restaurantClosedDay': 'Fermé',
      'restaurantCuisinesLabel': 'Cuisines',
      'restaurantReviewsTitle': 'Avis clients',
      'restaurantReviewsEmpty':
          'Aucun avis publié pour le moment. Les avis sont publiés après modération par l’établissement.',
      'restaurantReviewsLoadMore': 'Afficher plus d’avis',
      'restaurantReviewYourRating': 'Votre note',
      'restaurantReviewComment': 'Votre commentaire (facultatif)',
      'restaurantReviewSubmit': 'Publier mon avis',
      'restaurantReviewSent':
          'Merci ! Votre avis sera visible après modération par l’établissement.',
      'restaurantReviewAlready':
          'Un avis a déjà été déposé pour cette commande. Un seul avis par commande est accepté.',
      'restaurantReviewNotEligible':
          'Cette commande n’est pas encore servie : l’avis sera possible une fois la commande terminée.',
      'restaurantReviewOrderRef': 'Référence de commande',
      'restaurantReviewReplyFrom': 'Réponse de l’établissement',
      'restaurantPayTitle': 'Paiement',
      'restaurantPayCash': 'Espèces à réception',
      'restaurantPayMobileMoney': 'Mobile money',
      'restaurantPayAction': 'Payer la commande',
      'restaurantPayDone': 'Paiement enregistré.',
      'restaurantPayCardUnavailable':
          'Le paiement par carte n’est pas encore ouvert pour cette commande.',
      'restaurantTrackAuto': 'Le suivi se met à jour automatiquement.',
      'restaurantTrackStopped': 'Commande terminée : le suivi est à jour.',
      'journeyDayMonday': 'Lundi',
      'journeyDayTuesday': 'Mardi',
      'journeyDayWednesday': 'Mercredi',
      'journeyDayThursday': 'Jeudi',
      'journeyDayFriday': 'Vendredi',
      'journeyDaySaturday': 'Samedi',
      'journeyDaySunday': 'Dimanche',

      // ── Verticale Emploi (careers) — #9944 ──
      'careersDirectoryNotice':
          'Les offres viennent des entreprises qui publient sur la plateforme. Certaines offres sont agrégées de partenaires : la candidature se fait alors chez eux.',
      'careersSearchCompany': 'Rechercher une entreprise',
      'careersSearchHint': 'Nom de l’entreprise',
      'careersSearch': 'Rechercher',
      'careersEmptyTitle': 'Aucun portail carrière',
      'careersEmptyBody': 'Aucune entreprise ne publie d’offre pour le moment.',
      'careersCompanyOffers': 'Offres de l’entreprise',
      'careersPartnerOffers': 'Offres partenaires',
      'careersNoOffersTitle': 'Aucune offre ouverte',
      'careersNoOffersBody': 'Ce portail n’a pas d’offre publiée actuellement.',
      'careersExternalOffer': 'Offre partenaire',
      'careersClosed': 'Offre clôturée',
      'careersCompany': 'Entreprise',
      'careersDepartment': 'Service',
      'careersLocation': 'Lieu',
      'careersContract': 'Contrat',
      'careersRemote': 'Télétravail',
      'careersSalary': 'Salaire',
      'careersClosesAt': 'Clôture',
      'careersSkills': 'Compétences',
      'careersDescription': 'Description du poste',
      'careersApply': 'Postuler',
      'careersApplyPartner': 'Postuler chez le partenaire',
      'careersApplyNotice':
          'Vos données sont transmises à l’entreprise pour l’examen de votre candidature.',
      'careersFirstName': 'Prénom',
      'careersLastName': 'Nom',
      'careersCoverLetter': 'Lettre de motivation (optionnel)',
      'careersResumeUrl': 'Lien vers votre CV (optionnel)',
      'careersResumeUrlHint': 'https://… (PDF, DOC ou DOCX)',
      'careersConsent':
          'J’accepte que mes données soient traitées pour l’examen de ma candidature.',
      'careersSubmitApplication': 'Envoyer ma candidature',
      'careersAppliedTitle': 'Candidature envoyée',
      'careersAppliedNotice':
          'Votre candidature a bien été enregistrée. Conservez votre e-mail : il vous permet de suivre son avancement.',
      'careersApplicationStatus': 'Statut',
      'careersJob': 'Poste',
      'careersAppliedAt': 'Envoyée le',
      'careersTrackHint':
          'Le suivi se fait avec l’adresse e-mail utilisée pour candidater.',
      'careersTrackNeedsCompany':
          'Choisissez d’abord une entreprise pour suivre vos candidatures.',
      'careersTrackTitle': 'Suivre mes candidatures',
      'careersTrack': 'Voir mes candidatures',
      'careersTrackEmptyTitle': 'Aucune candidature trouvée',
      'careersTrackEmptyBody':
          'Aucune candidature n’est associée à cette adresse pour cette entreprise.',
      'careersRetention': 'Durée de conservation',
      'careersDays': 'jours',

      // ── Verticale Pharmacie (pharmacy) — #9945 ──
      'pharmacyDirectoryNotice':
          'Seules les officines ayant activé leur publication apparaissent ici. Aucune vente en ligne de médicaments n’est proposée.',
      'pharmacySearch': 'Rechercher une officine',
      'pharmacySearchHint': 'Nom ou description',
      'pharmacyCity': 'Ville',
      'pharmacySearchCta': 'Rechercher',
      'pharmacyEmptyTitle': 'Aucune officine publiée',
      'pharmacyEmptyBody':
          'Aucune pharmacie n’a encore activé sa publication. Ce n’est pas une panne : l’annuaire se remplira dès qu’une officine s’inscrira.',
      'pharmacyDuty': 'Garde',
      'pharmacyOnDuty': 'De garde',
      'pharmacyRegularHours': 'Horaires normaux',
      'pharmacyServices': 'Services',
      'pharmacyContactUnavailable':
          'La prise de contact depuis l’app n’est pas encore disponible pour les pharmacies. Appelez l’officine directement.',
      'pharmacyBackToDirectory': 'Retour à l’annuaire',
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

      // ── Vertical directories (#9948) ──
      'journeyDirectoryTitle': 'Directory',
      'journeyDirectoryNotice':
          'Listed businesses voluntarily publish on the platform. An unpublished business does not appear here.',
      'journeyDirectorySearch': 'Search',
      'journeyDirectorySearchHint': 'Name, brand (optional)',
      'journeyDirectoryCity': 'City',
      'journeyDirectoryCityHint': 'City (optional)',
      'journeyDirectorySearchAction': 'Search',
      'journeyDirectoryCategory': 'Category',
      'journeyDirectoryRating': 'Rating',
      'journeyDirectoryDistance': 'Distance',
      'journeyDirectoryEmptyTitle': 'No published business',
      'journeyDirectoryEmptyBody':
          'No business in this category has enabled publishing yet.',
      'journeyDirectoryUnavailableTitle': 'Service temporarily unavailable',
      'journeyDirectoryUnavailableBody':
          'The directory did not respond. This is not an absence of business: please retry.',
      'journeyDirectoryUnknownTitle': 'Unknown vertical',
      'journeyDirectoryUnknownBody':
          'This category does not exist in this version of the application.',
      'journeyContactAction': 'Contact',
      'journeyBlockerContactGeneric':
          'Contact is not available yet for this category.',
      'journeyStatusLabel': 'Status',
      // ── Mobility vertical (#9948) ──
      'vtcOperatorsNotice':
          'These operators accept rides booked online. A ride is requested without an account and tracked by reference.',
      'vtcEmptyTitle': 'No operator available',
      'vtcEmptyBody': 'No mobility operator has enabled publishing yet.',
      'vtcCoordinatesNotice':
          'A quote needs precise coordinates: without them the distance and price would be wrong. Expected format: latitude,longitude.',
      'vtcCoordinatesHint': 'e.g. 33.5731,-7.5898',
      'vtcPickup': 'Pickup point',
      'vtcPickupAddress': 'Pickup address',
      'vtcDropoff': 'Drop-off point',
      'vtcDropoffAddress': 'Drop-off address',
      'vtcAddressHint': 'Readable address (optional)',
      'vtcEstimate': 'Get a quote',
      'vtcEstimatedPrice': 'Estimated price',
      'vtcDistance': 'Distance',
      'vtcPriceToConfirm': 'Price to be confirmed by the operator',
      'vtcRequestRide': 'Request the ride',
      'vtcRideRequested': 'Ride requested',
      'vtcTrackingCode': 'Tracking code',
      'vtcTrackingCodeNotice': 'Keep this code: it is shown only once.',
      'vtcTrackingCodeMissing':
          'The code is not shown again for an already submitted request. Use the reference and code you received.',
      'vtcInvalidPickup': 'Invalid pickup point (latitude,longitude format)',
      'vtcInvalidDropoff': 'Invalid drop-off point (latitude,longitude format)',
      'vtcMissingName': 'Passenger name is required',
      'vtcMissingPhone': 'Passenger phone is required',
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
      // Per-vertical transactional journeys (#9907).
      'journeyReasonRestaurantOrder':
          'Online ordering is not open to the public yet. Send a request and the restaurant will reply.',
      'journeyReasonTradeQuote':
          'A detailed quote is prepared with the company. Send a request and they will come back with an offer.',
      'journeyReasonVtcRide':
          'Booking a ride needs a pickup and a drop-off address. Send a request and the operator will call you back.',
      'journeyReasonTravelPortal':
          'Tickets are not reachable from this app yet.',
      'journeyReasonPharmacyCatalog':
          'The pharmacy catalogue is not published yet.',
      'journeyReasonCareersPortal':
          'Job offers are not reachable from this app yet.',
      'journeyRequestCta': 'Send a request',
      'journeyNativeCta': 'Order',

      // ── Billets vertical (travel) — #9943 ──
      'travelSearchNotice':
          'Trips come from partner agencies. The final price is confirmed by the server when you book.',
      'travelOrigin': 'Departure city',
      'travelDestination': 'Arrival city',
      'travelCityHint': 'City name',
      'travelDate': 'Date (optional)',
      'travelSearch': 'Search trips',
      'travelEmptyTitle': 'No trip matches',
      'travelEmptyBody': 'Try another date, or clear a city filter.',
      'travelRouteUnknown': 'Route not provided',
      'travelSoldOut': 'Sold out',
      'travelNoFare':
          'No published fare for this trip: booking is not open yet.',
      'travelDeparture': 'Departure',
      'travelArrival': 'Arrival',
      'travelAgency': 'Agency',
      'travelSeatsAvailable': 'Seats available',
      'travelSeatsFree': 'Free seats',
      'travelPrices': 'Fares',
      'travelClass': 'Class',
      'travelAdult': 'Adult',
      'travelChild': 'Child',
      'travelBook': 'Book',
      'travelPassengerNotice':
          'One name per passenger: a ticket without a name is not a ticket.',
      'travelPassengers': 'Passengers',
      'travelPassenger': 'Passenger',
      'travelAddPassenger': 'Add a passenger',
      'travelAgeCategory': 'Age category',
      'travelAge_adult': 'Adult',
      'travelAge_child': 'Child',
      'travelAge_infant': 'Infant',
      'travelNotifyConsent': 'Notify me if the schedule changes.',
      'travelSubmitBooking': 'Confirm booking',
      'travelValidationCode': 'Validation code',
      'travelExpiresAt': 'Confirm before',
      'travelKeepCodeNotice':
          'Keep the reference and validation code: they are required to track or cancel the booking.',

      'travelPaymentTitle': 'Online payment',
      'travelPaymentMethod': 'Payment method',
      'travelPayment_momo': 'Mobile money',
      'travelPayment_card': 'Bank card',
      'travelPayment_pvit': 'PVit',
      'travelPaymentNotice':
          'Payment is initiated with the agency. You will receive the operator confirmation on your phone.',
      'travelPayNow': 'Pay now',
      'travelPaymentInitiated': 'Payment initiated',
      'travelPaymentReference': 'Payment reference',
      'travelPaymentProviderReference': 'Operator reference',
      'travelPaymentPendingNotice':
          'The payment is awaiting confirmation. Keep the payment reference: it is how you find it again.',
      'travelPaymentWatching':
          'Watching the payment… the status updates automatically.',
      'travelPaymentSettled': 'Payment confirmed.',
      'travelPaymentWatchStopped':
          'Automatic tracking stopped without confirmation. The payment may still go through: check again later.',
      'travelPaymentWatchRetry': 'Check again',

      // ── Restaurant vertical — order by slug (#9959) ──
      'restaurantOrderTitle': 'Order',
      'restaurantOrderAction': 'Order online',
      'restaurantNotPublished':
          'This establishment is not published on the platform, so it cannot be ordered from here.',
      'restaurantNoMenu':
          'This establishment is published but has not put anything online yet. Check back later.',
      'restaurantCart': 'Cart',
      'restaurantGoToCart': 'View cart',
      'restaurantBackToMenu': 'Back to menu',
      'restaurantPriceOnRequest': 'Price confirmed by the establishment',
      'restaurantTotalOnRequest':
          'The total will be confirmed by the establishment: some items have no published price.',
      'restaurantOrderType': 'Order type',
      'restaurantOrderType_pickup': 'Takeaway',
      'restaurantOrderType_delivery': 'Delivery',
      'restaurantOrderNote': 'Note for the establishment',
      'restaurantSubmitOrder': 'Send the order',
      'restaurantKeepSecretNotice':
          'Keep the tracking code: it is shown only once and is how you find the order again.',
      'restaurantReplayNotice':
          'This order already existed: the tracking code is the one from the first creation.',
      'restaurantTrackingSecret': 'Tracking code',

      // ── Restaurant vertical — web parity (#9991..#9994) ──
      'restaurantMenuSearch': 'Search the menu',
      'restaurantMenuSearchHint': 'A dish, a drink…',
      'restaurantMenuAll': 'All',
      'restaurantMenuNoMatch':
          'No product matches this search. Try another word or pick “All”.',
      'restaurantOpenNow': 'Open now',
      'restaurantClosedNow': 'Currently closed',
      'restaurantHoursTitle': 'Opening hours',
      'restaurantClosedDay': 'Closed',
      'restaurantCuisinesLabel': 'Cuisines',
      'restaurantReviewsTitle': 'Customer reviews',
      'restaurantReviewsEmpty':
          'No published review yet. Reviews appear after the establishment moderates them.',
      'restaurantReviewsLoadMore': 'Show more reviews',
      'restaurantReviewYourRating': 'Your rating',
      'restaurantReviewComment': 'Your comment (optional)',
      'restaurantReviewSubmit': 'Publish my review',
      'restaurantReviewSent':
          'Thank you! Your review will be visible after the establishment moderates it.',
      'restaurantReviewAlready':
          'A review has already been submitted for this order. One review per order is accepted.',
      'restaurantReviewNotEligible':
          'This order has not been served yet: you can review it once the order is complete.',
      'restaurantReviewOrderRef': 'Order reference',
      'restaurantReviewReplyFrom': 'Reply from the establishment',
      'restaurantPayTitle': 'Payment',
      'restaurantPayCash': 'Cash on delivery',
      'restaurantPayMobileMoney': 'Mobile money',
      'restaurantPayAction': 'Pay the order',
      'restaurantPayDone': 'Payment recorded.',
      'restaurantPayCardUnavailable':
          'Card payment is not open yet for this order.',
      'restaurantTrackAuto': 'Tracking updates automatically.',
      'restaurantTrackStopped': 'Order complete: tracking is up to date.',
      'journeyDayMonday': 'Monday',
      'journeyDayTuesday': 'Tuesday',
      'journeyDayWednesday': 'Wednesday',
      'journeyDayThursday': 'Thursday',
      'journeyDayFriday': 'Friday',
      'journeyDaySaturday': 'Saturday',
      'journeyDaySunday': 'Sunday',

      // ── Careers vertical — #9944 ──
      'careersDirectoryNotice':
          'Jobs come from companies publishing on the platform. Some offers are aggregated from partners: you then apply on their site.',
      'careersSearchCompany': 'Search a company',
      'careersSearchHint': 'Company name',
      'careersSearch': 'Search',
      'careersEmptyTitle': 'No career portal',
      'careersEmptyBody': 'No company is publishing jobs right now.',
      'careersCompanyOffers': 'Company jobs',
      'careersPartnerOffers': 'Partner jobs',
      'careersNoOffersTitle': 'No open position',
      'careersNoOffersBody': 'This portal has no published job at the moment.',
      'careersExternalOffer': 'Partner offer',
      'careersClosed': 'Position closed',
      'careersCompany': 'Company',
      'careersDepartment': 'Department',
      'careersLocation': 'Location',
      'careersContract': 'Contract',
      'careersRemote': 'Remote',
      'careersSalary': 'Salary',
      'careersClosesAt': 'Closes',
      'careersSkills': 'Skills',
      'careersDescription': 'Job description',
      'careersApply': 'Apply',
      'careersApplyPartner': 'Apply on partner site',
      'careersApplyNotice':
          'Your data is sent to the company to review your application.',
      'careersFirstName': 'First name',
      'careersLastName': 'Last name',
      'careersCoverLetter': 'Cover letter (optional)',
      'careersResumeUrl': 'Link to your resume (optional)',
      'careersResumeUrlHint': 'https://… (PDF, DOC or DOCX)',
      'careersConsent':
          'I agree that my data is processed to review my application.',
      'careersSubmitApplication': 'Send my application',
      'careersAppliedTitle': 'Application sent',
      'careersAppliedNotice':
          'Your application has been recorded. Keep your email: it lets you track its progress.',
      'careersApplicationStatus': 'Status',
      'careersJob': 'Position',
      'careersAppliedAt': 'Sent on',
      'careersTrackHint': 'Tracking uses the email address you applied with.',
      'careersTrackNeedsCompany':
          'Pick a company first to track your applications.',
      'careersTrackTitle': 'Track my applications',
      'careersTrack': 'View my applications',
      'careersTrackEmptyTitle': 'No application found',
      'careersTrackEmptyBody':
          'No application is linked to this address for this company.',
      'careersRetention': 'Retention period',
      'careersDays': 'days',

      // ── Pharmacy vertical — #9945 ──
      'pharmacyDirectoryNotice':
          'Only pharmacies that enabled publication appear here. No online medicine sales are offered.',
      'pharmacySearch': 'Search a pharmacy',
      'pharmacySearchHint': 'Name or description',
      'pharmacyCity': 'City',
      'pharmacySearchCta': 'Search',
      'pharmacyEmptyTitle': 'No published pharmacy',
      'pharmacyEmptyBody':
          'No pharmacy has enabled publication yet. This is not a failure: the directory fills up as soon as one signs up.',
      'pharmacyDuty': 'On duty',
      'pharmacyOnDuty': 'On duty',
      'pharmacyRegularHours': 'Regular hours',
      'pharmacyServices': 'Services',
      'pharmacyContactUnavailable':
          'Contacting pharmacies from the app is not available yet. Call the pharmacy directly.',
      'pharmacyBackToDirectory': 'Back to the directory',
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

      // ── أدلة القطاعات (#9948) ──
      'journeyDirectoryTitle': 'الدليل',
      'journeyDirectoryNotice':
          'المنشآت المعروضة تنشر طوعًا على المنصة. المنشأة غير المنشورة لا تظهر هنا.',
      'journeyDirectorySearch': 'بحث',
      'journeyDirectorySearchHint': 'الاسم أو العلامة (اختياري)',
      'journeyDirectoryCity': 'المدينة',
      'journeyDirectoryCityHint': 'المدينة (اختياري)',
      'journeyDirectorySearchAction': 'بحث',
      'journeyDirectoryCategory': 'الفئة',
      'journeyDirectoryRating': 'التقييم',
      'journeyDirectoryDistance': 'المسافة',
      'journeyDirectoryEmptyTitle': 'لا توجد منشأة منشورة',
      'journeyDirectoryEmptyBody': 'لم تُفعّل أي منشأة في هذه الفئة النشر بعد.',
      'journeyDirectoryUnavailableTitle': 'الخدمة غير متاحة مؤقتًا',
      'journeyDirectoryUnavailableBody':
          'لم يستجب الدليل. هذا ليس غيابًا للمنشآت: أعد المحاولة.',
      'journeyDirectoryUnknownTitle': 'قطاع غير معروف',
      'journeyDirectoryUnknownBody':
          'هذه الفئة غير موجودة في هذا الإصدار من التطبيق.',
      'journeyContactAction': 'تواصل',
      'journeyBlockerContactGeneric': 'التواصل غير متاح بعد لهذه الفئة.',
      'journeyStatusLabel': 'الحالة',
      // ── قطاع النقل (#9948) ──
      'vtcOperatorsNotice':
          'هؤلاء المشغّلون يقبلون رحلات تُحجز عبر الإنترنت. تُطلب الرحلة دون حساب وتُتبع بالمرجع.',
      'vtcEmptyTitle': 'لا يوجد مشغّل متاح',
      'vtcEmptyBody': 'لم يُفعّل أي مشغّل نقل النشر بعد.',
      'vtcCoordinatesNotice':
          'يتطلب العرض إحداثيات دقيقة: بدونها ستكون المسافة والسعر خاطئين. الصيغة المطلوبة: خط العرض،خط الطول.',
      'vtcCoordinatesHint': 'مثال 33.5731,-7.5898',
      'vtcPickup': 'نقطة الانطلاق',
      'vtcPickupAddress': 'عنوان الانطلاق',
      'vtcDropoff': 'نقطة الوصول',
      'vtcDropoffAddress': 'عنوان الوصول',
      'vtcAddressHint': 'عنوان مقروء (اختياري)',
      'vtcEstimate': 'احصل على عرض سعر',
      'vtcEstimatedPrice': 'السعر التقديري',
      'vtcDistance': 'المسافة',
      'vtcPriceToConfirm': 'السعر يؤكده المشغّل',
      'vtcRequestRide': 'اطلب الرحلة',
      'vtcRideRequested': 'تم طلب الرحلة',
      'vtcTrackingCode': 'رمز التتبع',
      'vtcTrackingCodeNotice': 'احتفظ بهذا الرمز: يظهر مرة واحدة فقط.',
      'vtcTrackingCodeMissing':
          'لا يُعاد إظهار الرمز لطلب أُرسل مسبقًا. استخدم المرجع والرمز الذين تلقيتهما.',
      'vtcInvalidPickup': 'نقطة انطلاق غير صالحة (صيغة خط العرض،خط الطول)',
      'vtcInvalidDropoff': 'نقطة وصول غير صالحة (صيغة خط العرض،خط الطول)',
      'vtcMissingName': 'اسم المسافر مطلوب',
      'vtcMissingPhone': 'هاتف المسافر مطلوب',
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
      // مسارات المعاملات لكل قطاع (#9907).
      'journeyReasonRestaurantOrder':
          'الطلب عبر الإنترنت غير متاح للعموم بعد. أرسل طلبك وسيرد عليك المطعم.',
      'journeyReasonTradeQuote':
          'يُعدّ عرض السعر المفصل مع الشركة. أرسل طلبك وستعود إليك بعرض.',
      'journeyReasonVtcRide':
          'حجز رحلة يتطلب عنوان الانطلاق والوصول. أرسل طلبك وسيتصل بك المشغّل.',
      'journeyReasonTravelPortal': 'التذاكر غير متاحة من هذا التطبيق بعد.',
      'journeyReasonPharmacyCatalog': 'دليل الصيدليات لم يُنشر بعد.',
      'journeyReasonCareersPortal': 'عروض العمل غير متاحة من هذا التطبيق بعد.',
      'journeyRequestCta': 'أرسل طلبًا',
      'journeyNativeCta': 'اطلب',

      // ── قطاع التذاكر (travel) — #9943 ──
      'travelSearchNotice':
          'الرحلات تأتي من وكالات شريكة. يؤكد الخادم السعر النهائي عند الحجز.',
      'travelOrigin': 'مدينة الانطلاق',
      'travelDestination': 'مدينة الوصول',
      'travelCityHint': 'اسم المدينة',
      'travelDate': 'التاريخ (اختياري)',
      'travelSearch': 'ابحث عن رحلات',
      'travelEmptyTitle': 'لا توجد رحلة مطابقة',
      'travelEmptyBody': 'جرّب تاريخًا آخر أو أزل فلتر المدينة.',
      'travelRouteUnknown': 'المسار غير متوفر',
      'travelSoldOut': 'مكتمل',
      'travelNoFare': 'لا يوجد سعر منشور لهذه الرحلة: الحجز غير مفتوح بعد.',
      'travelDeparture': 'الانطلاق',
      'travelArrival': 'الوصول',
      'travelAgency': 'الوكالة',
      'travelSeatsAvailable': 'المقاعد المتاحة',
      'travelSeatsFree': 'المقاعد الشاغرة',
      'travelPrices': 'الأسعار',
      'travelClass': 'الدرجة',
      'travelAdult': 'بالغ',
      'travelChild': 'طفل',
      'travelBook': 'احجز',
      'travelPassengerNotice': 'اسم لكل مسافر: التذكرة بلا اسم ليست تذكرة.',
      'travelPassengers': 'المسافرون',
      'travelPassenger': 'مسافر',
      'travelAddPassenger': 'أضف مسافرًا',
      'travelAgeCategory': 'الفئة العمرية',
      'travelAge_adult': 'بالغ',
      'travelAge_child': 'طفل',
      'travelAge_infant': 'رضيع',
      'travelNotifyConsent': 'أبلغوني في حال تغيّر الموعد.',
      'travelSubmitBooking': 'تأكيد الحجز',
      'travelValidationCode': 'رمز التحقق',
      'travelExpiresAt': 'أكّد قبل',
      'travelKeepCodeNotice':
          'احتفظ بالمرجع ورمز التحقق: يلزمك لتتبع الحجز أو إلغائه.',

      'travelPaymentTitle': 'الدفع عبر الإنترنت',
      'travelPaymentMethod': 'طريقة الدفع',
      'travelPayment_momo': 'المحفظة الإلكترونية',
      'travelPayment_card': 'بطاقة بنكية',
      'travelPayment_pvit': 'PVit',
      'travelPaymentNotice':
          'يتم بدء الدفع لدى الوكالة. ستتلقى تأكيد المشغّل على هاتفك.',
      'travelPayNow': 'ادفع الآن',
      'travelPaymentInitiated': 'تم بدء الدفع',
      'travelPaymentReference': 'مرجع الدفع',
      'travelPaymentProviderReference': 'مرجع المشغّل',
      'travelPaymentPendingNotice':
          'الدفع في انتظار التأكيد. احتفظ بمرجع الدفع للعثور عليه لاحقًا.',
      'travelPaymentWatching': 'جارٍ متابعة الدفع… تتحدّث الحالة تلقائيًا.',
      'travelPaymentSettled': 'تم تأكيد الدفع.',
      'travelPaymentWatchStopped':
          'توقّفت المتابعة التلقائية دون تأكيد. قد يكتمل الدفع: تحقّق لاحقًا.',
      'travelPaymentWatchRetry': 'تحقّق مرة أخرى',

      // ── قطاع المطاعم — الطلب بالمعرّف (#9959) ──
      'restaurantOrderTitle': 'اطلب',
      'restaurantOrderAction': 'اطلب عبر الإنترنت',
      'restaurantNotPublished':
          'هذا المطعم غير منشور على المنصة، لذلك لا يمكن الطلب منه هنا.',
      'restaurantNoMenu':
          'هذا المطعم منشور لكنه لم ينشر أي منتج بعد. عُد لاحقًا.',
      'restaurantCart': 'السلة',
      'restaurantGoToCart': 'عرض السلة',
      'restaurantBackToMenu': 'العودة إلى القائمة',
      'restaurantPriceOnRequest': 'السعر يؤكده المطعم',
      'restaurantTotalOnRequest':
          'سيؤكد المطعم المجموع: بعض المنتجات ليس لها سعر منشور.',
      'restaurantOrderType': 'نوع الطلب',
      'restaurantOrderType_pickup': 'للتحضير',
      'restaurantOrderType_delivery': 'توصيل',
      'restaurantOrderNote': 'ملاحظة للمطعم',
      'restaurantSubmitOrder': 'إرسال الطلب',
      'restaurantKeepSecretNotice':
          'احتفظ برمز التتبع: يُعرض مرة واحدة فقط وهو وسيلة العثور على الطلب.',
      'restaurantReplayNotice':
          'هذا الطلب موجود مسبقًا: رمز التتبع هو نفسه من الإنشاء الأول.',
      'restaurantTrackingSecret': 'رمز التتبع',

      // ── قطاع المطاعم — تكافؤ مع الويب (#9991..#9994) ──
      'restaurantMenuSearch': 'ابحث في القائمة',
      'restaurantMenuSearchHint': 'طبق، مشروب…',
      'restaurantMenuAll': 'الكل',
      'restaurantMenuNoMatch':
          'لا يوجد منتج يطابق هذا البحث. جرّب كلمة أخرى أو اختر «الكل».',
      'restaurantOpenNow': 'مفتوح الآن',
      'restaurantClosedNow': 'مغلق حاليًا',
      'restaurantHoursTitle': 'أوقات العمل',
      'restaurantClosedDay': 'مغلق',
      'restaurantCuisinesLabel': 'المطابخ',
      'restaurantReviewsTitle': 'تقييمات العملاء',
      'restaurantReviewsEmpty':
          'لا توجد تقييمات منشورة بعد. تُنشر التقييمات بعد مراجعة المطعم لها.',
      'restaurantReviewsLoadMore': 'عرض المزيد من التقييمات',
      'restaurantReviewYourRating': 'تقييمك',
      'restaurantReviewComment': 'تعليقك (اختياري)',
      'restaurantReviewSubmit': 'نشر تقييمي',
      'restaurantReviewSent':
          'شكرًا! سيظهر تقييمك بعد مراجعة المطعم له.',
      'restaurantReviewAlready':
          'تم إرسال تقييم لهذا الطلب من قبل. يُقبل تقييم واحد لكل طلب.',
      'restaurantReviewNotEligible':
          'لم يتم تقديم هذا الطلب بعد: يمكنك التقييم بعد اكتمال الطلب.',
      'restaurantReviewOrderRef': 'مرجع الطلب',
      'restaurantReviewReplyFrom': 'رد المطعم',
      'restaurantPayTitle': 'الدفع',
      'restaurantPayCash': 'نقدًا عند الاستلام',
      'restaurantPayMobileMoney': 'المحفظة الإلكترونية',
      'restaurantPayAction': 'دفع الطلب',
      'restaurantPayDone': 'تم تسجيل الدفع.',
      'restaurantPayCardUnavailable':
          'الدفع بالبطاقة غير متاح بعد لهذا الطلب.',
      'restaurantTrackAuto': 'يتحدّث التتبع تلقائيًا.',
      'restaurantTrackStopped': 'اكتمل الطلب: التتبع محدّث.',
      'journeyDayMonday': 'الاثنين',
      'journeyDayTuesday': 'الثلاثاء',
      'journeyDayWednesday': 'الأربعاء',
      'journeyDayThursday': 'الخميس',
      'journeyDayFriday': 'الجمعة',
      'journeyDaySaturday': 'السبت',
      'journeyDaySunday': 'الأحد',

      // ── قطاع التوظيف (careers) — #9944 ──
      'careersDirectoryNotice':
          'الوظائف تأتي من الشركات المنشورة على المنصة. بعض العروض مجمّعة من شركاء: التقديم يتم عندهم.',
      'careersSearchCompany': 'ابحث عن شركة',
      'careersSearchHint': 'اسم الشركة',
      'careersSearch': 'بحث',
      'careersEmptyTitle': 'لا يوجد بوابة توظيف',
      'careersEmptyBody': 'لا توجد شركة تنشر وظائف حاليًا.',
      'careersCompanyOffers': 'وظائف الشركة',
      'careersPartnerOffers': 'وظائف الشركاء',
      'careersNoOffersTitle': 'لا وظيفة مفتوحة',
      'careersNoOffersBody': 'لا توجد وظيفة منشورة في هذه البوابة حاليًا.',
      'careersExternalOffer': 'عرض شريك',
      'careersClosed': 'الوظيفة مغلقة',
      'careersCompany': 'الشركة',
      'careersDepartment': 'القسم',
      'careersLocation': 'المكان',
      'careersContract': 'نوع العقد',
      'careersRemote': 'عن بُعد',
      'careersSalary': 'الراتب',
      'careersClosesAt': 'يُغلق في',
      'careersSkills': 'المهارات',
      'careersDescription': 'وصف الوظيفة',
      'careersApply': 'تقدّم',
      'careersApplyPartner': 'تقدّم عند الشريك',
      'careersApplyNotice': 'تُرسل بياناتك إلى الشركة لدراسة ترشيحك.',
      'careersFirstName': 'الاسم الشخصي',
      'careersLastName': 'الاسم العائلي',
      'careersCoverLetter': 'رسالة تحفيزية (اختياري)',
      'careersResumeUrl': 'رابط سيرتك الذاتية (اختياري)',
      'careersResumeUrlHint': 'https://… (PDF أو DOC أو DOCX)',
      'careersConsent': 'أوافق على معالجة بياناتي لدراسة ترشيحي.',
      'careersSubmitApplication': 'أرسل ترشيحي',
      'careersAppliedTitle': 'تم إرسال الترشيح',
      'careersAppliedNotice':
          'تم تسجيل ترشيحك. احتفظ ببريدك الإلكتروني: به تتابع تقدمه.',
      'careersApplicationStatus': 'الحالة',
      'careersJob': 'الوظيفة',
      'careersAppliedAt': 'أُرسل في',
      'careersTrackHint': 'يتم التتبع بالبريد الإلكتروني المستخدم في الترشيح.',
      'careersTrackNeedsCompany': 'اختر شركة أولًا لتتبع ترشيحاتك.',
      'careersTrackTitle': 'تتبع ترشيحاتي',
      'careersTrack': 'عرض ترشيحاتي',
      'careersTrackEmptyTitle': 'لا ترشيح موجود',
      'careersTrackEmptyBody': 'لا يوجد ترشيح مرتبط بهذا العنوان لهذه الشركة.',
      'careersRetention': 'مدة الاحتفاظ',
      'careersDays': 'يومًا',

      // ── قطاع الصيدلة (pharmacy) — #9945 ──
      'pharmacyDirectoryNotice':
          'تظهر هنا فقط الصيدليات التي فعّلت النشر. لا يُعرض بيع الأدوية عبر الإنترنت.',
      'pharmacySearch': 'ابحث عن صيدلية',
      'pharmacySearchHint': 'الاسم أو الوصف',
      'pharmacyCity': 'المدينة',
      'pharmacySearchCta': 'بحث',
      'pharmacyEmptyTitle': 'لا صيدلية منشورة',
      'pharmacyEmptyBody':
          'لم تفعّل أي صيدلية النشر بعد. هذا ليس عطلًا: يمتلئ الدليل بمجرد تسجيل صيدلية.',
      'pharmacyDuty': 'المداومة',
      'pharmacyOnDuty': 'في المداومة',
      'pharmacyRegularHours': 'التوقيت العادي',
      'pharmacyServices': 'الخدمات',
      'pharmacyContactUnavailable':
          'التواصل مع الصيدليات من التطبيق غير متاح بعد. اتصل بالصيدلية مباشرة.',
      'pharmacyBackToDirectory': 'العودة إلى الدليل',
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

      // ── Dikey dizinler (#9948) ──
      'journeyDirectoryTitle': 'Dizin',
      'journeyDirectoryNotice':
          'Listelenen işletmeler platformda gönüllü olarak yayın yapar. Yayınlanmayan işletme burada görünmez.',
      'journeyDirectorySearch': 'Ara',
      'journeyDirectorySearchHint': 'Ad, marka (isteğe bağlı)',
      'journeyDirectoryCity': 'Şehir',
      'journeyDirectoryCityHint': 'Şehir (isteğe bağlı)',
      'journeyDirectorySearchAction': 'Ara',
      'journeyDirectoryCategory': 'Kategori',
      'journeyDirectoryRating': 'Puan',
      'journeyDirectoryDistance': 'Mesafe',
      'journeyDirectoryEmptyTitle': 'Yayınlanmış işletme yok',
      'journeyDirectoryEmptyBody':
          'Bu kategoride henüz yayını etkinleştiren işletme yok.',
      'journeyDirectoryUnavailableTitle': 'Hizmet geçici olarak kullanılamıyor',
      'journeyDirectoryUnavailableBody':
          'Dizin yanıt vermedi. Bu bir işletme yokluğu değildir: tekrar deneyin.',
      'journeyDirectoryUnknownTitle': 'Bilinmeyen dikey',
      'journeyDirectoryUnknownBody':
          'Bu kategori uygulamanın bu sürümünde yok.',
      'journeyContactAction': 'İletişim',
      'journeyBlockerContactGeneric':
          'İletişim bu kategori için henüz kullanılamıyor.',
      'journeyStatusLabel': 'Durum',
      // ── Mobilite dikeyi (#9948) ──
      'vtcOperatorsNotice':
          'Bu operatörler çevrimiçi rezerve edilen yolculukları kabul eder. Yolculuk hesapsız istenir ve referansla izlenir.',
      'vtcEmptyTitle': 'Kullanılabilir operatör yok',
      'vtcEmptyBody': 'Henüz yayınını etkinleştiren mobilite operatörü yok.',
      'vtcCoordinatesNotice':
          'Teklif kesin koordinat gerektirir: onlar olmadan mesafe ve fiyat yanlış olur. Beklenen biçim: enlem,boylam.',
      'vtcCoordinatesHint': 'örn. 33.5731,-7.5898',
      'vtcPickup': 'Kalkış noktası',
      'vtcPickupAddress': 'Kalkış adresi',
      'vtcDropoff': 'Varış noktası',
      'vtcDropoffAddress': 'Varış adresi',
      'vtcAddressHint': 'Okunabilir adres (isteğe bağlı)',
      'vtcEstimate': 'Teklif al',
      'vtcEstimatedPrice': 'Tahmini fiyat',
      'vtcDistance': 'Mesafe',
      'vtcPriceToConfirm': 'Fiyatı operatör onaylayacak',
      'vtcRequestRide': 'Yolculuk iste',
      'vtcRideRequested': 'Yolculuk istendi',
      'vtcTrackingCode': 'Takip kodu',
      'vtcTrackingCodeNotice': 'Bu kodu saklayın: yalnızca bir kez gösterilir.',
      'vtcTrackingCodeMissing':
          'Kod, zaten gönderilmiş bir talep için yeniden gösterilmez. Aldığınız referansı ve kodu kullanın.',
      'vtcInvalidPickup': 'Geçersiz kalkış noktası (enlem,boylam biçimi)',
      'vtcInvalidDropoff': 'Geçersiz varış noktası (enlem,boylam biçimi)',
      'vtcMissingName': 'Yolcu adı gerekli',
      'vtcMissingPhone': 'Yolcu telefonu gerekli',
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
      // Dikey başına işlem akışları (#9907).
      'journeyReasonRestaurantOrder':
          'Çevrimiçi sipariş henüz herkese açık değil. Talebinizi gönderin, restoran size dönsün.',
      'journeyReasonTradeQuote':
          'Ayrıntılı teklif şirketle birlikte hazırlanır. Talebinizi gönderin, size bir öneriyle dönsünler.',
      'journeyReasonVtcRide':
          'Yolculuk rezervasyonu kalkış ve varış adresi gerektirir. Talebinizi gönderin, operatör sizi arasın.',
      'journeyReasonTravelPortal':
          'Biletlere bu uygulamadan henüz ulaşılamıyor.',
      'journeyReasonPharmacyCatalog': 'Eczane kataloğu henüz yayımlanmadı.',
      'journeyReasonCareersPortal':
          'İş ilanlarına bu uygulamadan henüz ulaşılamıyor.',
      'journeyRequestCta': 'Talep gönder',
      'journeyNativeCta': 'Sipariş ver',

      // ── Bilet dikeyi (travel) — #9943 ──
      'travelSearchNotice':
          'Seferler iş ortağı acentelerden gelir. Nihai fiyat rezervasyon sırasında sunucu tarafından onaylanır.',
      'travelOrigin': 'Kalkış şehri',
      'travelDestination': 'Varış şehri',
      'travelCityHint': 'Şehir adı',
      'travelDate': 'Tarih (isteğe bağlı)',
      'travelSearch': 'Sefer ara',
      'travelEmptyTitle': 'Eşleşen sefer yok',
      'travelEmptyBody':
          'Başka bir tarih deneyin veya şehir filtresini kaldırın.',
      'travelRouteUnknown': 'Güzergâh belirtilmemiş',
      'travelSoldOut': 'Dolu',
      'travelNoFare':
          'Bu sefer için yayınlanmış ücret yok: rezervasyon henüz açık değil.',
      'travelDeparture': 'Kalkış',
      'travelArrival': 'Varış',
      'travelAgency': 'Acente',
      'travelSeatsAvailable': 'Mevcut koltuk',
      'travelSeatsFree': 'Boş koltuk',
      'travelPrices': 'Ücretler',
      'travelClass': 'Sınıf',
      'travelAdult': 'Yetişkin',
      'travelChild': 'Çocuk',
      'travelBook': 'Rezervasyon yap',
      'travelPassengerNotice':
          'Her yolcu için bir ad: adsız bilet bilet değildir.',
      'travelPassengers': 'Yolcular',
      'travelPassenger': 'Yolcu',
      'travelAddPassenger': 'Yolcu ekle',
      'travelAgeCategory': 'Yaş kategorisi',
      'travelAge_adult': 'Yetişkin',
      'travelAge_child': 'Çocuk',
      'travelAge_infant': 'Bebek',
      'travelNotifyConsent': 'Saat değişirse bilgilendirilmek istiyorum.',
      'travelSubmitBooking': 'Rezervasyonu onayla',
      'travelValidationCode': 'Doğrulama kodu',
      'travelExpiresAt': 'Şu tarihe kadar onayla',
      'travelKeepCodeNotice':
          'Referansı ve doğrulama kodunu saklayın: takip veya iptal için gereklidir.',

      'travelPaymentTitle': 'Çevrimiçi ödeme',
      'travelPaymentMethod': 'Ödeme yöntemi',
      'travelPayment_momo': 'Mobil ödeme',
      'travelPayment_card': 'Banka kartı',
      'travelPayment_pvit': 'PVit',
      'travelPaymentNotice':
          'Ödeme acente nezdinde başlatılır. Operatör onayını telefonunuza alacaksınız.',
      'travelPayNow': 'Şimdi öde',
      'travelPaymentInitiated': 'Ödeme başlatıldı',
      'travelPaymentReference': 'Ödeme referansı',
      'travelPaymentProviderReference': 'Operatör referansı',
      'travelPaymentPendingNotice':
          'Ödeme onay bekliyor. Ödeme referansını saklayın: onu bulmanın yolu budur.',
      'travelPaymentWatching':
          'Ödeme izleniyor… durum otomatik olarak güncellenir.',
      'travelPaymentSettled': 'Ödeme onaylandı.',
      'travelPaymentWatchStopped':
          'Otomatik izleme onay olmadan durdu. Ödeme yine de tamamlanabilir: daha sonra kontrol edin.',
      'travelPaymentWatchRetry': 'Yeniden kontrol et',

      // ── Restoran dikeyi — slug ile sipariş (#9959) ──
      'restaurantOrderTitle': 'Sipariş ver',
      'restaurantOrderAction': 'Çevrimiçi sipariş ver',
      'restaurantNotPublished':
          'Bu işletme platformda yayınlanmamış, buradan sipariş verilemez.',
      'restaurantNoMenu':
          'Bu işletme yayınlanmış ancak henüz bir ürün koymamış. Daha sonra tekrar bakın.',
      'restaurantCart': 'Sepet',
      'restaurantGoToCart': 'Sepeti gör',
      'restaurantBackToMenu': 'Menüye dön',
      'restaurantPriceOnRequest': 'Fiyatı işletme onaylar',
      'restaurantTotalOnRequest':
          'Toplamı işletme onaylayacak: bazı ürünlerin yayınlanmış fiyatı yok.',
      'restaurantOrderType': 'Sipariş türü',
      'restaurantOrderType_pickup': 'Gel al',
      'restaurantOrderType_delivery': 'Teslimat',
      'restaurantOrderNote': 'İşletmeye not',
      'restaurantSubmitOrder': 'Siparişi gönder',
      'restaurantKeepSecretNotice':
          'Takip kodunu saklayın: yalnızca bir kez gösterilir ve siparişi bulmanın yolu budur.',
      'restaurantReplayNotice':
          'Bu sipariş zaten vardı: takip kodu ilk oluşturmadaki koddur.',
      'restaurantTrackingSecret': 'Takip kodu',

      // ── Restoran dikeyi — web ile eşlik (#9991..#9994) ──
      'restaurantMenuSearch': 'Menüde ara',
      'restaurantMenuSearchHint': 'Bir yemek, bir içecek…',
      'restaurantMenuAll': 'Tümü',
      'restaurantMenuNoMatch':
          'Bu aramayla eşleşen ürün yok. Başka bir kelime deneyin veya «Tümü»nü seçin.',
      'restaurantOpenNow': 'Şu anda açık',
      'restaurantClosedNow': 'Şu anda kapalı',
      'restaurantHoursTitle': 'Çalışma saatleri',
      'restaurantClosedDay': 'Kapalı',
      'restaurantCuisinesLabel': 'Mutfaklar',
      'restaurantReviewsTitle': 'Müşteri yorumları',
      'restaurantReviewsEmpty':
          'Henüz yayınlanmış yorum yok. Yorumlar işletmenin denetiminden sonra yayınlanır.',
      'restaurantReviewsLoadMore': 'Daha fazla yorum göster',
      'restaurantReviewYourRating': 'Puanınız',
      'restaurantReviewComment': 'Yorumunuz (isteğe bağlı)',
      'restaurantReviewSubmit': 'Yorumumu yayınla',
      'restaurantReviewSent':
          'Teşekkürler! Yorumunuz işletmenin denetiminden sonra görünür olacak.',
      'restaurantReviewAlready':
          'Bu sipariş için zaten bir yorum gönderildi. Sipariş başına bir yorum kabul edilir.',
      'restaurantReviewNotEligible':
          'Bu sipariş henüz servis edilmedi: sipariş tamamlandığında yorum yapabilirsiniz.',
      'restaurantReviewOrderRef': 'Sipariş referansı',
      'restaurantReviewReplyFrom': 'İşletmenin yanıtı',
      'restaurantPayTitle': 'Ödeme',
      'restaurantPayCash': 'Teslimatta nakit',
      'restaurantPayMobileMoney': 'Mobil ödeme',
      'restaurantPayAction': 'Siparişi öde',
      'restaurantPayDone': 'Ödeme kaydedildi.',
      'restaurantPayCardUnavailable':
          'Bu sipariş için kartla ödeme henüz açık değil.',
      'restaurantTrackAuto': 'Takip otomatik olarak güncellenir.',
      'restaurantTrackStopped': 'Sipariş tamamlandı: takip güncel.',
      'journeyDayMonday': 'Pazartesi',
      'journeyDayTuesday': 'Salı',
      'journeyDayWednesday': 'Çarşamba',
      'journeyDayThursday': 'Perşembe',
      'journeyDayFriday': 'Cuma',
      'journeyDaySaturday': 'Cumartesi',
      'journeyDaySunday': 'Pazar',

      // ── İşe alım dikeyi (careers) — #9944 ──
      'careersDirectoryNotice':
          'İlanlar platformda yayın yapan şirketlerden gelir. Bazı ilanlar iş ortaklarından derlenmiştir: başvuru onların sitesinde yapılır.',
      'careersSearchCompany': 'Şirket ara',
      'careersSearchHint': 'Şirket adı',
      'careersSearch': 'Ara',
      'careersEmptyTitle': 'Kariyer portalı yok',
      'careersEmptyBody': 'Şu anda ilan yayınlayan şirket yok.',
      'careersCompanyOffers': 'Şirket ilanları',
      'careersPartnerOffers': 'Partner ilanları',
      'careersNoOffersTitle': 'Açık pozisyon yok',
      'careersNoOffersBody': 'Bu portalda şu anda yayınlanmış ilan yok.',
      'careersExternalOffer': 'Partner ilanı',
      'careersClosed': 'İlan kapandı',
      'careersCompany': 'Şirket',
      'careersDepartment': 'Departman',
      'careersLocation': 'Konum',
      'careersContract': 'Sözleşme',
      'careersRemote': 'Uzaktan',
      'careersSalary': 'Maaş',
      'careersClosesAt': 'Kapanış',
      'careersSkills': 'Beceriler',
      'careersDescription': 'İş tanımı',
      'careersApply': 'Başvur',
      'careersApplyPartner': 'Partner sitesinde başvur',
      'careersApplyNotice':
          'Verileriniz başvurunuzu değerlendirmek için şirkete iletilir.',
      'careersFirstName': 'Ad',
      'careersLastName': 'Soyad',
      'careersCoverLetter': 'Ön yazı (isteğe bağlı)',
      'careersResumeUrl': 'CV bağlantınız (isteğe bağlı)',
      'careersResumeUrlHint': 'https://… (PDF, DOC veya DOCX)',
      'careersConsent':
          'Verilerimin başvurumu değerlendirmek için işlenmesini kabul ediyorum.',
      'careersSubmitApplication': 'Başvurumu gönder',
      'careersAppliedTitle': 'Başvuru gönderildi',
      'careersAppliedNotice':
          'Başvurunuz kaydedildi. E-postanızı saklayın: ilerlemesini onunla takip edersiniz.',
      'careersApplicationStatus': 'Durum',
      'careersJob': 'Pozisyon',
      'careersAppliedAt': 'Gönderim tarihi',
      'careersTrackHint': 'Takip, başvuruda kullandığınız e-posta ile yapılır.',
      'careersTrackNeedsCompany':
          'Başvurularınızı takip etmek için önce bir şirket seçin.',
      'careersTrackTitle': 'Başvurularımı takip et',
      'careersTrack': 'Başvurularımı gör',
      'careersTrackEmptyTitle': 'Başvuru bulunamadı',
      'careersTrackEmptyBody': 'Bu şirket için bu adrese bağlı başvuru yok.',
      'careersRetention': 'Saklama süresi',
      'careersDays': 'gün',

      // ── Eczane dikeyi (pharmacy) — #9945 ──
      'pharmacyDirectoryNotice':
          'Burada yalnızca yayını etkinleştiren eczaneler görünür. Çevrimiçi ilaç satışı sunulmaz.',
      'pharmacySearch': 'Eczane ara',
      'pharmacySearchHint': 'Ad veya açıklama',
      'pharmacyCity': 'Şehir',
      'pharmacySearchCta': 'Ara',
      'pharmacyEmptyTitle': 'Yayınlanmış eczane yok',
      'pharmacyEmptyBody':
          'Henüz hiçbir eczane yayını etkinleştirmedi. Bu bir arıza değil: bir eczane kaydolduğunda dizin dolar.',
      'pharmacyDuty': 'Nöbet',
      'pharmacyOnDuty': 'Nöbette',
      'pharmacyRegularHours': 'Normal saatler',
      'pharmacyServices': 'Hizmetler',
      'pharmacyContactUnavailable':
          'Eczanelerle uygulamadan iletişim henüz kullanılamıyor. Eczaneyi doğrudan arayın.',
      'pharmacyBackToDirectory': 'Dizine dön',
    },
  };
}
