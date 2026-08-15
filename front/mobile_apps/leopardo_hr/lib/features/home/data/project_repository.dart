import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/project_task.dart';

class ProjectRepository {
  final ApiClient apiClient;

  ProjectRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);
}
