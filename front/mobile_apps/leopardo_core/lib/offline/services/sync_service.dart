// ============================================================
// SyncService — Manages sync between local DB and Edge/Cloud
// ============================================================

import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart' show visibleForTesting;

import '../database/edge_database.dart';

enum SyncMode { cloud, edge, offline }

class SyncService {
  final EdgeDatabase _db;
  final Dio _dio;
  final String _edgeBaseUrl; // e.g. http://leopardo.local:7878
  final String _cloudBaseUrl; // e.g. https://api.leopardo.app
  final String _edgeNodeId; // Cloud-issued UUID for this Edge node
  final String _edgeToken; // Bearer secret, distinct from _edgeNodeId

  SyncMode _currentMode = SyncMode.offline;
  Timer? _syncTimer;
  bool _isSyncing = false;

  final _modeController = StreamController<SyncMode>.broadcast();
  Stream<SyncMode> get modeStream => _modeController.stream;
  SyncMode get currentMode => _currentMode;

  /// Test-only hook to force [currentMode] without going through the real
  /// network reachability probes in [_detectMode]. See issue #1296.
  @visibleForTesting
  void debugSetModeForTesting(SyncMode mode) => _setMode(mode);

  SyncService({
    required EdgeDatabase db,
    required Dio dio,
    required String edgeBaseUrl,
    required String cloudBaseUrl,
    required String edgeNodeId,
    required String edgeToken,
  })  : _db = db,
        _dio = dio,
        _edgeBaseUrl = edgeBaseUrl,
        _cloudBaseUrl = cloudBaseUrl,
        _edgeNodeId = edgeNodeId,
        _edgeToken = edgeToken;

  /// Initialise connectivity monitoring + periodic sync.
  void start() {
    Connectivity().onConnectivityChanged.listen(_onConnectivityChangedList);
    _syncTimer = Timer.periodic(const Duration(minutes: 5), (_) => syncNow());
    _detectMode();
  }

  void stop() {
    _syncTimer?.cancel();
    _modeController.close();
  }

  Future<void> _detectMode() async {
    // 1. Try Edge node (local network first).
    //
    // The Edge box runs the same Laravel app image as the Cloud API (see
    // edge/docker-compose.yml + the EdgeSync module routes), which exposes
    // the health probe at `api/v1/edge/health` (see
    // api/app/Modules/EdgeSync/routes/api.php) — not `api/edge/health`,
    // which does not exist and would always fail the reachability check.
    final edgeReachable = await _ping('$_edgeBaseUrl/api/v1/edge/health');
    if (edgeReachable) {
      _setMode(SyncMode.edge);
      return;
    }

    // 2. Try Cloud. The Cloud API exposes its liveness probe at
    // `api/v1/health` (see api/routes/api.php), not `api/health`.
    final cloudReachable = await _ping('$_cloudBaseUrl/api/v1/health');
    if (cloudReachable) {
      _setMode(SyncMode.cloud);
      return;
    }

    // 3. Full offline
    _setMode(SyncMode.offline);
  }

  void _onConnectivityChangedList(List<ConnectivityResult> results) {
    final result = results.isNotEmpty ? results.first : ConnectivityResult.none;
    if (result == ConnectivityResult.none) {
      _setMode(SyncMode.offline);
    } else {
      _detectMode().then((_) {
        if (_currentMode != SyncMode.offline) {
          syncNow();
        }
      });
    }
  }

  void _setMode(SyncMode mode) {
    if (_currentMode != mode) {
      _currentMode = mode;
      _modeController.add(mode);
    }
  }

  /// Returns the correct base URL for API calls — transparent to callers.
  String get apiBaseUrl {
    return switch (_currentMode) {
      SyncMode.edge => '$_edgeBaseUrl/api',
      SyncMode.cloud => '$_cloudBaseUrl/api',
      SyncMode.offline => '$_edgeBaseUrl/api',
    };
  }

  /// Sync pending local records to Edge or Cloud.
  Future<SyncResult> syncNow() async {
    if (_isSyncing) return SyncResult.skipped();
    if (_currentMode == SyncMode.offline) return SyncResult.offline();

    _isSyncing = true;
    int sent = 0;
    int failed = 0;

    try {
      final pending = await _db.getPendingItems();

      // Batch into groups of 50
      final batches = <List<LocalSyncQueueItem>>[];
      for (var i = 0; i < pending.length; i += 50) {
        batches.add(
          pending.sublist(i, i + 50 > pending.length ? pending.length : i + 50),
        );
      }

      for (final batch in batches) {
        final records = batch
            .map(
              (item) => {
                'entity_type': item.entityType,
                'entity_id': item.entityId,
                'operation': item.operation,
                'payload': jsonDecode(item.payload),
              },
            )
            .toList();

        try {
          // Both the Cloud API and the local Edge node instance expose the
          // same EdgeSync module routes (the Edge box runs the same Laravel
          // app image), so the path is identical — only the base URL and
          // the real edgeNodeId (a Cloud-issued UUID, distinct from the
          // bearer edgeToken) differ.
          final target =
              '${_activeBaseUrl()}/api/v1/edge-node/$_edgeNodeId/push';

          await _dio.post(
            target,
            data: {'records': records},
            options: Options(headers: {'Authorization': 'Bearer $_edgeToken'}),
          );

          for (final item in batch) {
            await _db.markSynced(item.id);
            sent++;
          }
        } on DioException {
          for (final item in batch) {
            await _db.markFailed(item.id);
            failed++;
          }
        }
      }

      // Pull delta from Cloud/Edge
      await _pullDelta();
    } finally {
      _isSyncing = false;
    }

    return SyncResult.success(sent: sent, failed: failed);
  }

  /// The Cloud or Edge base URL currently in use, matching [_currentMode].
  String _activeBaseUrl() =>
      _currentMode == SyncMode.cloud ? _cloudBaseUrl : _edgeBaseUrl;

  Future<void> _pullDelta() async {
    try {
      final url = '${_activeBaseUrl()}/api/v1/edge-node/$_edgeNodeId/pull';

      final response = await _dio.get(
        url,
        options: Options(headers: {'Authorization': 'Bearer $_edgeToken'}),
      );

      final delta = response.data as Map<String, dynamic>?;
      if (delta == null) return;

      await _applyDelta(delta);
    } catch (_) {
      // Pull failures are silent — will retry next cycle
    }
  }

  Future<void> _applyDelta(Map<String, dynamic> delta) async {
    final entities = delta['entities'] as Map<String, dynamic>? ?? {};

    // Apply employees delta
    if (entities.containsKey('employees')) {
      final employees = entities['employees'] as List;
      for (final emp in employees) {
        final map = emp as Map<String, dynamic>;
        await _db.upsertEmployee(
          LocalEmployeesCompanion.insert(
            id: map['id'] as String,
            companyId: map['company_id'] as String,
            firstName: map['first_name'] as String,
            lastName: map['last_name'] as String,
            email: map['email'] as String,
          ),
        );
      }
    }

    // Additional entity types can be applied here
  }

  Future<bool> _ping(String url) async {
    try {
      final response = await _dio.get(
        url,
        options: Options(
          sendTimeout: const Duration(seconds: 3),
          receiveTimeout: const Duration(seconds: 3),
        ),
      );
      return response.statusCode == 200;
    } catch (_) {
      return false;
    }
  }
}

class SyncResult {
  final bool success;
  final bool skipped;
  final bool offline;
  final int sent;
  final int failed;

  const SyncResult._({
    this.success = false,
    this.skipped = false,
    this.offline = false,
    this.sent = 0,
    this.failed = 0,
  });

  factory SyncResult.success({required int sent, required int failed}) =>
      SyncResult._(success: true, sent: sent, failed: failed);

  factory SyncResult.skipped() => const SyncResult._(skipped: true);

  factory SyncResult.offline() => const SyncResult._(offline: true);

  @override
  String toString() =>
      'SyncResult(success:$success, skipped:$skipped, offline:$offline, sent:$sent, failed:$failed)';
}
