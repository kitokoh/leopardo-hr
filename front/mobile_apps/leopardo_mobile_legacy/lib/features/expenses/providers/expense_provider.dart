import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/expense_claim.dart';

final expenseClaimsProvider =
    FutureProvider<List<ExpenseClaim>>((ref) async {
  final repo = ref.watch(expenseRepositoryProvider);
  return await repo.getMyClaims();
});
