import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

class OfflineSyncService {
  final ApiClient apiClient;
  final Connectivity connectivity;
  late final Box<Map<dynamic, dynamic>> _offlineBox;
  StreamSubscription? _connectivitySub;

  OfflineSyncService(this.apiClient, this.connectivity);

  bool _initialized = false;

  Future<void> init() async {
    // Guard against being called more than once for the same instance
    // (e.g. logout followed by a fresh login in the same app session):
    // without this, each call would attach another connectivity listener.
    if (_initialized) return;
    _initialized = true;

    _offlineBox = Hive.isBoxOpen('offline_punches')
        ? Hive.box<Map<dynamic, dynamic>>('offline_punches')
        : await Hive.openBox<Map<dynamic, dynamic>>('offline_punches');

    // Listen to network changes
    _connectivitySub = connectivity.onConnectivityChanged.listen((List<ConnectivityResult> results) {
      if (results.any((result) => result != ConnectivityResult.none)) {
        _syncPendingPunches();
      }
    });

    // Initial check
    final results = await connectivity.checkConnectivity();
    if (results.any((result) => result != ConnectivityResult.none)) {
      _syncPendingPunches();
    }
  }

  Future<void> saveOfflinePunch(Map<String, dynamic> payload, bool isCheckIn) async {
    await _offlineBox.add({
      'type': isCheckIn ? 'check-in' : 'check-out',
      'payload': payload,
      'timestamp': DateTime.now().toIso8601String(),
    });
  }

  Future<void> _syncPendingPunches() async {
    if (_offlineBox.isEmpty) return;

    final keys = _offlineBox.keys.toList();
    for (final key in keys) {
      final item = _offlineBox.get(key);
      if (item == null) continue;

      final type = item['type'] as String;
      final payload = Map<String, dynamic>.from(item['payload'] as Map);

      try {
        final path = type == 'check-in' ? '/attendance/check-in' : '/attendance/check-out';
        await apiClient.requestWithRetry(
          path,
          method: 'POST',
          data: payload,
          maxRetriesOverride: 0,
        );
        // Success : purge l'entrée (règle « 1er pointage gagne » — le serveur
        // a accepté ce pointage, les doublons éventuels sont rejetés côté API).
        await _offlineBox.delete(key);
      } on ApiException catch (e) {
        // 4xx = erreur métier définitive (ex. double check-in rejeté,
        // identifiant inconnu) : inutile de re-tenter — on purge la file pour
        // éviter une boucle de retry infinie (issue #1551, F-21).
        if (e.statusCode != null && e.statusCode! >= 400 && e.statusCode! < 500) {
          await _offlineBox.delete(key);
        }
      } catch (_) {
        // Erreur réseau / timeout / cold start : on conserve l'entrée et on
        // réessaiera au prochain changement de connectivité.
      }
    }
  }

  void dispose() {
    _connectivitySub?.cancel();
  }
}
