import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/absence.dart';

class AbsenceRepository {
  final ApiClient apiClient;

  AbsenceRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 10);

  Future<List<Absence>> getMyAbsences() async {
    final response = await apiClient.requestWithRetry(
      '/absences',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map((e) => Absence.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<List<Map<String, dynamic>>> getLeaveBalances() async {
    final response = await apiClient.requestWithRetry(
      '/leave-balances',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.cast<Map<String, dynamic>>();
  }

  Future<Absence> requestAbsence({
    required int absenceTypeId,
    required DateTime startDate,
    required DateTime endDate,
    String? reason,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/absences',
      method: 'POST',
      data: {
        'absence_type_id': absenceTypeId,
        'start_date': startDate.toIso8601String().split('T')[0],
        'end_date': endDate.toIso8601String().split('T')[0],
        'reason': reason,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }

  Future<Absence> cancelAbsence(int absenceId) async {
    final response = await apiClient.requestWithRetry(
      '/absences/$absenceId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }

  Future<Absence> approveAbsence(int absenceId) async {
    final response = await apiClient.requestWithRetry(
      '/absences/$absenceId/approve',
      method: 'PUT',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }

  Future<Absence> rejectAbsence({
    required int absenceId,
    required String reason,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/absences/$absenceId/reject',
      method: 'PUT',
      data: {'rejected_reason': reason.trim()},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Absence.fromJson(extractDataMap(response.data));
  }
}
