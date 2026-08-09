// ============================================================
// AttendanceOfflineService — Check-in/out offline
// Uses local DB when offline, syncs when connected
// ============================================================

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import '../database/edge_database.dart';
import 'sync_service.dart';

class AttendanceOfflineService {
  final EdgeDatabase _db;
  final SyncService _syncService;
  final Dio _dio;

  AttendanceOfflineService({
    required EdgeDatabase db,
    required SyncService syncService,
    required Dio dio,
  })  : _db = db,
        _syncService = syncService,
        _dio = dio;

  /// Check-in — works online and offline
  Future<CheckInResult> checkIn({
    required String employeeId,
    required String companyId,
    double? gpsLat,
    double? gpsLng,
    String method = 'mobile',
  }) async {
    if (_syncService.currentMode == SyncMode.offline) {
      // Save locally
      final id = await _db.insertAttendanceLog(
        LocalAttendanceLogsCompanion.insert(
          employeeId: employeeId,
          companyId: companyId,
          checkIn: DateTime.now(),
          method: Value(method),
          gpsLat: Value(gpsLat),
          gpsLng: Value(gpsLng),
          syncStatus: const Value('pending'),
        ),
      );
      return CheckInResult(id: id, savedLocally: true, synced: false);
    }

    // Online — call API
    try {
      final response = await _dio.post(
        '${_syncService.apiBaseUrl}/v1/attendance/check-in',
        data: {
          'employee_id': employeeId,
          'company_id': companyId,
          'gps_lat': gpsLat,
          'gps_lng': gpsLng,
          'method': method,
        },
      );
      return CheckInResult(
        id: response.data['data']['id'] as String,
        savedLocally: false,
        synced: true,
      );
    } on DioException {
      // API failed — fallback to local
      final id = await _db.insertAttendanceLog(
        LocalAttendanceLogsCompanion.insert(
          employeeId: employeeId,
          companyId: companyId,
          checkIn: DateTime.now(),
          method: Value(method),
          gpsLat: Value(gpsLat),
          gpsLng: Value(gpsLng),
          syncStatus: const Value('pending'),
        ),
      );
      return CheckInResult(
        id: id,
        savedLocally: true,
        synced: false,
        fallback: true,
      );
    }
  }

  /// Check-out — works online and offline
  Future<void> checkOut({required String logId, bool isLocalId = false}) async {
    if (_syncService.currentMode == SyncMode.offline || isLocalId) {
      await _db.checkOut(logId);
      return;
    }

    try {
      await _dio.post(
        '${_syncService.apiBaseUrl}/v1/attendance/check-out',
        data: {'log_id': logId},
      );
    } on DioException {
      await _db.checkOut(logId);
    }
  }
}

class CheckInResult {
  final String id;
  final bool savedLocally;
  final bool synced;
  final bool fallback;

  const CheckInResult({
    required this.id,
    required this.savedLocally,
    required this.synced,
    this.fallback = false,
  });
}
