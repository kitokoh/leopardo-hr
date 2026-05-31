import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/services/push_notification_service.dart';
import 'package:leopardo_manager/features/auth/data/auth_repository.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

class AuthState {
  final bool isLoading;
  final Employee? employee;
  final String? error;

  AuthState({this.isLoading = false, this.employee, this.error});

  AuthState copyWith({
    bool? isLoading,
    Employee? employee,
    String? error,
    bool clearError = false,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      employee: employee ?? this.employee,
      error: clearError ? null : (error ?? this.error),
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
      state = state.copyWith(isLoading: false);
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.login(email, password);
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

  Future<bool> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final employee = await _repository.updateProfile(
        firstName: firstName,
        lastName: lastName,
        email: email,
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
  return AuthNotifier(
    ref.watch(authRepositoryProvider),
    ref.watch(pushNotificationServiceProvider),
    ref.watch(apiClientProvider),
  );
});
