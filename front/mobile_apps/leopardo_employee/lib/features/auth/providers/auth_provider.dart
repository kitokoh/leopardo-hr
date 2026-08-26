import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/session_expired_handler.dart';
import 'package:leopardo_core/core/services/push_notification_service.dart';
import 'package:leopardo_employee/features/auth/data/auth_repository.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

class AuthState {
  final bool isLoading;
  final Employee? employee;
  final String? error;
  /// Token de challenge 2FA (non null → l'utilisateur doit saisir son code TOTP).
  final String? mfaChallengeToken;

  AuthState({this.isLoading = false, this.employee, this.error, this.mfaChallengeToken});

  AuthState copyWith({
    bool? isLoading,
    Employee? employee,
    String? error,
    bool clearError = false,
    String? mfaChallengeToken,
    bool clearMfaChallenge = false,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      employee: employee ?? this.employee,
      error: clearError ? null : (error ?? this.error),
      mfaChallengeToken: clearMfaChallenge ? null : (mfaChallengeToken ?? this.mfaChallengeToken),
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  static const _startupAuthTimeout = Duration(seconds: 12);

  final AuthRepository _repository;
  final PushNotificationService _pushNotifications;
  final ApiClient _apiClient;

  AuthNotifier(this._repository, this._pushNotifications, this._apiClient)
      : super(AuthState(isLoading: true)) {
    // Démarrer isLoading:true immédiatement pour éviter un flash /welcome
    // avant que checkAuth() ne s'exécute (race GoRouter vs async init).
    Future.microtask(checkAuth);
  }

  Future<void> checkAuth() async {
    state = state.copyWith(isLoading: true);
    try {
      final data = await _repository.checkAuth().timeout(_startupAuthTimeout);
      if (data != null) {
        state = state.copyWith(isLoading: false, employee: data['employee']);
      } else {
        state = state.copyWith(isLoading: false);
      }
    } catch (e) {
      // Sécurité : si checkAuth lève une exception non gérée, sortir du loading
      // pour éviter que l'app reste bloquée sur le splash indéfiniment.
      state = state.copyWith(isLoading: false);
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, clearError: true, clearMfaChallenge: true);
    try {
      final data = await _repository.login(email, password);

      // #5627 — Réponse 2FA challenge : pas encore authentifié, rediriger
      // l'utilisateur vers l'écran de saisie du code TOTP.
      if (data.containsKey('mfa_challenge_token')) {
        state = state.copyWith(
          isLoading: false,
          mfaChallengeToken: data['mfa_challenge_token'] as String,
        );
        return false; // Pas encore connecté — challenge en attente.
      }

      state = state.copyWith(isLoading: false, employee: data['employee']);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  /// Vérifie le code TOTP (ou de récupération) après un challenge 2FA.
  /// Appelé depuis [TwoFactorChallengeScreen].
  Future<bool> verifyMfaChallenge({String? code, String? recoveryCode}) async {
    final challengeToken = state.mfaChallengeToken;
    if (challengeToken == null) return false;

    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.verifyMfaChallenge(
        challengeToken: challengeToken,
        code: code,
        recoveryCode: recoveryCode,
      );
      state = state.copyWith(
        isLoading: false,
        employee: data['employee'],
        clearMfaChallenge: true,
      );
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> loginWithGoogle() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.loginWithGoogle();
      state = state.copyWith(isLoading: false, employee: data['employee']);
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.register(
        firstName: firstName,
        lastName: lastName,
        email: email,
        password: password,
      );
      state = state.copyWith(isLoading: false, employee: data['employee']);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<void> logout() async {
    await _pushNotifications.unregisterCurrentToken(apiClient: _apiClient);
    await _repository.logout();
    state = AuthState(); // reset completely
  }

  /// Issue #2737 (QA 2026-08-15) — session révoquée (401) : reset local
  /// complet SANS appel API (l'intercepteur 401 a déjà supprimé le token ;
  /// un logout() complet récurserait via l'intercepteur).
  void handleSessionExpired() {
    state = AuthState();
  }

  Future<bool> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
    String? personalEmail,
    String? recoveryEmail,
    String? personalPhone,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final employee = await _repository.updateProfile(
        firstName: firstName,
        lastName: lastName,
        email: email,
        personalEmail: personalEmail,
        recoveryEmail: recoveryEmail,
        personalPhone: personalPhone,
      );
      state = state.copyWith(isLoading: false, employee: employee);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _repository.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmation: confirmation,
      );
      state = state.copyWith(isLoading: false);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> updatePreferredLanguage(String language) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final employee = await _repository.updatePreferredLanguage(language);
      state = state.copyWith(isLoading: false, employee: employee);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final notifier = AuthNotifier(
    ref.watch(authRepositoryProvider),
    ref.watch(pushNotificationServiceProvider),
    ref.watch(apiClientProvider),
  );
  // Issue #3153 : enregistrement du handler de session expirée via un holder
  // pour ne pas referencer `authProvider` depuis `apiClientProvider`
  // (cycle statique de providers → top_level_cycle).
  ref.watch(sessionExpiredHandlerProvider).callback = notifier.handleSessionExpired;
  return notifier;
});
