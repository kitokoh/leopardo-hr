import 'package:leopardo_core/core/providers/core_providers.dart';
export 'package:leopardo_core/core/providers/core_providers.dart';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/features/onboarding/data/onboarding_repository.dart';
import 'package:leopardo_manager/features/ai_chat/data/ai_chat_repository.dart';
import 'package:leopardo_manager/features/vehicle_position/data/vehicle_position_repository.dart';
import 'package:leopardo_manager/features/approvals/data/approval_repository.dart';

// ── Providers spécifiques à leopardo_manager (issue #5279, lot 1) ──────────
// Les providers communs vivent dans leopardo_core (re-export ci-dessus).

final onboardingRepositoryProvider = Provider<OnboardingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return OnboardingRepository(apiClient);
});

final aiChatRepositoryProvider = Provider<AiChatRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AiChatRepository(apiClient);
});

final vehiclePositionRepositoryProvider = Provider<VehiclePositionRepository>((
  ref,
) {
  final apiClient = ref.watch(apiClientProvider);
  return VehiclePositionRepository(apiClient);
});

final approvalRepositoryProvider = Provider<ApprovalRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ApprovalRepository(apiClient);
});
