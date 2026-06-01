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

  Future<AttendanceLog> checkIn({double? gpsLat, double? gpsLng}) async {
    final response = await apiClient.requestWithRetry(
      '/attendance/check-in',
      method: 'POST',
      data: {
        'device_timezone': _deviceTimezoneContext(),
        if (gpsLat != null) 'gps_lat': gpsLat,
        if (gpsLng != null) 'gps_lng': gpsLng,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return AttendanceLog.fromJson(_dataMap(response.data));
  }

  Future<AttendanceLog> checkOut({double? gpsLat, double? gpsLng}) async {
    final response = await apiClient.requestWithRetry(
      '/attendance/check-out',
      method: 'POST',
      data: {
        'device_timezone': _deviceTimezoneContext(),
        if (gpsLat != null) 'gps_lat': gpsLat,
        if (gpsLng != null) 'gps_lng': gpsLng,
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

  Future<List<AttendanceLog>> getManagerAttendanceToday() async {
    final now = DateTime.now();
    final date =
        '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final response = await apiClient.requestWithRetry(
      '/attendance',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: {'date_from': date, 'date_to': date, 'per_page': 50},
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((e) => AttendanceLog.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<ManagerAnomalyReport> getManagerAnomalies() async {
    final now = DateTime.now();
    final from = now.subtract(const Duration(days: 7));
    String fmt(DateTime d) =>
        '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

    final response = await apiClient.requestWithRetry(
      '/attendance/anomalies',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: {
        'date_from': fmt(from),
        'date_to': fmt(now),
        'per_page': 50,
      },
    );
    final data = _dataMap(response.data);
    return ManagerAnomalyReport.fromJson(data);
  }

  Future<List<AttendanceCorrection>> getPendingCorrections() async {
    final response = await apiClient.requestWithRetry(
      '/attendance/corrections',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
      queryParameters: {'status': 'pending', 'per_page': 50},
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((e) => AttendanceCorrection.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  Future<void> approveCorrection(int correctionId) async {
    await apiClient.requestWithRetry(
      '/attendance/corrections/$correctionId/approve',
      method: 'PUT',
      data: const {},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<void> rejectCorrection(int correctionId) async {
    await apiClient.requestWithRetry(
      '/attendance/corrections/$correctionId/reject',
      method: 'PUT',
      data: const {},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
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

class ManagerAnomalyReport {
  const ManagerAnomalyReport({
    required this.total,
    required this.critical,
    required this.warning,
    required this.info,
    required this.lateMinutes,
    required this.missingCheckOuts,
    required this.manualCorrections,
    required this.items,
  });

  final int total;
  final int critical;
  final int warning;
  final int info;
  final int lateMinutes;
  final int missingCheckOuts;
  final int manualCorrections;
  final List<ManagerAnomaly> items;

  factory ManagerAnomalyReport.fromJson(Map<String, dynamic> json) {
    final summary =
        json['summary'] is Map
            ? (json['summary'] as Map).cast<String, dynamic>()
            : const <String, dynamic>{};
    final impact =
        summary['business_impact'] is Map
            ? (summary['business_impact'] as Map).cast<String, dynamic>()
            : const <String, dynamic>{};
    final rawItems = json['items'] as List? ?? const [];

    return ManagerAnomalyReport(
      total: _asInt(summary['total']),
      critical: _asInt(summary['critical']),
      warning: _asInt(summary['warning']),
      info: _asInt(summary['info']),
      lateMinutes: _asInt(impact['late_minutes']),
      missingCheckOuts: _asInt(impact['missing_check_outs']),
      manualCorrections: _asInt(impact['manual_corrections']),
      items:
          rawItems
              .whereType<Map>()
              .map((e) => ManagerAnomaly.fromJson(e.cast<String, dynamic>()))
              .toList(),
    );
  }
}

class ManagerAnomaly {
  const ManagerAnomaly({
    required this.type,
    required this.severity,
    required this.title,
    required this.employeeName,
    required this.date,
    required this.recommendedAction,
  });

  final String type;
  final String severity;
  final String title;
  final String employeeName;
  final String date;
  final String recommendedAction;

  factory ManagerAnomaly.fromJson(Map<String, dynamic> json) {
    final employee =
        json['employee'] is Map
            ? (json['employee'] as Map).cast<String, dynamic>()
            : const <String, dynamic>{};

    return ManagerAnomaly(
      type: json['type']?.toString() ?? 'unknown',
      severity: json['severity']?.toString() ?? 'info',
      title: json['title']?.toString() ?? 'Anomalie',
      employeeName: employee['name']?.toString() ?? 'Employe',
      date: json['date']?.toString() ?? '',
      recommendedAction: json['recommended_action']?.toString() ?? '',
    );
  }
}

class AttendanceCorrection {
  const AttendanceCorrection({
    required this.id,
    required this.employeeName,
    required this.date,
    required this.requestedCheckIn,
    this.requestedCheckOut,
    required this.reason,
    required this.status,
  });

  final int id;
  final String employeeName;
  final String date;
  final DateTime requestedCheckIn;
  final DateTime? requestedCheckOut;
  final String reason;
  final String status;

  factory AttendanceCorrection.fromJson(Map<String, dynamic> json) {
    final employee =
        json['employee'] is Map
            ? (json['employee'] as Map).cast<String, dynamic>()
            : const <String, dynamic>{};

    return AttendanceCorrection(
      id: _asInt(json['id']),
      employeeName: employee['name']?.toString() ?? 'Employe',
      date: json['date']?.toString() ?? '',
      requestedCheckIn: DateTime.parse(json['requested_check_in'].toString()),
      requestedCheckOut:
          json['requested_check_out'] != null
              ? DateTime.parse(json['requested_check_out'].toString())
              : null,
      reason: json['reason']?.toString() ?? '',
      status: json['status']?.toString() ?? 'pending',
    );
  }
}

int _asInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}
