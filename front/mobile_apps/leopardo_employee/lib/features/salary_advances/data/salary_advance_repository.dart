import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/salary_advance.dart';

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
}
