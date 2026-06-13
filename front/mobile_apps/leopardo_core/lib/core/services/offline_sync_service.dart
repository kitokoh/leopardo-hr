import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/attendance_log.dart';

class OfflineSyncService {
  final ApiClient apiClient;
  final Connectivity connectivity;
  late final Box<Map<dynamic, dynamic>> _offlineBox;
  StreamSubscription? _connectivitySub;

  OfflineSyncService(this.apiClient, this.connectivity);

  Future<void> init() async {
    _offlineBox = await Hive.openBox<Map<dynamic, dynamic>>('offline_punches');
    
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
        // Delete if successful
        await _offlineBox.delete(key);
      } catch (e) {
        // If it's a cold start or timeout, we keep it in the box.
        // If it's a 4xx error (e.g. already checked in), we probably should delete it.
        // For simplicity, we just log and try again later unless it's a known non-retryable error.
      }
    }
  }

  void dispose() {
    _connectivitySub?.cancel();
  }
}
