/// Moteur de **recherche predictive** partage par les deux caisses POS.
///
/// Volontairement **pur** (aucune dependance Flutter, aucun plugin) : il est
/// teste en `flutter test` sans moteur graphique, et il est partage par
/// `leopardo_pos_retail` et `leopardo_pos_restaurant` sans dupliquer la regle
/// de classement (garde CI `check-mobile-core-duplication.py`).
///
/// Pourquoi un classement plutot qu'un `contains()` : avec 300 articles, un
/// filtre binaire laisse le vendeur faire defiler une grille. Un classement par
/// pertinence fait remonter l'article des 3 premieres lettres, ce qui est le
/// geste reel d'une caisse.
library;

/// Un article indexable par la recherche.
///
/// Les deux apps ont leurs propres modeles (`RetailProduct`, `PosProduct`) :
/// elles les adaptent a cette interface plutot que de partager un modele, ce
/// qui evite de coupler deux domaines qui divergent legitimement.
abstract class PosSearchable {
  /// Identifiant stable de l'article.
  int get id;

  /// Libelle affiche et recherche.
  String get name;

  /// Reference interne optionnelle (SKU, code plat).
  String? get sku;

  /// Code-barres optionnel.
  String? get barcode;

  /// Rayon / categorie optionnel.
  int? get categoryId;
}

/// Qualite d'une correspondance, du meilleur au pire.
///
/// L'ordre de declaration **est** l'ordre de tri : `index` sert de score.
enum PosMatchQuality {
  /// Le code-barres scanne correspond exactement.
  barcodeExact,

  /// Le libelle commence par la saisie (`bis` -> `Bissap`).
  namePrefix,

  /// Un mot du libelle commence par la saisie (`ment` -> `The a la menthe`).
  wordPrefix,

  /// Le SKU commence par la saisie.
  skuPrefix,

  /// Le libelle contient la saisie ailleurs (`ssa` -> `Bissap`).
  nameContains,

  /// Le SKU contient la saisie ailleurs.
  skuContains,

  /// Aucune correspondance.
  none,
}

/// Resultat d'une correspondance : qualite + article.
class PosSearchHit<T extends PosSearchable> {
  const PosSearchHit({required this.item, required this.quality});

  final T item;
  final PosMatchQuality quality;

  /// Score numerique : plus petit = plus pertinent.
  int get score => quality.index;

  bool get isMatch => quality != PosMatchQuality.none;
}

/// Normalise une chaine pour la recherche : minuscules, sans accents.
///
/// Un vendeur tape `the` et non `Thé` : sans normalisation, la moitie du
/// catalogue francophone devient introuvable au clavier.
String normalizePosQuery(String input) {
  const accented = 'àáâãäåçèéêëìíîïñòóôõöùúûüýÿœæ';
  const plain = 'aaaaaaceeeeiiiinooooouuuuyyoeae';

  final buffer = StringBuffer();

  for (final rune in input.toLowerCase().runes) {
    final char = String.fromCharCode(rune);
    final index = accented.indexOf(char);

    buffer.write(index == -1 ? char : plain[index]);
  }

  return buffer.toString().trim();
}

/// Evalue la qualite de correspondance d'un article pour une saisie.
///
/// Regles, dans l'ordre :
///  1. code-barres **exact** (un scan ne doit jamais ramener un voisin) ;
///  2. prefixe du libelle ;
///  3. prefixe d'un mot du libelle ;
///  4. prefixe du SKU ;
///  5. sous-chaine du libelle ;
///  6. sous-chaine du SKU.
PosMatchQuality matchQuality({
  required String query,
  required String name,
  String? sku,
  String? barcode,
}) {
  final needle = normalizePosQuery(query);

  if (needle.isEmpty) {
    return PosMatchQuality.namePrefix;
  }

  // Le code-barres se compare **avant** normalisation : un code est une suite
  // de chiffres, et une correspondance partielle ramenerait le mauvais article.
  if (barcode != null && barcode.trim().toLowerCase() == query.trim().toLowerCase()) {
    return PosMatchQuality.barcodeExact;
  }

  final haystack = normalizePosQuery(name);

  if (haystack.startsWith(needle)) {
    return PosMatchQuality.namePrefix;
  }

  for (final word in haystack.split(RegExp(r'[\s\-_/]+'))) {
    if (word.isNotEmpty && word.startsWith(needle)) {
      return PosMatchQuality.wordPrefix;
    }
  }

  final normalizedSku = normalizePosQuery(sku ?? '');

  if (normalizedSku.isNotEmpty && normalizedSku.startsWith(needle)) {
    return PosMatchQuality.skuPrefix;
  }

  if (haystack.contains(needle)) {
    return PosMatchQuality.nameContains;
  }

  if (normalizedSku.isNotEmpty && normalizedSku.contains(needle)) {
    return PosMatchQuality.skuContains;
  }

  return PosMatchQuality.none;
}

/// Classe une liste d'articles pour une saisie donnee.
///
/// Le tri est **stable** a qualite egale : l'ordre d'entree (deja trie par
/// favoris puis recents par l'appelant) est preserve. C'est ce qui permet a un
/// article epingle de rester en tete sans que le moteur ait a connaitre la
/// notion de favori.
List<T> rankPosItems<T extends PosSearchable>({
  required List<T> items,
  required String query,
}) {
  if (normalizePosQuery(query).isEmpty) {
    return List<T>.of(items);
  }

  final hits = <PosSearchHit<T>>[];

  for (final item in items) {
    final quality = matchQuality(
      query: query,
      name: item.name,
      sku: item.sku,
      barcode: item.barcode,
    );

    if (quality != PosMatchQuality.none) {
      hits.add(PosSearchHit<T>(item: item, quality: quality));
    }
  }

  // `List.sort` n'est pas garanti stable en Dart : on trie par (score, index
  // d'origine) pour preserver explicitement l'ordre d'entree a qualite egale.
  final indexed = <({int index, PosSearchHit<T> hit})>[
    for (var index = 0; index < hits.length; index++) (index: index, hit: hits[index]),
  ];

  indexed.sort((a, b) {
    final byScore = a.hit.score.compareTo(b.hit.score);

    return byScore != 0 ? byScore : a.index.compareTo(b.index);
  });

  return [for (final entry in indexed) entry.hit.item];
}

/// Un article epingle (favori) ou recemment vendu.
///
/// `weight` permet de departager deux favoris : un article vendu 40 fois passe
/// avant un article epingle jamais vendu, sans qu'il faille deux listes.
class PosItemAffinity {
  const PosItemAffinity({
    required this.itemId,
    this.favourite = false,
    this.usageCount = 0,
    this.lastUsedAt,
  });

  final int itemId;
  final bool favourite;
  final int usageCount;
  final DateTime? lastUsedAt;

  PosItemAffinity withUse(DateTime at) => PosItemAffinity(
        itemId: itemId,
        favourite: favourite,
        usageCount: usageCount + 1,
        lastUsedAt: at,
      );

  PosItemAffinity withFavourite(bool value) => PosItemAffinity(
        itemId: itemId,
        favourite: value,
        usageCount: usageCount,
        lastUsedAt: lastUsedAt,
      );

  Map<String, dynamic> toJson() => {
        'item_id': itemId,
        'favourite': favourite,
        'usage_count': usageCount,
        'last_used_at': lastUsedAt?.toIso8601String(),
      };

  factory PosItemAffinity.fromJson(Map<String, dynamic> json) => PosItemAffinity(
        itemId: (json['item_id'] as num).toInt(),
        favourite: (json['favourite'] as bool?) ?? false,
        usageCount: (json['usage_count'] as num?)?.toInt() ?? 0,
        lastUsedAt: json['last_used_at'] is String
            ? DateTime.tryParse(json['last_used_at'] as String)
            : null,
      );
}

/// Registre d'affinites : favoris + recents, persiste par l'appelant.
///
/// Pur et immuable : chaque mutation renvoie un nouveau registre, ce qui rend
/// l'etat testable et evite les surprises de mutation partagee entre deux
/// ecrans.
class PosAffinityRegistry {
  const PosAffinityRegistry({this.entries = const {}});

  final Map<int, PosItemAffinity> entries;

  /// Nombre maximal d'affinites conservees : au-dela, les entrees les moins
  /// utiles sont oubliees (une caisse ne doit pas grossir indefiniment).
  static const int maxEntries = 200;

  PosItemAffinity? affinityFor(int itemId) => entries[itemId];

  bool isFavourite(int itemId) => entries[itemId]?.favourite ?? false;

  /// Enregistre une vente : incremente l'usage et horodate.
  PosAffinityRegistry recordUse(int itemId, {DateTime? at}) {
    final now = at ?? DateTime.now();
    final existing = entries[itemId] ?? PosItemAffinity(itemId: itemId);

    return _withEntry(existing.withUse(now));
  }

  /// Epingle ou depingle un article.
  PosAffinityRegistry toggleFavourite(int itemId) {
    final existing = entries[itemId] ?? PosItemAffinity(itemId: itemId);

    return _withEntry(existing.withFavourite(!existing.favourite));
  }

  PosAffinityRegistry _withEntry(PosItemAffinity entry) {
    final updated = Map<int, PosItemAffinity>.of(entries)..[entry.itemId] = entry;

    if (updated.length > maxEntries) {
      // On oublie d'abord les non-favoris les moins utilises : un favori
      // epingle explicitement ne doit jamais disparaitre tout seul.
      final evictable = updated.values
          .where((candidate) => !candidate.favourite)
          .toList()
        ..sort((a, b) => a.usageCount.compareTo(b.usageCount));

      for (final candidate in evictable) {
        if (updated.length <= maxEntries) {
          break;
        }

        updated.remove(candidate.itemId);
      }
    }

    return PosAffinityRegistry(entries: updated);
  }

  /// Reordonne une liste : favoris d'abord, puis par usage decroissant, puis
  /// par recence. Les articles sans affinite gardent leur ordre d'origine.
  List<T> order<T extends PosSearchable>(List<T> items) {
    final indexed = <({int index, T item, PosItemAffinity? affinity})>[
      for (var index = 0; index < items.length; index++)
        (index: index, item: items[index], affinity: entries[items[index].id]),
    ];

    indexed.sort((a, b) {
      final aFav = a.affinity?.favourite ?? false;
      final bFav = b.affinity?.favourite ?? false;

      if (aFav != bFav) {
        return aFav ? -1 : 1;
      }

      final aUsage = a.affinity?.usageCount ?? 0;
      final bUsage = b.affinity?.usageCount ?? 0;

      if (aUsage != bUsage) {
        return bUsage.compareTo(aUsage);
      }

      final aLast = a.affinity?.lastUsedAt;
      final bLast = b.affinity?.lastUsedAt;

      if (aLast != null && bLast != null && aLast != bLast) {
        return bLast.compareTo(aLast);
      }

      if ((aLast == null) != (bLast == null)) {
        return aLast == null ? 1 : -1;
      }

      return a.index.compareTo(b.index);
    });

    return [for (final entry in indexed) entry.item];
  }

  /// Les articles recemment utilises, du plus recent au plus ancien.
  List<int> recentItemIds({int limit = 12}) {
    final used = entries.values.where((entry) => entry.lastUsedAt != null).toList()
      ..sort((a, b) => b.lastUsedAt!.compareTo(a.lastUsedAt!));

    return [for (final entry in used.take(limit)) entry.itemId];
  }

  Map<String, dynamic> toJson() => {
        'entries': [for (final entry in entries.values) entry.toJson()],
      };

  factory PosAffinityRegistry.fromJson(Map<String, dynamic> json) {
    final raw = (json['entries'] as List<dynamic>?) ?? const [];
    final map = <int, PosItemAffinity>{};

    for (final entry in raw) {
      if (entry is Map<String, dynamic>) {
        try {
          final affinity = PosItemAffinity.fromJson(entry);
          map[affinity.itemId] = affinity;
        } catch (_) {
          // Entree corrompue : ignoree, le registre reste utilisable.
        }
      }
    }

    return PosAffinityRegistry(entries: map);
  }
}
