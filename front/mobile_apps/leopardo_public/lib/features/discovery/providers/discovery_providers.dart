import 'dart:async';
import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';

import '../data/public_discovery_repository.dart';
import '../models/discovery_category.dart';
import '../models/discovery_item.dart';

/// Providers de la découverte publique (couloir 16, M1).
///
/// ## Pourquoi un cache mémoire et pas Hive
///
/// L'état obligatoire « hors ligne : affiche le cache » ne demande PAS une base
/// locale : il demande que le dernier résultat utile reste visible quand le
/// réseau tombe. Un cache mémoire d'une entrée par requête suffit à couvrir le
/// geste réel (ouvrir l'app, perdre le réseau, revenir sur l'écran), sans
/// ajouter une dépendance de stockage et sans risque de données périmées qui
/// survivraient des jours.
///
/// M4 tranchera s'il faut persister (budget de poids, cache de la dernière
/// recherche demandé au cahier des charges). D'ici là, aucune décision de
/// stockage durable n'est prise à la légère.

/// Un résultat de recherche + de quoi le réafficher hors ligne.
class DiscoveryState {
  const DiscoveryState({
    this.items = const [],
    this.total = 0,
    this.page = 1,
    this.lastPage = 1,
    this.actions = const {},
    this.actionUrls = const {},
    this.isLoading = false,
    this.isOffline = false,
    this.failure,
    this.query = '',
    this.city,
    this.categoryKey,
    this.near,
    this.openNowOnly = false,
    this.usedLocation = false,
  });

  final List<DiscoveryItem> items;
  final int total;
  final int page;
  final int lastPage;
  final Map<String, List<String>> actions;
  final Map<String, Map<String, String?>> actionUrls;
  final bool isLoading;

  /// Vrai quand les items affichés viennent du cache faute de réseau : l'écran
  /// doit alors AFFICHER un bandeau « hors ligne », pas laisser croire que la
  /// liste est fraîche.
  final bool isOffline;
  final PublicApiFailure? failure;

  final String query;
  final String? city;
  final String? categoryKey;

  /// `lat,lng` — présent seulement si la position a été consentie.
  final String? near;

  /// Filtre appliqué CÔTÉ CLIENT (le contrat public n'a pas encore `open_now`).
  final bool openNowOnly;

  /// Vrai si un `near` a été utilisé : l'interface peut alors trier par
  /// distance au lieu de proposer « utiliser ma position ».
  final bool usedLocation;

  bool get hasMore => page < lastPage;

  /// Capacités RÉELLES d'un type, telles que le serveur les a déclarées.
  /// Source unique des boutons de la fiche.
  List<String> actionsFor(String type) => actions[type] ?? const <String>[];

  bool get isEmpty => !isLoading && items.isEmpty && failure == null;
  bool get hasError => failure != null;

  DiscoveryState copyWith({
    List<DiscoveryItem>? items,
    int? total,
    int? page,
    int? lastPage,
    Map<String, List<String>>? actions,
    Map<String, Map<String, String?>>? actionUrls,
    bool? isLoading,
    bool? isOffline,
    PublicApiFailure? failure,
    bool clearFailure = false,
    String? query,
    String? city,
    bool clearCity = false,
    String? categoryKey,
    bool clearCategory = false,
    String? near,
    bool clearNear = false,
    bool? openNowOnly,
    bool? usedLocation,
  }) {
    return DiscoveryState(
      items: items ?? this.items,
      total: total ?? this.total,
      page: page ?? this.page,
      lastPage: lastPage ?? this.lastPage,
      actions: actions ?? this.actions,
      actionUrls: actionUrls ?? this.actionUrls,
      isLoading: isLoading ?? this.isLoading,
      isOffline: isOffline ?? this.isOffline,
      failure: clearFailure ? null : (failure ?? this.failure),
      query: query ?? this.query,
      city: clearCity ? null : (city ?? this.city),
      categoryKey: clearCategory ? null : (categoryKey ?? this.categoryKey),
      near: clearNear ? null : (near ?? this.near),
      openNowOnly: openNowOnly ?? this.openNowOnly,
      usedLocation: usedLocation ?? this.usedLocation,
    );
  }
}

final publicDiscoveryRepositoryProvider = Provider<PublicDiscoveryRepository>(
  (ref) => PublicDiscoveryRepository(ref.watch(apiClientProvider)),
);

/// Cache mémoire du dernier résultat réussi (une entrée par requête).
///
/// Clé = combinaison des filtres. On indexe par clé, pas par écran : revenir à
/// « restaurant, ville X » après un détour doit retrouver le même cache, et une
/// nouvelle recherche ne doit jamais écraser le cache d'une autre.
final _discoveryCacheProvider =
    Provider<DiscoveryCache>((ref) => DiscoveryCache());

class DiscoveryCache {
  final Map<String, DiscoveryState> _entries = {};
  static const int maxEntries = 20;

  static String keyFor({
    required String query,
    String? city,
    String? categoryKey,
    String? near,
    bool openNowOnly = false,
  }) =>
      jsonEncode({
        'q': query.trim(),
        'city': city,
        'cat': categoryKey,
        'near': near,
        'open': openNowOnly,
      });

  DiscoveryState? read(String key) => _entries[key];

  /// Écrit une entrée ; évince la plus ancienne au-delà de [maxEntries].
  ///
  /// Une Map Dart conserve l'ordre d'insertion : supprimer la première clé est
  /// une éviction LRU suffisante ici (le volume réel est une poignée d'écrans).
  void write(String key, DiscoveryState state) {
    _entries.remove(key);
    _entries[key] = state;
    while (_entries.length > maxEntries) {
      _entries.remove(_entries.keys.first);
    }
  }

  void clear() => _entries.clear();

  int get size => _entries.length;
}

final discoveryCacheProvider = _discoveryCacheProvider;

/// Seuil de catégories + liste, pour la ville courante.
final discoveryCategoriesProvider =
    FutureProvider.family<DiscoveryCategories, String?>((ref, city) async {
  return ref.watch(publicDiscoveryRepositoryProvider).categories(city);
});

class DiscoveryController extends Notifier<DiscoveryState> {
  Timer? _debounce;

  /// Jeton logique de la requête courante.
  ///
  /// `requestWithRetry` n'accepte pas de `CancelToken` (il réserve l'annulation
  /// aux flux de fichiers) : on ne peut donc pas interrompre une requête
  /// réseau. Ce compteur fait mieux que rien et couvre le vrai problème — une
  /// réponse lente qui arrive APRÈS une réponse plus récente ne doit pas écraser
  /// l'affichage (« je tape vite et je vois les résultats de la lettre
  /// précédente »).
  int _requestSeq = 0;

  @override
  DiscoveryState build() {
    ref.onDispose(() => _debounce?.cancel());
    return const DiscoveryState();
  }

  PublicDiscoveryRepository get _repo =>
      ref.read(publicDiscoveryRepositoryProvider);

  DiscoveryCache get _cache => ref.read(discoveryCacheProvider);

  String get _cacheKey => DiscoveryCache.keyFor(
        query: state.query,
        city: state.city,
        categoryKey: state.categoryKey,
        near: state.near,
        openNowOnly: state.openNowOnly,
      );

  /// Recherche avec anti-rebond : 350 ms.
  ///
  /// Sans rebond, chaque frappe lancerait une requête (`throttle:shop-public`
  /// la refuserait vite) et la dernière réponse arrivée écraserait la plus
  /// récente — le classique « je tape vite et je vois les résultats de la
  /// lettre précédente ».
  void search(String query) {
    state = state.copyWith(query: query);
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      unawaited(load());
    });
  }

  void setCity(String? city) {
    state = state.copyWith(city: city, clearCity: city == null);
    // Changer de ville invalide le `near` : garder une position d'une autre
    // ville produirait une liste vide inexplicable.
    if (city != null) {
      state = state.copyWith(clearNear: true, usedLocation: false);
    }
    unawaited(load());
  }

  void setCategory(String? key) {
    state = state.copyWith(categoryKey: key, clearCategory: key == null);
    unawaited(load());
  }

  void toggleOpenNow() {
    state = state.copyWith(openNowOnly: !state.openNowOnly);
    // Filtre purement local : aucun appel réseau nécessaire.
  }

  void useLocation(String near) {
    state = state.copyWith(near: near, usedLocation: true);
    unawaited(load());
  }

  void clearLocation() {
    state = state.copyWith(clearNear: true, usedLocation: false);
    unawaited(load());
  }

  /// Charge (ou recharge) la page 1.
  Future<void> load() async {
    await _fetch(page: 1, append: false);
  }

  /// Page suivante, si elle existe.
  Future<void> loadMore() async {
    if (state.isLoading || state.page >= state.lastPage) return;
    await _fetch(page: state.page + 1, append: true);
  }

  Future<void> _fetch({required int page, required bool append}) async {
    final seq = ++_requestSeq;
    final key = _cacheKey;

    state = state.copyWith(isLoading: true, clearFailure: true);

    try {
      // La catégorie n'est pas un paramètre du contrat `discover` : elle est
      // appliquée côté client sur `item.category` (voir §5 du plan M0). Ce
      // n'est pas un contournement — le serveur n'expose pas encore de filtre
      // catégorie sur cette route, et l'inventer produirait un 422.
      final result = await _repo.discover(
        query: state.query,
        city: state.city,
        near: state.near,
        page: page,
      );

      // Une réponse plus ancienne est arrivée après une plus récente : on la
      // jette. Sans ce garde-fou, la latence réseau décide du contenu affiché.
      if (seq != _requestSeq) return;

      final merged = append
          ? <DiscoveryItem>[...state.items, ...result.items]
          : result.items;

      final filtered = state.openNowOnly
          ? merged.where(_isProbablyOpenNow).toList(growable: false)
          : merged;

      final categoryFiltered = state.categoryKey == null
          ? filtered
          : filtered
              .where((i) => i.category == state.categoryKey)
              .toList(growable: false);

      final next = state.copyWith(
        items: categoryFiltered,
        total: result.total,
        page: result.page,
        lastPage: result.lastPage,
        actions: result.actions,
        actionUrls: result.actionUrls,
        isLoading: false,
        isOffline: false,
        clearFailure: true,
      );

      state = next;
      _cache.write(key, next);
    } on PublicApiFailure catch (failure) {
      if (seq != _requestSeq) return;

      final cached = _cache.read(key);

      if (failure.kind == PublicFailureKind.network ||
          failure.kind == PublicFailureKind.timeout ||
          failure.kind == PublicFailureKind.server) {
        // Réseau ou serveur indisponible : si on a un cache, on l'affiche avec
        // le drapeau hors ligne. Sinon on remonte l'erreur — mais une erreur
        // franche, pas une liste vide déguisée en « aucun résultat ».
        if (cached != null && cached.items.isNotEmpty) {
          state = cached.copyWith(
            isLoading: false,
            isOffline: true,
            failure: failure,
          );
          return;
        }
      }

      state = state.copyWith(
        isLoading: false,
        items: append ? state.items : const [],
        failure: failure,
      );
    }
  }

  /// Heuristique « ouvert maintenant » côté client.
  ///
  /// L'app ne connaît pas les horaires dans la liste (le contrat de la fiche
  /// les expose, pas celui de la découverte). Tant que la liste ne porte pas
  /// d'indicateur `is_open_now`, on ne FILTRE PAS sur une donnée qu'on n'a pas :
  /// on renvoie tout, et l'écran explique que le filtre s'appliquera quand le
  /// backend l'exposera.
  ///
  /// C'est volontairement conservateur : mieux vaut un filtre visiblement
  /// inactif qu'une liste amputée au hasard.
  static bool _isProbablyOpenNow(DiscoveryItem item) => true;
}

final discoveryControllerProvider =
    NotifierProvider<DiscoveryController, DiscoveryState>(
  DiscoveryController.new,
);
