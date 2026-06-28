import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/onboarding_step.dart';

class OnboardingRepository {
  final ApiClient apiClient;

  OnboardingRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<List<OnboardingStep>> getChecklist() async {
    final response = await apiClient.requestWithRetry(
      '/onboarding-setup/checklist',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => OnboardingStep.fromJson(e)).toList();
  }

  Future<void> completeStep(int stepId) async {
    await apiClient.requestWithRetry(
      '/onboarding-setup/$stepId/complete',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<void> skipStep(int stepId) async {
    await apiClient.requestWithRetry(
      '/onboarding-setup/$stepId/skip',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }
}
