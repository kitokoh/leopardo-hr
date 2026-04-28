class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.payload,
    required this.isRead,
    this.readAt,
    this.createdAt,
  });

  final int id;
  final String type;
  final String title;
  final String body;
  final Map<String, dynamic> payload;
  final bool isRead;
  final DateTime? readAt;
  final DateTime? createdAt;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final rawPayload = json['data'];
    return AppNotification(
      id: (json['id'] as num?)?.toInt() ?? 0,
      type: (json['type'] ?? '') as String,
      title: (json['title'] ?? '') as String,
      body: (json['body'] ?? '') as String,
      payload: rawPayload is Map ? rawPayload.cast<String, dynamic>() : const <String, dynamic>{},
      isRead: json['is_read'] == true,
      readAt: DateTime.tryParse(json['read_at']?.toString() ?? ''),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }
}
