import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/providers/base_providers.dart' as core;
import 'package:leopardo_accounting/features/auth/data/auth_repository.dart';
import 'package:leopardo_accounting/features/auth/providers/auth_provider.dart';

// Ré-export des providers de base de leopardo_core (stockage sécurisé + prefs).
final secureStorageProvider = core.secureStorageProvider;
final appPreferencesProvider = core.appPreferencesProvider;

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return ApiClient(storage, preferences);
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(secureStorageProvider),
    ref.watch(appPreferencesProvider),
  );
});

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.watch(authRepositoryProvider));
});
