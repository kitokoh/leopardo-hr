class CabinetFolder {
  final int id;
  final int? parentId;
  final String name;
  final String? color;
  final String? icon;
  final int documentsCount;
  final int childrenCount;
  final DateTime createdAt;

  CabinetFolder({
    required this.id,
    this.parentId,
    required this.name,
    this.color,
    this.icon,
    this.documentsCount = 0,
    this.childrenCount = 0,
    required this.createdAt,
  });

  factory CabinetFolder.fromJson(Map<String, dynamic> json) {
    return CabinetFolder(
      id: json['id'] as int,
      parentId: json['parent_id'] as int?,
      name: (json['name'] ?? '') as String,
      color: json['color'] as String?,
      icon: json['icon'] as String?,
      documentsCount: (json['documents_count'] ?? 0) as int,
      childrenCount: (json['children_count'] ?? 0) as int,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
