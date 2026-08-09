import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/location/attendance_location_service.dart';
import 'package:leopardo_core/core/services/offline_sync_service.dart';
import 'package:leopardo_core/core/services/push_notification_service.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/offline/database/edge_database.dart';
import 'package:leopardo_core/offline/services/attendance_offline_service.dart';
import 'package:leopardo_core/offline/services/sync_service.dart';
import 'package:leopardo_employee/features/auth/data/auth_repository.dart';
import 'package:leopardo_employee/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_employee/features/settings/data/settings_repository.dart';
import 'package:leopardo_employee/features/absences/data/absence_repository.dart';
import 'package:leopardo_employee/features/salary_advances/data/salary_advance_repository.dart';
import 'package:leopardo_employee/features/payrolls/data/payroll_repository.dart';
import 'package:leopardo_employee/features/notifications/data/notification_repository.dart';
import 'package:leopardo_employee/features/evaluations/data/evaluation_repository.dart';
import 'package:leopardo_employee/features/cabinet/data/cabinet_repository.dart';
import 'package:leopardo_employee/features/home/data/project_repository.dart';
import 'package:leopardo_employee/features/user_auth/data/user_auth_repository.dart';
import 'package:leopardo_employee/features/contracts/data/contract_repository.dart';
import 'package:leopardo_employee/features/training/data/training_repository.dart';
import 'package:leopardo_employee/features/expenses/data/expense_repository.dart';
import 'package:leopardo_employee/features/ai_chat/data/ai_chat_repository.dart';
import 'package:leopardo_employee/features/ai_voice/data/ai_voice_repository.dart';
import 'package:leopardo_employee/features/vehicle_position/data/vehicle_position_repository.dart';
import 'package:leopardo_employee/features/onboarding/data/onboarding_repository.dart';
import 'package:leopardo_employee/features/smart_attendance/data/smart_attendance_repository.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

final appPreferencesProvider = Provider<AppPreferences>((ref) {
  return AppPreferences();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return ApiClient(storage, preferences);
});

final pushNotificationServiceProvider = Provider<PushNotificationService>((
  ref,
) {
  return PushNotificationService();
});

/// Replays the `offline_punches` Hive box written by [AttendanceRepository]
/// (see issue #1290) whenever connectivity comes back. Without this, offline
/// check-in/check-out attempts stay stuck in Hive forever.
final offlineSyncServiceProvider = Provider<OfflineSyncService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final service = OfflineSyncService(apiClient, Connectivity());
  ref.onDispose(service.dispose);
  return service;
});

/// Local SQLite (Drift) database used by the Edge/offline module
/// (see issue #1287 — `leopardo_core/lib/offline/*` was previously never
/// wired into any app). Long-lived: closed only when the app itself exits.
final edgeDatabaseProvider = Provider<EdgeDatabase>((ref) {
  return EdgeDatabase();
});

/// Detects Cloud / local-Edge-network / fully-offline connectivity and
/// syncs the Edge SQLite queue accordingly. Only reaches [SyncMode.edge]
/// once the device has been paired with an Edge node from Settings (see
/// [AppPreferences.edgeNodeId]); otherwise it simply oscillates between
/// cloud and offline, same as before this module existed.
final syncServiceProvider = Provider<SyncService>((ref) {
  final preferences = ref.watch(appPreferencesProvider);
  final db = ref.watch(edgeDatabaseProvider);
  final service = SyncService(
    db: db,
    dio: Dio(),
    edgeBaseUrl: preferences.edgeBaseUrl.isNotEmpty
        ? preferences.edgeBaseUrl
        : 'http://leopardo.local:7878',
    cloudBaseUrl: ApiClient.resolveBaseUrl().replaceFirst('/api/v1', ''),
    edgeNodeId: preferences.edgeNodeId,
    edgeToken: preferences.edgeToken,
  );
  service.start();
  ref.onDispose(service.stop);
  return service;
});

/// Offline-first check-in/check-out backed by [EdgeDatabase] + [SyncService]
/// (see issue #1287). Distinct from [offlineSyncServiceProvider], which only
/// drains the legacy Hive `offline_punches` fallback queue.
final attendanceOfflineServiceProvider = Provider<AttendanceOfflineService>((
  ref,
) {
  return AttendanceOfflineService(
    db: ref.watch(edgeDatabaseProvider),
    syncService: ref.watch(syncServiceProvider),
    dio: Dio(),
  );
});

final attendanceLocationServiceProvider = Provider<AttendanceLocationService>((
  ref,
) {
  return const AttendanceLocationService();
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

final projectRepositoryProvider = Provider<ProjectRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ProjectRepository(apiClient);
});

final userAuthRepositoryProvider = Provider<UserAuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return UserAuthRepository(apiClient, storage, preferences);
});

final contractRepositoryProvider = Provider<ContractRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ContractRepository(apiClient);
});

final trainingRepositoryProvider = Provider<TrainingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TrainingRepository(apiClient);
});

final expenseRepositoryProvider = Provider<ExpenseRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ExpenseRepository(apiClient);
});

final aiChatRepositoryProvider = Provider<AiChatRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AiChatRepository(apiClient);
});

final aiVoiceRepositoryProvider = Provider<AiVoiceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AiVoiceRepository(apiClient);
});

final vehiclePositionRepositoryProvider = Provider<VehiclePositionRepository>((
  ref,
) {
  final apiClient = ref.watch(apiClientProvider);
  return VehiclePositionRepository(apiClient);
});

final onboardingRepositoryProvider = Provider<OnboardingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return OnboardingRepository(apiClient);
});

final smartAttendanceRepositoryCoreProvider =
    Provider<SmartAttendanceRepository>((ref) {
      final apiClient = ref.watch(apiClientProvider);
      return SmartAttendanceRepository(apiClient);
    });
