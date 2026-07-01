import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_hr/features/smart_attendance/data/models/geo_attendance_session.dart';

/// Repository Smart Attendance — app Manager.
/// Expose validation des sessions GPS et dashboard stats.
class HrSmartAttendanceRepository {
  const HrSmartAttendanceRepository(this._apiClient);

  final ApiClient _apiClient;

  static const _readTimeout = Duration(seconds: 8);
  static const _writeTimeout = Duration(seconds: 10);

  /// GET /api/v1/smart-attendance/sessions?status=pending_validation
  Future<List<GeoAttendanceSession>> getPendingSessions() async {
    final response = await _apiClient.requestWithRetry(
      '/smart-attendance/sessions?status=pending_validation&per_page=50',
      timeoutOverride: _readTimeout,
    );
    final raw = response.data;
    final list = (raw is Map ? raw['data'] : raw) as List<dynamic>? ?? [];
    return list
        .map((e) => GeoAttendanceSession.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// GET /api/v1/smart-attendance/dashboard
  Future<Map<String, dynamic>> getDashboardStats() async {
    final response = await _apiClient.requestWithRetry(
      '/smart-attendance/dashboard',
      timeoutOverride: _readTimeout,
    );
    return extractDataMap(response.data);
  }

  /// POST /api/v1/smart-attendance/sessions/{id}/approve
  Future<void> approveSession(int sessionId, {String? note}) async {
    await _apiClient.requestWithRetry(
      '/smart-attendance/sessions/$sessionId/approve',
      method: 'POST',
      data: {if (note != null) 'note': note},
      maxRetriesOverride: 1,
      timeoutOverride: _writeTimeout,
    );
  }

  /// POST /api/v1/smart-attendance/sessions/{id}/reject
  Future<void> rejectSession(int sessionId, {required String note}) async {
    await _apiClient.requestWithRetry(
      '/smart-attendance/sessions/$sessionId/reject',
      method: 'POST',
      data: {'reason': note},
      maxRetriesOverride: 1,
      timeoutOverride: _writeTimeout,
    );
  }
}
