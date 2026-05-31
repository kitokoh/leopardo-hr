import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/training_enrollment.dart';

class TrainingRepository {
  final ApiClient apiClient;

  TrainingRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);

  Future<List<TrainingEnrollment>> getMyEnrollments() async {
    final response = await apiClient.requestWithRetry(
      '/me/training-enrollments',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => TrainingEnrollment.fromJson(e)).toList();
  }
}
