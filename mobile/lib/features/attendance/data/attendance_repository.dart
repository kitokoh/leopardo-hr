import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/attendance_log.dart';
import 'package:leopardo_rh/models/daily_summary.dart';

class AttendanceRepository {
  final ApiClient apiClient;

  AttendanceRepository(this.apiClient);

  Future<Map<String, dynamic>> getTodayStatus() async {
    final response = await apiClient.dio.get('/attendance/today');
    return decodeTodayResponse((response.data as Map).cast<String, dynamic>());
  }

  Future<AttendanceLog> checkIn() async {
    final response = await apiClient.dio.post('/attendance/check-in', data: {});
    return AttendanceLog.fromJson(response.data['data']);
  }

  Future<AttendanceLog> checkOut() async {
    final response = await apiClient.dio.post('/attendance/check-out', data: {});
    return AttendanceLog.fromJson(response.data['data']);
  }

  Future<DailySummary> getDailySummary(int employeeId) async {
    final response = await apiClient.dio.get('/employees/$employeeId/daily-summary');
    return DailySummary.fromJson(response.data['data']);
  }

  Future<List<AttendanceLog>> getHistory(int year, int month) async {
    final from = DateTime(year, month, 1);
    final to = DateTime(year, month + 1, 0);

    final response = await apiClient.dio.get('/attendance', queryParameters: {
      'date_from': '${from.year.toString().padLeft(4, '0')}-${from.month.toString().padLeft(2, '0')}-${from.day.toString().padLeft(2, '0')}',
      'date_to': '${to.year.toString().padLeft(4, '0')}-${to.month.toString().padLeft(2, '0')}-${to.day.toString().padLeft(2, '0')}',
      'per_page': 50
    });
    final items = response.data['data'] as List;
    return items.map((e) => AttendanceLog.fromJson(e)).toList();
  }

  static Map<String, dynamic> decodeTodayResponse(Map<String, dynamic> responseData) {
    final payload = responseData['data'];

    if (payload == null) {
      return {'log': null, 'context': responseData['context']};
    }

    if (payload is! Map) {
      throw const FormatException('Invalid attendance/today payload');
    }

    final data = payload.cast<String, dynamic>();
    final rawContext = data['context'] ?? responseData['context'];
    final context = rawContext is Map ? rawContext.cast<String, dynamic>() : null;

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
    final todayPayload = itemPayload is Map
        ? itemPayload.cast<String, dynamic>()
        : (data.containsKey('item') ? null : data);

    if (todayPayload == null) {
      return {'log': null, 'context': context};
    }

    final now = DateTime.now();
    final today = todayPayload.cast<String, dynamic>();

    return {
      'log': AttendanceLog(
        id: (today['id'] ?? 0) as int,
        employeeId: (today['employee_id'] ?? 0) as int,
        date: DateTime(now.year, now.month, now.day),
        checkIn: _parseLocalTime(today['check_in_time'] as String?),
        checkOut: _parseLocalTime(today['check_out_time'] as String?),
        status: (today['status'] ?? 'absent') as String,
        workedHours: today['hours_worked'] != null ? double.tryParse(today['hours_worked'].toString()) : 0.0,
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
}
