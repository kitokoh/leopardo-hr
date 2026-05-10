import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/evaluation.dart';

final evaluationsProvider = FutureProvider<List<Evaluation>>((ref) async {
  final repo = ref.watch(evaluationRepositoryProvider);
  return await repo.getMyEvaluations();
});
