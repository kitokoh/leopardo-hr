import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/project_task.dart';

class ProjectRepository {
  final ApiClient apiClient;

  ProjectRepository(this.apiClient);

  Future<List<Project>> getProjects() async {
    final response = await apiClient.dio.get('/projects');
    final items = response.data['data'] as List;
    return items.map((e) => Project.fromJson(e)).toList();
  }

  Future<List<Task>> getMyTasks() async {
    final response = await apiClient.dio.get('/tasks');
    final items = response.data['data'] as List;
    return items.map((e) => Task.fromJson(e)).toList();
  }
}
