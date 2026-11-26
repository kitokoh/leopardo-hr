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
      id: _asInt(json['id']),
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

  /// Convertit un identifiant reçu en `int`, quel que soit son type JSON.
  ///
  /// Le cast direct `(json['id'] as num?)` levait une `TypeError` dès que le
  /// serveur sérialisait l'id en chaîne (`"42"`) — un driver SQL qui rend les
  /// entiers en texte suffit. L'exception remontait au rendu de la liste et
  /// de la fiche de détail, donc l'écran entier tombait pour un champ dont
  /// la valeur était parfaitement lisible. On accepte donc `num` et `String`,
  /// et on retombe sur 0 (valeur neutre déjà utilisée pour un id absent)
  /// plutôt que de lever.
  static int _asInt(Object? value) {
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value.trim()) ?? 0;
    return 0;
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
