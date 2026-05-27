import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/schedules/data/schedule_repository.dart';

final schedulesProvider = FutureProvider.autoDispose<List<WorkSchedule>>((
  ref,
) async {
  return ref.watch(scheduleRepositoryProvider).list();
});
