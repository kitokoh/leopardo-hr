import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/salary_advance.dart';

class SalaryAdvanceRepository {
  final ApiClient apiClient;

  SalaryAdvanceRepository(this.apiClient);

  Future<List<SalaryAdvance>> getMySalaryAdvances() async {
    final response = await apiClient.requestWithRetry('/salary-advances');
    final items = extractDataList(response.data);
    return items
        .map((e) => SalaryAdvance.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<SalaryAdvance> requestAdvance({
    required double amount,
    String? reason,
    int? repaymentMonths,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances',
      method: 'POST',
      data: {
        'amount': amount,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
        if (repaymentMonths != null) 'repayment_months': repaymentMonths,
      },
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> cancelAdvance(int advanceId) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId',
      method: 'DELETE',
      maxRetriesOverride: 0,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> confirmReceived(int advanceId) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/confirm-received',
      method: 'PUT',
      maxRetriesOverride: 0,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }
}
