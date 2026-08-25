import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_accounting/features/auth/data/auth_repository.dart';

/// État d'authentification (pattern leopardo_marketing/manager).
class AuthState {
  const AuthState({this.isLoading = false, this.employee, this.error});

  final bool isLoading;
  final Employee? employee;
  final String? error;

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

  AuthNotifier(this._repository) : super(const AuthState(isLoading: true)) {
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
    } catch (_) {
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

  Future<void> logout() async {
    await _repository.logout();
    state = state.copyWith(employee: null);
  }
}
