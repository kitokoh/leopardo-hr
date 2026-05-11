import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/salary_advance.dart';

final salaryAdvancesProvider = FutureProvider<List<SalaryAdvance>>((ref) async {
  final repo = ref.watch(salaryAdvanceRepositoryProvider);
  return await repo.getMySalaryAdvances();
});
