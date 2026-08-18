import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

import '../models/social_post.dart';

/// Repository for social posts via the Marketing API.
///
/// Routes: GET/POST /social-posts, PATCH/DELETE /social-posts/{id},
/// POST /social-posts/{id}/publish (api/routes/modules/marketing.php).
class SocialPostRepository {
  const SocialPostRepository(this._apiClient);

  final ApiClient _apiClient;

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 12);

  Future<List<SocialPost>> listPosts({String? status}) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/social-posts',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
      queryParameters: {
        if (status != null) 'status': status,
        'per_page': '50',
      },
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) => SocialPost.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  Future<SocialPost> getPost(int id) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/social-posts/$id',
      timeoutOverride: _readTimeout,
      maxRetriesOverride: 0,
    );
    return SocialPost.fromJson(extractDataMap(response.data));
  }

  Future<SocialPost> createPost({
    required String content,
    required List<String> targetPlatforms,
    DateTime? scheduledAt,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/social-posts',
      method: 'POST',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
      data: {
        'content': content.trim(),
        'target_platforms': targetPlatforms,
        if (scheduledAt != null)
          'scheduled_at': scheduledAt.toUtc().toIso8601String(),
      },
    );
    return SocialPost.fromJson(extractDataMap(response.data));
  }

  Future<SocialPost> publishPost(int id) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/social-posts/$id/publish',
      method: 'POST',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
    );
    return SocialPost.fromJson(extractDataMap(response.data));
  }

  Future<void> deletePost(int id) async {
    await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/social-posts/$id',
      method: 'DELETE',
      timeoutOverride: _actionTimeout,
      maxRetriesOverride: 0,
    );
  }
}
