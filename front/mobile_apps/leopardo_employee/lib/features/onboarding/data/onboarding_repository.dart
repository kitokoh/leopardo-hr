import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/onboarding_step.dart';

class OnboardingRepository {
  final ApiClient apiClient;

  OnboardingRepository(this.apiClient);

  Future<List<OnboardingStep>> getChecklist() async {
    final response = await apiClient.dio.get('/onboarding-setup/checklist');
    final items = response.data['data'] as List;
    return items.map((e) => OnboardingStep.fromJson(e)).toList();
  }

  Future<void> completeStep(int stepId) async {
    await apiClient.dio.post('/onboarding-setup/$stepId/complete');
  }

  Future<void> skipStep(int stepId) async {
    await apiClient.dio.post('/onboarding-setup/$stepId/skip');
  }
}
