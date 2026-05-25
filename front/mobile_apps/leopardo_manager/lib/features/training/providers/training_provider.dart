import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/models/training_enrollment.dart';

final trainingEnrollmentsProvider = FutureProvider<List<TrainingEnrollment>>((
  ref,
) async {
  final repo = ref.watch(trainingRepositoryProvider);
  return await repo.getMyEnrollments();
});
