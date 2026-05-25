import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/onboarding_step.dart';

final onboardingChecklistProvider = FutureProvider<List<OnboardingStep>>((
  ref,
) async {
  final repo = ref.watch(onboardingRepositoryProvider);
  return await repo.getChecklist();
});
