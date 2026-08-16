import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/models/onboarding_step.dart';

/// #4529 — factory du provider checklist onboarding.
///
/// Le provider était dupliqué à l'identique dans leopardo_manager,
/// leopardo_hr et leopardo_employee (seule l'importation du repository
/// changeait). Chaque app injecte son loader (son `onboardingRepositoryProvider`
/// local — le repository reste dupliqué par app, résiduel #4529) et reçoit le
/// provider canonique.
typedef OnboardingChecklistLoader =
    Future<List<OnboardingStep>> Function(Ref ref);

FutureProvider<List<OnboardingStep>> onboardingChecklistProviderFor(
  OnboardingChecklistLoader load,
) {
  return FutureProvider<List<OnboardingStep>>((ref) async {
    return load(ref);
  });
}
