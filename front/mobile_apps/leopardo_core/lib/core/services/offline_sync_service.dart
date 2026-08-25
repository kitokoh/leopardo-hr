import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

/// Signature d'envoi d'un pointage vers l'API (injectable pour les tests).
/// [idempotencyKey] est la clé RTMX (#5407) stockée dans l'entrée de la file
/// : le rejeu doit réutiliser la MÊME clé que l'appel initial pour que le
/// serveur rejoue la 1ʳᵉ réponse au lieu de créer un doublon.
typedef SyncPunchSender = Future<void> Function(
  String path,
  Map<String, dynamic> payload, {
  String? idempotencyKey,
});

class OfflineSyncService {
  final ApiClient apiClient;
  final Connectivity connectivity;

  /// Surcharge d'envoi (tests unitaires) — remplace apiClient quand fournie.
  final SyncPunchSender? sendPunchOverride;

  late final Box<Map<dynamic, dynamic>> _offlineBox;
  StreamSubscription? _connectivitySub;

  OfflineSyncService(this.apiClient, this.connectivity,
      {this.sendPunchOverride});

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
    _connectivitySub = connectivity.onConnectivityChanged
        .listen((List<ConnectivityResult> results) {
      if (results.any((result) => result != ConnectivityResult.none)) {
        syncPendingPunches();
      }
    });

    // Initial check
    final results = await connectivity.checkConnectivity();
    if (results.any((result) => result != ConnectivityResult.none)) {
      syncPendingPunches();
    }
  }

  Future<void> saveOfflinePunch(
    Map<String, dynamic> payload,
    bool isCheckIn, {
    String? idempotencyKey,
  }) async {
    await _offlineBox.add({
      'type': isCheckIn ? 'check-in' : 'check-out',
      'payload': payload,
      // RTMX (#5407) : la clé générée au moment du pointage est conservée pour
      // que le rejeu réutilise la MÊME clé (idempotence serveur #5277).
      if (idempotencyKey != null) 'idempotencyKey': idempotencyKey,
      'timestamp': DateTime.now().toIso8601String(),
    });
  }

  /// Sync de la file hors-ligne (appelé à la reconnexion, public pour les tests).
  Future<void> syncPendingPunches() async {
    if (_offlineBox.isEmpty) return;

    final keys = _offlineBox.keys.toList();
    for (final key in keys) {
      final item = _offlineBox.get(key);
      if (item == null) continue;

      final type = item['type'] as String;
      final payload = Map<String, dynamic>.from(item['payload'] as Map);
      // RTMX (#5407) : clé d'idempotence générée au pointage initial et
      // stockée dans l'entrée — le rejeu réutilise la MÊME clé (le serveur
      // rejoue la 1ʳᵉ réponse 2xx au lieu de créer un doublon).
      final idempotencyKey = item['idempotencyKey'] as String?;

      try {
        final path = type == 'check-in'
            ? '/attendance/check-in'
            : '/attendance/check-out';
        final sender = sendPunchOverride;
        if (sender != null) {
          await sender(path, payload, idempotencyKey: idempotencyKey);
        } else {
          await apiClient.requestWithRetry(
            path,
            method: 'POST',
            data: payload,
            maxRetriesOverride: 0,
            options: Options(
              headers: {
                if (idempotencyKey != null) 'Idempotency-Key': idempotencyKey,
              },
            ),
          );
        }
        // Success : purge l'entrée (règle « 1er pointage gagne » — le serveur
        // a accepté ce pointage, les doublons éventuels sont rejetés côté API).
        await _offlineBox.delete(key);
      } on ApiException catch (e) {
        // 4xx = erreur métier définitive (ex. double check-in rejeté 409/422,
        // identifiant inconnu 404) : inutile de re-tenter — on purge la file
        // pour éviter une boucle de retry infinie (issue #1551, F-21).
        // Seules les erreurs 4xx DÉFINITIVES sont purgées : 401 (re-login
        // possible), 403 (droits peut-être rétablies), 408/425/429
        // (transitoires) restent en file et seront retentées à la prochaine
        // connexion.
        if (e.statusCode != null && _definitive4xx.contains(e.statusCode)) {
          await _offlineBox.delete(key);
        }
      } catch (_) {
        // Erreur réseau / timeout / cold start : on conserve l'entrée et on
        // réessaiera au prochain changement de connectivité.
      }
    }
  }

  /// Codes 4xx définitifs : l'API a tranché, un retry ne changera rien.
  static const Set<int> _definitive4xx = {400, 404, 409, 410, 422};

  void dispose() {
    _connectivitySub?.cancel();
  }
}
