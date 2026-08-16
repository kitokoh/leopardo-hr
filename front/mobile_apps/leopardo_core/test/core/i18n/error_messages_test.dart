// ============================================================
// error_messages catalog tests — issue #4408.
// ============================================================

import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/i18n/error_messages.dart';

void main() {
  group('localizedErrorCode', () {
    test('resolves each code in all 4 locales', () {
      for (final code in [
        'CONNECTION',
        'NOT_IMPLEMENTED',
        'ACCOUNT_SUSPENDED',
        'FORBIDDEN',
        'CONNECTION_TIMEOUT',
        'RECEIVE_TIMEOUT',
        'CONNECTION_ERROR',
        'DOWNLOAD_FAILED',
      ]) {
        for (final locale in ['fr', 'en', 'tr', 'ar']) {
          final msg = localizedErrorCode(code, locale);
          expect(msg, isNotEmpty, reason: '$code/$locale should resolve');
          expect(msg, isNot(equals(code)), reason: '$code/$locale should be translated');
        }
      }
    });

    test('falls back to fr for unknown locale or unknown code', () {
      expect(localizedErrorCode('CONNECTION', 'xx'), 'Impossible de se connecter au serveur');
      expect(localizedErrorCode('UNKNOWN_CODE'), 'UNKNOWN_CODE');
    });

    test('defaults to device locale when none given', () {
      expect(localizedErrorCode('CONNECTION'), isNotEmpty);
    });
  });
}
