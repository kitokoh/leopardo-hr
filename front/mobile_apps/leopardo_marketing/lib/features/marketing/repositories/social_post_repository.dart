import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class SocialPostRepository {
  final ApiClient apiClient;

  SocialPostRepository(this.apiClient);

  Future<List<Map<String, dynamic>>> getPosts() async {
    final response = await apiClient.requestWithRetry('/marketing/posts');
    return extractDataList(response.data)
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
  }

  Future<Map<String, dynamic>> createPost(Map<String, dynamic> data) async {
    final response = await apiClient.requestWithRetry('/marketing/posts', method: 'POST', data: data);
    return extractDataMap(response.data);
  }

  Future<void> publishPost(String postId) async {
    await apiClient.requestWithRetry('/marketing/posts/$postId/publish', method: 'POST');
  }

  /// Agrégation réelle des statistiques marketing à partir de
  /// GET /marketing/posts (aucune donnée fabriquée — #2595).
  /// Retourne les compteurs sur les 30 derniers jours + la ventilation
  /// par plateforme ciblée.
  Future<Map<String, dynamic>> fetchStats() async {
    final response = await apiClient.requestWithRetry(
      '/marketing/posts',
      queryParameters: {'per_page': 100},
    );
    final posts = extractDataList(response.data);
    final now = DateTime.now();
    final cutoff = now.subtract(const Duration(days: 30));

    var total = 0, published = 0, scheduled = 0, failed = 0;
    final byPlatform = <String, int>{};

    for (final post in posts) {
      final createdAt = DateTime.tryParse(post['created_at']?.toString() ?? '');
      if (createdAt == null || createdAt.isBefore(cutoff)) continue;
      total++;
      switch (post['status']?.toString()) {
        case 'published':
          published++;
        case 'scheduled':
          scheduled++;
        case 'failed':
          failed++;
      }
      for (final platform in (post['target_platforms'] as List? ?? const [])) {
        final key = platform.toString();
        byPlatform[key] = (byPlatform[key] ?? 0) + 1;
      }
    }

    final platformList = byPlatform.entries
        .map((e) => {'platform': e.key, 'posts': e.value})
        .toList()
      ..sort((a, b) => (b['posts'] as int).compareTo(a['posts'] as int));

    return {
      'total': total,
      'published': published,
      'scheduled': scheduled,
      'failed': failed,
      'byPlatform': platformList,
    };
  }
}
