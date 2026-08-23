import 'package:leopardo_core/core/providers/core_providers.dart';
export 'package:leopardo_core/core/providers/core_providers.dart';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_hr/features/auth/data/auth_repository.dart';
import 'package:leopardo_hr/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_hr/features/settings/data/settings_repository.dart';
import 'package:leopardo_hr/features/user_auth/data/user_auth_repository.dart';
import 'package:leopardo_hr/features/contracts/data/contract_repository.dart';
import 'package:leopardo_hr/features/onboarding/data/onboarding_repository.dart';

// ── Providers spécifiques à leopardo_hr (issue #5279, lot 1) ───────────────
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

final userAuthRepositoryProvider = Provider<UserAuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return UserAuthRepository(apiClient, storage, preferences);
});

final contractRepositoryProvider = Provider<ContractRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ContractRepository(apiClient);
});

final onboardingRepositoryProvider = Provider<OnboardingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return OnboardingRepository(apiClient);
});
