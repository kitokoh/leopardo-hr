/// Modèles de la verticale **Pharmacie** (pharmacy) — officines publiques.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// `GET /public/discover?type=pharmacy&q&city&page&per_page` remonte les
/// officines qui ont **opté** pour la publication
/// (`pharmacy_public_profiles.enabled`, défaut `false`). Le provider
/// `PharmacyDiscoverableProvider` est fail-closed : un profil incomplet (slug
/// ou nom vide) n'est jamais publié, même si `enabled` est vrai.
///
/// ## Ce que ces modèles refusent d'inventer
///
/// - **La garde.** Le provider publie `category: 'on_duty'` quand l'officine
///   est de garde, et `null` sinon. `null` ne veut pas dire « fermée » : on ne
///   sait pas. `onDuty` est donc un `bool?`, jamais un `bool` par défaut.
/// - **Les services.** Le contrat public ne les expose pas encore : la liste
///   reste vide. Une liste inventée promettrait des services que l'officine
///   n'a pas déclarés.
/// - **La géolocalisation.** Le provider renvoie `latitude: null` et
///   `longitude: null` (aucune coordonnée stockée). On ne fabrique pas de
///   point sur une carte.
library;

/// Une officine telle que le grand public la voit.
class Pharmacy {
  const Pharmacy({
    required this.slug,
    required this.name,
    this.city,
    this.country,
    this.description,
    this.onDuty,
    this.services = const [],
  });

  final String slug;
  final String name;
  final String? city;
  final String? country;
  final String? description;

  /// `true` = de garde, `false` = horaires normaux, `null` = non déclaré.
  ///
  /// Le provider ne publie que `on_duty` ou rien : `false` n'est donc jamais
  /// produit par le serveur actuel. Le type reste `bool?` pour que l'écran
  /// n'ait pas à deviner si un futur contrat distinguera les deux cas.
  final bool? onDuty;

  /// Services publiés — vide tant que le contrat ne les expose pas.
  final List<String> services;

  bool get hasServices => services.isNotEmpty;

  /// Construit depuis un item de `GET /public/discover`.
  ///
  /// `category == 'on_duty'` → de garde ; `category == null` → inconnu. Toute
  /// autre valeur est traitée comme inconnue plutôt que comme « pas de
  /// garde » : une catégorie future ne doit pas se traduire par une
  /// affirmation fausse.
  static Pharmacy? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final slug = _str(raw['slug']);
    final name = _str(raw['name']);
    if (slug == null || name == null) return null;

    final category = _str(raw['category']);

    final services = <String>[];
    final rawServices = raw['services'];
    if (rawServices is List) {
      for (final entry in rawServices) {
        final service = _str(entry);
        if (service != null) services.add(service);
      }
    }

    return Pharmacy(
      slug: slug,
      name: name,
      city: _str(raw['city']),
      country: _str(raw['country']),
      description: _str(raw['description']),
      onDuty: category == 'on_duty' ? true : null,
      services: services,
    );
  }
}

/// Résultat d'un chargement d'annuaire.
///
/// ## Pourquoi `available` existe
///
/// « Aucune officine n'a activé sa publication » et « l'API n'a pas répondu »
/// sont deux états différents, et l'utilisateur mérite le bon message. Le web
/// fait exactement cette distinction (`PharmacyLoadResult.available`).
class PharmacyDirectory {
  const PharmacyDirectory({
    required this.available,
    required this.pharmacies,
    this.lastPage = 1,
  });

  /// `false` = panne API. `true` + liste vide = aucune officine opt-in.
  final bool available;
  final List<Pharmacy> pharmacies;
  final int lastPage;

  bool get isEmpty => available && pharmacies.isEmpty;

  static const PharmacyDirectory unavailable = PharmacyDirectory(
    available: false,
    pharmacies: [],
  );
}

String? _str(Object? value) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  return null;
}
