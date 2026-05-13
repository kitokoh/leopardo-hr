class Approval {
  final int id;
  final String type;
  final String requesterName;
  final String summary;
  final String createdAt;
  final String status;

  Approval({
    required this.id,
    required this.type,
    required this.requesterName,
    required this.summary,
    required this.createdAt,
    required this.status,
  });

  factory Approval.fromJson(Map<String, dynamic> json) {
    return Approval(
      id: json['id'] as int,
      type: json['type'] as String? ?? '',
      requesterName: json['requester_name'] as String? ?? '',
      summary: json['summary'] as String? ?? '',
      createdAt: json['created_at'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
    );
  }
}
