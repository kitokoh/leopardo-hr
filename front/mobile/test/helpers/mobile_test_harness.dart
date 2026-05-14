import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/app.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/l10n/l10n.dart';
import 'package:leopardo_rh/models/employee.dart';

class FakeAppPreferences extends AppPreferences {
  FakeAppPreferences({this.language = 'fr', this.rtl = false});

  final String language;
  final bool rtl;

  @override
  String get preferredLanguage => language;

  @override
  bool get isRtl => rtl;

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

class FakeSecureStorage extends SecureStorage {
  String? _token;

  @override
  Future<void> saveToken(String token) async {
    _token = token;
  }

  @override
  Future<String?> getToken() async => _token;

  @override
  Future<void> deleteToken() async {
    _token = null;
  }

  @override
  Future<void> clearAll() async {
    _token = null;
  }
}

class StaticAuthRepository extends AuthRepository {
  StaticAuthRepository()
    : super(
        ApiClient(FakeSecureStorage(), FakeAppPreferences()),
        FakeSecureStorage(),
        FakeAppPreferences(),
      );

  @override
  Future<Map<String, dynamic>?> checkAuth() async => null;

  @override
  Future<void> logout() async {}
}

class StaticAuthNotifier extends AuthNotifier {
  StaticAuthNotifier(AuthState initialState) : super(StaticAuthRepository()) {
    state = initialState;
  }

  @override
  Future<void> checkAuth() async {}

  @override
  Future<bool> login(String email, String password) async => true;

  @override
  Future<void> logout() async {
    state = AuthState();
  }
}

Employee testEmployee({
  int id = 1,
  String firstName = 'Samia',
  String lastName = 'RH',
  String role = 'employee',
  String? managerRole,
}) {
  return Employee(
    id: id,
    firstName: firstName,
    lastName: lastName,
    email: 'samia@example.test',
    role: role,
    managerRole: managerRole,
    status: 'active',
    features: const {'rh': true, 'finance': true},
  );
}

dynamic fakePreferencesOverride({
  String language = 'fr',
  bool rtl = false,
}) {
  return appPreferencesProvider.overrideWith(
    (ref) => FakeAppPreferences(language: language, rtl: rtl),
  );
}

dynamic fakeStorageOverride() {
  return secureStorageProvider.overrideWith((ref) => FakeSecureStorage());
}

dynamic authOverride(Employee? employee) {
  return authProvider.overrideWith(
    (ref) => StaticAuthNotifier(
      AuthState(isLoading: false, employee: employee),
    ),
  );
}

Widget localizedHarness(
  Widget child, {
  List<dynamic> overrides = const [],
  Size surfaceSize = const Size(390, 844),
}) {
  return ProviderScope(
    overrides: [
      fakePreferencesOverride(),
      fakeStorageOverride(),
      ...overrides,
    ],
    child: MediaQuery(
      data: MediaQueryData(size: surfaceSize),
      child: MaterialApp(
        locale: const Locale('fr'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: child,
      ),
    ),
  );
}

Widget appRouterHarness({List<dynamic> overrides = const []}) {
  return ProviderScope(
    overrides: [
      fakePreferencesOverride(),
      fakeStorageOverride(),
      ...overrides,
    ],
    child: const LeopardoApp(),
  );
}

Future<void> pumpMobile(
  WidgetTester tester,
  Widget child, {
  List<dynamic> overrides = const [],
}) async {
  await tester.pumpWidget(localizedHarness(child, overrides: overrides));
  await tester.pump();
}
