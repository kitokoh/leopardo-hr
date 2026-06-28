import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/approval.dart';

class ApprovalRepository {
  final ApiClient apiClient;

  ApprovalRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<List<Approval>> getPending() async {
    final response = await apiClient.requestWithRetry(
      '/approvals/pending',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Approval.fromJson(e)).toList();
  }

  Future<void> approve(int id, {String? comment}) async {
    await apiClient.requestWithRetry(
      '/approvals/$id/approve',
      method: 'POST',
      data: {if (comment != null) 'comment': comment},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<void> reject(int id, {required String comment}) async {
    await apiClient.requestWithRetry(
      '/approvals/$id/reject',
      method: 'POST',
      data: {'comment': comment},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }
}
