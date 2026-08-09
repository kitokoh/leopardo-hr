import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class SocialPostRepository {
  final ApiClient apiClient;

  SocialPostRepository(this.apiClient);

  Future<List<Map<String, dynamic>>> getPosts() async {
    final response = await apiClient.requestWithRetry('/marketing/posts');
    return extractDataList(response.data);
  }

  Future<Map<String, dynamic>> createPost(Map<String, dynamic> data) async {
    final response = await apiClient.requestWithRetry('/marketing/posts', method: 'POST', data: data);
    return extractDataMap(response.data);
  }

  Future<void> publishPost(String postId) async {
    await apiClient.requestWithRetry('/marketing/posts/$postId/publish', method: 'POST');
  }
}
