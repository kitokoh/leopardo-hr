import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';

/// Tests de la configuration runtime des caisses (#9263).
void main() {
  group('PosAppConfig.normalizeBaseUrl', () {
    test('n ajoute pas de scheme s il manque', () {
      expect(PosAppConfig.normalizeBaseUrl('api.leopardo.app'),
          'https://api.leopardo.app');
    });

    test('garde un scheme deja present', () {
      expect(
        PosAppConfig.normalizeBaseUrl('http://10.0.2.2:8080'),
        'http://10.0.2.2:8080',
      );
    });

    test('retire le slash final', () {
      expect(PosAppConfig.normalizeBaseUrl('https://api.leopardo.app/'),
          'https://api.leopardo.app');
    });

    test('ignore les espaces parasites', () {
      expect(PosAppConfig.normalizeBaseUrl('  https://api.leopardo.app  '),
          'https://api.leopardo.app');
    });

    test('laisse une saisie vide telle quelle', () {
      expect(PosAppConfig.normalizeBaseUrl(''), '');
    });
  });

  group('PosAppConfig.isValid', () {
    test('valide une URL bien formee', () {
      const config = PosAppConfig(apiBaseUrl: 'https://api.leopardo.app');

      expect(config.isValid, isTrue);
    });

    test('refuse une URL sans hote', () {
      const config = PosAppConfig(apiBaseUrl: 'https://');

      expect(config.isValid, isFalse);
    });

    test('refuse un scheme inconnu', () {
      const config = PosAppConfig(apiBaseUrl: 'ftp://api.leopardo.app');

      expect(config.isValid, isFalse);
    });

    test('refuse une URL vide', () {
      const config = PosAppConfig(apiBaseUrl: '');

      expect(config.isValid, isFalse);
    });
  });

  group('PosAppConfig.healthUrl', () {
    test('pointe vers /health sur la base normalisee', () {
      const config = PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/');

      expect(config.healthUrl, 'https://api.leopardo.app/health');
    });
  });

  group('checkPosBackendHealth', () {
    test('reussit sur une reponse 200', () async {
      final result = await checkPosBackendHealth(
        apiBaseUrl: 'https://api.leopardo.app',
        performRequest: (_) async => 200,
      );

      expect(result.isOk, isTrue);
      expect(result.httpStatus, 200);
    });

    test('considere 401 comme un endpoint valide', () async {
      final result = await checkPosBackendHealth(
        apiBaseUrl: 'https://api.leopardo.app',
        performRequest: (_) async => 401,
      );

      expect(result.isOk, isTrue);
    });

    test('signale une URL invalide avant tout appel', () async {
      var called = false;

      final result = await checkPosBackendHealth(
        apiBaseUrl: 'not a url',
        performRequest: (_) async {
          called = true;

          return 200;
        },
      );

      expect(result.isOk, isFalse);
      expect(result.status, PosHealthStatus.invalidUrl);
      expect(called, isFalse);
    });

    test('classe une erreur de transport comme injoignable', () async {
      final result = await checkPosBackendHealth(
        apiBaseUrl: 'https://api.leopardo.app',
        performRequest: (_) async => throw Exception('conn refused'),
      );

      expect(result.status, PosHealthStatus.unreachable);
    });

    test('classe une 500 comme erreur serveur', () async {
      final result = await checkPosBackendHealth(
        apiBaseUrl: 'https://api.leopardo.app',
        performRequest: (_) async => 503,
      );

      expect(result.status, PosHealthStatus.serverError);
    });
  });

  group('PosPinCode', () {
    test('valide un PIN de 4 a 8 chiffres', () {
      expect(PosPinCode.isValid('0000'), isTrue);
      expect(PosPinCode.isValid('12345678'), isTrue);
    });

    test('refuse un PIN trop court ou non numerique', () {
      expect(PosPinCode.isValid('123'), isFalse);
      expect(PosPinCode.isValid('12a4'), isFalse);
      expect(PosPinCode.isValid('123456789'), isFalse);
    });

    test('le hash est stable', () {
      final first = PosPinCode.hash('1234', salt: 'tablette-01');
      final second = PosPinCode.hash('1234', salt: 'tablette-01');

      expect(first, second);
    });

    test('matches verifie une saisie', () {
      final stored = PosPinCode.hash('4821', salt: 'tablette-01');

      expect(
          PosPinCode.matches('4821', storedHash: stored, salt: 'tablette-01'),
          isTrue);
      expect(
          PosPinCode.matches('0000', storedHash: stored, salt: 'tablette-01'),
          isFalse);
    });
  });
}
