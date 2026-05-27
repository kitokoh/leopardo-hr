import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/models/project_task.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/tasks/data/task_repository.dart';

final taskRepositoryProvider = Provider<TaskRepository>((ref) {
  return TaskRepository(ref.watch(apiClientProvider));
});

final todayManagerTasksProvider = FutureProvider.autoDispose<List<Task>>((
  ref,
) async {
  return ref.watch(taskRepositoryProvider).listToday();
});
