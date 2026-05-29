import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';

class PlatformAuthState {
  const PlatformAuthState({
    this.user,
    this.isBootstrapping = true,
    this.isSubmitting = false,
    this.error,
    this.requiresTwoFactor = false,
  });

  final PlatformAdminUser? user;
  final bool isBootstrapping;
  final bool isSubmitting;
  final String? error;
  final bool requiresTwoFactor;

  bool get isAuthenticated => user != null;

  PlatformAuthState copyWith({
    PlatformAdminUser? user,
    bool? isBootstrapping,
    bool? isSubmitting,
    String? error,
    bool? requiresTwoFactor,
    bool clearUser = false,
    bool clearError = false,
  }) {
    return PlatformAuthState(
      user: clearUser ? null : user ?? this.user,
      isBootstrapping: isBootstrapping ?? this.isBootstrapping,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      error: clearError ? null : error ?? this.error,
      requiresTwoFactor: requiresTwoFactor ?? this.requiresTwoFactor,
    );
  }
}

final platformAuthControllerProvider =
    NotifierProvider<PlatformAuthController, PlatformAuthState>(
      PlatformAuthController.new,
    );

class PlatformAuthController extends Notifier<PlatformAuthState> {
  @override
  PlatformAuthState build() {
    Future.microtask(_hydrate);
    return const PlatformAuthState();
  }

  Future<void> _hydrate() async {
    try {
      final user = await ref.read(platformRepositoryProvider).me();
      state = state.copyWith(user: user, isBootstrapping: false);
    } catch (_) {
      state = state.copyWith(
        clearUser: true,
        isBootstrapping: false,
        clearError: true,
        requiresTwoFactor: false,
      );
    }
  }

  Future<void> login({
    required String email,
    required String password,
    String? twoFactorCode,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final user = await ref
          .read(platformRepositoryProvider)
          .login(
            email: email,
            password: password,
            twoFactorCode: twoFactorCode,
          );
      state = state.copyWith(
        user: user,
        isSubmitting: false,
        requiresTwoFactor: false,
      );
    } catch (error) {
      if (error is ApiException && error.code == 'TWO_FA_REQUIRED') {
        state = state.copyWith(
          isSubmitting: false,
          requiresTwoFactor: true,
          error: error.message,
        );
        return;
      }

      state = state.copyWith(
        isSubmitting: false,
        error: error.toString().replaceFirst('Exception: ', ''),
      );
    }
  }

  Future<void> logout() async {
    await ref.read(platformRepositoryProvider).logout();
    state = state.copyWith(
      clearUser: true,
      clearError: true,
      requiresTwoFactor: false,
    );
  }
}
