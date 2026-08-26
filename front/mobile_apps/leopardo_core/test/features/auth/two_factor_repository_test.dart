import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/features/auth/data/two_factor_repository.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

class _FakePreferences extends AppPreferences {
  @override
  String get preferredLanguage => 'fr';
  @override
  bool get isRtl => false;
  @override
  bool get biometricEnabled => false;
  @override
  bool get fingerprintEnabled => false;
  @override
  bool get faceEnabled => false;
  @override
  bool get attendanceConsent => true;
  @override
  String get biometricNote => '';
  @override
  Future<void> saveBiometricSettings({
    required bool biometricEnabled,
    required bool fingerprintEnabled,
    required bool faceEnabled,
    required bool attendanceConsent,
    required String biometricNote,
  }) async {}
  @override
  Future<void> saveLocaleSettings({
    required String preferredLanguage,
    required bool isRtl,
  }) async {}
  @override
  Future<void> clearLocaleSettings() async {}
}

class _FakeStorage extends SecureStorage {
  @override
  Future<void> saveToken(String token) async {}
  @override
  Future<String?> getToken() async => null;
  @override
  Future<void> deleteToken() async {}
  @override
  Future<void> clearAll() async {}
}

/// Intercepteur qui simule le backend TwoFactorAuthController (#5436).
class _TwoFaInterceptor extends Interceptor {
  final requests = <String>[];
  String? lastCode;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add('${options.method} ${options.path}');
    final body = (options.data is Map) ? options.data as Map : const {};
    lastCode = body['code'] as String?;

    Map<String, dynamic> data;
    switch (options.path) {
      case '/auth/2fa/status':
        data = {'enabled': false, 'mfa_required': true};
        break;
      case '/auth/2fa/enroll':
        data = {
          'secret': 'JBSWY3DPEHPK3PXP',
          'qr_url':
              'otpauth://totp/Leopardo:amina@test.dev?secret=JBSWY3DPEHPK3PXP&issuer=Leopardo',
        };
        break;
      case '/auth/2fa/confirm':
        data = {
          'recovery_codes': ['AAAA-BBBB', 'CCCC-DDDD'],
        };
        break;
      case '/auth/2fa/disable':
        data = {'enabled': false};
        break;
      case '/auth/2fa/recovery-codes':
        data = {
          'recovery_codes': ['EEEE-FFFF', 'GGGG-HHHH'],
        };
        break;
      default:
        handler.next(options);
        return;
    }
    handler.resolve(
      Response(
        requestOptions: options,
        statusCode: options.path == '/auth/2fa/enroll' ||
                options.path == '/auth/2fa/confirm' ||
                options.path == '/auth/2fa/recovery-codes'
            ? 201
            : 200,
        data: {'data': data},
      ),
    );
  }
}

void main() {
  late _TwoFaInterceptor interceptor;
  late TwoFactorRepository repository;

  setUp(() {
    interceptor = _TwoFaInterceptor();
    final client = ApiClient(
      _FakeStorage(),
      _FakePreferences(),
    );
    client.dio.interceptors.add(interceptor);
    repository = TwoFactorRepository(client);
  });

  test('status() retourne enabled + politique tenant mfa_required', () async {
    final status = await repository.status();
    expect(status['enabled'], false);
    expect(status['mfa_required'], true);
    expect(interceptor.requests, contains('GET /auth/2fa/status'));
  });

  test('enroll() retourne secret + URL otpauth', () async {
    final data = await repository.enroll();
    expect(data['secret'], 'JBSWY3DPEHPK3PXP');
    expect(
      data['qr_url'],
      startsWith('otpauth://totp/'),
    );
    expect(interceptor.requests, contains('POST /auth/2fa/enroll'));
  });

  test('confirm() envoie le code et retourne les codes de récupération',
      () async {
    final codes = await repository.confirm('123456');
    expect(interceptor.lastCode, '123456');
    expect(codes, ['AAAA-BBBB', 'CCCC-DDDD']);
  });

  test('disable() envoie le code de désactivation', () async {
    await repository.disable('654321');
    expect(interceptor.lastCode, '654321');
    expect(interceptor.requests, contains('POST /auth/2fa/disable'));
  });

  test('regenerateRecoveryCodes() retourne le nouveau jeu de codes', () async {
    final codes = await repository.regenerateRecoveryCodes();
    expect(codes, ['EEEE-FFFF', 'GGGG-HHHH']);
    expect(interceptor.requests, contains('POST /auth/2fa/recovery-codes'));
  });
}
