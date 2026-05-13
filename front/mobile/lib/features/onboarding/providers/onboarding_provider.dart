import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/onboarding_step.dart';

final onboardingChecklistProvider =
    FutureProvider<List<OnboardingStep>>((ref) async {
  final repo = ref.watch(onboardingRepositoryProvider);
  return await repo.getChecklist();
});
