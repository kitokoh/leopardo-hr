import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/payroll.dart';

class PayrollRepository {
  final ApiClient apiClient;

  PayrollRepository(this.apiClient);

  Future<List<Payroll>> getMyPayrolls() async {
    final response = await apiClient.dio.get('/payrolls');
    final items = response.data['data'] as List;
    return items.map((e) => Payroll.fromJson(e)).toList();
  }
}
