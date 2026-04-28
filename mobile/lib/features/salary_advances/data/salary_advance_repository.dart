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
  }) async {
    final response = await apiClient.dio.post('/salary-advances', data: {
      'amount': amount,
      'reason': reason,
    });
    return SalaryAdvance.fromJson(response.data['data']);
  }
}
