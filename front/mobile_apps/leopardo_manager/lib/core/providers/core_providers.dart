import 'package:leopardo_core/core/providers/core_providers.dart';
export 'package:leopardo_core/core/providers/core_providers.dart';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/features/auth/data/auth_repository.dart';
import 'package:leopardo_manager/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_manager/features/settings/data/settings_repository.dart';
import 'package:leopardo_manager/features/onboarding/data/onboarding_repository.dart';
import 'package:leopardo_manager/features/ai_chat/data/ai_chat_repository.dart';
import 'package:leopardo_manager/features/vehicle_position/data/vehicle_position_repository.dart';
import 'package:leopardo_manager/features/approvals/data/approval_repository.dart';

// ── Providers spécifiques à leopardo_manager (issue #5279, lot 1) ──────────
// Les providers communs vivent dans leopardo_core (re-export ci-dessus).

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return AuthRepository(apiClient, storage, preferences);
});

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AttendanceRepository(apiClient);
});

final settingsRepositoryProvider = Provider<SettingsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return SettingsRepository(apiClient, preferences);
});

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
