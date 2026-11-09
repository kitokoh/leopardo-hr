import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_search.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';

import '../models/retail_models.dart';

/// Stockage local du POS Retail tablette (#9215).
///
/// Quatre boites Hive :
///  - `retail_catalog` : categories + produits (cache TTL, survit au redemarrage) ;
///  - `retail_cart`    : panier en cours (le vendeur retrouve sa vente apres
///                       un passage en arriere-plan) ;
///  - `retail_session` : session de caisse ouverte (id + fonds de caisse) ;
///  - `retail_meta`    : metadonnees (emplacement, horodatage du cache).
///
/// Le stockage est **tolerant** : une entree corrompue est ignoree plutot que
/// de faire planter l'ouverture de caisse (une caisse qui refuse de demarrer
/// parce qu'un octet est abime est une caisse inutilisable).
class RetailLocalStore {
  RetailLocalStore._(this._catalog, this._cart, this._session, this._meta);

  static const _catalogBox = 'retail_catalog';
  static const _cartBox = 'retail_cart';
  static const _sessionBox = 'retail_session';
  static const _metaBox = 'retail_meta';

  final Box<dynamic> _catalog;
  final Box<dynamic> _cart;
  final Box<dynamic> _session;
  final Box<dynamic> _meta;

  static Future<RetailLocalStore> open() async {
    await Hive.initFlutter();

    return RetailLocalStore._(
      await Hive.openBox<dynamic>(_catalogBox),
      await Hive.openBox<dynamic>(_cartBox),
      await Hive.openBox<dynamic>(_sessionBox),
      await Hive.openBox<dynamic>(_metaBox),
    );
  }

  // ── Catalogue ────────────────────────────────────────────────────────────

  Future<void> saveCatalog({
    required List<RetailCategory> categories,
    required List<RetailProduct> products,
  }) async {
    await _catalog.put('categories', jsonEncode(categories.map((c) => c.toJson()).toList()));
    await _catalog.put('products', jsonEncode(products.map((p) => p.toJson()).toList()));
    await _catalog.put('cached_at', DateTime.now().toIso8601String());
  }

  List<RetailCategory> readCategories() => _decodeList(
        _catalog.get('categories'),
        (json) => RetailCategory.fromJson(json),
      );

  List<RetailProduct> readProducts() => _decodeList(
        _catalog.get('products'),
        (json) => RetailProduct.fromJson(json),
      );

  DateTime? get catalogCachedAt {
    final raw = _catalog.get('cached_at');

    return raw is String ? DateTime.tryParse(raw) : null;
  }

  /// Le cache est-il encore frais ? (defaut : 12 h — un catalogue de commerce
  /// change rarement en cours de journee, mais pas jamais.)
  bool isCatalogFresh({Duration maxAge = const Duration(hours: 12)}) {
    final cachedAt = catalogCachedAt;

    if (cachedAt == null) {
      return false;
    }

    return DateTime.now().difference(cachedAt) < maxAge;
  }

  // ── Panier ───────────────────────────────────────────────────────────────

  Future<void> saveCart(RetailCart cart) async {
    await _cart.put('current', jsonEncode(cart.toJson()));
  }

  RetailCart? readCart() {
    final raw = _cart.get('current');

    if (raw is! String) {
      return null;
    }

    try {
      return RetailCart.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> clearCart() async {
    await _cart.delete('current');
  }

  // ── Session de caisse ────────────────────────────────────────────────────

  Future<void> saveSession(RetailSession session) async {
    await _session.put('current', jsonEncode(session.toJson()));
  }

  RetailSession? readSession() {
    final raw = _session.get('current');

    if (raw is! String) {
      return null;
    }

    try {
      return RetailSession.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> clearSession() async {
    await _session.delete('current');
  }

  // ── Metadonnees ──────────────────────────────────────────────────────────

  Future<void> saveLocationId(int locationId) async {
    await _meta.put('location_id', locationId);
  }

  int? get locationId {
    final raw = _meta.get('location_id');

    return raw is num ? raw.toInt() : null;
  }

  // ── Affinites (favoris + recents) ────────────────────────────────────────

  /// Favoris et articles recemment vendus.
  ///
  /// Persistes : c'est ce qui fait remonter les articles habituels en tete de
  /// grille des le premier service du matin, sans que le vendeur ait a les
  /// rechercher.
  Future<void> saveAffinity(PosAffinityRegistry registry) async {
    await _meta.put('affinity', jsonEncode(registry.toJson()));
  }

  PosAffinityRegistry readAffinity() {
    final raw = _meta.get('affinity');

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
    await _meta.put('parked_tickets', jsonEncode(tickets.toJson()));
  }

  PosParkedTickets readParkedTickets() {
    final raw = _meta.get('parked_tickets');

    if (raw is! String) {
      return const PosParkedTickets();
    }

    try {
      return PosParkedTickets.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return const PosParkedTickets();
    }
  }

  // ── Configuration runtime (#9263) ────────────────────────────────────────

  /// Configuration de la tablette (URL API + emplacement).
  ///
  /// Persistee : une tablette redemarree doit retrouver son serveur sans
  /// repasser par l'ecran de reglages.
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
            // Entree corrompue : on l'ignore, on ne casse pas la caisse.
          }
        }
      }

      return result;
    } catch (_) {
      return const [];
    }
  }
}
