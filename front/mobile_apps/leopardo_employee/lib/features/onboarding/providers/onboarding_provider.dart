import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/features/onboarding/onboarding_checklist_provider.dart';

/// #4529 : provider dédupliqué — la logique vit dans leopardo_core
/// (onboardingChecklistProviderFor) ; chaque app injecte son repository.
final onboardingChecklistProvider = onboardingChecklistProviderFor(
  (ref) => ref.watch(onboardingRepositoryProvider).getChecklist(),
);
