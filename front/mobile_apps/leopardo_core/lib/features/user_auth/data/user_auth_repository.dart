import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/models/app_user.dart';

class UserAuthRepository {
  final ApiClient apiClient;
  final SecureStorage storage;
  final AppPreferences preferences;

  UserAuthRepository(this.apiClient, this.storage, this.preferences);

  Future<Map<String, dynamic>> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
    String? phone,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/register',
      method: 'POST', maxRetriesOverride: 0,
      isLoginRequest: true,
      data: {
        'first_name': firstName,
        'last_name': lastName,
        'email': email,
        'password': password,
        if (phone != null) 'phone': phone,
      },
    );

    final data = _authPayload(response.data);
    final token = data['token'] as String;
    await storage.saveUserToken(token);

    final user = AppUser.fromJson(_userPayload(data));
    return {'user': user};
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/login',
      method: 'POST', maxRetriesOverride: 0,
      isLoginRequest: true,
      data: {'email': email, 'password': password, 'device_name': 'Mobile App'},
    );

    final data = _authPayload(response.data);
    final token = data['token'] as String;
    await storage.saveUserToken(token);

    final user = AppUser.fromJson(_userPayload(data));
    return {'user': user};
  }

  Future<Map<String, dynamic>> googleSignIn({required String idToken}) async {
    final response = await apiClient.requestWithRetry(
      '/user/google-signin',
      method: 'POST', maxRetriesOverride: 0,
      isLoginRequest: true,
      data: {'id_token': idToken},
    );

    final data = _authPayload(response.data);
    final token = data['token'] as String;
    await storage.saveUserToken(token);

    final user = AppUser.fromJson(_userPayload(data));
    return {'user': user, 'is_new': data['is_new'] ?? false};
  }

  Future<AppUser?> checkAuth() async {
    final token = await storage.getUserToken();
    if (token == null) return null;

    try {
      final response = await apiClient.requestWithRetry(
        '/user/me',
        useUserSession: true,
        timeoutOverride: const Duration(seconds: 8),
        maxRetriesOverride: 0,
      );
      final data = _userPayload(extractDataMap(response.data));
      return AppUser.fromJson(data);
    } catch (_) {
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await apiClient.requestWithRetry(
        '/user/logout',
        method: 'POST', maxRetriesOverride: 0,
        useUserSession: true,
        timeoutOverride: const Duration(seconds: 8),
      );
    } catch (_) {
      // Ignore
    } finally {
      await storage.deleteUserToken();
    }
  }

  Future<Map<String, dynamic>> submitCompanyRequest({
    required String companyName,
    required String email,
    String? sector,
    String? country,
    String? city,
    String? phone,
    String? description,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/company-requests',
      method: 'POST', maxRetriesOverride: 0,
      useUserSession: true,
      timeoutOverride: const Duration(seconds: 15),
      data: {
        'company_name': companyName,
        'email': email,
        if (sector != null) 'sector': sector,
        if (country != null) 'country': country,
        if (city != null) 'city': city,
        if (phone != null) 'phone': phone,
        if (description != null) 'description': description,
      },
    );
    return extractDataMap(response.data);
  }

  // ── #5540 — Onboarding multi-statuts ────────────────────────────────────

  /// Met à jour les statuts personnels cumulables de l'utilisateur.
  ///
  /// Valeurs acceptées : student, employee, entrepreneur, seeking_employment.
  Future<AppUser> updatePersonalStatuses(
      List<PersonalStatus> statuses) async {
    final response = await apiClient.requestWithRetry(
      '/user/personal-statuses',
      method: 'PATCH',
      useUserSession: true,
      maxRetriesOverride: 0,
      timeoutOverride: const Duration(seconds: 10),
      data: {
        'statuses': statuses.map((s) => s.toApiValue()).toList(),
      },
    );
    return AppUser.fromJson(_userPayload(extractDataMap(response.data)));
  }

  /// Recherche d'entreprises existantes par nom.
  ///
  /// Retourne une liste de [CompanySearchResult] (id, name, country, city).
  Future<List<CompanySearchResult>> searchCompanies(String query) async {
    final response = await apiClient.requestWithRetry(
      '/user/companies/search',
      useUserSession: true,
      queryParameters: {'q': query},
      timeoutOverride: const Duration(seconds: 10),
      maxRetriesOverride: 0,
    );
    return extractDataList(response.data)
        .map((e) => CompanySearchResult.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Soumet une demande d'intégration pour rejoindre une entreprise existante.
  Future<IntegrationRequestSummary> submitIntegrationRequest({
    required String targetCompanyId,
    required String targetCompanyName,
    String? message,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/company-integration-requests',
      method: 'POST',
      useUserSession: true,
      maxRetriesOverride: 0,
      timeoutOverride: const Duration(seconds: 12),
      data: {
        'target_company_id': targetCompanyId,
        'target_company_name': targetCompanyName,
        if (message != null && message.isNotEmpty) 'message': message,
      },
    );
    return IntegrationRequestSummary.fromJson(extractDataMap(response.data));
  }

  /// Récupère les demandes d'intégration en cours de l'utilisateur.
  Future<List<IntegrationRequestSummary>> getIntegrationRequests() async {
    final response = await apiClient.requestWithRetry(
      '/user/company-integration-requests',
      useUserSession: true,
      maxRetriesOverride: 0,
      timeoutOverride: const Duration(seconds: 10),
    );
    return extractDataList(response.data)
        .map((e) =>
            IntegrationRequestSummary.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  // ── Profile ──────────────────────────────────────────────────────────────

  Future<AppUser> updateProfile({
    String? firstName,
    String? lastName,
    String? phone,
    String? preferredLanguage,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/profile',
      method: 'PATCH',
      useUserSession: true,
      timeoutOverride: const Duration(seconds: 12),
      data: {
        if (firstName != null) 'first_name': firstName,
        if (lastName != null) 'last_name': lastName,
        if (phone != null) 'phone': phone,
        if (preferredLanguage != null) 'preferred_language': preferredLanguage,
      },
    );

    final user = AppUser.fromJson(_userPayload(extractDataMap(response.data)));

    // Persiste la nouvelle locale localement pour que le header Accept-Language
    // et le Locale Flutter soient mis à jour sans nécessiter un re-login.
    if (preferredLanguage != null) {
      final lang = user.preferredLanguage.isNotEmpty
          ? user.preferredLanguage
          : preferredLanguage;
      final isRtl = lang == 'ar';
      await preferences.saveLocaleSettings(
        preferredLanguage: lang,
        isRtl: isRtl,
      );
      // Header Accept-Language gere par l'intercepteur core (api_client.dart)
      // a partir de _preferences.preferredLanguage — pas de mutation globale.
    }

    return user;
  }

  Map<String, dynamic> _authPayload(dynamic payload) {
    if (payload is! Map) {
      return const <String, dynamic>{};
    }

    final root = payload.cast<String, dynamic>();
    final rootToken = root['token'];
    if (rootToken is String && rootToken.isNotEmpty) {
      return root;
    }

    // #4199 : parse multi-formes volontaire (token racine / data / auth) —
    // le helper extractDataMap() ne couvre que l'enveloppe {data:{...}}.
    final data = root['data'];
    if (data is Map) {
      final dataMap = data.cast<String, dynamic>();
      final dataToken = dataMap['token'];
      if (dataToken is String && dataToken.isNotEmpty) {
        return dataMap;
      }
    }

    final nested = root['auth'];
    if (nested is Map) {
      return nested.cast<String, dynamic>();
    }

    return extractDataMap(payload);
  }

  Map<String, dynamic> _userPayload(Map<String, dynamic> payload) {
    final user = payload['user'] ?? payload['employee'] ?? payload['data'];
    if (user is Map) {
      return user.cast<String, dynamic>();
    }
    return payload;
  }
}
