import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/features/auth/data/two_factor_service.dart';
import 'package:leopardo_core/offline/database/edge_database.dart';
import 'package:leopardo_core/offline/services/sync_service.dart';
import 'package:leopardo_employee/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_employee/features/auth/data/auth_repository.dart';
import 'package:leopardo_employee/features/cabinet/data/cabinet_repository.dart';
import 'package:leopardo_employee/features/evaluations/data/evaluation_repository.dart';
import 'package:leopardo_employee/features/notifications/data/notification_repository.dart';
import 'package:leopardo_employee/features/onboarding/data/onboarding_repository.dart';
import 'package:leopardo_employee/features/payrolls/data/payroll_repository.dart';
import 'package:leopardo_employee/features/salary_advances/data/salary_advance_repository.dart';
import 'package:leopardo_employee/features/settings/data/settings_repository.dart';
import 'package:leopardo_employee/features/user_auth/data/user_auth_repository.dart';

export 'package:leopardo_core/core/providers/core_providers.dart'
    hide
        cabinetRepositoryProvider,
        evaluationRepositoryProvider,
        notificationRepositoryProvider,
        payrollRepositoryProvider,
        salaryAdvanceRepositoryProvider,
        userAuthRepositoryProvider;

/// Local SQLite (Drift) database used by the Edge/offline module.
final edgeDatabaseProvider = Provider<EdgeDatabase>((ref) {
  return EdgeDatabase();
});

/// Detects cloud, local Edge network, and fully offline states, then syncs the
/// Edge queue. The service starts once when this provider is created and is
/// stopped when the provider is disposed.
final syncServiceProvider = Provider<SyncService>((ref) {
  final preferences = ref.watch(appPreferencesProvider);
  final db = ref.watch(edgeDatabaseProvider);
  final service = SyncService(
    db: db,
    dio: Dio(),
    edgeBaseUrl: preferences.edgeBaseUrl.isNotEmpty
        ? preferences.edgeBaseUrl
        : SyncService.defaultEdgeBaseUrl,
    cloudBaseUrl: ApiClient.resolveBaseUrl().replaceFirst('/api/v1', ''),
    edgeNodeId: preferences.edgeNodeId,
    edgeToken: preferences.edgeToken,
  );
  service.start();
  unawaited(
    preferences.hydrateEdgeToken().then((_) {
      service.updateEdgeToken(preferences.edgeToken);
    }),
  );
  ref.onDispose(service.stop);
  return service;
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return AuthRepository(apiClient, storage, preferences);
});

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AttendanceRepository(apiClient);
});

final settingsRepositoryProvider = Provider<SettingsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return SettingsRepository(apiClient, preferences);
});

final salaryAdvanceRepositoryProvider = Provider<SalaryAdvanceRepository>((
  ref,
) {
  final apiClient = ref.watch(apiClientProvider);
  return SalaryAdvanceRepository(apiClient);
});

final payrollRepositoryProvider = Provider<PayrollRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PayrollRepository(apiClient);
});

final notificationRepositoryProvider = Provider<NotificationRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return NotificationRepository(apiClient);
});

final evaluationRepositoryProvider = Provider<EvaluationRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return EvaluationRepository(apiClient);
});

final cabinetRepositoryProvider = Provider<CabinetRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return CabinetRepository(apiClient);
});

final userAuthRepositoryProvider = Provider<UserAuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return UserAuthRepository(apiClient, storage, preferences);
});

final onboardingRepositoryProvider = Provider<OnboardingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return OnboardingRepository(apiClient);
});

/// Issue #5627 — Service 2FA (enrôlement, challenge, désactivation).
final twoFactorServiceProvider = Provider<TwoFactorService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return TwoFactorService(apiClient, storage);
});
