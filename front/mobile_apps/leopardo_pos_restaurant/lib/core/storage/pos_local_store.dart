import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_search.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

import '../models/pos_models.dart';

/// Stockage local du POS Restaurant tablette (#9214).
///
/// Trois boîtes Hive :
///  - `pos_catalog` : catégories + produits (cache TTL, survit au redémarrage) ;
///  - `pos_floor`   : plan de salle ;
///  - `pos_cart`    : panier en cours (le serveur retrouve sa commande après
///                    un passage en arrière-plan).
///
/// Le stockage est **tolérant** : une entrée corrompue est ignorée plutôt que
/// de faire planter l'ouverture de caisse (une caisse qui refuse de démarrer
/// parce qu'un octet est abîmé est une caisse inutilisable).
class PosLocalStore {
  PosLocalStore._(this._catalog, this._floor, this._cart, this._meta);

  static const _catalogBox = 'pos_catalog';
  static const _floorBox = 'pos_floor';
  static const _cartBox = 'pos_cart';
  static const _metaBox = 'pos_meta';

  final Box<dynamic> _catalog;
  final Box<dynamic> _floor;
  final Box<dynamic> _cart;
  final Box<dynamic> _meta;

  static Future<PosLocalStore> open() async {
    await Hive.initFlutter();

    return PosLocalStore._(
      await Hive.openBox<dynamic>(_catalogBox),
      await Hive.openBox<dynamic>(_floorBox),
      await Hive.openBox<dynamic>(_cartBox),
      await Hive.openBox<dynamic>(_metaBox),
    );
  }

  // ── Configuration runtime (#9263) ────────────────────────────────────────

  /// Configuration de la tablette (URL API + succursale).
  Future<void> saveConfig(PosAppConfig config) async {
    await _meta.put('app_config', jsonEncode(config.toJson()));
  }

  PosAppConfig? readConfig() {
    final raw = _meta.get('app_config');

    if (raw is! String) {
      return null;
    }

    try {
      return PosAppConfig.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  /// Empreinte du PIN de l'ecran de reglages (null = PIN d'usine).
  Future<void> savePinHash(String hash) async {
    await _meta.put('pin_hash', hash);
  }

  String? get pinHash {
    final raw = _meta.get('pin_hash');

    return raw is String && raw.isNotEmpty ? raw : null;
  }

  /// Imprimante retenue (#9264).
  Future<void> savePrinter({required String id, required String name}) async {
    await _meta.put('printer_id', id);
    await _meta.put('printer_name', name);
  }

  ({String id, String name})? get printer {
    final id = _meta.get('printer_id');
    final name = _meta.get('printer_name');

    if (id is! String || id.isEmpty) {
      return null;
    }

    return (id: id, name: name is String ? name : id);
  }

  // ── Catalogue ────────────────────────────────────────────────────────────

  Future<void> saveCatalog({
    required List<PosCategory> categories,
    required List<PosProduct> products,
  }) async {
    await _catalog.put('categories', jsonEncode(categories.map((c) => c.toJson()).toList()));
    await _catalog.put('products', jsonEncode(products.map((p) => p.toJson()).toList()));
    await _catalog.put('cached_at', DateTime.now().toIso8601String());
  }

  List<PosCategory> readCategories() => _decodeList(
        _catalog.get('categories'),
        (json) => PosCategory.fromJson(json),
      );

  List<PosProduct> readProducts() => _decodeList(
        _catalog.get('products'),
        (json) => PosProduct.fromJson(json),
      );

  DateTime? get catalogCachedAt {
    final raw = _catalog.get('cached_at');

    return raw is String ? DateTime.tryParse(raw) : null;
  }

  /// Le cache est-il encore frais ? (défaut : 12 h — un menu change rarement
  /// en cours de service, mais pas jamais.)
  bool isCatalogFresh({Duration maxAge = const Duration(hours: 12)}) {
    final cachedAt = catalogCachedAt;

    if (cachedAt == null) {
      return false;
    }

    return DateTime.now().difference(cachedAt) < maxAge;
  }

  // ── Plan de salle ────────────────────────────────────────────────────────

  Future<void> saveTables(List<PosTable> tables) async {
    await _floor.put('tables', jsonEncode(tables.map((t) => t.toJson()).toList()));
  }

  List<PosTable> readTables() => _decodeList(
        _floor.get('tables'),
        (json) => PosTable.fromJson(json),
      );

  // ── Panier ───────────────────────────────────────────────────────────────

  Future<void> saveCart(Cart cart) async {
    await _cart.put('current', jsonEncode(cart.toJson()));
  }

  Cart? readCart() {
    final raw = _cart.get('current');

    if (raw is! String) {
      return null;
    }

    try {
      return Cart.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> clearCart() async {
    await _cart.delete('current');
  }

  // ── Affinites (favoris + recents) ────────────────────────────────────────

  /// Favoris et plats recemment servis.
  ///
  /// Persistes : c'est ce qui fait remonter les plats habituels en tete de
  /// grille des le premier service, sans que le serveur ait a les chercher.
  Future<void> saveAffinity(PosAffinityRegistry registry) async {
    await _cart.put('affinity', jsonEncode(registry.toJson()));
  }

  PosAffinityRegistry readAffinity() {
    final raw = _cart.get('affinity');

    if (raw is! String) {
      return const PosAffinityRegistry();
    }

    try {
      return PosAffinityRegistry.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return const PosAffinityRegistry();
    }
  }

  // ── Commandes en attente ─────────────────────────────────────────────────

  /// Commandes mises de cote : elles doivent survivre a un redemarrage, sinon
  /// la mise en attente ne sert a rien.
  Future<void> saveParkedTickets(PosParkedTickets tickets) async {
    await _cart.put('parked_tickets', jsonEncode(tickets.toJson()));
  }

  PosParkedTickets readParkedTickets() {
    final raw = _cart.get('parked_tickets');

    if (raw is! String) {
      return const PosParkedTickets();
    }

    try {
      return PosParkedTickets.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return const PosParkedTickets();
    }
  }

  /// Decode une liste JSON en ignorant les entrees corrompues.
  List<T> _decodeList<T>(dynamic raw, T Function(Map<String, dynamic>) build) {
    if (raw is! String) {
      return const [];
    }

    try {
      final decoded = jsonDecode(raw);

      if (decoded is! List) {
        return const [];
      }

      final result = <T>[];

      for (final entry in decoded) {
        if (entry is Map<String, dynamic>) {
          try {
            result.add(build(entry));
          } catch (_) {
            // Entrée corrompue : on l'ignore, on ne casse pas la caisse.
          }
        }
      }

      return result;
    } catch (_) {
      return const [];
    }
  }
}
