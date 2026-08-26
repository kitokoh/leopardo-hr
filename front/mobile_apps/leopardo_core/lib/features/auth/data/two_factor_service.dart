/// Issue #5627 — Service 2FA partagé (leopardo_employee + leopardo_manager).
///
/// Expose les méthodes correspondant aux endpoints du backend TwoFactorAuthController
/// (#5436) : status, enroll, confirm, disable, verify (challenge).
library;

import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

class TwoFactorService {
  TwoFactorService(this._apiClient, this._storage);

  final ApiClient _apiClient;
  final SecureStorage _storage;

  static const _timeout = Duration(seconds: 12);

  // ── Statut ──────────────────────────────────────────────────────────────

  /// GET /auth/2fa/status → { enabled, enforced, has_recovery_codes }
  Future<Map<String, dynamic>> getStatus() async {
    final res = await _apiClient.requestWithRetry(
      '/auth/2fa/status',
      timeoutOverride: _timeout,
    );
    return extractDataMap(res.data);
  }

  // ── Enrôlement ───────────────────────────────────────────────────────────

  /// POST /auth/2fa/enroll → { secret, qr_code_url, qr_code_svg? }
  Future<Map<String, dynamic>> enroll() async {
    final res = await _apiClient.requestWithRetry(
      '/auth/2fa/enroll',
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: _timeout,
    );
    return extractDataMap(res.data);
  }

  /// POST /auth/2fa/confirm { code } → { recovery_codes: [String] }
  Future<List<String>> confirm(String code) async {
    final res = await _apiClient.requestWithRetry(
      '/auth/2fa/confirm',
      method: 'POST',
      data: {'code': code},
      maxRetriesOverride: 0,
      timeoutOverride: _timeout,
    );
    final data = extractDataMap(res.data);
    final codes = data['recovery_codes'];
    if (codes is List) {
      return codes.whereType<String>().toList();
    }
    return [];
  }

  // ── Désactivation ────────────────────────────────────────────────────────

  /// POST /auth/2fa/disable { code }
  Future<void> disable(String code) async {
    await _apiClient.requestWithRetry(
      '/auth/2fa/disable',
      method: 'POST',
      data: {'code': code},
      maxRetriesOverride: 0,
      timeoutOverride: _timeout,
    );
  }

  // ── Vérification du challenge (login avec 2FA actif) ────────────────────

  /// POST /auth/2fa/verify { challenge_token, code?, recovery_code?, remember_device }
  /// → { token, employee }  (pose le token Sanctum en secure storage)
  Future<Map<String, dynamic>> verifyChallenge({
    required String challengeToken,
    String? totpCode,
    String? recoveryCode,
    bool rememberDevice = false,
  }) async {
    assert(
      totpCode != null || recoveryCode != null,
      'totpCode ou recoveryCode requis',
    );

    final res = await _apiClient.requestWithRetry(
      '/auth/2fa/verify',
      method: 'POST',
      data: {
        'challenge_token': challengeToken,
        if (totpCode != null) 'code': totpCode,
        if (recoveryCode != null) 'recovery_code': recoveryCode,
        'remember_device': rememberDevice,
        'device_name': 'Mobile App',
      },
      isLoginRequest: true,
      maxRetriesOverride: 0,
      timeoutOverride: _timeout,
    );

    final payload = res.data is Map
        ? (res.data as Map).cast<String, dynamic>()
        : <String, dynamic>{};

    final token = payload['token'];
    if (token is String && token.isNotEmpty) {
      await _storage.saveToken(token);
    }

    return payload;
  }
}
