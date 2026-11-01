/// Modèle d'une catégorie de découverte (`GET /public/discovery/categories`).
///
/// ## Le champ `visible` n'est pas un filtre, c'est une information
///
/// Le serveur renvoie AUSSI les catégories sous le seuil, avec
/// `visible: false`. Supprimer la ligne serait indistinguable d'une ville où
/// la catégorie n'existe pas ; ici l'interface peut dire « bientôt disponible »
/// et reste honnête. Two cas à ne pas confondre :
///
///  - `count == 0`            → la catégorie n'existe pas encore dans la ville ;
///  - `0 < count < seuil`     → elle existe, mais pas assez pour être mise en
///                              avant (règle produit du couloir 16).
///
/// L'écran ne montre QUE les `visible`, mais conserve le seuil pour pouvoir
/// expliquer pourquoi une catégorie manque si la personne le demande.
class DiscoveryCategory {
  const DiscoveryCategory({
    required this.key,
    required this.label,
    required this.count,
    required this.visible,
  });

  final String key;
  final String label;
  final int count;
  final bool visible;

  static DiscoveryCategory? tryParse(Object? raw) {
    if (raw is! Map) return null;

    final key = raw['key'];
    if (key is! String || key.isEmpty) return null;

    final count = raw['count'];

    return DiscoveryCategory(
      key: key,
      label: raw['label'] is String && (raw['label'] as String).isNotEmpty
          ? raw['label'] as String
          : key,
      count: count is num ? count.toInt() : int.tryParse('$count') ?? 0,
      // Absence de `visible` traitée comme false : fail-closed, on n'affiche
      // pas une catégorie que le serveur n'a pas explicitement validée.
      visible: raw['visible'] == true,
    );
  }
}

/// Réponse complète de `GET /public/discovery/categories`.
class DiscoveryCategories {
  const DiscoveryCategories({
    required this.categories,
    required this.threshold,
    this.city,
  });

  final List<DiscoveryCategory> categories;

  /// Seuil appliqué par le serveur (réglage Superadmin). Renvoyé pour que
  /// l'app puisse l'expliquer sans le deviner ni le coder en dur.
  final int threshold;
  final String? city;

  List<DiscoveryCategory> get visible =>
      categories.where((c) => c.visible).toList(growable: false);

  static const DiscoveryCategories empty =
      DiscoveryCategories(categories: [], threshold: 0);

  static DiscoveryCategories fromJson(Map<String, dynamic> json) {
    final data = json['data'];
    final meta = json['meta'];
    final metaMap = meta is Map ? meta : const <String, dynamic>{};

    final list = <DiscoveryCategory>[];
    if (data is List) {
      for (final raw in data) {
        final category = DiscoveryCategory.tryParse(raw);
        if (category != null) list.add(category);
      }
    }

    final rawThreshold = metaMap['threshold'];

    return DiscoveryCategories(
      categories: list,
      threshold: rawThreshold is num
          ? rawThreshold.toInt()
          : int.tryParse('$rawThreshold') ?? 0,
      city: metaMap['city'] is String ? metaMap['city'] as String : null,
    );
  }
}
