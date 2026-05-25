import 'package:leopardo_core/core/api/api_client.dart';

class OrgNode {
  final int id;
  final String name;
  final String? position;
  final String? department;
  final String? photoUrl;
  final int? parentId;
  final List<OrgNode> children;

  OrgNode({
    required this.id,
    required this.name,
    this.position,
    this.department,
    this.photoUrl,
    this.parentId,
    this.children = const [],
  });

  factory OrgNode.fromJson(Map<String, dynamic> json) {
    final childrenJson = json['children'] as List? ?? [];
    return OrgNode(
      id: json['id'] as int,
      name:
          (json['name'] ??
                  '${json['first_name'] ?? ''} ${json['last_name'] ?? ''}')
              .toString()
              .trim(),
      position: json['position']?.toString(),
      department: json['department']?.toString(),
      photoUrl: json['photo_url']?.toString(),
      parentId: json['parent_id'] as int?,
      children:
          childrenJson
              .map((c) => OrgNode.fromJson(c as Map<String, dynamic>))
              .toList(),
    );
  }
}

class OrganigrammeRepository {
  final ApiClient apiClient;

  OrganigrammeRepository(this.apiClient);

  Future<List<OrgNode>> getOrgChart() async {
    final response = await apiClient.dio.get('/org-chart');
    final items = response.data['data'] as List;
    return items
        .map((e) => OrgNode.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<OrgNode>> getDepartmentHierarchy(int departmentId) async {
    final response = await apiClient.dio.get(
      '/departments/$departmentId/hierarchy',
    );
    final items = response.data['data'] as List;
    return items
        .map((e) => OrgNode.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
