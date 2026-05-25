import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/approval.dart';

final pendingApprovalsProvider =
    FutureProvider<List<Approval>>((ref) async {
  final repo = ref.watch(approvalRepositoryProvider);
  return await repo.getPending();
});
