import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/session_expired_handler.dart';
import 'package:leopardo_core/core/location/attendance_location_service.dart';
import 'package:leopardo_core/core/services/offline_sync_service.dart';
import 'package:leopardo_core/core/services/push_notification_service.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/features/absences/data/absence_repository.dart';
import 'package:leopardo_core/features/salary_advances/data/salary_advance_repository.dart';
import 'package:leopardo_core/features/payrolls/data/payroll_repository.dart';
import 'package:leopardo_core/features/notifications/data/notification_repository.dart';
import 'package:leopardo_core/features/evaluations/data/evaluation_repository.dart';
import 'package:leopardo_core/features/cabinet/data/cabinet_repository.dart';
import 'package:leopardo_core/features/schedules/data/schedule_repository.dart';
import 'package:leopardo_core/features/company_branding/data/company_branding_repository.dart';
import 'package:leopardo_core/features/user_auth/data/user_auth_repository.dart';

// ── Providers communs (apps leopardo_hr / leopardo_manager) ────────────────
// Extrait de <app>/core/providers/core_providers.dart (issue #5279, lot 1) :
// la partie indépendante des repositories spécifiques aux apps. Les apps
// re-exportent ce fichier et n'ajoutent que leurs providers spécifiques.

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

final appPreferencesProvider = Provider<AppPreferences>((ref) {
  return AppPreferences();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  final sessionExpiredHandler = ref.watch(sessionExpiredHandlerProvider);
  // Issue #2737 — un 401 (session révoquée, mot de passe changé) doit sortir
  // l'utilisateur de l'état « authentifié » fantôme. Lecture différée du
  // notifier pour éviter la dépendance circulaire apiClient ↔ authProvider.
  return ApiClient(storage, preferences,
      onUnauthorized: sessionExpiredHandler.call);
});

final pushNotificationServiceProvider = Provider<PushNotificationService>((
  ref,
) {
  return PushNotificationService();
});

/// Replays the `offline_punches` Hive box written by [AttendanceRepository]
/// when check-in/check-out fails offline (see issue #1289).
final offlineSyncServiceProvider = Provider<OfflineSyncService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final service = OfflineSyncService(apiClient, Connectivity());
  ref.onDispose(service.dispose);
  return service;
});

final attendanceLocationServiceProvider = Provider<AttendanceLocationService>((
  ref,
) {
  return const AttendanceLocationService();
});

final absenceRepositoryProvider = Provider<AbsenceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AbsenceRepository(apiClient);
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

final scheduleRepositoryProvider = Provider<ScheduleRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ScheduleRepository(apiClient);
});

final companyBrandingRepositoryProvider = Provider<CompanyBrandingRepository>((
  ref,
) {
  final apiClient = ref.watch(apiClientProvider);
  return CompanyBrandingRepository(apiClient);
});

final userAuthRepositoryProvider = Provider<UserAuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return UserAuthRepository(apiClient, storage, preferences);
});
