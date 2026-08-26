import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/features/auth/data/two_factor_repository.dart';
import 'package:leopardo_core/features/settings/data/settings_repository.dart';

/// Provider du repository settings (unifié hr/manager, issue #5514).
final settingsRepositoryProvider = Provider<SettingsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return SettingsRepository(apiClient, preferences);
});

/// Provider du repository 2FA (enrôlement, codes de récupération,
/// désactivation) — issue #5683, endpoints TwoFactorAuthController #5436.
final twoFactorRepositoryProvider = Provider<TwoFactorRepository>((ref) {
  return TwoFactorRepository(ref.watch(apiClientProvider));
});
