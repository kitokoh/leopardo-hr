import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/evaluation.dart';

class EvaluationRepository {
  final ApiClient apiClient;

  EvaluationRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<List<Evaluation>> getMyEvaluations() async {
    final response = await apiClient.requestWithRetry(
      '/evaluations',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Evaluation.fromJson(e)).toList();
  }

  Future<Evaluation> acknowledgeEvaluation(int id) async {
    final response = await apiClient.requestWithRetry(
      '/evaluations/$id/acknowledge',
      method: 'PUT',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return Evaluation.fromJson(extractDataMap(response.data));
  }
}
