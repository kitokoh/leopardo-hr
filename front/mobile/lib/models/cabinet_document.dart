class CabinetDocument {
  final int id;
  final int? folderId;
  final String name;
  final String originalName;
  final String mimeType;
  final int size;
  final String? notes;
  final DateTime createdAt;

  CabinetDocument({
    required this.id,
    this.folderId,
    required this.name,
    required this.originalName,
    required this.mimeType,
    required this.size,
    this.notes,
    required this.createdAt,
  });

  factory CabinetDocument.fromJson(Map<String, dynamic> json) {
    return CabinetDocument(
      id: json['id'] as int,
      folderId: json['folder_id'] as int?,
      name: (json['name'] ?? '') as String,
      originalName: (json['original_name'] ?? '') as String,
      mimeType: (json['mime_type'] ?? 'application/octet-stream') as String,
      size: (json['size'] ?? 0) as int,
      notes: json['notes'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  String get sizeFormatted {
    if (size < 1024) return '$size o';
    if (size < 1024 * 1024) return '${(size / 1024).toStringAsFixed(1)} Ko';
    return '${(size / (1024 * 1024)).toStringAsFixed(1)} Mo';
  }

  bool get isPdf => mimeType == 'application/pdf';
  bool get isImage => mimeType.startsWith('image/');
}
