import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_cameras/features/auth/data/cameras_auth_repository.dart';
import 'package:leopardo_cameras/features/auth/providers/cameras_auth_provider.dart';
import 'package:leopardo_cameras/features/cameras/data/cameras_repository.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/providers/base_providers.dart' as core;

// Ré-export des providers de base de leopardo_core (stockage sécurisé + prefs).
final secureStorageProvider = core.secureStorageProvider;
final appPreferencesProvider = core.appPreferencesProvider;

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return ApiClient(storage, preferences);
});

final authRepositoryProvider = Provider<CamerasAuthRepository>((ref) {
  return CamerasAuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(secureStorageProvider),
    ref.watch(appPreferencesProvider),
  );
});

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.watch(authRepositoryProvider));
});

final camerasRepositoryProvider = Provider<CamerasRepository>((ref) {
  return CamerasRepository(ref.watch(apiClientProvider));
});
