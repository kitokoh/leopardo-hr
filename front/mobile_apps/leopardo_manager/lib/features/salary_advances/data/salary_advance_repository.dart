import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/salary_advance.dart';

class SalaryAdvanceRepository {
  final ApiClient apiClient;

  SalaryAdvanceRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 10);

  Future<List<SalaryAdvance>> getMySalaryAdvances() async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances',
      maxRetriesOverride: 1,
      timeoutOverride: _readTimeout,
    );
    final items = response.data['data'] as List;
    return items.map((e) => SalaryAdvance.fromJson(e)).toList();
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
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }

  Future<SalaryAdvance> cancelAdvance(int advanceId) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }

  Future<SalaryAdvance> approveAdvance({
    required int advanceId,
    String? comment,
    int? repaymentMonths,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/approve',
      method: 'PUT',
      data: {
        if (comment != null && comment.trim().isNotEmpty)
          'decision_comment': comment.trim(),
        if (repaymentMonths != null) 'repayment_months': repaymentMonths,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }

  Future<SalaryAdvance> rejectAdvance({
    required int advanceId,
    required String comment,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/salary-advances/$advanceId/reject',
      method: 'PUT',
      data: {'decision_comment': comment.trim()},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(response.data['data']);
  }
}
