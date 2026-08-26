import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/features/auth/data/two_factor_service.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

final appPreferencesProvider = Provider<AppPreferences>((ref) {
  return AppPreferences();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return ApiClient(storage, preferences);
});

/// Issue #5627 — Service 2FA partagé (employee + manager).
final twoFactorServiceProvider = Provider<TwoFactorService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return TwoFactorService(apiClient, storage);
});
