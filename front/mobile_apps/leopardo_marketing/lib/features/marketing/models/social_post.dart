/// Model representing a SocialPost from the Marketing API.
class SocialPost {
  const SocialPost({
    required this.id,
    required this.content,
    required this.status,
    required this.targetPlatforms,
    required this.createdAt,
    this.scheduledAt,
    this.publishedAt,
    this.providerPostRef,
  });

  final int id;
  final String content;
  final String status;
  final List<String> targetPlatforms;
  final String createdAt;
  final String? scheduledAt;
  final String? publishedAt;
  final String? providerPostRef;

  bool get isPublished => status == 'published';
  bool get isScheduled => status == 'scheduled';
  bool get isDraft => status == 'draft';
  bool get isFailed => status == 'failed';

  factory SocialPost.fromJson(Map<String, dynamic> json) {
    final platforms = json['target_platforms'];
    return SocialPost(
      id: (json['id'] as num?)?.toInt() ?? 0,
      content: json['content']?.toString() ?? '',
      status: json['status']?.toString() ?? 'draft',
      targetPlatforms: platforms is List
          ? platforms.map((p) => p.toString()).toList()
          : const [],
      createdAt: json['created_at']?.toString() ?? '',
      scheduledAt: json['scheduled_at']?.toString(),
      publishedAt: json['published_at']?.toString(),
      providerPostRef: json['provider_post_ref']?.toString(),
    );
  }

  static const availablePlatforms = [
    'linkedin',
    'facebook',
    'instagram',
    'twitter',
  ];

  static const platformIcons = {
    'linkedin': '💼',
    'facebook': '📘',
    'instagram': '📸',
    'twitter': '🐦',
  };
}
