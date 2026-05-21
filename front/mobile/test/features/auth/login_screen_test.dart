import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/models/employee.dart';

import '../../helpers/mobile_test_harness.dart';

class _RecordingAppPreferences extends FakeAppPreferences {
  String? savedLanguage;
  bool? savedRtl;

  @override
  Future<void> saveLocaleSettings({
    required String preferredLanguage,
    required bool isRtl,
  }) async {
    savedLanguage = preferredLanguage;
    savedRtl = isRtl;
  }
}

class _AuthFlowInterceptor extends Interceptor {
  final requests = <String>[];
  String? authMeAuthorization;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add('${options.method} ${options.path}');

    if (options.path == '/auth/login') {
      handler.resolve(
        Response(
          requestOptions: options,
          statusCode: 200,
          data: {
            'data': {
              'id': 7,
              'first_name': 'Amina',
              'last_name': 'Bensaid',
              'email': 'amina@test.dev',
              'role': 'employee',
              'status': 'active',
            },
            'token': 'mobile-token',
          },
        ),
      );
      return;
    }

    if (options.path == '/auth/me') {
      authMeAuthorization = options.headers['Authorization']?.toString();
      handler.resolve(
        Response(
          requestOptions: options,
          statusCode: 200,
          data: {
            'data': {
              'id': 7,
              'first_name': 'Amina',
              'last_name': 'Bensaid',
              'email': 'amina@test.dev',
              'role': 'manager',
              'manager_role': 'rh',
              'status': 'active',
              'language': 'ar',
              'is_rtl': true,
              'capabilities': ['employees.manage', 'payroll.view'],
              'features': {'rh': true, 'finance': true},
            },
          },
        ),
      );
      return;
    }

    handler.next(options);
  }
}

void main() {
  test('uses local debug api as default base url when none is provided', () {
    expect(ApiClient.resolveBaseUrl(), 'http://10.0.2.2:8000/api/v1');
  });

  test('extracts token from root API payload', () {
    final payload = {
      'data': {
        'id': 1,
        'first_name': 'Hamid',
        'last_name': 'Djebari',
        'email': 'hamid@test.dev',
        'role': 'employee',
        'status': 'active',
      },
      'token': 'root-token',
    };

    expect(AuthRepository.extractToken(payload), 'root-token');
  });

  test('keeps compatibility with legacy mock payloads', () {
    final payload = {
      'data': {
        'token': 'nested-token',
        'user': {
          'id': 1,
          'company_id': 'company-1',
          'first_name': 'Hamid',
          'last_name': 'Djebari',
          'email': 'hamid@test.dev',
          'role': 'employee',
          'status': 'active',
        },
      },
    };

    final employee = Employee.fromJson(
      AuthRepository.extractEmployeeJson(payload),
    );

    expect(AuthRepository.extractToken(payload), 'nested-token');
    expect(employee.companyId, 'company-1');
    expect(employee.role, 'employee');
  });

  test('parses language metadata returned by auth payloads', () {
    final employee = Employee.fromJson({
      'id': 7,
      'first_name': 'Amina',
      'last_name': 'Bensaid',
      'email': 'amina@test.dev',
      'role': 'employee',
      'status': 'active',
      'language': 'ar',
      'is_rtl': true,
    });

    expect(employee.language, 'ar');
    expect(employee.isRtl, isTrue);
  });

  test('hydrates mobile login from /auth/me with tenant role metadata', () async {
    final storage = FakeSecureStorage();
    final preferences = _RecordingAppPreferences();
    final client = ApiClient(storage, preferences);
    final interceptor = _AuthFlowInterceptor();
    client.dio.interceptors.add(interceptor);

    final result = await AuthRepository(
      client,
      storage,
      preferences,
    ).login('amina@test.dev', 'password123');

    final employee = result['employee'] as Employee;

    expect(interceptor.requests, ['POST /auth/login', 'GET /auth/me']);
    expect(interceptor.authMeAuthorization, 'Bearer mobile-token');
    expect(await storage.getToken(), 'mobile-token');
    expect(employee.role, 'manager');
    expect(employee.managerRole, 'rh');
    expect(employee.canManageTeam, isTrue);
    expect(employee.capabilities, contains('employees.manage'));
    expect(employee.hasFinanceModule, isTrue);
    expect(employee.language, 'ar');
    expect(employee.isRtl, isTrue);
    expect(preferences.savedLanguage, 'ar');
    expect(preferences.savedRtl, isTrue);
  });
}
