import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/approval.dart';

class ApprovalRepository {
  final ApiClient apiClient;

  ApprovalRepository(this.apiClient);

  Future<List<Approval>> getPending() async {
    final response = await apiClient.dio.get('/approvals/pending');
    final items = response.data['data'] as List;
    return items.map((e) => Approval.fromJson(e)).toList();
  }

  Future<void> approve(int id, {String? comment}) async {
    await apiClient.dio.post(
      '/approvals/$id/approve',
      data: {if (comment != null) 'comment': comment},
    );
  }

  Future<void> reject(int id, {required String comment}) async {
    await apiClient.dio.post(
      '/approvals/$id/reject',
      data: {'comment': comment},
    );
  }
}
