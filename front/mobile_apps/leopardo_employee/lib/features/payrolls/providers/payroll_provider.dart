import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/payroll.dart';

final payrollsProvider = FutureProvider<List<Payroll>>((ref) async {
  final repo = ref.watch(payrollRepositoryProvider);
  return await repo.getMyPayrolls();
});
