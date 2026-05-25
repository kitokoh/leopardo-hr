import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';
import 'package:leopardo_manager/features/modules/data/modules_repository.dart';
import 'package:leopardo_core/models/app_notification.dart';
import 'package:leopardo_core/models/evaluation.dart';
import 'package:leopardo_core/models/payroll_record.dart';
import 'package:leopardo_core/models/salary_advance.dart';

final modulesRepositoryProvider = Provider<ModulesRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ModulesRepository(apiClient);
});

final evaluationsProvider = FutureProvider.autoDispose<List<Evaluation>>((
  ref,
) async {
  return ref.watch(modulesRepositoryProvider).listEvaluations();
});

final salaryAdvancesProvider = FutureProvider.autoDispose<List<SalaryAdvance>>((
  ref,
) async {
  return ref.watch(modulesRepositoryProvider).listSalaryAdvances();
});

final payrollsProvider = FutureProvider.autoDispose<List<PayrollRecord>>((
  ref,
) async {
  final repo = ref.watch(modulesRepositoryProvider);
  final employee = ref.watch(authProvider).employee;
  final isManager = employee?.isManager == true;
  if (!isManager) {
    return repo.listMyPaySlips();
  }
  return repo.listPayrolls();
});

final notificationsProvider = FutureProvider.autoDispose<List<AppNotification>>(
  (ref) async {
    return ref.watch(modulesRepositoryProvider).listNotifications();
  },
);
