import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/app_notification.dart';
import 'package:leopardo_rh/models/evaluation.dart';
import 'package:leopardo_rh/models/payroll_record.dart';
import 'package:leopardo_rh/models/salary_advance.dart';

class ModulesRepository {
  ModulesRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<Evaluation>> listEvaluations({int perPage = 30}) async {
    final response = await _apiClient.dio.get(
      '/evaluations',
      queryParameters: {'per_page': perPage},
    );
    final items = response.data['data'] as List;
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
    final response = await _apiClient.dio.post(
      '/evaluations',
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
    );
    return Evaluation.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<Evaluation> submitEvaluation(int evaluationId) async {
    final response = await _apiClient.dio.put(
      '/evaluations/$evaluationId/submit',
    );
    return Evaluation.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<Evaluation> acknowledgeEvaluation(int evaluationId) async {
    final response = await _apiClient.dio.put(
      '/evaluations/$evaluationId/acknowledge',
    );
    return Evaluation.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<void> deleteEvaluation(int evaluationId) async {
    await _apiClient.dio.delete('/evaluations/$evaluationId');
  }

  Future<List<SalaryAdvance>> listSalaryAdvances({int perPage = 30}) async {
    final response = await _apiClient.dio.get(
      '/salary-advances',
      queryParameters: {'per_page': perPage},
    );
    final items = response.data['data'] as List;
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
    final response = await _apiClient.dio.post(
      '/salary-advances',
      data: {
        'amount': amount,
        'reason': reason.trim(),
        'repayment_months': repaymentMonths,
      },
    );
    return SalaryAdvance.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<SalaryAdvance> approveSalaryAdvance(
    int advanceId, {
    String? decisionComment,
  }) async {
    final response = await _apiClient.dio.put(
      '/salary-advances/$advanceId/approve',
      data: {
        if (decisionComment != null && decisionComment.trim().isNotEmpty)
          'decision_comment': decisionComment.trim(),
      },
    );
    return SalaryAdvance.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<SalaryAdvance> rejectSalaryAdvance(
    int advanceId, {
    String? decisionComment,
  }) async {
    final response = await _apiClient.dio.put(
      '/salary-advances/$advanceId/reject',
      data: {
        if (decisionComment != null && decisionComment.trim().isNotEmpty)
          'decision_comment': decisionComment.trim(),
      },
    );
    return SalaryAdvance.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<SalaryAdvance> cancelSalaryAdvance(int advanceId) async {
    final response = await _apiClient.dio.delete('/salary-advances/$advanceId');
    return SalaryAdvance.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<List<PayrollRecord>> listPayrolls({int perPage = 30}) async {
    final response = await _apiClient.dio.get(
      '/payrolls',
      queryParameters: {'per_page': perPage},
    );
    final items = response.data['data'] as List;
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
    final response = await _apiClient.dio.post(
      '/payrolls',
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
    );
    return PayrollRecord.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<PayrollRecord> validatePayroll(int payrollId) async {
    final response = await _apiClient.dio.put('/payrolls/$payrollId/validate');
    return PayrollRecord.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<void> deletePayroll(int payrollId) async {
    await _apiClient.dio.delete('/payrolls/$payrollId');
  }

  Future<List<AppNotification>> listNotifications({
    bool unreadOnly = false,
    int perPage = 30,
  }) async {
    final response = await _apiClient.dio.get(
      '/notifications',
      queryParameters: {'unread_only': unreadOnly, 'per_page': perPage},
    );
    final items = response.data['data'] as List;
    return items
        .map(
          (item) =>
              AppNotification.fromJson((item as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<AppNotification> markNotificationRead(int notificationId) async {
    final response = await _apiClient.dio.put(
      '/notifications/$notificationId/read',
    );
    return AppNotification.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<void> markAllNotificationsRead() async {
    await _apiClient.dio.put('/notifications/read-all');
  }

  Future<void> deleteNotification(int notificationId) async {
    await _apiClient.dio.delete('/notifications/$notificationId');
  }
}
