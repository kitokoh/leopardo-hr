import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/salary_advance.dart';

class SalaryAdvanceRepository {
  final ApiClient apiClient;

  SalaryAdvanceRepository(this.apiClient);

  Future<List<SalaryAdvance>> getMySalaryAdvances() async {
    final response = await apiClient.dio.get('/salary-advances');
    final items = response.data['data'] as List;
    return items.map((e) => SalaryAdvance.fromJson(e)).toList();
  }

  Future<SalaryAdvance> requestAdvance({
    required double amount,
    String? reason,
    int? repaymentMonths,
  }) async {
    final response = await apiClient.dio.post(
      '/salary-advances',
      data: {
        'amount': amount,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
        if (repaymentMonths != null) 'repayment_months': repaymentMonths,
      },
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }

  Future<SalaryAdvance> cancelAdvance(int advanceId) async {
    final response = await apiClient.dio.delete('/salary-advances/$advanceId');
    return SalaryAdvance.fromJson(response.data['data']);
  }

  Future<SalaryAdvance> approveAdvance({
    required int advanceId,
    String? comment,
    int? repaymentMonths,
  }) async {
    final response = await apiClient.dio.put(
      '/salary-advances/$advanceId/approve',
      data: {
        if (comment != null && comment.trim().isNotEmpty)
          'decision_comment': comment.trim(),
        if (repaymentMonths != null) 'repayment_months': repaymentMonths,
      },
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }

  Future<SalaryAdvance> rejectAdvance({
    required int advanceId,
    required String comment,
  }) async {
    final response = await apiClient.dio.put(
      '/salary-advances/$advanceId/reject',
      data: {'decision_comment': comment.trim()},
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }
}
