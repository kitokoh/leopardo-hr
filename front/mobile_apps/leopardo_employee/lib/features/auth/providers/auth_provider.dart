export 'package:leopardo_core/features/auth/providers/auth_provider.dart'
    show authProvider, AuthState, AuthNotifier;

/// Leopardo employee — provider auth partagé (leopardo_core, #7652).
/// La déconnexion nettoie le token push (`unregisterCurrentToken`) puis appelle
/// `_repository.logout()` (ordre contractuel du garde readiness mobile).
