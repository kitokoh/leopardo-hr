import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/salary_advance.dart';

final salaryAdvancesProvider = FutureProvider<List<SalaryAdvance>>((ref) async {
  final repo = ref.watch(salaryAdvanceRepositoryProvider);
  return await repo.getMySalaryAdvances();
});
