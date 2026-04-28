import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_rh/features/settings/data/settings_repository.dart';
import 'package:leopardo_rh/features/absences/data/absence_repository.dart';
import 'package:leopardo_rh/features/salary_advances/data/salary_advance_repository.dart';
import 'package:leopardo_rh/features/payrolls/data/payroll_repository.dart';
import 'package:leopardo_rh/features/notifications/data/notification_repository.dart';
import 'package:leopardo_rh/features/evaluations/data/evaluation_repository.dart';
import 'package:leopardo_rh/features/home/data/project_repository.dart';

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

final salaryAdvanceRepositoryProvider = Provider<SalaryAdvanceRepository>((ref) {
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

final projectRepositoryProvider = Provider<ProjectRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ProjectRepository(apiClient);
});
