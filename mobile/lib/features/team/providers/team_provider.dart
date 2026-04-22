import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/features/team/data/employee_repository.dart';
import 'package:leopardo_rh/models/employee.dart';

final employeeRepositoryProvider = Provider<EmployeeRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return EmployeeRepository(apiClient);
});

final teamListProvider = FutureProvider.autoDispose<List<Employee>>((ref) async {
  final repo = ref.watch(employeeRepositoryProvider);
  return repo.list();
});

final invitationsListProvider = FutureProvider.autoDispose<List<Invitation>>((ref) async {
  final repo = ref.watch(employeeRepositoryProvider);
  return repo.listInvitations();
});
