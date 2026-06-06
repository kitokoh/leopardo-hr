import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

import '../features/platform/platform_repository.dart';
import '../features/platform/platform_models.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) => SecureStorage());

final appPreferencesProvider = Provider<AppPreferences>(
  (ref) => AppPreferences(),
);

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    ref.watch(secureStorageProvider),
    ref.watch(appPreferencesProvider),
  );
});

final platformRepositoryProvider = Provider<PlatformRepository>((ref) {
  return PlatformRepository(
    ref.watch(apiClientProvider),
    ref.watch(secureStorageProvider),
  );
});

final platformCountryDefaultsProvider =
    FutureProvider<List<PlatformCountryDefault>>((ref) {
      return ref.watch(platformRepositoryProvider).countryDefaults();
    });
