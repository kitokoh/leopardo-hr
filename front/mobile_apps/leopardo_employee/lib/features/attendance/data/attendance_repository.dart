import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/attendance_log.dart';
import 'package:leopardo_core/models/daily_summary.dart';
import 'package:leopardo_core/models/monthly_summary.dart';

class AttendanceRepository {
  final ApiClient apiClient;

  AttendanceRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 8);
  static const _readTimeout = Duration(seconds: 6);

  Future<Map<String, dynamic>> getTodayStatus() async {
    final response = await apiClient.requestWithRetry(
      '/attendance/today',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return decodeTodayResponse((response.data as Map).cast<String, dynamic>());
  }

  Future<AttendanceLog> checkIn({
    String workType = 'normal',
    String? punchNote,
    double? gpsLat,
    double? gpsLng,
    double? gpsAccuracy,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/attendance/check-in',
      method: 'POST',
      data: {
        'work_type': workType,
        'device_timezone': _deviceTimezoneContext(),
        if (gpsLat != null) 'gps_lat': gpsLat,
        if (gpsLng != null) 'gps_lng': gpsLng,
        if (gpsAccuracy != null) 'gps_accuracy': gpsAccuracy,
        if (punchNote != null && punchNote.trim().isNotEmpty)
          'punch_note': punchNote.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return AttendanceLog.fromJson(_dataMap(response.data));
  }

  Future<AttendanceLog> checkOut({
    String workType = 'normal',
    String? punchNote,
    double? gpsLat,
    double? gpsLng,
    double? gpsAccuracy,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/attendance/check-out',
      method: 'POST',
      data: {
        'work_type': workType,
        'device_timezone': _deviceTimezoneContext(),
        if (gpsLat != null) 'gps_lat': gpsLat,
        if (gpsLng != null) 'gps_lng': gpsLng,
        if (gpsAccuracy != null) 'gps_accuracy': gpsAccuracy,
        if (punchNote != null && punchNote.trim().isNotEmpty)
          'punch_note': punchNote.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return AttendanceLog.fromJson(_dataMap(response.data));
  }

  Future<AttendanceLog> updateAttendanceLog({
    required int logId,
    required DateTime checkIn,
    DateTime? checkOut,
    required String notes,
  }) async {
    final payload = <String, dynamic>{
      'check_in': checkIn.toIso8601String(),
      'notes': notes,
    };
    if (checkOut != null) {
      payload['check_out'] = checkOut.toIso8601String();
    }

    final response = await apiClient.requestWithRetry(
      '/attendance/$logId',
      method: 'PUT',
      data: payload,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return AttendanceLog.fromJson(_dataMap(response.data));
  }

  Future<void> requestCorrection({
    int? logId,
    required DateTime date,
    required DateTime checkIn,
    DateTime? checkOut,
    required String reason,
  }) async {
    final payload = <String, dynamic>{
      'date':
          '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}',
      'requested_check_in': checkIn.toIso8601String(),
      'reason': reason,
    };
    if (logId != null) payload['attendance_log_id'] = logId;
    if (checkOut != null) {
      payload['requested_check_out'] = checkOut.toIso8601String();
    }

    await apiClient.requestWithRetry(
      '/attendance/corrections',
      method: 'POST',
      data: payload,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<DailySummary> getDailySummary(int employeeId) async {
    final response = await apiClient.requestWithRetry(
      '/employees/$employeeId/daily-summary',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return DailySummary.fromJson(extractDataMap(response.data));
  }

  Future<DailySummary> getMyDailySummary({DateTime? date}) async {
    final qp = <String, dynamic>{};
    if (date != null) {
      qp['date'] =
          '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    }
    final response = await apiClient.requestWithRetry(
      '/me/daily-summary',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: qp,
    );
    return DailySummary.fromJson(extractDataMap(response.data));
  }

  Future<MonthlySummary> getMyMonthlySummary({int? year, int? month}) async {
    final qp = <String, dynamic>{};
    if (year != null) qp['year'] = year;
    if (month != null) qp['month'] = month;
    final response = await apiClient.requestWithRetry(
      '/me/monthly-summary',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: qp,
    );
    return MonthlySummary.fromJson(extractDataMap(response.data));
  }

  Future<MonthlySummary> getMyQuickEstimate({
    required DateTime from,
    required DateTime to,
  }) async {
    String fmt(DateTime d) =>
        '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    final response = await apiClient.requestWithRetry(
      '/me/quick-estimate',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: {'from': fmt(from), 'to': fmt(to)},
    );
    return MonthlySummary.fromJson(extractDataMap(response.data));
  }

  Future<List<AttendanceLog>> getHistory(int year, int month) async {
    final from = DateTime(year, month, 1);
    final to = DateTime(year, month + 1, 0);

    final response = await apiClient.requestWithRetry(
      '/attendance',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: {
        'date_from':
            '${from.year.toString().padLeft(4, '0')}-${from.month.toString().padLeft(2, '0')}-${from.day.toString().padLeft(2, '0')}',
        'date_to':
            '${to.year.toString().padLeft(4, '0')}-${to.month.toString().padLeft(2, '0')}-${to.day.toString().padLeft(2, '0')}',
        'per_page': 50,
      },
    );
    final items = extractDataList(response.data);
    return items.map((e) => AttendanceLog.fromJson(e)).toList();
  }

  Future<List<Map<String, dynamic>>> getTodayTasks() async {
    final response = await apiClient.requestWithRetry(
      '/tasks/today',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((entry) => entry.cast<String, dynamic>())
        .toList();
  }

  Future<void> completeTask({
    required int taskId,
    required int completedMinutes,
    String? note,
  }) async {
    await apiClient.requestWithRetry(
      '/tasks/$taskId',
      method: 'PATCH',
      data: {
        'status': 'done',
        'completed_minutes': completedMinutes,
        if (note != null && note.trim().isNotEmpty)
          'completion_note': note.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  static Map<String, dynamic> decodeTodayResponse(
    Map<String, dynamic> responseData,
  ) {
    final payload = responseData['data'];

    if (payload == null) {
      return {
        'log': null,
        'sessions': const <AttendanceLog>[],
        'summary': responseData['summary'],
        'context': responseData['context'],
      };
    }

    if (payload is! Map) {
      throw const FormatException('Invalid attendance/today payload');
    }

    final data = payload.cast<String, dynamic>();
    final rawContext = data['context'] ?? responseData['context'];
    final context =
        rawContext is Map ? rawContext.cast<String, dynamic>() : null;

    if (data.containsKey('items')) {
      return {
        'log': null,
        'context': {
          'mode': data['mode'] ?? 'collection',
          'items': data['items'],
          'meta': data['meta'] ?? responseData['meta'],
          ...?context,
        },
      };
    }

    final itemPayload = data['item'];
    final todayPayload =
        itemPayload is Map
            ? itemPayload.cast<String, dynamic>()
            : (data.containsKey('item') ? null : data);

    if (todayPayload == null) {
      return {
        'log': null,
        'sessions': const <AttendanceLog>[],
        'summary': data['summary'],
        'context': context,
      };
    }

    final now = DateTime.now();
    final today = todayPayload.cast<String, dynamic>();
    final rawSessions = data['sessions'];
    final sessions =
        rawSessions is List
            ? rawSessions
                .whereType<Map>()
                .map(
                  (entry) =>
                      AttendanceLog.fromJson(entry.cast<String, dynamic>()),
                )
                .toList()
            : const <AttendanceLog>[];

    return {
      'log': AttendanceLog(
        id: int.tryParse(today['id']?.toString() ?? '') ?? 0,
        employeeId: int.tryParse(today['employee_id']?.toString() ?? '') ?? 0,
        date: DateTime(now.year, now.month, now.day),
        checkIn: _parseLocalTime(today['check_in_time'] as String?),
        checkOut: _parseLocalTime(today['check_out_time'] as String?),
        status: (today['status'] ?? 'absent') as String,
        workedHours:
            today['hours_worked'] != null
                ? double.tryParse(today['hours_worked'].toString())
                : 0.0,
        overtimeHours:
            today['overtime_hours'] != null
                ? double.tryParse(today['overtime_hours'].toString())
                : 0.0,
        lateMinutes:
            today['late_minutes'] != null
                ? int.tryParse(today['late_minutes'].toString())
                : null,
        employeeName: today['name']?.toString(),
        employeePhotoUrl:
            (today['photo_url'] ?? today['photo_path'])?.toString(),
        sessionNumber:
            int.tryParse(today['session_number']?.toString() ?? '') ?? 1,
        workType: (today['work_type'] ?? 'normal').toString(),
      ),
      'sessions': sessions,
      'summary': data['summary'],
      'context': context,
    };
  }

  static DateTime? _parseLocalTime(String? hhmm) {
    if (hhmm == null || hhmm.isEmpty) return null;
    final parts = hhmm.split(':');
    if (parts.length < 2) return null;
    final hour = int.tryParse(parts[0]) ?? 0;
    final minute = int.tryParse(parts[1]) ?? 0;
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, hour, minute);
  }

  static Map<String, dynamic> _dataMap(dynamic responseData) {
    final response =
        responseData is Map
            ? responseData.cast<String, dynamic>()
            : const <String, dynamic>{};
    final payload = response['data'];
    if (payload is Map) {
      final item = payload['item'];
      if (item is Map) return item.cast<String, dynamic>();
      return payload.cast<String, dynamic>();
    }
    return response;
  }

  static String _deviceTimezoneContext() {
    final now = DateTime.now();
    final offset = now.timeZoneOffset;
    final sign = offset.isNegative ? '-' : '+';
    final absolute = offset.abs();
    final hours = absolute.inHours.toString().padLeft(2, '0');
    final minutes = (absolute.inMinutes % 60).toString().padLeft(2, '0');

    return 'UTC$sign$hours:$minutes; local=${now.timeZoneName}';
  }
}
