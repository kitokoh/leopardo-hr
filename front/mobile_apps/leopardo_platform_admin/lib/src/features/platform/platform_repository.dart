import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

import 'platform_models.dart';

class PlatformRepository {
  const PlatformRepository(this._apiClient, this._storage);

  final ApiClient _apiClient;
  final SecureStorage _storage;

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<PlatformAdminUser> login({
    required String email,
    required String password,
    String? twoFactorCode,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/auth/login',
      method: 'POST',
      isLoginRequest: true,
      timeoutOverride: _actionTimeout,
      data: {
        'email': email,
        'password': password,
        'device_name': 'leopardo-platform-admin-mobile',
        if (twoFactorCode != null && twoFactorCode.trim().isNotEmpty)
          'two_fa_code': twoFactorCode.trim(),
      },
    );

    final payload = response.data ?? {};
    if (response.statusCode == 202 &&
        payload['error']?.toString() == 'TWO_FA_REQUIRED') {
      throw ApiException(
        payload['message']?.toString() ??
            'Code 2FA requis pour ce compte super-admin.',
        statusCode: 202,
        code: 'TWO_FA_REQUIRED',
      );
    }

    final token = payload['token']?.toString();
    if (token == null || token.isEmpty) {
      throw StateError('Token plateforme manquant');
    }

    await _storage.saveToken(token);
    return PlatformAdminUser.fromJson(extractDataMap(payload));
  }

  Future<PlatformAdminUser> me() async {
    final token = await _storage.getToken();
    if (token == null || token.isEmpty) {
      throw StateError('Session plateforme absente');
    }

    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/auth/me',
      // Timeout court pour le bootstrap : ne pas bloquer le premier ecran.
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 1,
    );
    return PlatformAdminUser.fromJson(extractDataMap(response.data));
  }

  Future<void> logout() async {
    try {
      await _apiClient.requestWithRetry<Map<String, dynamic>>(
        '/platform/auth/logout',
        method: 'POST',
        timeoutOverride: _actionTimeout,
        maxRetriesOverride: 0,
      );
    } catch (_) {
      // Local cleanup remains mandatory even if the token is already expired.
    } finally {
      await _storage.deleteToken();
    }
  }

  Future<PlatformMetrics> metrics() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/metrics/overview',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    return PlatformMetrics.fromJson(extractDataMap(response.data));
  }

  Future<List<PlatformPlan>> plans() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/plans',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => PlatformPlan.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  Future<List<PlatformCompany>> companies() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => PlatformCompany.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  Future<PlatformCompanyHealth> companyHealth(String companyId) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/health',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    return PlatformCompanyHealth.fromJson(response.data ?? {});
  }

  Future<PlatformCompanySubscription> companySubscription(
    String companyId,
  ) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/subscription',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    return PlatformCompanySubscription.fromJson(response.data ?? {});
  }

  Future<PlatformCompanySubscription> updateCompanySubscription({
    required String companyId,
    required int planId,
    required String status,
    String? notes,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/subscription',
      method: 'PATCH',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
      data: {
        'plan_id': planId,
        'status': status,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return PlatformCompanySubscription.fromJson(response.data ?? {});
  }

  Future<PlatformCompanyFeatures> companyFeatures(String companyId) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/features',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    return PlatformCompanyFeatures.fromJson(response.data ?? {});
  }

  Future<PlatformCompanyFeatures> updateCompanyFeatures({
    required String companyId,
    required Map<String, bool> features,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/features',
      method: 'PATCH',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
      data: {'features': features},
    );
    return PlatformCompanyFeatures.fromJson(response.data ?? {});
  }

  Future<void> createCompany({
    required String name,
    required String email,
    required String country,
    required String city,
    required String managerFirstName,
    required String managerLastName,
    required String managerEmail,
    int? planId,
  }) async {
    await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies',
      method: 'POST',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
      data: {
        'name': name,
        'email': email,
        'country': country.toUpperCase(),
        'city': city,
        'manager_first_name': managerFirstName,
        'manager_last_name': managerLastName,
        'manager_email': managerEmail,
        if (planId != null) 'plan_id': planId,
      },
    );
  }

  Future<List<PlatformCompanyRequest>> companyRequests() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/company-requests',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
      queryParameters: {'status': 'pending'},
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map(
          (item) =>
              PlatformCompanyRequest.fromJson(item.cast<String, dynamic>()),
        )
        .toList();
  }

  Future<void> reviewCompanyRequest({
    required int id,
    required bool approved,
    String? adminNotes,
  }) async {
    await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/company-requests/$id',
      method: 'PATCH',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
      data: {
        'status': approved ? 'approved' : 'rejected',
        if (adminNotes != null && adminNotes.trim().isNotEmpty)
          'admin_notes': adminNotes.trim(),
      },
    );
  }
}
