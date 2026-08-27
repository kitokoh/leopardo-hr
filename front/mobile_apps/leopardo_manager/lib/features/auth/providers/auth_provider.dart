export 'package:leopardo_core/features/auth/providers/auth_provider.dart'
    show authProvider, AuthState, AuthNotifier;

/// Leopardo manager — provider auth partagé (leopardo_core, #5279).
/// La déconnexion nettoie le token push (`unregisterCurrentToken`) puis appelle
/// `_repository.logout()` (ordre contractuel du garde readiness mobile).
