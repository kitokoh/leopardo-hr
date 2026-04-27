import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/absence.dart';

final absencesProvider = FutureProvider<List<Absence>>((ref) async {
  final repo = ref.watch(absenceRepositoryProvider);
  return await repo.getMyAbsences();
});
