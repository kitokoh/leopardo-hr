import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/evaluation.dart';

class EvaluationRepository {
  final ApiClient apiClient;

  EvaluationRepository(this.apiClient);

  Future<List<Evaluation>> getMyEvaluations() async {
    final response = await apiClient.dio.get('/evaluations');
    final items = response.data['data'] as List;
    return items.map((e) => Evaluation.fromJson(e)).toList();
  }

  Future<Evaluation> acknowledgeEvaluation(int id) async {
    final response = await apiClient.dio.put('/evaluations/$id/acknowledge');
    return Evaluation.fromJson(response.data['data']);
  }
}
