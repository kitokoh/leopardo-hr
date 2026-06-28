import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/project_task.dart';

class ProjectRepository {
  final ApiClient apiClient;

  ProjectRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);

  Future<List<Project>> getProjects() async {
    final response = await apiClient.requestWithRetry(
      '/projects',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Project.fromJson(e)).toList();
  }

  Future<List<Task>> getMyTasks() async {
    final response = await apiClient.requestWithRetry(
      '/tasks',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => Task.fromJson(e)).toList();
  }
}
