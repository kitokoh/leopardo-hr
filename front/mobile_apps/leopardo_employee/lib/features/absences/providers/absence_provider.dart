import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/absence.dart';

final absencesProvider = FutureProvider<List<Absence>>((ref) async {
  final repo = ref.watch(absenceRepositoryProvider);
  return await repo.getMyAbsences();
});

final leaveBalancesProvider = FutureProvider<List<Map<String, dynamic>>>((
  ref,
) async {
  final repo = ref.watch(absenceRepositoryProvider);
  return await repo.getLeaveBalances();
});
