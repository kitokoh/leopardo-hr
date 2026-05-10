import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/features/user_auth/data/user_auth_repository.dart';
import 'package:leopardo_rh/models/app_user.dart';

class UserAuthState {
  final bool isLoading;
  final AppUser? user;
  final String? error;

  UserAuthState({this.isLoading = false, this.user, this.error});

  UserAuthState copyWith({
    bool? isLoading,
    AppUser? user,
    String? error,
    bool clearError = false,
    bool clearUser = false,
  }) {
    return UserAuthState(
      isLoading: isLoading ?? this.isLoading,
      user: clearUser ? null : (user ?? this.user),
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class UserAuthNotifier extends Notifier<UserAuthState> {
  late final UserAuthRepository _repository;

  @override
  UserAuthState build() {
    _repository = ref.read(userAuthRepositoryProvider);
    return UserAuthState();
  }

  Future<void> checkAuth() async {
    state = state.copyWith(isLoading: true);
    final user = await _repository.checkAuth();
    if (user != null) {
      state = state.copyWith(isLoading: false, user: user);
    } else {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<bool> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
    String? phone,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.register(
        firstName: firstName,
        lastName: lastName,
        email: email,
        password: password,
        phone: phone,
      );
      state = state.copyWith(isLoading: false, user: data['user'] as AppUser);
      return true;
    } catch (e) {
      final msg =
          e is ApiException ? e.message : 'Erreur lors de l\'inscription';
      state = state.copyWith(isLoading: false, error: msg);
      return false;
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.login(email: email, password: password);
      state = state.copyWith(isLoading: false, user: data['user'] as AppUser);
      return true;
    } catch (e) {
      final msg = e is ApiException ? e.message : 'Identifiants invalides';
      state = state.copyWith(isLoading: false, error: msg);
      return false;
    }
  }

  Future<bool> googleSignIn({
    required String googleId,
    required String email,
    required String firstName,
    required String lastName,
    String? avatarUrl,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.googleSignIn(
        googleId: googleId,
        email: email,
        firstName: firstName,
        lastName: lastName,
        avatarUrl: avatarUrl,
      );
      state = state.copyWith(isLoading: false, user: data['user'] as AppUser);
      return true;
    } catch (e) {
      final msg = e is ApiException ? e.message : 'Erreur connexion Google';
      state = state.copyWith(isLoading: false, error: msg);
      return false;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    state = UserAuthState();
  }
}

final userAuthProvider = NotifierProvider<UserAuthNotifier, UserAuthState>(() {
  return UserAuthNotifier();
});
