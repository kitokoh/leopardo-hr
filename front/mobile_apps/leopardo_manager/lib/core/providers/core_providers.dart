import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/services/push_notification_service.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_manager/features/auth/data/auth_repository.dart';
import 'package:leopardo_manager/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_manager/features/settings/data/settings_repository.dart';
import 'package:leopardo_manager/features/absences/data/absence_repository.dart';
import 'package:leopardo_manager/features/salary_advances/data/salary_advance_repository.dart';
import 'package:leopardo_manager/features/payrolls/data/payroll_repository.dart';
import 'package:leopardo_manager/features/notifications/data/notification_repository.dart';
import 'package:leopardo_manager/features/evaluations/data/evaluation_repository.dart';
import 'package:leopardo_manager/features/cabinet/data/cabinet_repository.dart';
import 'package:leopardo_manager/features/home/data/project_repository.dart';
import 'package:leopardo_manager/features/user_auth/data/user_auth_repository.dart';
import 'package:leopardo_manager/features/contracts/data/contract_repository.dart';
import 'package:leopardo_manager/features/training/data/training_repository.dart';
import 'package:leopardo_manager/features/expenses/data/expense_repository.dart';
import 'package:leopardo_manager/features/ai_chat/data/ai_chat_repository.dart';
import 'package:leopardo_manager/features/ai_voice/data/ai_voice_repository.dart';
import 'package:leopardo_manager/features/vehicle_position/data/vehicle_position_repository.dart';
import 'package:leopardo_manager/features/approvals/data/approval_repository.dart';
import 'package:leopardo_manager/features/onboarding/data/onboarding_repository.dart';
import 'package:leopardo_manager/features/schedules/data/schedule_repository.dart';

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
  return UserAuthRepository(apiClient, storage);
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

final approvalRepositoryProvider = Provider<ApprovalRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ApprovalRepository(apiClient);
});

final onboardingRepositoryProvider = Provider<OnboardingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return OnboardingRepository(apiClient);
});

final scheduleRepositoryProvider = Provider<ScheduleRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ScheduleRepository(apiClient);
});
