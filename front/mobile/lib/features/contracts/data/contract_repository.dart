import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/contract.dart';

class ContractRepository {
  final ApiClient apiClient;

  ContractRepository(this.apiClient);

  Future<List<Contract>> getMyContracts() async {
    final response = await apiClient.dio.get('/me/contracts');
    final items = response.data['data'] as List;
    return items.map((e) => Contract.fromJson(e)).toList();
  }
}
