/// Registre des 8 verticales de la surface publique et de leur parcours.
///
/// ## Pourquoi un registre et pas une liste dans un écran
///
/// Le 2026-11-23 (#9883) a montré qu'une app qui se vérifie ISOLEMENT ne voit
/// jamais ce qui lui manque : elle analyse, elle teste, et elle est incomplète.
/// Ici le même défaut prendrait la forme « sept verticales sur huit ont un
/// bouton, la huitième n'en a pas et personne ne le sait ». Le registre rend
/// l'oubli mesurable : une verticale doit y figurer, et chaque capacité absente
/// doit porter un **motif textuel** (une clé i18n) plutôt que disparaître.
///
/// ## Les trois états, et ce qu'ils veulent dire
///
/// - [JourneyMode.order] / [JourneyMode.booking] : l'app sait faire le parcours
///   de bout en bout, avec un contrat public **sans jeton ni compte**.
/// - [JourneyMode.request] : l'app sait envoyer une demande via
///   `POST /public/contact-requests` (le seul contrat universel côté serveur).
/// - [JourneyMode.blocked] : aucun contrat public appelable. L'écran doit dire
///   POURQUOI (clé de [blockers]) au lieu d'afficher un bouton mort.
///
/// ## Ce que le registre ne décide PAS
///
/// Il ne déclare jamais qu'une capacité existe parce qu'elle « devrait »
/// exister. Les modes ci-dessous sont mesurés sur `api/routes` (voir issue
/// #9907) : `restaurant` a une boutique publique mais derrière un jeton par
/// tenant, `pharmacy` n'a ni catalogue ni resolver dans
/// `PublicProfileLocator::types()`, `travel` et `careers` n'ont aucun provider
/// `public.discoverable` donc aucune entrée depuis la découverte.
library;

/// Nature du parcours disponible depuis l'application grand public.
enum JourneyMode {
  /// Commande native (boutiques).
  order,

  /// Réservation native (séjours).
  booking,

  /// Demande native via la passerelle de contact (devis, question, rappel).
  request,

  /// Aucun contrat public appelable : l'écran explique le blocage.
  blocked,
}

/// Une capacité absente, avec le motif exact à afficher.
class JourneyBlocker {
  const JourneyBlocker({required this.capability, required this.reasonKey});

  /// Capacité concernée (`order`, `quote`, `ride`, `catalog`, `contact`, `entry`).
  final String capability;

  /// Clé i18n du motif — vérifiée par `test/journey_registry_test.dart`.
  final String reasonKey;
}

/// Une verticale, son contrat et ce qui manque.
class JourneyVertical {
  const JourneyVertical({
    required this.code,
    required this.labelKey,
    required this.counterpartPath,
    required this.mode,
    this.discoveryType,
    this.contactTargetType,
    this.blockers = const [],
  });

  /// Code stable (`restaurant`, `retail`, …).
  final String code;

  /// Clé i18n du nom de la verticale.
  final String labelKey;

  /// Contrepartie web, pour la cartographie (jamais utilisée pour naviguer).
  final String counterpartPath;

  final JourneyMode mode;

  /// Type renvoyé par `GET /public/discover` (`meta.actions`), ou `null` si la
  /// verticale n'est pas exposée par la découverte.
  final String? discoveryType;

  /// Type accepté par `POST /public/contact-requests` ; `null` quand aucun
  /// resolver public n'existe (une demande serait refusée en 422).
  final String? contactTargetType;

  final List<JourneyBlocker> blockers;

  bool get hasNativeOrder => mode == JourneyMode.order;

  bool get hasNativeBooking => mode == JourneyMode.booking;

  bool get canSendRequest => contactTargetType != null;

  /// Actions de fiche réellement servies par un écran natif.
  Set<String> get nativeActions => {
        if (hasNativeOrder) 'order',
        if (hasNativeBooking) 'booking',
      };

  /// Motif d'une capacité bloquée, ou `null` si elle ne l'est pas.
  String? blockerFor(String capability) {
    for (final blocker in blockers) {
      if (blocker.capability == capability) return blocker.reasonKey;
    }
    return null;
  }
}

/// Les 8 verticales du grand public, dans l'ordre de la cartographie mobile.
///
/// Cet ordre est celui de `docs/mobile/CARTOGRAPHIE_APPS_MOBILES.md` §2 : une
/// verticale ajoutée en haut du tableau doit être ajoutée ici, et le test
/// `journey_registry_test.dart` refuse un registre incomplet.
const List<String> canonicalJourneyCodes = [
  'restaurant',
  'retail',
  'hospitality',
  'trade',
  'pharmacy',
  'vtc',
  'travel',
  'careers',
];

const List<JourneyVertical> journeyVerticals = [
  JourneyVertical(
    code: 'restaurant',
    labelKey: 'journeyVerticalRestaurant',
    counterpartPath: '/restaurants',
    mode: JourneyMode.request,
    discoveryType: 'restaurant',
    contactTargetType: 'restaurant',
    blockers: [
      // La boutique publique existe (`/public/restaurant/shop/*`) mais exige
      // `X-Restaurant-Shop-Token`, un jeton par tenant émis au back-office :
      // l'exposer au grand public est une décision de sécurité serveur.
      JourneyBlocker(
        capability: 'order',
        reasonKey: 'journeyBlockerRestaurantOrder',
      ),
    ],
  ),
  JourneyVertical(
    code: 'retail',
    labelKey: 'journeyVerticalRetail',
    counterpartPath: '/boutiques',
    mode: JourneyMode.order,
    discoveryType: 'retail_shop',
    contactTargetType: 'retail_shop',
  ),
  JourneyVertical(
    code: 'hospitality',
    labelKey: 'journeyVerticalHospitality',
    counterpartPath: '/stay',
    mode: JourneyMode.booking,
    discoveryType: 'hospitality_property',
    contactTargetType: 'hospitality_property',
  ),
  JourneyVertical(
    code: 'trade',
    labelKey: 'journeyVerticalTrade',
    counterpartPath: '/entreprises',
    mode: JourneyMode.request,
    discoveryType: 'trade_company',
    contactTargetType: 'trade_company',
    blockers: [
      // Le contrat public n'expose que le profil et le catalogue produits :
      // aucun endpoint de devis structuré (lignes, quantités, incoterm).
      JourneyBlocker(
        capability: 'quote',
        reasonKey: 'journeyBlockerTradeQuote',
      ),
    ],
  ),
  JourneyVertical(
    code: 'pharmacy',
    labelKey: 'journeyVerticalPharmacy',
    counterpartPath: '/pharmacies',
    mode: JourneyMode.blocked,
    discoveryType: 'pharmacy',
    // `PublicProfileLocator::types()` ne contient pas `pharmacy` : une demande
    // serait refusée en 422. Ce n'est PAS un oubli côté app.
    contactTargetType: null,
    blockers: [
      JourneyBlocker(
        capability: 'catalog',
        reasonKey: 'journeyBlockerPharmacyCatalog',
      ),
      JourneyBlocker(
        capability: 'contact',
        reasonKey: 'journeyBlockerPharmacyContact',
      ),
    ],
  ),
  JourneyVertical(
    code: 'vtc',
    labelKey: 'journeyVerticalVtc',
    counterpartPath: '/operateurs',
    mode: JourneyMode.request,
    discoveryType: 'vtc_operator',
    contactTargetType: 'vtc_operator',
    blockers: [
      // `POST /public/vtc/marketplace/rides` exige `pickup.lat/lng` ET
      // `dropoff.lat/lng` : sans recherche d'adresse ni géocodage dans l'app,
      // un devis serait géographiquement faux.
      JourneyBlocker(capability: 'ride', reasonKey: 'journeyBlockerVtcRide'),
    ],
  ),
  JourneyVertical(
    code: 'travel',
    labelKey: 'journeyVerticalTravel',
    counterpartPath: '/billets',
    // #9943 — le contrat public marketplace existe et est appelé par la
    // vitrine web `/billets` : recherche, détail, réservation, suivi et
    // annulation sont servis sans compte ni jeton d'agence. Le blocage
    // `entry` (aucun provider `public.discoverable`) est donc périmé : la
    // verticale n'a pas besoin de la découverte, elle a sa propre recherche.
    mode: JourneyMode.booking,
    discoveryType: null,
    contactTargetType: null,
  ),
  JourneyVertical(
    code: 'careers',
    labelKey: 'journeyVerticalCareers',
    counterpartPath: '/emploi',
    // #9944 — le contrat public ATS existe et est appelé par la vitrine web
    // `/emploi` : annuaire, portail, offre, candidature et suivi par e-mail
    // sont servis sans compte. Le blocage `entry` est donc périmé.
    mode: JourneyMode.request,
    discoveryType: null,
    // Aucun resolver public `careers` dans `PublicProfileLocator::types()` :
    // la candidature passe par la route ATS dédiée, pas par
    // `POST /public/contact-requests`.
    contactTargetType: null,
  ),
];

/// Verticale par code stable.
JourneyVertical? journeyByCode(String? code) {
  if (code == null || code.isEmpty) return null;
  for (final vertical in journeyVerticals) {
    if (vertical.code == code) return vertical;
  }
  return null;
}

/// Verticale exposée par `GET /public/discover` pour un `type` donné.
///
/// Fail-closed : un type inconnu rend `null`, jamais la première verticale.
JourneyVertical? journeyByType(String? type) {
  if (type == null || type.isEmpty) return null;
  for (final vertical in journeyVerticals) {
    if (vertical.discoveryType == type) return vertical;
  }
  return null;
}
