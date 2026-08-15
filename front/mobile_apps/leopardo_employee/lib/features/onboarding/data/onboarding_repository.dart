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

  // T115 (QA omnichannel 2026-08-15) : le backend expose
  // PATCH /onboarding-setup/{stepKey}/complete|skip (clé string) — l'ancien
  // appel POST + id numérique renvoyait 405/404.
  Future<void> completeStep(String stepKey) async {
    await apiClient.requestWithRetry(
      '/onboarding-setup/$stepKey/complete',
      method: 'PATCH',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<void> skipStep(String stepKey) async {
    await apiClient.requestWithRetry(
      '/onboarding-setup/$stepKey/skip',
      method: 'PATCH',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }
}
