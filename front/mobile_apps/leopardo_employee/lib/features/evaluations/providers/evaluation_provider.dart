import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/evaluation.dart';

final evaluationsProvider = FutureProvider<List<Evaluation>>((ref) async {
  final repo = ref.watch(evaluationRepositoryProvider);
  return await repo.getMyEvaluations();
});
