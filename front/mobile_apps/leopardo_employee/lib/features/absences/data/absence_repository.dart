import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/absence.dart';

class AbsenceRepository {
  final ApiClient apiClient;

  AbsenceRepository(this.apiClient);

  Future<List<Absence>> getMyAbsences() async {
    final response = await apiClient.dio.get('/absences');
    final items = response.data['data'] as List;
    return items.map((e) => Absence.fromJson(e)).toList();
  }

  Future<List<Map<String, dynamic>>> getLeaveBalances() async {
    final response = await apiClient.dio.get('/leave-balances');
    final items = response.data['data'] as List;
    return items.cast<Map<String, dynamic>>();
  }

  Future<Absence> requestAbsence({
    required int absenceTypeId,
    required DateTime startDate,
    required DateTime endDate,
    String? reason,
  }) async {
    final response = await apiClient.dio.post(
      '/absences',
      data: {
        'absence_type_id': absenceTypeId,
        'start_date': startDate.toIso8601String().split('T')[0],
        'end_date': endDate.toIso8601String().split('T')[0],
        'reason': reason,
      },
    );
    return Absence.fromJson(response.data['data']);
  }

  Future<Absence> cancelAbsence(int absenceId) async {
    final response = await apiClient.dio.delete('/absences/$absenceId');
    return Absence.fromJson(response.data['data']);
  }
}
