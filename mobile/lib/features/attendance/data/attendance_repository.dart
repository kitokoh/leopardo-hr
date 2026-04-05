import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/attendance_log.dart';
import 'package:leopardo_rh/models/daily_summary.dart';

class AttendanceRepository {
  final ApiClient apiClient;

  AttendanceRepository(this.apiClient);

  Future<Map<String, dynamic>> getTodayStatus() async {
    final response = await apiClient.dio.get('/attendance/today');
    final data = (response.data as Map).cast<String, dynamic>();

    if (data['data'] == null) {
      return {'log': null, 'context': data['context']};
    }

    final today = (data['data'] as Map).cast<String, dynamic>();
    final now = DateTime.now();

    DateTime? parseLocalTime(String? hhmm) {
      if (hhmm == null || hhmm.isEmpty) return null;
      final parts = hhmm.split(':');
      if (parts.length < 2) return null;
      final hour = int.tryParse(parts[0]) ?? 0;
      final minute = int.tryParse(parts[1]) ?? 0;
      return DateTime(now.year, now.month, now.day, hour, minute);
    }

    return {
      'log': AttendanceLog(
        id: (today['id'] ?? 0) as int,
        employeeId: today['employee_id'] as int,
        date: DateTime(now.year, now.month, now.day),
        checkIn: parseLocalTime(today['check_in_time'] as String?),
        checkOut: parseLocalTime(today['check_out_time'] as String?),
        status: (today['status'] ?? 'absent') as String,
        workedHours: today['hours_worked'] != null ? double.tryParse(today['hours_worked'].toString()) : 0.0,
      ),
      'context': data['context'],
    };
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
}
