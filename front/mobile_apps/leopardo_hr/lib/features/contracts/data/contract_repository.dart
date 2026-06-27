import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/contract.dart';

class ContractRepository {
  final ApiClient apiClient;

  ContractRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);

  Future<List<Contract>> getMyContracts() async {
    final response = await apiClient.requestWithRetry(
      '/me/contracts',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Contract.fromJson(e)).toList();
  }
}
