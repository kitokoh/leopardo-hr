import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/project_task.dart';

class TaskRepository {
  const TaskRepository(this.apiClient);

  final ApiClient apiClient;

  Future<List<Task>> listToday({int? assignedTo}) async {
    final response = await apiClient.dio.get(
      '/tasks/today',
      queryParameters: {if (assignedTo != null) 'assigned_to': assignedTo},
    );
    final items = response.data['data'];
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((entry) => Task.fromJson(entry.cast<String, dynamic>()))
        .toList();
  }

  Future<Task> create({
    required String title,
    String? description,
    required int employeeId,
    required DateTime dueDate,
    String priority = 'normal',
    int? estimatedMinutes,
    String? category,
    String? templateKey,
    String? recurrenceRule,
  }) async {
    final response = await apiClient.dio.post(
      '/tasks',
      data: {
        'title': title.trim(),
        if (description != null && description.trim().isNotEmpty)
          'description': description.trim(),
        'assigned_to': [employeeId],
        'due_date': _formatDate(dueDate),
        'priority': priority,
        if (estimatedMinutes != null) 'estimated_minutes': estimatedMinutes,
        if (category != null && category.trim().isNotEmpty)
          'category': category.trim(),
        if (templateKey != null && templateKey.trim().isNotEmpty)
          'template_key': templateKey.trim(),
        if (recurrenceRule != null && recurrenceRule.trim().isNotEmpty)
          'recurrence_rule': recurrenceRule.trim(),
      },
    );
    return Task.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }

  static String _formatDate(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
}
