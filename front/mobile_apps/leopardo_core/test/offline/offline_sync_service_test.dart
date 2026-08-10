// ============================================================
// OfflineSyncService tests — F-21 (#1551) : purge 4xx définitifs.
//
// Couvre les branches de sync de la file hors-ligne :
//   - 4xx définitif (400/404/409/410/422)  -> purge (pas de retry infini)
//   - 4xx transitoire (401/403/408/425/429) -> conservé (retry à la reconnexion)
//   - erreur réseau (statusCode null)       -> conservé
//   - 5xx (500)                             -> conservé
//   - succès                                -> purge
// ============================================================

import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/services/offline_sync_service.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

class FakeConnectivity implements Connectivity {
  @override
  Future<List<ConnectivityResult>> checkConnectivity() async =>
      <ConnectivityResult>[ConnectivityResult.wifi];

  @override
  Stream<List<ConnectivityResult>> get onConnectivityChanged =>
      const Stream<List<ConnectivityResult>>.empty();
}

void main() {
  late Directory tempDir;

  setUp(() async {
    tempDir = await Directory.systemTemp.createTemp('offline_sync_test');
    Hive.init(tempDir.path);
  });

  tearDown(() async {
    // Isolation stricte entre tests (A-1/mobile) : Hive garde les boxes
    // ouvertes en mémoire ; sans fermeture + deleteFromDisk systématiques,
    // la boîte `offline_punches` d'un test précédent « fuit » dans le
    // suivant (compteurs cumulés → faux échecs).
    if (Hive.isBoxOpen('offline_punches')) {
      await Hive.box('offline_punches').close();
      await Hive.deleteBoxFromDisk('offline_punches');
    }
    await Hive.deleteFromDisk();
    await tempDir.delete(recursive: true);
  });

  OfflineSyncService buildService(Future<void> Function(String path, Map<String, dynamic> payload) sender) {
    final apiClient = ApiClient(SecureStorage(), AppPreferences());
    return OfflineSyncService(
      apiClient,
      FakeConnectivity(),
      sendPunchOverride: sender,
    );
  }

  Future<Box<Map<dynamic, dynamic>>> seedBox(List<Map<dynamic, dynamic>> items) async {
    final box = await Hive.openBox<Map<dynamic, dynamic>>('offline_punches');
    for (final item in items) {
      await box.add(item);
    }
    return box;
  }

  Map<dynamic, dynamic> punch(String type) => <dynamic, dynamic>{
        'type': type,
        'payload': <String, dynamic>{'work_type': 'normal'},
        'timestamp': DateTime.now().toIso8601String(),
      };

  test('4xx définitif (422 double check-in) : purge la file', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-in')]);
    final service = buildService((path, payload) async {
      throw ApiException('Double check-in rejeté', statusCode: 422);
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 0, reason: '422 est définitif : l\'entrée doit être purgée');
  });

  test('4xx définitif (404) : purge la file', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-out')]);
    final service = buildService((path, payload) async {
      throw ApiException('Inconnu', statusCode: 404);
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 0);
  });

  test('429 (rate limit) : conservé pour retry', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-in')]);
    final service = buildService((path, payload) async {
      throw ApiException('Too many requests', statusCode: 429);
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 1, reason: '429 est transitoire : l\'entrée reste en file');
  });

  test('401 (session expirée) : conservé (re-login possible)', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-in')]);
    final service = buildService((path, payload) async {
      throw ApiException('Unauthorized', statusCode: 401);
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 1, reason: '401 peut devenir valide après re-login');
  });

  test('erreur réseau (statusCode null) : conservé', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-in')]);
    final service = buildService((path, payload) async {
      throw ApiException('Connection failed', statusCode: null);
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 1);
  });

  test('5xx (500) : conservé', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-in')]);
    final service = buildService((path, payload) async {
      throw ApiException('Server error', statusCode: 500);
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 1, reason: '5xx est transitoire (cold start)');
  });

  test('succès : purge la file', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[punch('check-in')]);
    var sentPath = '';
    final service = buildService((path, payload) async {
      sentPath = path;
    });
    await service.init();

    await service.syncPendingPunches();

    expect(box.length, 0);
    expect(sentPath, '/attendance/check-in');
  });

  test('plusieurs entrées : seules les définitives sont purgées', () async {
    final box = await seedBox(<Map<dynamic, dynamic>>[
      punch('check-in'),
      punch('check-out'),
      punch('check-in'),
    ]);
    var calls = 0;
    final service = buildService((path, payload) async {
      calls++;
      if (calls == 2) {
        throw ApiException('Rate limit', statusCode: 429);
      }
      throw ApiException('Définitif', statusCode: 422);
    });
    await service.init();

    await service.syncPendingPunches();

    // 1er : 422 -> purgé ; 2e : 429 -> conservé ; 3e : 422 -> purgé
    expect(box.length, 1);
    expect(calls, 3);
  });
}
