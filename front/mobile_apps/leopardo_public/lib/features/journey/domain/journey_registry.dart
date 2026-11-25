/// Registre des parcours transactionnels des 8 verticales — **domaine pur**.
///
/// ## Pourquoi un registre et pas des `if` dans les écrans
///
/// La cartographie mobile (`docs/mobile/CARTOGRAPHIE_APPS_MOBILES.md` §2)
/// constate que les 8 verticales sont « fiche + boutons génériques » et que
/// « les parcours transactionnels verticaux ne le sont pas encore ». Ce constat
/// se dégrade tout seul : une verticale oubliée ne casse aucun build, elle
/// affiche simplement un bouton mort que personne ne remarque.
///
/// Ce registre rend l'oubli **impossible à représenter** : il déclare une
/// entrée par verticale de [SurfaceVertical], et un test échoue si une
/// verticale manque, si un type est dupliqué, ou si un blocage n'a pas de clé
/// i18n. C'est la même mécanique que le registre de couche et la garde
/// d'identités (#9883) : la règle « rien ne doit être oublié » devient
/// mécanique au lieu d'être un commentaire.
///
/// ## Les trois natures de parcours
///
///  - [JourneyKind.native] — le contrat public suffit : l'app peut mener la
///    transaction de bout en bout (catalogue → panier → commande → suivi).
///  - [JourneyKind.request] — seul `POST /public/contact-requests` est
///    universel : l'app envoie une demande, elle ne prétend pas commander.
///  - [JourneyKind.blocked] — **aucun** contrat appelable sans jeton tenant ou
///    sans service absent de l'app. L'écran doit dire *pourquoi*, jamais
///    afficher un bouton mort.
///
/// ## Ce fichier n'importe aucun framework
///
/// Aucun `material_ui`, aucun `flutter_riverpod`, aucun `dio`. C'est la règle
/// d'ADR-0035, opposée par `test/architecture/layer_guard_test.dart`. Seul
/// `dart:` est autorisé — ici, rien du tout.
library;

import '../../surface/domain/surface_identity.dart';

/// Nature du parcours disponible pour une verticale.
enum JourneyKind {
  /// Contrat public complet : la transaction va jusqu'au bout dans l'app.
  native,

  /// Passerelle de demande (`POST /public/contact-requests`).
  request,

  /// Rien d'appelable : l'écran explique le motif, il ne propose pas d'action.
  blocked,
}

/// Une entrée du registre : une verticale, son parcours, et son motif.
class JourneyEntry {
  const JourneyEntry({
    required this.vertical,
    required this.kind,
    this.reasonKey,
    this.contractPaths = const [],
  });

  /// La verticale couverte. Une et une seule entrée par valeur de l'enum.
  final SurfaceVertical vertical;

  /// Ce que l'app peut réellement faire pour cette verticale.
  final JourneyKind kind;

  /// Clé i18n du motif — **obligatoire** pour [JourneyKind.blocked] et
  /// [JourneyKind.request] : une limite non expliquée est un bouton mort.
  /// `null` uniquement pour [JourneyKind.native], qui n'a rien à excuser.
  final String? reasonKey;

  /// Chemins du contrat public réellement appelés, pour la traçabilité.
  /// Vide pour [JourneyKind.blocked] — c'est précisément le constat.
  final List<String> contractPaths;

  /// Vrai quand l'écran doit afficher un motif plutôt qu'une action.
  bool get isExplained => kind != JourneyKind.native;

  /// Vrai quand l'app peut mener la transaction elle-même.
  bool get isTransactional => kind == JourneyKind.native;
}

/// Le registre des 8 verticales, mesuré le 2026-11-24 sur `main`.
///
/// L'ordre suit [SurfaceVertical.values] : un test le vérifie, pour qu'ajouter
/// une verticale oblige à lui donner une entrée au bon endroit.
class JourneyRegistry {
  const JourneyRegistry._();

  static const List<JourneyEntry> all = [
    // Restaurant — la commande exige un jeton boutique `X-Restaurant-Shop-Token`
    // émis côté back-office : l'exposer au grand public est une décision de
    // sécurité serveur, pas un correctif mobile.
    JourneyEntry(
      vertical: SurfaceVertical.restaurant,
      kind: JourneyKind.request,
      reasonKey: 'journeyReasonRestaurantOrder',
      contractPaths: ['/public/contact-requests'],
    ),

    // Boutiques — parcours natif complet : catalogue vendeur, commande, suivi.
    JourneyEntry(
      vertical: SurfaceVertical.retail,
      kind: JourneyKind.native,
      contractPaths: [
        '/public/market/products',
        '/public/market/orders',
        '/public/market/orders/{ref}',
      ],
    ),

    // Séjour — parcours natif complet : disponibilités, réservation, suivi.
    JourneyEntry(
      vertical: SurfaceVertical.hospitality,
      kind: JourneyKind.native,
      contractPaths: [
        '/public/hospitality/properties/{slug}/availability',
        '/public/hospitality/properties/{slug}/reservations',
        '/public/hospitality/reservations/{ref}',
      ],
    ),

    // Entreprises — la demande de devis passe par la passerelle universelle ;
    // le devis STRUCTURÉ n'existe pas dans le contrat public.
    JourneyEntry(
      vertical: SurfaceVertical.trade,
      kind: JourneyKind.request,
      reasonKey: 'journeyReasonTradeQuote',
      contractPaths: ['/public/contact-requests'],
    ),

    // Billets — `/public/travel/marketplace/*` existe, mais aucun provider
    // `public.discoverable` ne l'expose dans `/public/discover` : la verticale
    // est inatteignable depuis l'app.
    JourneyEntry(
      vertical: SurfaceVertical.travel,
      kind: JourneyKind.blocked,
      reasonKey: 'journeyReasonTravelPortal',
    ),

    // Pharmacies — aucune route publique : ni catalogue, ni resolver dans
    // `PublicProfileLocator::types()`, donc `contact-requests` refuse
    // `target_type=pharmacy` en 422.
    JourneyEntry(
      vertical: SurfaceVertical.pharmacy,
      kind: JourneyKind.blocked,
      reasonKey: 'journeyReasonPharmacyCatalog',
    ),

    // VTC — l'estimation et la course exigent `pickup.lat/lng` ET
    // `dropoff.lat/lng` ; l'app n'a ni recherche d'adresse ni géocodage, et
    // envoyer un `dropoff` inventé produirait un devis faux.
    JourneyEntry(
      vertical: SurfaceVertical.vtc,
      kind: JourneyKind.request,
      reasonKey: 'journeyReasonVtcRide',
      contractPaths: ['/public/contact-requests'],
    ),

    // Emploi — `/public/careers/*` existe, mais aucun provider
    // `public.discoverable` : même impasse que les billets.
    JourneyEntry(
      vertical: SurfaceVertical.careers,
      kind: JourneyKind.blocked,
      reasonKey: 'journeyReasonCareersPortal',
    ),
  ];

  /// Entrée d'une verticale ; `null` si le registre est incomplet.
  ///
  /// `null` est un défaut, pas un cas normal : [validate] le signale, et un
  /// test l'interdit. L'écran, lui, doit rester fail-closed — il n'invente pas
  /// de parcours pour une verticale qu'il ne connaît pas.
  static JourneyEntry? forVertical(SurfaceVertical vertical) {
    for (final entry in all) {
      if (entry.vertical == vertical) {
        return entry;
      }
    }
    return null;
  }

  /// Contrôles d'intégrité du registre — consommés par les tests.
  ///
  /// Retourne la liste des problèmes ; vide = registre sain. On retourne les
  /// problèmes au lieu de lancer : le test peut alors afficher *tous* les
  /// défauts d'un coup, au lieu du premier.
  static List<String> validate() {
    final problems = <String>[];

    // 1. Une entrée par verticale, ni plus ni moins.
    final seen = <SurfaceVertical>{};
    for (final entry in all) {
      if (!seen.add(entry.vertical)) {
        problems.add('verticale dupliquée : ${entry.vertical.code}');
      }
    }
    for (final vertical in SurfaceVertical.values) {
      if (!seen.contains(vertical)) {
        problems.add('verticale absente du registre : ${vertical.code}');
      }
    }

    // 2. Un blocage ou une demande sans motif est un bouton mort.
    for (final entry in all) {
      final key = entry.reasonKey;
      if (entry.isExplained && (key == null || key.isEmpty)) {
        problems.add(
          '${entry.vertical.code} : parcours ${entry.kind.name} sans motif i18n',
        );
      }
      if (!entry.isExplained && key != null) {
        problems.add(
          '${entry.vertical.code} : parcours natif avec un motif '
          '(il n\'a rien à excuser)',
        );
      }
    }

    // 3. Un parcours natif sans contrat appelable est un mensonge.
    for (final entry in all) {
      if (entry.isTransactional && entry.contractPaths.isEmpty) {
        problems.add(
          '${entry.vertical.code} : parcours natif sans chemin de contrat',
        );
      }
      if (entry.kind == JourneyKind.blocked && entry.contractPaths.isNotEmpty) {
        problems.add(
          '${entry.vertical.code} : parcours bloqué mais chemins déclarés',
        );
      }
    }

    return problems;
  }
}
