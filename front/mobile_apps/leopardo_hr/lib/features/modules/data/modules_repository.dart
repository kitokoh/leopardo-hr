import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/app_notification.dart';
import 'package:leopardo_core/models/evaluation.dart';
import 'package:leopardo_core/models/payroll_record.dart';
import 'package:leopardo_core/models/salary_advance.dart';

class ModulesRepository {
  ModulesRepository(this._apiClient);

  final ApiClient _apiClient;

  static const _actionTimeout = Duration(seconds: 12);
  static const _readTimeout = Duration(seconds: 10);

  Future<List<Evaluation>> listEvaluations({int perPage = 30}) async {
    final response = await _apiClient.requestWithRetry(
      '/evaluations',
      queryParameters: {'per_page': perPage},
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map(
          (item) => Evaluation.fromJson((item as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<Evaluation> createEvaluation({
    required int employeeId,
    required String period,
    double? score,
    String? strengths,
    String? improvements,
    String? overallComment,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/evaluations',
      method: 'POST',
      data: {
        'employee_id': employeeId,
        'period': period.trim(),
        if (score != null) 'score': score,
        if (strengths != null && strengths.trim().isNotEmpty)
          'strengths': strengths.trim(),
        if (improvements != null && improvements.trim().isNotEmpty)
          'improvements': improvements.trim(),
        if (overallComment != null && overallComment.trim().isNotEmpty)
          'overall_comment': overallComment.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Evaluation.fromJson(extractDataMap(response.data));
  }

  Future<Evaluation> submitEvaluation(int evaluationId) async {
    final response = await _apiClient.requestWithRetry(
      '/evaluations/$evaluationId/submit',
      method: 'PUT',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Evaluation.fromJson(extractDataMap(response.data));
  }

  Future<Evaluation> acknowledgeEvaluation(int evaluationId) async {
    final response = await _apiClient.requestWithRetry(
      '/evaluations/$evaluationId/acknowledge',
      method: 'PUT',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Evaluation.fromJson(extractDataMap(response.data));
  }

  Future<void> deleteEvaluation(int evaluationId) async {
    await _apiClient.requestWithRetry(
      '/evaluations/$evaluationId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<List<SalaryAdvance>> listSalaryAdvances({int perPage = 30}) async {
    final response = await _apiClient.requestWithRetry(
      '/salary-advances',
      queryParameters: {'per_page': perPage},
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map(
          (item) =>
              SalaryAdvance.fromJson((item as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<SalaryAdvance> createSalaryAdvance({
    required double amount,
    required String reason,
    required int repaymentMonths,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/salary-advances',
      method: 'POST',
      data: {
        'amount': amount,
        'reason': reason.trim(),
        'repayment_months': repaymentMonths,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> approveSalaryAdvance(
    int advanceId, {
    String? decisionComment,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/salary-advances/$advanceId/approve',
      method: 'PUT',
      data: {
        if (decisionComment != null && decisionComment.trim().isNotEmpty)
          'decision_comment': decisionComment.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> rejectSalaryAdvance(
    int advanceId, {
    String? decisionComment,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/salary-advances/$advanceId/reject',
      method: 'PUT',
      data: {
        if (decisionComment != null && decisionComment.trim().isNotEmpty)
          'decision_comment': decisionComment.trim(),
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<SalaryAdvance> cancelSalaryAdvance(int advanceId) async {
    final response = await _apiClient.requestWithRetry(
      '/salary-advances/$advanceId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return SalaryAdvance.fromJson(extractDataMap(response.data));
  }

  Future<List<PayrollRecord>> listMyPaySlips({int perPage = 50}) async {
    final response = await _apiClient.requestWithRetry(
      '/me/pay-slips',
      queryParameters: {'per_page': perPage},
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map(
          (item) => PayrollRecord.fromMePaySlipJson(
            (item as Map).cast<String, dynamic>(),
          ),
        )
        .toList();
  }

  Future<List<PayrollRecord>> listPayrolls({int perPage = 30}) async {
    final response = await _apiClient.requestWithRetry(
      '/payrolls',
      queryParameters: {'per_page': perPage},
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .map(
          (item) =>
              PayrollRecord.fromJson((item as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<PayrollRecord> createPayroll({
    required int employeeId,
    required int periodMonth,
    required int periodYear,
    required double grossSalary,
    double bonuses = 0,
    double deductions = 0,
    double overtimeAmount = 0,
    double cotisations = 0,
    double irAmount = 0,
    double advanceDeduction = 0,
    double absenceDeduction = 0,
    double penaltyDeduction = 0,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/payrolls',
      method: 'POST',
      data: {
        'employee_id': employeeId,
        'period_month': periodMonth,
        'period_year': periodYear,
        'gross_salary': grossSalary,
        'bonuses': bonuses,
        'deductions': deductions,
        'overtime_amount': overtimeAmount,
        'cotisations': cotisations,
        'ir_amount': irAmount,
        'advance_deduction': advanceDeduction,
        'absence_deduction': absenceDeduction,
        'penalty_deduction': penaltyDeduction,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return PayrollRecord.fromJson(extractDataMap(response.data));
  }

  Future<PayrollRecord> validatePayroll(int payrollId) async {
    final response = await _apiClient.requestWithRetry(
      '/payrolls/$payrollId/validate',
      method: 'PUT',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return PayrollRecord.fromJson(extractDataMap(response.data));
  }

  Future<void> deletePayroll(int payrollId) async {
    await _apiClient.requestWithRetry(
      '/payrolls/$payrollId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<List<AppNotification>> listNotifications({
    bool unreadOnly = false,
    int perPage = 30,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/notifications',
      queryParameters: {'unread': unreadOnly, 'per_page': perPage},
      timeoutOverride: const Duration(seconds: 12),
    );
    final items = extractDataList(response.data);
    return items
        .map(
          (item) =>
              AppNotification.fromJson((item as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<AppNotification> markNotificationRead(int notificationId) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/notifications/$notificationId/read',
      method: 'PUT',
      timeoutOverride: const Duration(seconds: 12),
    );
    return AppNotification.fromJson(extractDataMap(response.data));
  }

  Future<void> markAllNotificationsRead() async {
    await _apiClient.requestWithRetry<void>(
      '/notifications/read-all',
      method: 'PUT',
      timeoutOverride: const Duration(seconds: 12),
    );
  }

  Future<void> deleteNotification(int notificationId) async {
    await _apiClient.requestWithRetry<void>(
      '/notifications/$notificationId',
      method: 'DELETE',
      timeoutOverride: const Duration(seconds: 12),
    );
  }
}
