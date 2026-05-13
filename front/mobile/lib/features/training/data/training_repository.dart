import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/training_enrollment.dart';

class TrainingRepository {
  final ApiClient apiClient;

  TrainingRepository(this.apiClient);

  Future<List<TrainingEnrollment>> getMyEnrollments() async {
    final response = await apiClient.dio.get('/me/training-enrollments');
    final items = response.data['data'] as List;
    return items.map((e) => TrainingEnrollment.fromJson(e)).toList();
  }
}
