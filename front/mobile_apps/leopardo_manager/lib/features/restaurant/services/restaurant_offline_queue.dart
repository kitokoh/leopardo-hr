import 'package:leopardo_core/features/restaurant/data/restaurant_repository.dart';
import 'package:leopardo_core/models/restaurant_sync.dart';

/// File d'opérations hors ligne restaurant (RESTO-804/#6225).
///
/// L'app pousse ses opérations effectuées hors ligne ; le serveur les rejoue
/// IDEMPOTEMENT via `POST /api/v1/restaurant/mobile/sync` (clés client
/// générées ici). Une opération `created` ou `duplicate` est retirée de la
/// file ; une opération `error` reste pour un prochain rejeu — un rejeu ne
/// crée jamais de doublon (invariant serveur `RestaurantMobileSyncTest`,
/// critère d'acceptation #6406).
///
/// NB : file en mémoire pour ce lot (persistance Hive/Drift documentée dans
/// la suite RESTO-028) — le contrat d'idempotence ne dépend pas du stockage.
class RestaurantOfflineQueue {
  RestaurantOfflineQueue(this._repository);

  final RestaurantRepository _repository;
  final List<RestaurantSyncOperation> _pending = <RestaurantSyncOperation>[];
  final List<RestaurantSyncResult> _history = <RestaurantSyncResult>[];
  int _counter = 0;

  List<RestaurantSyncOperation> get pending =>
      List<RestaurantSyncOperation>.unmodifiable(_pending);

  List<RestaurantSyncResult> get history =>
      List<RestaurantSyncResult>.unmodifiable(_history);

  bool get hasPending => _pending.isNotEmpty;

  String _nextKey() =>
      'mobile-${DateTime.now().millisecondsSinceEpoch}-${_counter++}';

  /// Ajoute une opération à la file (hors ligne).
  void enqueue({
    required String type,
    Map<String, dynamic> payload = const <String, dynamic>{},
  }) {
    _pending.add(
      RestaurantSyncOperation(
        type: type,
        idempotencyKey: _nextKey(),
        payload: payload,
      ),
    );
  }

  /// Rejoue toute la file ; retourne les résultats serveur.
  Future<List<RestaurantSyncResult>> flush() async {
    if (_pending.isEmpty) return const <RestaurantSyncResult>[];
    final results = await _repository.syncOffline(_pending);
    _history.addAll(results);
    _pending.removeWhere((op) {
      return results.any(
        (r) =>
            r.idempotencyKey == op.idempotencyKey &&
            (r.status == 'created' || r.status == 'duplicate'),
      );
    });
    return results;
  }
}
