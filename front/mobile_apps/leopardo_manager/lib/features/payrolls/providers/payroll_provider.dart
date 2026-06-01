import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/models/payment_document.dart';
import 'package:leopardo_core/models/payroll.dart';
import 'package:leopardo_core/models/payroll_balance.dart';

final payrollsProvider = FutureProvider<List<Payroll>>((ref) async {
  final repo = ref.watch(payrollRepositoryProvider);
  return await repo.getMyPayrolls();
});

final payrollMobileSummaryProvider = FutureProvider<PayrollMobileSummary>((
  ref,
) async {
  final repo = ref.watch(payrollRepositoryProvider);
  return await repo.getMobileSummary();
});

final payrollPaymentDocumentsProvider =
    FutureProvider.family<List<PaymentDocument>, int>((
      ref,
      payrollRunId,
    ) async {
      final repo = ref.watch(payrollRepositoryProvider);
      return await repo.getPaymentDocumentsForPayrollRun(payrollRunId);
    });
