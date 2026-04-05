import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/attendance_log.dart';
import 'package:leopardo_rh/models/daily_summary.dart';

class AttendanceRepository {
  final ApiClient apiClient;

  AttendanceRepository(this.apiClient);

  Future<Map<String, dynamic>> getTodayStatus() async {
    final response = await apiClient.dio.get('/attendance/today');
    final data = response.data;
    
    if (data['data'] == null) {
        return {'log': null, 'context': data['context']};
    }

    return {
      'log': AttendanceLog.fromJson(data['data']),
      'context': data['context'],
    };
  }

  Future<AttendanceLog> checkIn() async {
    final response = await apiClient.dio.post('/attendance/check-in', data: {
      'timestamp': DateTime.now().toIso8601String(),
    });
    return AttendanceLog.fromJson(response.data['data']);
  }

  Future<AttendanceLog> checkOut() async {
    final response = await apiClient.dio.post('/attendance/check-out', data: {
      'timestamp': DateTime.now().toIso8601String(),
    });
    return AttendanceLog.fromJson(response.data['data']);
  }

  Future<DailySummary> getDailySummary(int employeeId) async {
    final response = await apiClient.dio.get('/employees/$employeeId/daily-summary');
    return DailySummary.fromJson(response.data['data']);
  }

  Future<List<AttendanceLog>> getHistory(int year, int month) async {
    final response = await apiClient.dio.get('/attendance', queryParameters: {
      'year': year,
      'month': month,
      'per_page': 50
    });
    final items = response.data['data'] as List;
    return items.map((e) => AttendanceLog.fromJson(e)).toList();
  }
}
