import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

/// Issue #5683 — accès aux endpoints 2FA employé/manager (follow-up #5627).
///
/// Contrat backend (`TwoFactorAuthController`, #5436) :
/// - GET  /auth/2fa/status          → `{data: {enabled, mfa_required}}`
/// - POST /auth/2fa/enroll          → `{data: {secret, qr_url}}` (otpauth://)
/// - POST /auth/2fa/confirm {code}  → `{data: {recovery_codes: [...]}}` (201)
/// - POST /auth/2fa/disable {code}  → `{data: {enabled: false}}`
/// - POST /auth/2fa/recovery-codes  → `{data: {recovery_codes: [...]}}` (201)
///
/// Erreurs : 422 `TWO_FACTOR_INVALID` (code erroné), 409
/// `TWO_FACTOR_ALREADY_ENABLED`, 403 `TWO_FACTOR_REQUIRED` (politique tenant).
class TwoFactorRepository {
  TwoFactorRepository(this.apiClient);

  final ApiClient apiClient;

  /// `{enabled: bool, mfa_required: bool}` — `mfa_required` reflète la
  /// politique tenant `mfa_required_roles` pour le profil connecté.
  Future<Map<String, dynamic>> status() async {
    final response = await apiClient.requestWithRetry('/auth/2fa/status');
    return extractDataMap(response.data);
  }

  /// Démarre l'enrôlement. Retourne `{secret, qr_url}` où `qr_url` est une
  /// URL `otpauth://` à afficher en QR (widget `LeopardoQrCard`).
  Future<Map<String, dynamic>> enroll() async {
    final response = await apiClient.requestWithRetry(
      '/auth/2fa/enroll',
      method: 'POST',
    );
    return extractDataMap(response.data);
  }

  /// Confirme l'enrôlement avec un premier code TOTP et active la 2FA.
  /// Retourne les codes de récupération à afficher une seule fois.
  Future<List<String>> confirm(String code) async {
    final response = await apiClient.requestWithRetry(
      '/auth/2fa/confirm',
      method: 'POST',
      data: {'code': code},
    );
    final data = extractDataMap(response.data);
    final codes = data['recovery_codes'];
    return codes is List ? codes.cast<String>() : const <String>[];
  }

  /// Désactive la 2FA (code TOTP ou code de récupération requis).
  Future<void> disable(String code) async {
    await apiClient.requestWithRetry(
      '/auth/2fa/disable',
      method: 'POST',
      data: {'code': code},
    );
  }

  /// Régénère les codes de récupération (remplace l'ancien jeu).
  Future<List<String>> regenerateRecoveryCodes() async {
    final response = await apiClient.requestWithRetry(
      '/auth/2fa/recovery-codes',
      method: 'POST',
    );
    final data = extractDataMap(response.data);
    final codes = data['recovery_codes'];
    return codes is List ? codes.cast<String>() : const <String>[];
  }
}
