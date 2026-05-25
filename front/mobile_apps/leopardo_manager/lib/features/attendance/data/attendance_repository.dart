import 'package:leopardo_core/core/api/api_client.dart';
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

  Future<AttendanceLog> checkIn() async {
    final response = await apiClient.requestWithRetry(
      '/attendance/check-in',
      method: 'POST',
      data: {},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return AttendanceLog.fromJson(_dataMap(response.data));
  }

  Future<AttendanceLog> checkOut() async {
    final response = await apiClient.requestWithRetry(
      '/attendance/check-out',
      method: 'POST',
      data: {},
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
    final response = await apiClient.dio.get(
      '/employees/$employeeId/daily-summary',
    );
    return DailySummary.fromJson(response.data['data']);
  }

  Future<DailySummary> getMyDailySummary({DateTime? date}) async {
    final qp = <String, dynamic>{};
    if (date != null) {
      qp['date'] =
          '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    }
    final response = await apiClient.dio.get(
      '/me/daily-summary',
      queryParameters: qp,
    );
    return DailySummary.fromJson(response.data['data']);
  }

  Future<MonthlySummary> getMyMonthlySummary({int? year, int? month}) async {
    final qp = <String, dynamic>{};
    if (year != null) qp['year'] = year;
    if (month != null) qp['month'] = month;
    final response = await apiClient.dio.get(
      '/me/monthly-summary',
      queryParameters: qp,
    );
    return MonthlySummary.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<MonthlySummary> getMyQuickEstimate({
    required DateTime from,
    required DateTime to,
  }) async {
    String fmt(DateTime d) =>
        '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    final response = await apiClient.dio.get(
      '/me/quick-estimate',
      queryParameters: {'from': fmt(from), 'to': fmt(to)},
    );
    return MonthlySummary.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
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
    final items = response.data['data'] as List;
    return items.map((e) => AttendanceLog.fromJson(e)).toList();
  }

  static Map<String, dynamic> decodeTodayResponse(
    Map<String, dynamic> responseData,
  ) {
    final payload = responseData['data'];

    if (payload == null) {
      return {'log': null, 'context': responseData['context']};
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
      return {'log': null, 'context': context};
    }

    final now = DateTime.now();
    final today = todayPayload.cast<String, dynamic>();

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
      ),
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
}
